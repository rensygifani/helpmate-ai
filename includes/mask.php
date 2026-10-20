<?php
// Penyamaran data sensitif (versi lengkap, Checkpoint 3).
// Dipakai di dua tempat:
//  1. saat merchant atau petugas menyimpan tiket dan catatan, supaya data sensitif tidak tersimpan di database
//  2. sebelum teks dikirim ke Gemini
// Fungsi mengembalikan teks yang sudah disamarkan dan daftar jenis data yang ditemukan.

function luhn_valid(string $digits): bool
{
    $sum = 0;
    $alt = false;
    for ($i = strlen($digits) - 1; $i >= 0; $i--) {
        $n = (int) $digits[$i];
        if ($alt) {
            $n *= 2;
            if ($n > 9) {
                $n -= 9;
            }
        }
        $sum += $n;
        $alt = !$alt;
    }
    return $sum % 10 === 0;
}

// Kata di depan angka yang menandakan angka itu ID transaksi, bukan nomor kartu
function mask_is_id_context(string $before): bool
{
    return (bool) preg_match('/(id|trx|transaksi|ref|referensi|order|invoice|inv|va|virtual\s*account|no\.?\s*pesanan)\W{0,6}$/iu', $before);
}

function mask_sensitive_report(string $text): array
{
    $found = [];

    // 1. OTP, PIN, CVV: angka setelah kata kuncinya disamarkan
    $text = preg_replace_callback('/\b(otp|pin|cvv|cvc|kode\s+verifikasi|kode\s+otp)\b([^\d\n]{0,14})(\d{3,8})\b/iu', function ($m) use (&$found) {
        $found['otp_pin'] = true;
        return $m[1] . $m[2] . '[DISAMARKAN]';
    }, $text);

    // 2. Password dan kata sandi
    $text = preg_replace_callback('/\b(password|kata\s+sandi|sandi)\b(\s*(?:adalah|=|:)\s*)(\S+)/iu', function ($m) use (&$found) {
        $found['password'] = true;
        return $m[1] . $m[2] . '[DISAMARKAN]';
    }, $text);

    // 3. Kunci API dan token
    $text = preg_replace_callback('/\b(AIza[0-9A-Za-z_\-]{20,}|sk-[A-Za-z0-9_\-]{20,}|eyJ[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,})/', function () use (&$found) {
        $found['kunci_api'] = true;
        return '[KUNCI_API]';
    }, $text);
    $text = preg_replace_callback('/\b(Bearer\s+)[A-Za-z0-9._\-]{20,}/i', function ($m) use (&$found) {
        $found['kunci_api'] = true;
        return $m[1] . '[TOKEN]';
    }, $text);

    // 4. Email dan telepon
    $text = preg_replace_callback('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', function () use (&$found) {
        $found['email'] = true;
        return '[EMAIL]';
    }, $text);
    $text = preg_replace_callback('/(?<!\d)(?:\+62|62|0)8\d{8,11}(?!\d)/', function () use (&$found) {
        $found['telepon'] = true;
        return '[TELEPON]';
    }, $text);

    // 5. Nomor rekening dan NIK berdasarkan kata di depannya
    $text = preg_replace_callback('/\b(rekening|norek|no\.?\s*rek|a\/n|account)\b([^\d\n]{0,18})(\d[\d ]{6,18}\d)/iu', function ($m) use (&$found) {
        $found['rekening'] = true;
        $digits = preg_replace('/\D/', '', $m[3]);
        return $m[1] . $m[2] . '[REKENING ***' . substr($digits, -3) . ']';
    }, $text);
    $text = preg_replace_callback('/\b(nik|ktp)\b([^\d\n]{0,14})(\d{16})\b/iu', function ($m) use (&$found) {
        $found['nik'] = true;
        return $m[1] . $m[2] . '[NIK DISAMARKAN]';
    }, $text);

    // 6. Nomor kartu: 13-19 digit. Disamarkan jika lolos uji Luhn atau didahului kata kartu,
    //    kecuali angka itu jelas ID transaksi.
    $out = '';
    $pos = 0;
    if (preg_match_all('/\b\d(?:[ -]?\d){12,18}\b/', $text, $mm, PREG_OFFSET_CAPTURE)) {
        foreach ($mm[0] as [$num, $offset]) {
            $digits = preg_replace('/\D/', '', $num);
            $before = substr($text, max(0, $offset - 25), min(25, $offset));
            $cardWord = (bool) preg_match('/(kartu|card|debit|kredit|visa|master|\bcc)\b[^\d]{0,20}$/iu', $before);
            $mask = false;
            if (strlen($digits) >= 13 && strlen($digits) <= 19) {
                if ($cardWord) {
                    $mask = true;
                } elseif (!mask_is_id_context($before) && luhn_valid($digits)) {
                    $mask = true;
                }
            }
            $out .= substr($text, $pos, $offset - $pos);
            if ($mask) {
                $found['kartu'] = true;
                $out .= '[KARTU ****' . substr($digits, -4) . ']';
            } else {
                $out .= $num;
            }
            $pos = $offset + strlen($num);
        }
    }
    $text = $out . substr($text, $pos);

    return [$text, array_keys($found)];
}

function mask_sensitive(string $text): string
{
    return mask_sensitive_report($text)[0];
}

const MASK_LABELS = [
    'kartu'    => 'nomor kartu',
    'rekening' => 'nomor rekening',
    'nik'      => 'NIK',
    'otp_pin'  => 'OTP atau PIN',
    'password' => 'password',
    'kunci_api' => 'kunci API atau token',
    'email'    => 'email',
    'telepon'  => 'nomor telepon',
];

// Samarkan beberapa kolom sekaligus. Mengembalikan [kolom yang sudah bersih, daftar label yang ditemukan]
function mask_fields(array $data, array $keys): array
{
    $labels = [];
    foreach ($keys as $k) {
        if (isset($data[$k]) && is_string($data[$k])) {
            [$data[$k], $found] = mask_sensitive_report($data[$k]);
            foreach ($found as $f) {
                $labels[$f] = MASK_LABELS[$f] ?? $f;
            }
        }
    }
    return [$data, array_values($labels)];
}
