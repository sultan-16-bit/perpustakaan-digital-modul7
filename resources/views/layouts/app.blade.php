<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Sistem Perpustakaan</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-slate-100 font-sans antialiased min-h-screen flex flex-col justify-between text-gray-800">

    <div>
        <!-- Header Banner Biru -->
        <header class="bg-blue-600 py-6 text-center text-white shadow-md">
            <h1 class="text-3xl font-bold tracking-wide">Sistem Perpustakaan</h1>
        </header>

        <!-- Navigasi Utama Rata Tengah -->
        <nav class="bg-white border-b border-gray-200 py-3 flex justify-center items-center space-x-6 text-sm font-semibold text-gray-700">
            {{-- Link Beranda --}}
            <a href="{{ route('dashboard') }}"
                class="{{ request()->routeIs('dashboard*') || request()->routeIs('*.dashboard') ? 'text-blue-600 font-bold border-b-2 border-blue-600 pb-1' : 'hover:text-blue-600' }}">
                Beranda
            </a>

            {{-- Link Koleksi Buku --}}
            <a href="{{ route('buku.index') }}"
                class="{{ request()->routeIs('buku.index') ? 'text-blue-600 font-bold border-b-2 border-blue-600 pb-1' : 'hover:text-blue-600' }}">
                Koleksi Buku
            </a>

            {{-- Link Riwayat Peminjaman (Khusus Siswa atau semua user) --}}
            <a href="{{ route('peminjaman.riwayat') }}"
                class="{{ request()->routeIs('peminjaman.riwayat') ? 'text-blue-600 font-bold border-b-2 border-blue-600 pb-1' : 'hover:text-blue-600' }}">
                Riwayat Saya
            </a>

            {{-- Link Laporan Peminjaman (Hanya Admin & Petugas sesuai kriteria routemu) --}}
            @if(Auth::user() && method_exists(Auth::user(), 'hasRole') && (Auth::user()->hasRole('admin') || Auth::user()->hasRole('petugas')))
            <a href="{{ route('laporan.index') }}"
                class="{{ request()->routeIs('laporan.*') ? 'text-blue-600 font-bold border-b-2 border-blue-600 pb-1' : 'hover:text-blue-600' }}">
                Peminjaman & Laporan
            </a>
            @endif

            {{-- Link Kelola Akun (Hanya Admin) --}}
            @if(Auth::user() && method_exists(Auth::user(), 'hasRole') && Auth::user()->hasRole('admin'))
            <a href="{{ route('admin.users') }}"
                class="{{ request()->routeIs('admin.users') ? 'text-blue-600 font-bold border-b-2 border-blue-600 pb-1' : 'hover:text-blue-600' }}">
                Kelola Akun & Role
            </a>
            @endif

            {{-- Link Edit Profil --}}
            <a href="{{ route('profile.edit') }}"
                class="{{ request()->routeIs('profile.*') ? 'text-blue-600 font-bold border-b-2 border-blue-600 pb-1' : 'hover:text-blue-600' }}">
                Profil Saya
            </a>

            {{-- Tombol Logout --}}
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button type="submit" class="hover:text-red-600 font-semibold transition">
                    Keluar ({{ Auth::user()->name }})
                </button>
            </form>
        </nav>

        <!-- Slot Isi Konten Tiap Halaman -->
        <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>
    </div>

    <!-- Footer -->
    <footer class="bg-white py-4 text-center text-xs text-gray-500 border-t border-gray-200">
        © 2026 Sistem Informasi
    </footer>

</body>

</html>