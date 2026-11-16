<?php

use App\Http\Controllers\ProfileController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth'])->group(function () {

    // SOAL A1 & 2a (XSS): Escaping {{ }} dan escapeHtml() ada di file view buku/index.blade.php
    Route::get('/buku', function () {
        return view('buku.index');
    })->name('buku.index');

    // SOAL 3 (RBAC Gate): Dikunci hanya untuk Admin & Petugas
    Route::get('/buku/create', function () {
        return view('buku.index');
    })->middleware(['can:kelola-buku'])->name('buku.create');

    // SOAL 3 (RBAC Gate): Simpan buku dikunci hanya untuk Admin & Petugas
    Route::post('/buku', function (Request $request) {
        if (Schema::hasTable('bukus')) {
            DB::table('bukus')->insert([
                'judul' => $request->judul,
                'penulis' => $request->penulis ?? '-',
                'tahun' => $request->tahun ?? date('Y'),
                'stok' => $request->stok ?? 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return back()->with('status', 'Buku berhasil ditambahkan!');
    })->middleware(['can:kelola-buku'])->name('buku.store');

    // SOAL A2 & 2b (SQL Injection): Pakai parameter binding (?) agar input tidak dieksekusi sebagai SQL
    Route::get('/buku-cari', function (Request $request) {
        $judul = $request->query('judul');
        $buku = Schema::hasTable('bukus')
            ? DB::select("SELECT * FROM bukus WHERE judul = ?", [$judul])
            : [];
        return response()->json($buku);
    })->name('buku.cari');

    // SOAL A3 & 2c (CSRF): Route GET lama dihapus, diganti DELETE + token @csrf + Gate
    Route::delete('/buku/{id}', function ($id) {
        if (Schema::hasTable('bukus')) {
            DB::table('bukus')->where('id', $id)->delete();
        }

        $bukus = Cache::get('bukus', []);
        if (!empty($bukus)) {
            $bukus = array_values(array_filter($bukus, fn($b) => ($b['id'] ?? null) != $id));
            Cache::put('bukus', $bukus, now()->addDays(7));
        }

        return back()->with('status', 'Buku berhasil dihapus!');
    })->middleware(['can:kelola-buku'])->name('buku.destroy');


    // ================= PEMINJAMAN (SOAL A4 & 4 - SESUAI SCHEMA SUPABASE) =================

    // SOAL 4: Filter riwayat (Admin lihat semua, Siswa & Petugas lihat miliknya sendiri)
    Route::get('/peminjaman/riwayat', function () {
        $user = Auth::user();
        $hasAnggotaCol = Schema::hasColumn('peminjamans', 'anggota_id');
        $userCol = $hasAnggotaCol ? 'anggota_id' : 'user_id';

        $query = DB::table('peminjamans')
            ->leftJoin('bukus', 'peminjamans.buku_id', '=', 'bukus.id')
            ->select('peminjamans.*', 'bukus.judul as judul_buku')
            ->orderByDesc('peminjamans.id');

        if ($hasAnggotaCol && Schema::hasTable('anggotas')) {
            $query->leftJoin('anggotas', 'peminjamans.anggota_id', '=', 'anggotas.id')
                  ->addSelect('anggotas.nama as nama_anggota');
        } else {
            $query->leftJoin('users', "peminjamans.{$userCol}", '=', 'users.id')
                  ->addSelect('users.name as nama_user');
        }

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            $raw = $query->get();
        } else {
            $raw = $query->where("peminjamans.{$userCol}", Auth::id())->get();
        }

        $peminjamans = $raw->map(function ($item) use ($userCol) {
            $status = property_exists($item, 'status') && $item->status
                ? $item->status
                : ($item->tanggal_kembali ? 'Sudah Dikembalikan' : 'Sedang Dipinjam');

            return [
                'id' => $item->id,
                'user_id' => $item->{$userCol} ?? Auth::id(),
                'user_name' => $item->nama_anggota ?? $item->nama_user ?? 'Anggota #' . ($item->{$userCol} ?? '-'),
                'judul' => $item->judul_buku ?? 'Buku #' . $item->buku_id,
                'tanggal_pinjam' => $item->tanggal_pinjam ?? '-',
                'tanggal_kembali' => $item->tanggal_kembali ?? 'Belum Dikembalikan',
                'status' => $status,
            ];
        })->toArray();

        return view('peminjaman.riwayat', compact('peminjamans'));
    })->name('peminjaman.riwayat');

    // SOAL A4 & 4 (IDOR): Dicek pakai PeminjamanPolicy agar siswa lain ditolak (403)
    Route::get('/peminjaman/{id}', function ($id) {
        $hasAnggotaCol = Schema::hasColumn('peminjamans', 'anggota_id');
        $userCol = $hasAnggotaCol ? 'anggota_id' : 'user_id';

        $item = DB::table('peminjamans')
            ->leftJoin('bukus', 'peminjamans.buku_id', '=', 'bukus.id')
            ->select('peminjamans.*', 'bukus.judul as judul')
            ->where('peminjamans.id', $id)
            ->first();

        if (!$item) {
            abort(404, 'Data peminjaman tidak ditemukan.');
        }

        $peminjaman = (array) $item;
        $peminjaman['user_id'] = $item->{$userCol} ?? null;

        Gate::authorize('view', [$peminjaman]);

        return view('peminjaman.show', compact('peminjaman'));
    })->name('peminjaman.detail');

    Route::post('/peminjaman/store', function (Request $request) {
        $hasAnggotaCol = Schema::hasColumn('peminjamans', 'anggota_id');
        $userCol = $hasAnggotaCol ? 'anggota_id' : 'user_id';

        // Pastikan ID anggota tersedia jika memakai foreign key anggota_id
        $ownerId = Auth::id();
        if ($hasAnggotaCol && Schema::hasTable('anggotas')) {
            $anggota = DB::table('anggotas')->where('id', $ownerId)->first()
                ?? DB::table('anggotas')->first();
            if ($anggota) {
                $ownerId = $anggota->id;
            }
        }

        $data = [
            $userCol => $ownerId,
            'buku_id' => $request->buku_id,
            'tanggal_pinjam' => now()->format('Y-m-d'),
            'tanggal_kembali' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('peminjamans', 'status')) {
            $data['status'] = 'Menunggu Persetujuan';
        }

        DB::table('peminjamans')->insert($data);

        return response()->json(['message' => 'Peminjaman berhasil diajukan!']);
    })->name('peminjaman.store');

    Route::post('/peminjaman/setujui/{id}', function ($id) {
        $update = ['updated_at' => now()];
        if (Schema::hasColumn('peminjamans', 'status')) {
            $update['status'] = 'Sedang Dipinjam';
        }
        DB::table('peminjamans')->where('id', $id)->update($update);
        return back()->with('status', 'Peminjaman buku berhasil disetujui!');
    })->name('peminjaman.setujui');

    Route::post('/peminjaman/kembali/{id}', function ($id) {
        $update = [
            'tanggal_kembali' => now()->format('Y-m-d'),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('peminjamans', 'status')) {
            $update['status'] = 'Sudah Dikembalikan';
        }
        DB::table('peminjamans')->where('id', $id)->update($update);
        return back()->with('status', 'Pengembalian buku berhasil dicatat!');
    })->name('peminjaman.kembali');

    Route::post('/peminjaman/kembalikan-sendiri/{id}', function ($id) {
        $update = [
            'tanggal_kembali' => now()->format('Y-m-d'),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('peminjamans', 'status')) {
            $update['status'] = 'Sudah Dikembalikan';
        }
        DB::table('peminjamans')->where('id', $id)->update($update);
        return back()->with('status', 'Buku berhasil dikembalikan!');
    })->name('peminjaman.kembalikan.sendiri');


    // ================= DASHBOARD & PROFIL =================

    Route::get('/dashboard', function () {
        $user = Auth::user();

        if ($user && method_exists($user, 'hasRole')) {
            if ($user->hasRole('admin')) {
                return redirect()->route('admin.dashboard');
            } elseif ($user->hasRole('petugas')) {
                return redirect()->route('petugas.dashboard');
            } elseif ($user->hasRole('siswa')) {
                return redirect()->route('siswa.dashboard');
            }
        }

        return view('dashboard');
    })->name('dashboard');

    Route::get('/dashboard/siswa', function () {
        return view('dashboard');
    })->name('siswa.dashboard');

    Route::get('/dashboard/petugas', function () {
        return view('dashboard');
    })->name('petugas.dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    // ================= KHUSUS ADMIN & PETUGAS (SOAL 3 - RBAC) =================

    // SOAL 3: Hanya Admin
    Route::get('/dashboard/admin', function () {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'hasRole') || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized action.');
        }
        return view('dashboard');
    })->name('admin.dashboard');

    // SOAL 3: Kelola User hanya untuk Admin
    Route::get('/admin/users', function () {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'hasRole') || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized action.');
        }

        if (!Cache::has('app_users')) {
            Cache::put('app_users', [
                ['id' => 1, 'name' => 'Admin Perpustakaan', 'email' => 'admin@test.com', 'role' => 'Admin'],
                ['id' => 2, 'name' => 'Petugas Perpustakaan', 'email' => 'petugas@test.com', 'role' => 'Petugas'],
                ['id' => 3, 'name' => 'Siswa Perpustakaan', 'email' => 'siswa@test.com', 'role' => 'Siswa'],
            ], now()->addDays(7));
        }
        $users = Cache::get('app_users', []);
        return view('admin.users', compact('users'));
    })->name('admin.users');

    Route::post('/admin/users/store', function (Request $request) {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'hasRole') || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:6',
            'role' => 'required|string',
        ]);

        try {
            $newUser = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            if (method_exists($newUser, 'assignRole')) {
                $newUser->assignRole(strtolower($request->role));
            }
        } catch (\Exception $e) {}

        $users = Cache::get('app_users', []);
        $users[] = [
            'id' => time(),
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
        ];
        Cache::put('app_users', $users, now()->addDays(7));

        return back()->with('status', 'Pengguna baru berhasil ditambahkan!');
    })->name('admin.users.store');

    Route::post('/admin/users/update-role/{id}', function (Request $request, $id) {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'hasRole') || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $users = Cache::get('app_users', []);
        foreach ($users as &$u) {
            if ($u['id'] == $id) {
                $u['role'] = $request->role;
            }
        }
        Cache::put('app_users', $users, now()->addDays(7));

        $userDb = User::where('email', $request->email ?? '')->first();
        if ($userDb && method_exists($userDb, 'syncRoles')) {
            $userDb->syncRoles([strtolower($request->role)]);
        }

        return back()->with('status', 'Role pengguna berhasil diperbarui!');
    })->name('admin.users.update_role');

    Route::post('/admin/users/delete/{id}', function ($id) {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'hasRole') || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $users = Cache::get('app_users', []);
        $users = array_values(array_filter($users, fn($u) => $u['id'] != $id));
        Cache::put('app_users', $users, now()->addDays(7));
        return back()->with('status', 'Pengguna berhasil dihapus!');
    })->name('admin.users.delete');

    // SOAL 3: Laporan hanya untuk Admin & Petugas (Siswa diblokir 403)
    Route::get('/laporan', function () {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'hasRole') || (!$user->hasRole('admin') && !$user->hasRole('petugas'))) {
            abort(403, 'Unauthorized action. Siswa dilarang mengakses laporan.');
        }

        $hasAnggotaCol = Schema::hasColumn('peminjamans', 'anggota_id');
        $userCol = $hasAnggotaCol ? 'anggota_id' : 'user_id';

        $query = DB::table('peminjamans')
            ->leftJoin('bukus', 'peminjamans.buku_id', '=', 'bukus.id')
            ->select('peminjamans.*', 'bukus.judul as judul_buku')
            ->orderByDesc('peminjamans.id');

        if ($hasAnggotaCol && Schema::hasTable('anggotas')) {
            $query->leftJoin('anggotas', 'peminjamans.anggota_id', '=', 'anggotas.id')
                  ->addSelect('anggotas.nama as nama_anggota');
        } else {
            $query->leftJoin('users', "peminjamans.{$userCol}", '=', 'users.id')
                  ->addSelect('users.name as nama_user');
        }

        $raw = $query->get();

        $peminjamans = $raw->map(function ($item) use ($userCol) {
            $status = property_exists($item, 'status') && $item->status
                ? $item->status
                : ($item->tanggal_kembali ? 'Sudah Dikembalikan' : 'Sedang Dipinjam');

            return [
                'id' => $item->id,
                'user_id' => $item->{$userCol} ?? Auth::id(),
                'user_name' => $item->nama_anggota ?? $item->nama_user ?? 'Anggota #' . ($item->{$userCol} ?? '-'),
                'judul' => $item->judul_buku ?? 'Buku #' . $item->buku_id,
                'tanggal_pinjam' => $item->tanggal_pinjam ?? '-',
                'tanggal_kembali' => $item->tanggal_kembali ?? 'Belum Dikembalikan',
                'status' => $status,
            ];
        })->toArray();

        return view('laporan.index', compact('peminjamans'));
    })->name('laporan.index');

});

require __DIR__.'/auth.php';