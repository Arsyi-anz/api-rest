<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Lokasi penyimpanan file JSON (prioritaskan folder public/data)
if (file_exists(__DIR__ . '/data/nilai.json')) {
    define('FILE_DATA', __DIR__ . '/data/nilai.json');
} elseif (file_exists(__DIR__ . '/../data/nilai.json')) {
    define('FILE_DATA', __DIR__ . '/../data/nilai.json');
} else {
    define('FILE_DATA', __DIR__ . '/data/nilai.json');
}

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
    $dir = dirname(FILE_DATA);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
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

// Petakan nilai numerik ke grade huruf
function hitungGrade(float $nilai): string
{
    return match (true) {
        $nilai >= 85 => 'A',
        $nilai >= 75 => 'B',
        $nilai >= 65 => 'C',
        $nilai >= 55 => 'D',
        default      => 'E',
    };
}

// ---------- Routing ----------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = $_SERVER['REQUEST_URI'] ?? '/api/nilai';
$path   = rtrim(parse_url($uri, PHP_URL_PATH), '/');

// Izinkan /api/nilai, serta root / dan /index.php agar mudah diakses langsung di browser
$isApiNilai = ($path === '/api/nilai' || $path === '' || $path === '/index.php');

if (!$isApiNilai) {
    kirim(404, ['status' => 'error', 'pesan' => 'Endpoint tidak ditemukan']);
}

// ---------- GET /api/nilai dan GET /api/nilai?id={id} ----------
if ($method === 'GET') {
    $data = bacaData();

    // Cari satu data bila parameter 'id' dikirim
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $item = array_values(array_filter($data, fn($i) => ($i['id'] ?? null) == $id));

        if (empty($item)) {
            kirim(404, [
                'status'  => 'error',
                'message' => 'Data tidak ditemukan',
            ]);
        }

        $satu = $item[0];
        $satu['grade'] = hitungGrade((float) ($satu['nilai'] ?? 0));

        kirim(200, ['status' => 'success', 'data' => $satu]);
    }

    // Petakan (map) data untuk menambahkan properti 'grade' di samping 'keterangan'
    $data = array_map(function($item) {
        $item['grade'] = hitungGrade((float) ($item['nilai'] ?? 0));
        return $item;
    }, $data);

    // Filter opsional berdasarkan keterangan (Lulus / Tidak Lulus)
    if (!empty($_GET['keterangan'])) {
        $data = array_values(array_filter($data, fn($i) => ($i['keterangan'] ?? '') === $_GET['keterangan']));
    }

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
    $nim  = trim((string) ($input['nim'] ?? ''));

    $error = [];
    if ($nama === '') $error[] = 'nama wajib diisi';
    if ($mk === '')   $error[] = 'mata_kuliah wajib diisi';
    if ($nim === '' || !ctype_digit($nim) || strlen($nim) !== 8) {
        $error[] = 'nim wajib diisi, hanya angka, dan tepat 8 digit';
    }
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
        'nim'         => $nim,
        'mata_kuliah' => $mk,
        'nilai'       => $nilai + 0,
        'keterangan'  => tentukanKeterangan((float) $nilai),
        'grade'       => hitungGrade((float) $nilai),
    ];

    $data[] = $baru;
    simpanData($data);

    kirim(201, ['status' => 'success', 'pesan' => 'Data berhasil disimpan', 'data' => $baru]);
}

// ---------- Method lain ----------
header('Allow: GET, POST');
kirim(405, ['status' => 'error', 'pesan' => 'Method tidak diizinkan']);
