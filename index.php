<?php
header('Content-Type: application/json; charset=utf-8');

const FILE_DATA = __DIR__ . '/data/nilai.json';

// Kirim response JSON lalu hentikan program
function kirim(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Baca isi file JSON menjadi array PHP
function bacaData(): array
{
    if (!file_exists(FILE_DATA)) {
        return [];
    }
    $isi = file_get_contents(FILE_DATA);
    return json_decode($isi, true) ?? [];
}

// Tulis array PHP ke file JSON (LOCK_EX mencegah tabrakan tulis)
function simpanData(array $data): void
{
    file_put_contents(
        FILE_DATA,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

// Aturan bisnis: 60 ke atas Lulus, di bawah 60 Tidak Lulus
function tentukanKeterangan(float $nilai): string
{
    return $nilai >= 60 ? 'Lulus' : 'Tidak Lulus';
}

// ---------- Routing ----------
$method = $_SERVER['REQUEST_METHOD'];
$path   = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

if ($path !== '/api/nilai') {
    kirim(404, ['status' => 'error', 'pesan' => 'Endpoint tidak ditemukan']);
}

// ---------- GET /api/nilai ----------
if ($method === 'GET') {
    $data = bacaData();
    kirim(200, [
        'status' => 'success',
        'total'  => count($data),
        'data'   => $data,
    ]);
}

// ---------- POST /api/nilai ----------
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        kirim(400, ['status' => 'error', 'pesan' => 'Body harus berupa JSON yang valid']);
    }

    $nama = trim($input['nama'] ?? '');
    $mk   = trim($input['mata_kuliah'] ?? '');
    $nilai = $input['nilai'] ?? null;

    $error = [];
    if ($nama === '') $error[] = 'nama wajib diisi';
    if ($mk === '')   $error[] = 'mata_kuliah wajib diisi';
    if (!is_numeric($nilai) || $nilai < 0 || $nilai > 100) {
        $error[] = 'nilai harus angka 0 sampai 100';
    }

    if ($error) {
        kirim(400, ['status' => 'error', 'pesan' => 'Data tidak valid', 'detail' => $error]);
    }

    $data = bacaData();

    // id baru = id terbesar + 1
    $idBaru = $data ? max(array_column($data, 'id')) + 1 : 1;

    $baru = [
        'id'          => $idBaru,
        'nama'        => $nama,
        'mata_kuliah' => $mk,
        'nilai'       => $nilai + 0,
        'keterangan'  => tentukanKeterangan((float) $nilai),
    ];

    $data[] = $baru;
    simpanData($data);

    kirim(201, ['status' => 'success', 'pesan' => 'Data berhasil disimpan', 'data' => $baru]);
}

// ---------- Method lain ----------
header('Allow: GET, POST');
kirim(405, ['status' => 'error', 'pesan' => 'Method tidak diizinkan']);
