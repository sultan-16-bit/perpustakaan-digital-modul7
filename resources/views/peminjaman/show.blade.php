<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Detail Transaksi Peminjaman') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-bold text-gray-800 border-b pb-2">Informasi Peminjaman</h3>
                <div class="text-sm space-y-2">
                    <p><strong>ID Transaksi:</strong> {{ $peminjaman['id'] ?? '-' }}</p>
                    <p><strong>Nama Peminjam:</strong> {{ $peminjaman['user_name'] ?? '-' }} (User ID: {{ $peminjaman['user_id'] ?? '-' }})</p>
                    <p><strong>Judul Buku:</strong> {{ $peminjaman['judul'] ?? '-' }}</p>
                    <p><strong>Tanggal Pinjam:</strong> {{ $peminjaman['tanggal_pinjam'] ?? '-' }}</p>
                    <p><strong>Tanggal Kembali:</strong> {{ $peminjaman['tanggal_kembali'] ?? '-' }}</p>
                    <p><strong>Status:</strong> {{ $peminjaman['status'] ?? '-' }}</p>
                </div>
                <div class="pt-4">
                    <a href="{{ route('peminjaman.riwayat') }}" class="text-blue-600 hover:underline text-sm font-semibold">
                        &larr; Kembali ke Riwayat
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>