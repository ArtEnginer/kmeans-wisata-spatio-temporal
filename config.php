<?php
// ============================================================
// KONFIGURASI DATABASE
// ============================================================

require_once __DIR__ . '/env.php';
loadEnv(__DIR__ . '/.env');

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME', 'spatemporal'));
define('DB_PORT', env('DB_PORT', '3306'));

// Create connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, intval(DB_PORT));

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Set charset to UTF-8
    $conn->set_charset("utf8");
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Function to fetch all destinations
function getAllDestinations($conn)
{
    $sql = "SELECT * FROM destinations ORDER BY id ASC";
    $result = $conn->query($sql);
    $destinations = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $destinations[] = $row;
        }
    }

    return $destinations;
}

// Function to fetch single destination by ID
function getDestinationById($conn, $id)
{
    $id = intval($id);
    $sql = "SELECT * FROM destinations WHERE id = $id";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    return null;
}

// Function to fetch cluster info
function getClusterInfo($conn)
{
    $sql = "SELECT * FROM cluster_info ORDER BY klaster ASC";
    $result = $conn->query($sql);
    $clusters = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $clusters[$row['klaster']] = $row;
        }
    }

    return $clusters;
}

// Function to fetch evaluasi metrics
function getEvaluasi($conn)
{
    $sql = "SELECT * FROM evaluasi ORDER BY id DESC LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    return null;
}

// Function to fetch proyeksi data
function getProyeksi($conn)
{
    $sql = "SELECT * FROM proyeksi ORDER BY id ASC";
    $result = $conn->query($sql);
    $proyeksi = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $proyeksi[] = $row;
        }
    }

    return $proyeksi;
}

// ============================================================
// USER MANAGEMENT FUNCTIONS
// ============================================================

// Function to authenticate user
function authenticateUser($conn, $username, $password)
{
    $username = $conn->real_escape_string($username);
    $password = md5($password); // Simple MD5 hashing - TODO: use bcrypt in production

    $sql = "SELECT id, username, nama_lengkap, email, role, is_active FROM users 
            WHERE username = '$username' AND password = '$password' AND is_active = 1";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    return null;
}

// Function to get all users
function getAllUsers($conn)
{
    $sql = "SELECT id, username, nama_lengkap, email, role, is_active, created_at FROM users ORDER BY id ASC";
    $result = $conn->query($sql);
    $users = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }

    return $users;
}

// Function to get user by ID
function getUserById($conn, $id)
{
    $id = intval($id);
    $sql = "SELECT id, username, nama_lengkap, email, role, is_active, created_at FROM users WHERE id = $id";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    return null;
}

// Function to add new user
function addUser($conn, $username, $password, $nama_lengkap, $email, $role)
{
    $username = $conn->real_escape_string($username);
    $password = md5($password);
    $nama_lengkap = $conn->real_escape_string($nama_lengkap);
    $email = $conn->real_escape_string($email);
    $role = $conn->real_escape_string($role);

    $sql = "INSERT INTO users (username, password, nama_lengkap, email, role, is_active) 
            VALUES ('$username', '$password', '$nama_lengkap', '$email', '$role', 1)";

    return $conn->query($sql);
}

// Function to update user
function updateUser($conn, $id, $username, $nama_lengkap, $email, $role, $is_active)
{
    $id = intval($id);
    $username = $conn->real_escape_string($username);
    $nama_lengkap = $conn->real_escape_string($nama_lengkap);
    $email = $conn->real_escape_string($email);
    $role = $conn->real_escape_string($role);
    $is_active = intval($is_active);

    $sql = "UPDATE users SET username = '$username', nama_lengkap = '$nama_lengkap', 
            email = '$email', role = '$role', is_active = $is_active WHERE id = $id";

    return $conn->query($sql);
}

// Function to delete user
function deleteUser($conn, $id)
{
    $id = intval($id);
    $sql = "DELETE FROM users WHERE id = $id";

    return $conn->query($sql);
}

// ============================================================
// IMPORT/EXPORT FUNCTIONS
// ============================================================

// ============================================================
// IMPORT/EXPORT FUNCTIONS (EXCEL & CSV SUPPORT)
// ============================================================

