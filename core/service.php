<?php

function konversiGolongan($golongan)
{
    $golongan = strtoupper(trim($golongan));

    switch ($golongan) {
        case 'IV/E':
        case 'IV/D':
        case 'IV/C':
        case 'IV/B':
        case 'IV/A':
            return 100;

        case 'III/D':
        case 'III/C':
        case 'III/B':
        case 'III/A':
            return 80;

        case 'II/D':
        case 'II/C':
        case 'II/B':
        case 'II/A':
            return 60;

        default:
            return 40;
    }
}

function konversiDisiplin($disiplin)
{
    switch ($disiplin) {

        case 'Sangat Baik':
            return 100;

        case 'Baik':
            return 80;

        case 'Cukup':
            return 60;

        case 'Kurang':
            return 40;

        default:
            return 40;
    }
}

function hitungKelayakan($pdo, $pegawai)
{
    /*
    Bobot AHP
    */

    $bobotMasaKerja = 0.40;
    $bobotKehadiran = 0.30;
    $bobotGolongan = 0.20;
    $bobotDisiplin = 0.10;

    $masaKerja = min(
        ($pegawai['masa_kerja'] / 30) * 100,
        100
    );

        $kehadiran = min(
            $pegawai['kehadiran'] ?? 80,
            100
        );

    $golongan = konversiGolongan(
        $pegawai['golongan']
    );

    $disiplin = konversiDisiplin(
        $pegawai['disiplin'] ?? 'Baik'
    );

    $nilaiAkhir =
        ($masaKerja * $bobotMasaKerja) +
        ($kehadiran * $bobotKehadiran) +
        ($golongan * $bobotGolongan) +
        ($disiplin * $bobotDisiplin);

    if ($nilaiAkhir >= 85) {
        $kategori = 'Sangat Layak';
    } elseif ($nilaiAkhir >= 70) {
        $kategori = 'Layak';
    } elseif ($nilaiAkhir >= 55) {
        $kategori = 'Cukup Layak';
    } else {
        $kategori = 'Tidak Layak';
    }

    return [
        'masa_kerja' => round($masaKerja, 2),
        'kehadiran' => round($kehadiran, 2),
        'golongan' => round($golongan, 2),
        'disiplin' => round($disiplin, 2),
        'nilai' => round($nilaiAkhir, 2),
        'kategori' => $kategori
    ];
}