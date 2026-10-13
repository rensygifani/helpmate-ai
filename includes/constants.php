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
    'merchant'   => 'Merchant',
    'cs'         => 'CS (Customer Support)',
    'tim_teknis' => 'Tim Teknis',
    'admin'      => 'Admin',
];