/**
 * Parsing file XLSX menjadi array 2 dimensi.
 */
function parseXlsx($filePath)
{
    if (!class_exists('ZipArchive')) {
        throw new Exception("Ekstensi PHP 'zip' (ZipArchive) tidak terinstall di server.");
    }
    
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== TRUE) {
        return false;
    }

    // 1. Ambil shared strings
    $sharedStrings = [];
    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedStringsXml) {
        $xml = simplexml_load_string($sharedStringsXml);
        if ($xml) {
            foreach ($xml->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } elseif (isset($si->r)) {
                    $text = '';
                    foreach ($si->r as $r) {
                        $text .= (string)$r->t;
                    }
                    $sharedStrings[] = $text;
                } else {
                    $sharedStrings[] = '';
                }
            }
        }
    }

    // 2. Ambil worksheet pertama
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if (!$sheetXml) {
        $zip->close();
        return false;
    }

    $xml = simplexml_load_string($sheetXml);
    if (!$xml) {
        $zip->close();
        return false;
    }

    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $rowIndex = (int)$row['r'];
        $rowData = [];
        
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            preg_match('/^([A-Z]+)([0-9]+)$/', $ref, $matches);
            $colLetter = $matches[1];
            
            // Konversi kolom huruf ke index 0-based
            $colIndex = 0;
            $len = strlen($colLetter);
            for ($i = 0; $i < $len; $i++) {
                $colIndex = $colIndex * 26 + (ord($colLetter[$i]) - 64);
            }
            $colIndex = $colIndex - 1;
            
            $val = '';
            if (isset($c->v)) {
                $val = (string)$c->v;
                if (isset($c['t']) && (string)$c['t'] === 's') {
                    $idx = (int)$val;
                    $val = isset($sharedStrings[$idx]) ? $sharedStrings[$idx] : '';
                }
            }
            $rowData[$colIndex] = $val;
        }
        
        if (!empty($rowData)) {
            $maxCol = max(array_keys($rowData));
            for ($i = 0; $i <= $maxCol; $i++) {
                if (!isset($rowData[$i])) {
                    $rowData[$i] = '';
                }
            }
            ksort($rowData);
            $rows[$rowIndex] = $rowData;
        }
    }

    $zip->close();
    ksort($rows);
    return array_values($rows);
}

/**
 * Parsing file CSV menjadi array 2 dimensi.
 */
function getRowsFromCSV($filePath)
{
    if (!file_exists($filePath)) {
        return [];
    }

    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return [];
    }

    $rows = [];
    $firstLine = fgets($handle);
    
    // Hilangkan UTF-8 BOM jika ada
    if (substr($firstLine, 0, 3) === "\xEF\xBB\xBF") {
        $firstLine = substr($firstLine, 3);
    }
    
    // Cek jika separator diset, misal "sep=," atau "sep=;"
    $separator = ',';
    if (preg_match('/^sep=(.)$/i', trim($firstLine), $matches)) {
        $separator = $matches[1];
    } else {
        rewind($handle);
        $bomCheck = fread($handle, 3);
        if ($bomCheck !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
    }

    while (($row = fgetcsv($handle, 0, $separator)) !== FALSE) {
        $rows[] = $row;
    }

    fclose($handle);
    return $rows;
}

/**
 * Simpan data array 2 dimensi ke database.
 */
