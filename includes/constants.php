<?php
const CATEGORIES = [
    'transaksi_ganda'  => 'Transaksi ganda',
    'transaksi_gagal'  => 'Transaksi gagal',
    'pending'          => 'Transaksi pending',
    'saldo_terpotong'  => 'Saldo terpotong',
    'settlement'       => 'Settlement belum masuk',
    'refund'           => 'Refund',
    'integrasi_api'    => 'Integrasi API / callback',
    'akun_merchant'    => 'Akun merchant',
    'lainnya'          => 'Lainnya',
];

const PRIORITIES = [
    'rendah' => 'Rendah',
    'sedang' => 'Sedang',
    'tinggi' => 'Tinggi',
    'kritis' => 'Kritis',
];

const STATUSES = [
    'baru'              => 'Baru',
    'diproses'          => 'Diproses',
    'menunggu_merchant' => 'Menunggu merchant',
    'selesai'           => 'Selesai',
];

const PAYMENT_METHODS = [
    'va'      => 'Virtual account',
    'qris'    => 'QRIS',
    'ewallet' => 'Dompet digital',
    'kartu'   => 'Kartu',
];

const ROLES = [
    'merchant'    => 'Merchant',
    'cs'          => 'CS (Customer Support)',
    'tim_teknis'  => 'Tim Teknis',
    'admin'       => 'Admin',
    'super_admin' => 'Super Admin',
];

// Detail asal akun merchant
const ACCOUNT_TYPES = [
    'individu'   => 'Perorangan / pengguna pribadi',
    'sekolah'    => 'Sekolah / institusi pendidikan',
    'perusahaan' => 'Perusahaan / bisnis',
    'klien'      => 'Klien / mitra',
    'lainnya'    => 'Lainnya',
];

// Jenis tindakan pada Riwayat Tindak Lanjut
const FOLLOWUP_TYPES = [
    'catatan'          => 'Catatan internal',
    'hubungi_merchant' => 'Menghubungi merchant',
    'balasan'          => 'Balasan ke merchant',
    'eskalasi_teknis'  => 'Eskalasi ke tim teknis',
    'perbaikan'        => 'Perbaikan / tindakan teknis',
    'selesai'          => 'Penyelesaian',
    'tanggapan_merchant' => 'Tanggapan merchant',
];

// Jenis tindakan yang boleh dipilih petugas (tanggapan merchant dibuat oleh merchant sendiri)
const FOLLOWUP_STAFF_TYPES = ['catatan', 'hubungi_merchant', 'balasan', 'eskalasi_teknis', 'perbaikan', 'selesai'];

// Jenis tindakan yang boleh dibaca merchant. Catatan internal dan tindakan teknis tidak ditampilkan ke merchant.
const FOLLOWUP_MERCHANT_VISIBLE = ['hubungi_merchant', 'balasan', 'selesai', 'tanggapan_merchant'];

// Batas waktu respons pertama (jam) menurut prioritas tiket (SLA)
const SLA_HOURS = ['kritis' => 1, 'tinggi' => 4, 'sedang' => 24, 'rendah' => 72];