function importDestinations($conn, $rows)
{
    if (empty($rows)) {
        return ['success' => false, 'message' => 'Tidak ada data untuk diimport.'];
    }

    // Skip baris header jika kolom pertama adalah 'nama'
    $firstRow = $rows[0];
    if (isset($firstRow[0]) && strtolower(trim($firstRow[0])) === 'nama') {
        array_shift($rows);
    }

    $importedCount = 0;
    $errorCount = 0;

    foreach ($rows as $row) {
        $row = array_pad($row, 12, '');

        $nama = $conn->real_escape_string(trim($row[0]));
        if ($nama === '') {
            continue; // Skip baris kosong
        }

        $lon = floatval(trim($row[1]));
        $lat = floatval(trim($row[2]));
        $kunjungan = intval(trim($row[3]));
        $rating = floatval(trim($row[4]));
        $aksesibilitas = intval(trim($row[5]));
        $fasilitas = intval(trim($row[6]));
        $potensi_alam = intval(trim($row[7]));
        $potensi_budaya = intval(trim($row[8]));
        $pendapatan = floatval(trim($row[9]));
        $trend = floatval(trim($row[10]));
        $zona = $conn->real_escape_string(trim($row[11]));

        $sql = "INSERT INTO destinations (nama, lon, lat, kunjungan, rating, aksesibilitas, fasilitas, 
                potensi_alam, potensi_budaya, pendapatan, trend, zona, klaster, skor, rekomendasi) 
                VALUES ('$nama', $lon, $lat, $kunjungan, $rating, $aksesibilitas, $fasilitas, 
                $potensi_alam, $potensi_budaya, $pendapatan, $trend, '$zona', 0, 0.0000, 'Belum dianalisis')";

        if ($conn->query($sql) === TRUE) {
            $importedCount++;
        } else {
            $errorCount++;
        }
    }

    return [
        'success' => true,
        'imported' => $importedCount,
        'failed' => $errorCount,
        'message' => "Import selesai: $importedCount data berhasil, $errorCount data gagal."
    ];
}

/**
 * Handler utama untuk import file (CSV / XLSX).
 */
function importDestinationsFromFile($conn, $filePath, $extension)
{
    try {
        if (strtolower($extension) === 'xlsx') {
            $rows = parseXlsx($filePath);
            if ($rows === false) {
                return ['success' => false, 'message' => 'Gagal membaca file Excel (.xlsx)'];
            }
        } else {
            $rows = getRowsFromCSV($filePath);
        }
        return importDestinations($conn, $rows);
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Wrapper untuk kecocokan ke belakang
function importDestinationsFromCSV($conn, $filePath)
{
    return importDestinationsFromFile($conn, $filePath, 'csv');
}

/**
 * Generate File Excel (.xlsx) secara native tanpa library eksternal.
 */
function generateXlsx($filename, $headers, $dataRows = [])
{
    $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        return false;
    }

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>';
    $zip->addFromString('[Content_Types].xml', $contentTypes);

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    $zip->addFromString('_rels/.rels', $rels);

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>';
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Sheet1" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';
    $zip->addFromString('xl/workbook.xml', $workbook);

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>';

    // Add headers
    $sheet .= '<row r="1">';
    $colIndex = 0;
    foreach ($headers as $header) {
        $colLetter = '';
        $tempCol = $colIndex;
        while ($tempCol >= 0) {
            $colLetter = chr(($tempCol % 26) + 65) . $colLetter;
            $tempCol = intval($tempCol / 26) - 1;
        }
        $cleanHeader = htmlspecialchars($header, ENT_QUOTES, 'UTF-8');
        $sheet .= '<c r="' . $colLetter . '1" t="inlineStr"><is><t>' . $cleanHeader . '</t></is></c>';
        $colIndex++;
    }
    $sheet .= '</row>';

    // Add data rows
    $rowNum = 2;
    foreach ($dataRows as $rowData) {
        $sheet .= '<row r="' . $rowNum . '">';
        $colIndex = 0;
        foreach ($rowData as $val) {
            $colLetter = '';
            $tempCol = $colIndex;
            while ($tempCol >= 0) {
                $colLetter = chr(($tempCol % 26) + 65) . $colLetter;
                $tempCol = intval($tempCol / 26) - 1;
            }
            $cellRef = $colLetter . $rowNum;
            
            if (is_numeric($val) && !preg_match('/^0[0-9]+/', $val)) {
                $sheet .= '<c r="' . $cellRef . '"><v>' . $val . '</v></c>';
            } else {
                $cleanVal = htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
                $sheet .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . $cleanVal . '</t></is></c>';
            }
            $colIndex++;
        }
        $sheet .= '</row>';
        $rowNum++;
    }

    $sheet .= '  </sheetData>
</worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    // Jalankan download file
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($tempFile));
    readfile($tempFile);
    unlink($tempFile);
    exit;
}

/**
 * Export data dari database ke Excel (.xlsx).
 */
function exportDestinationsToXlsx($conn)
{
    $destinations = getAllDestinations($conn);
    $filename = "destinasi_wisata_" . date('Y-m-d_H-i-s') . ".xlsx";

    $headers = [
        'Nama', 'Longitude', 'Latitude', 'Kunjungan', 'Rating', 'Aksesibilitas', 'Fasilitas', 
        'Potensi Alam', 'Potensi Budaya', 'Pendapatan', 'Trend', 'Zona', 'Klaster', 'Skor', 'Rekomendasi'
    ];

    $dataRows = [];
    foreach ($destinations as $dest) {
        $dataRows[] = [
            $dest['nama'],
            $dest['lon'],
            $dest['lat'],
            $dest['kunjungan'],
            $dest['rating'],
            $dest['aksesibilitas'],
            $dest['fasilitas'],
            $dest['potensi_alam'],
            $dest['potensi_budaya'],
            $dest['pendapatan'],
            $dest['trend'],
            $dest['zona'],
            $dest['klaster'],
            $dest['skor'],
            $dest['rekomendasi']
        ];
    }

    generateXlsx($filename, $headers, $dataRows);
}

/**
 * Download template Excel (.xlsx).
 */
function downloadTemplateXlsx()
{
    $filename = "template_import_destinasi.xlsx";

    $headers = [
        'Nama', 'Longitude', 'Latitude', 'Kunjungan', 'Rating', 'Aksesibilitas', 'Fasilitas', 
        'Potensi Alam', 'Potensi Budaya', 'Pendapatan', 'Trend', 'Zona'
    ];

    $sampleData = [
        [
            'Candi Borobudur', '110.2037', '-7.6079', '1250000', '4.9', '5', '5', 
            '4', '5', '18500', '0.123', 'Peak Season'
        ],
        [
            'Punthuk Setumbu', '110.1952', '-7.6025', '185000', '4.7', '3', '3', 
            '5', '4', '2800', '0.185', 'Peak Season'
        ]
    ];

    generateXlsx($filename, $headers, $sampleData);
}

// Function to export destinations to CSV
function exportDestinationsToCSV($conn)
{
    $destinations = getAllDestinations($conn);

    $filename = "destinasi_wisata_" . date('Y-m-d_H-i-s') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Tulis UTF-8 BOM agar Excel membukanya dengan benar
    fwrite($output, "\xEF\xBB\xBF");
    // Tulis separator info agar Excel membukanya otomatis dengan separator koma
    fwrite($output, "sep=,\n");

    // Header row
    fputcsv($output, [
        'Nama',
        'Longitude',
        'Latitude',
        'Kunjungan',
        'Rating',
        'Aksesibilitas',
        'Fasilitas',
        'Potensi Alam',
        'Potensi Budaya',
        'Pendapatan',
        'Trend',
        'Zona',
        'Klaster',
        'Skor',
        'Rekomendasi'
    ]);

    // Data rows
    foreach ($destinations as $dest) {
        fputcsv($output, [
            $dest['nama'],
            $dest['lon'],
            $dest['lat'],
            $dest['kunjungan'],
            $dest['rating'],
            $dest['aksesibilitas'],
            $dest['fasilitas'],
            $dest['potensi_alam'],
            $dest['potensi_budaya'],
            $dest['pendapatan'],
            $dest['trend'],
            $dest['zona'],
            $dest['klaster'],
            $dest['skor'],
            $dest['rekomendasi']
        ]);
    }

    fclose($output);
    exit;
}

/**
 * Algoritma Spatio-Temporal K-Means++ Core
 */
function runKMeansClustering($conn)
{
    $destinations = getAllDestinations($conn);
    $n = count($destinations);
    if ($n < 3) {
        return ['success' => false, 'message' => 'Minimal harus ada 3 destinasi untuk klasterisasi.'];
    }

    $features = ['lon', 'lat', 'kunjungan', 'rating', 'aksesibilitas', 'fasilitas', 'potensi_alam', 'potensi_budaya', 'pendapatan', 'trend'];

    // 1. Cari Min dan Max per fitur
    $minMax = [];
    foreach ($features as $f) {
        $vals = array_column($destinations, $f);
        $minMax[$f] = [
            'min' => floatval(min($vals)),
            'max' => floatval(max($vals)),
        ];
        $minMax[$f]['range'] = $minMax[$f]['max'] - $minMax[$f]['min'];
    }

    // 2. Normalisasi Min-Max
    $normData = [];
    foreach ($destinations as $d) {
        $id = $d['id'];
        $norm = ['id' => $id, 'nama' => $d['nama']];
        foreach ($features as $f) {
            $val = floatval($d[$f]);
            $min = $minMax[$f]['min'];
            $range = $minMax[$f]['range'];
            $norm[$f] = $range == 0 ? 0.0 : ($val - $min) / $range;
        }
        $normData[$id] = $norm;
    }

    // 3. Inisialisasi Centroid K-Means++
    // C1: Cari Borobudur, fallback ke kunjungan max
    $c1_id = null;
    foreach ($destinations as $d) {
        if (stripos($d['nama'], 'Borobudur') !== false) {
            $c1_id = $d['id'];
            break;
        }
    }
    if ($c1_id === null) {
        $maxVisits = -1;
        foreach ($destinations as $d) {
            if ($d['kunjungan'] > $maxVisits) {
                $maxVisits = $d['kunjungan'];
                $c1_id = $d['id'];
            }
        }
    }
    $c1 = $normData[$c1_id];

    // Jarak kuadrat ke C1
    $d2_c1 = [];
    $total_d2_c1 = 0.0;
    foreach ($normData as $id => $norm) {
        $dist = 0.0;
        foreach ($features as $f) {
            $dist += pow($norm[$f] - $c1[$f], 2);
        }
        $d2_c1[$id] = $dist;
        $total_d2_c1 += $dist;
    }

    // C2: Cari D²(x, C1) maksimum
    $c2_id = null;
    $max_d2 = -1.0;
    foreach ($d2_c1 as $id => $dist) {
        if ($id == $c1_id) continue;
        if ($dist > $max_d2) {
            $max_d2 = $dist;
            $c2_id = $id;
        }
    }
    $c2 = $normData[$c2_id];

    // Jarak kuadrat ke C2
    $d2_c2 = [];
    foreach ($normData as $id => $norm) {
        $dist = 0.0;
        foreach ($features as $f) {
            $dist += pow($norm[$f] - $c2[$f], 2);
        }
        $d2_c2[$id] = $dist;
    }

    // C3: Cari D²_min maksimum
    $d2_min = [];
    $max_d2_min = -1.0;
    $c3_id = null;
    foreach ($normData as $id => $norm) {
        if ($id == $c1_id || $id == $c2_id) {
            $d2_min[$id] = 0.0;
            continue;
        }
        $min_d = min($d2_c1[$id], $d2_c2[$id]);
        $d2_min[$id] = $min_d;
        if ($min_d > $max_d2_min) {
            $max_d2_min = $min_d;
            $c3_id = $id;
        }
    }
    $c3 = $normData[$c3_id];

    // Centroids
    $centroids = [
        1 => $c1,
        2 => $c2,
        3 => $c3
    ];

    // 4. Iterasi K-Means
    $max_iter = 10;
    $iter_count = 0;
    $prev_assignments = [];
    $iterations_history = [];
    $converged = false;
    $assignments = [];
    $wcss = 0.0;

    while (!$converged && $iter_count < $max_iter) {
        $iter_count++;
        $assignments = [];
        $wcss = 0.0;

        foreach ($normData as $id => $norm) {
            $min_dist = INF;
            $best_k = 1;
            for ($k = 1; $k <= 3; $k++) {
                $dist = 0.0;
                foreach ($features as $f) {
                    $dist += pow($norm[$f] - $centroids[$k][$f], 2);
                }
                if ($dist < $min_dist) {
                    $min_dist = $dist;
                    $best_k = $k;
                }
            }
            $assignments[$id] = $best_k;
            $wcss += $min_dist;
        }

        $iterations_history[$iter_count] = [
            'centroids' => $centroids,
            'assignments' => $assignments,
            'wcss' => $wcss
        ];

        if ($assignments === $prev_assignments) {
            $converged = true;
            break;
        }
        $prev_assignments = $assignments;

        // Update Centroids
        $new_centroids = [
            1 => array_fill_keys($features, 0.0),
            2 => array_fill_keys($features, 0.0),
            3 => array_fill_keys($features, 0.0)
        ];
        $counts = [1 => 0, 2 => 0, 3 => 0];
        foreach ($normData as $id => $norm) {
            $k = $assignments[$id];
            $counts[$k]++;
            foreach ($features as $f) {
                $new_centroids[$k][$f] += $norm[$f];
            }
        }
        for ($k = 1; $k <= 3; $k++) {
            if ($counts[$k] > 0) {
                foreach ($features as $f) {
                    $new_centroids[$k][$f] /= $counts[$k];
                }
                $centroids[$k] = $new_centroids[$k];
            }
        }
    }

    // 5. Perhitungan Silhouette Coefficient (SC)
    $silhouette_scores = [];
    $total_sc = 0.0;
    foreach ($normData as $id => $norm) {
        $cluster = $assignments[$id];
        $same_dists = [];
        foreach ($normData as $other_id => $other_norm) {
            if ($id == $other_id || $assignments[$other_id] != $cluster) continue;
            $dist = 0.0;
            foreach ($features as $f) {
                $dist += pow($norm[$f] - $other_norm[$f], 2);
            }
            $same_dists[] = sqrt($dist);
        }
        $a_i = empty($same_dists) ? 0.0 : array_sum($same_dists) / count($same_dists);

        $other_cluster_avg_dists = [];
        for ($k = 1; $k <= 3; $k++) {
            if ($k == $cluster) continue;
            $k_dists = [];
            foreach ($normData as $other_id => $other_norm) {
                if ($assignments[$other_id] != $k) continue;
                $dist = 0.0;
                foreach ($features as $f) {
                    $dist += pow($norm[$f] - $other_norm[$f], 2);
                }
                $k_dists[] = sqrt($dist);
            }
            if (!empty($k_dists)) {
                $other_cluster_avg_dists[$k] = array_sum($k_dists) / count($k_dists);
            }
        }
        $b_i = empty($other_cluster_avg_dists) ? 0.0 : min($other_cluster_avg_dists);

        $s_i = max($a_i, $b_i) == 0 ? 0.0 : ($b_i - $a_i) / max($a_i, $b_i);
        $silhouette_scores[$id] = $s_i;
        $total_sc += $s_i;
    }
    $avg_sc = $total_sc / $n;

    // 6. Perhitungan Davies-Bouldin Index (DBI)
    $S_k = [1 => 0.0, 2 => 0.0, 3 => 0.0];
    $counts = [1 => 0, 2 => 0, 3 => 0];
    foreach ($normData as $id => $norm) {
        $k = $assignments[$id];
        $counts[$k]++;
        $dist = 0.0;
        foreach ($features as $f) {
            $dist += pow($norm[$f] - $centroids[$k][$f], 2);
        }
        $S_k[$k] += sqrt($dist);
    }
    for ($k = 1; $k <= 3; $k++) {
        if ($counts[$k] > 0) {
            $S_k[$k] /= $counts[$k];
        }
    }

    $R = [];
    for ($i = 1; $i <= 3; $i++) {
        $max_r = 0.0;
        for ($j = 1; $j <= 3; $j++) {
            if ($i == $j) continue;
            $centroid_dist = 0.0;
            foreach ($features as $f) {
                $centroid_dist += pow($centroids[$i][$f] - $centroids[$j][$f], 2);
            }
            $centroid_dist = sqrt($centroid_dist);
            if ($centroid_dist > 0) {
                $r_val = ($S_k[$i] + $S_k[$j]) / $centroid_dist;
                if ($r_val > $max_r) {
                    $max_r = $r_val;
                }
            }
        }
        $R[$i] = $max_r;
    }
    $dbi = array_sum($R) / 3.0;

    // 7. Perhitungan Calinski-Harabasz Index (CHI)
    $global_mean = array_fill_keys($features, 0.0);
    foreach ($normData as $id => $norm) {
        foreach ($features as $f) {
            $global_mean[$f] += $norm[$f];
        }
    }
    foreach ($features as $f) {
        $global_mean[$f] /= $n;
    }

    $bcss = 0.0;
    for ($k = 1; $k <= 3; $k++) {
        $dist = 0.0;
        foreach ($features as $f) {
            $dist += pow($centroids[$k][$f] - $global_mean[$f], 2);
        }
        $bcss += $counts[$k] * $dist;
    }
    $chi = $wcss == 0 ? 0.0 : ($bcss / (3 - 1)) / ($wcss / ($n - 3));

    // 8. Skor Potensi dan Rekomendasi
    $scores = [];
    $recommendations = [];
    $rec_mapping = [
        1 => 'Prioritas pengembangan infrastruktur & promosi',
        2 => 'Tingkatkan fasilitas & aksesibilitas',
        3 => 'Eksplorasi & pengembangan awal'
    ];

    foreach ($destinations as $d) {
        $id = $d['id'];
        $norm = $normData[$id];
        $score = (0.4 * $norm['rating']) + (0.3 * $norm['kunjungan']) + (0.2 * $norm['pendapatan']) + (0.1 * (($norm['potensi_alam'] + $norm['potensi_budaya']) / 2));
        $scores[$id] = $score;
        $recommendations[$id] = $rec_mapping[$assignments[$id]] ?? 'Tingkatkan fasilitas & aksesibilitas';
    }

    // Compute centroid inter-distances for DBI display
    $centroid_dists = [];
    for ($i = 1; $i <= 3; $i++) {
        for ($j = 1; $j <= 3; $j++) {
            if ($i == $j) { $centroid_dists[$i][$j] = 0.0; continue; }
            $d = 0.0;
            foreach ($features as $f) {
                $d += pow($centroids[$i][$f] - $centroids[$j][$f], 2);
            }
            $centroid_dists[$i][$j] = sqrt($d);
        }
    }

    // Compute R matrix entries for DBI display
    $R_detail = [];
    for ($i = 1; $i <= 3; $i++) {
        $max_r = 0.0; $max_j = 1;
        for ($j = 1; $j <= 3; $j++) {
            if ($i == $j) continue;
            $cd = $centroid_dists[$i][$j];
            $r_val = $cd > 0 ? ($S_k[$i] + $S_k[$j]) / $cd : 0.0;
            $R_detail[$i][$j] = $r_val;
            if ($r_val > $max_r) { $max_r = $r_val; $max_j = $j; }
        }
        $R[$i] = $max_r;
    }

    return [
        'success' => true,
        'destinations' => $destinations,
        'normData' => $normData,
        'minMax' => $minMax,
        'd2_c1' => $d2_c1,
        'total_d2_c1' => $total_d2_c1,
        'c1_id' => $c1_id,
        'c2_id' => $c2_id,
        'c3_id' => $c3_id,
        'd2_c2' => $d2_c2,
        'd2_min' => $d2_min,
        'iterations_history' => $iterations_history,
        'final_centroids' => $centroids,
        'assignments' => $assignments,
        'wcss' => $wcss,
        'iter_count' => $iter_count,
        'sc' => $avg_sc,
        'dbi' => $dbi,
        'chi' => $chi,
        'silhouette_scores' => $silhouette_scores,
        'scores' => $scores,
        'recommendations' => $recommendations,
        'S_k' => $S_k,
        'R' => $R,
        'R_detail' => $R_detail,
        'centroid_dists' => $centroid_dists,
        'counts' => $counts,
        'bcss' => $bcss,
        'global_mean' => $global_mean,
        'n' => $n,
    ];
}


/**
 * Menyimpan hasil runKMeansClustering ke database
 */
function saveKMeansClusteringResults($conn, $results)
{
    if (!$results['success']) {
        return $results;
    }

    $conn->begin_transaction();
    try {
        // 1. Update Destinations
        foreach ($results['assignments'] as $id => $klaster) {
            $skor = $results['scores'][$id];
            $rekomendasi = $conn->real_escape_string($results['recommendations'][$id]);
            $updateDestSql = "UPDATE destinations SET klaster = $klaster, skor = $skor, rekomendasi = '$rekomendasi' WHERE id = $id";
            $conn->query($updateDestSql);
        }

        // 2. Rebuild Proyeksi
        $conn->query("DELETE FROM proyeksi");
        foreach ($results['destinations'] as $d) {
            $id = $d['id'];
            $nama = $conn->real_escape_string($d['nama']);
            $k = $results['assignments'][$id];
            $trend = floatval($d['trend']);
            $kunjungan = intval($d['kunjungan']);

            $y2024 = $kunjungan;
            $y2025 = round($kunjungan * (1 + $trend));
            $y2026 = round($kunjungan * pow(1 + $trend, 2));
            $y2027 = round($kunjungan * pow(1 + $trend, 3));

            $kat = 'Moderat →';
            if ($trend >= 0.3) {
                $kat = 'Sangat Cepat 🚀';
            } else if ($trend >= 0.15) {
                $kat = 'Cepat ⬆';
            } else if ($trend < 0.0) {
                $kat = 'Lambat ⬇';
            }

            $insertProjSql = "INSERT INTO proyeksi (destination_id, nama, k, y2024, y2025, y2026, y2027, cagr, kat) 
                              VALUES ($id, '$nama', $k, $y2024, $y2025, $y2026, $y2027, $trend, '$kat')";
            $conn->query($insertProjSql);
        }

        // 3. Update Evaluasi
        $sc = $results['sc'];
        $dbi = $results['dbi'];
        $chi = $results['chi'];
        $wcss = $results['wcss'];
        $iter = $results['iter_count'];

        $conn->query("DELETE FROM evaluasi");
        $insertEvalSql = "INSERT INTO evaluasi (sc, dbi, chi, wcss, iter) VALUES ($sc, $dbi, $chi, $wcss, $iter)";
        $conn->query($insertEvalSql);

        // 4. Update Cluster Info
        $counts = [1 => 0, 2 => 0, 3 => 0];
        $total_visits = [1 => 0, 2 => 0, 3 => 0];
        $total_rating = [1 => 0.0, 2 => 0.0, 3 => 0.0];
        $total_revenue = [1 => 0.0, 2 => 0.0, 3 => 0.0];

        foreach ($results['destinations'] as $d) {
            $id = $d['id'];
            $k = $results['assignments'][$id];
            $counts[$k]++;
            $total_visits[$k] += intval($d['kunjungan']);
            $total_rating[$k] += floatval($d['rating']);
            $total_revenue[$k] += floatval($d['pendapatan']);
        }

        $conn->query("DELETE FROM cluster_info");
        $strategies = [
            1 => 'Pertahankan kualitas, ekspansi kapasitas, tingkatkan promosi internasional',
            2 => 'Investasi infrastruktur, sertifikasi standar wisata, paket wisata kombinasi',
            3 => 'Pengembangan aksesibilitas, promosi digital, eco-tourism'
        ];
        $labels = [
            1 => 'Klaster 1: Tinggi',
            2 => 'Klaster 2: Sedang',
            3 => 'Klaster 3: Rendah'
        ];
        $colors = [
            1 => '#f59e0b',
            2 => '#3b82f6',
            3 => '#10b981'
        ];
        $bgs = [
            1 => '#fef3c7',
            2 => '#dbeafe',
            3 => '#d1fae5'
        ];

        for ($k = 1; $k <= 3; $k++) {
            $n_k = $counts[$k];
            $avg_v = $n_k > 0 ? round($total_visits[$k] / $n_k) : 0;
            $avg_r = $n_k > 0 ? round($total_rating[$k] / $n_k, 2) : 0.0;
            $tot_rev = $total_revenue[$k];
            $strat = $conn->real_escape_string($strategies[$k]);
            $lbl = $conn->real_escape_string($labels[$k]);
            $col = $colors[$k];
            $bg = $bgs[$k];

            $insertClusterInfoSql = "INSERT INTO cluster_info (klaster, label, color, bg, n, avg_kunjungan, avg_rating, total_pend, strategi) 
                                     VALUES ($k, '$lbl', '$col', '$bg', $n_k, $avg_v, $avg_r, $tot_rev, '$strat')";
            $conn->query($insertClusterInfoSql);
        }

        $conn->commit();
        return ['success' => true, 'message' => 'Seluruh perhitungan K-Means++ berhasil disimpan ke database.'];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => 'Gagal menyimpan hasil klasterisasi: ' . $e->getMessage()];
    }
}

// Close connection when script ends
register_shutdown_function(function () use ($conn) {
    if (isset($conn)) {
        $conn->close();
    }
});
