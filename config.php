<?php
// ============================================================
// KONFIGURASI DATABASE
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'muqorobin_wisata');

// Create connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

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
    $sql = "SELECT * FROM evaluasi LIMIT 1";
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

// Function to import CSV data
function importDestinationsFromCSV($conn, $filePath)
{
    if (!file_exists($filePath)) {
        return ['success' => false, 'message' => 'File tidak ditemukan'];
    }

    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return ['success' => false, 'message' => 'Tidak bisa membuka file'];
    }

    $importedCount = 0;
    $errorCount = 0;
    $header = fgetcsv($handle);

    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) < 15) {
            $errorCount++;
            continue;
        }

        $nama = $conn->real_escape_string(trim($row[0]));
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
        $klaster = intval(trim($row[12]));
        $skor = floatval(trim($row[13]));
        $rekomendasi = $conn->real_escape_string(trim($row[14]));

        $sql = "INSERT INTO destinations (nama, lon, lat, kunjungan, rating, aksesibilitas, fasilitas, 
                potensi_alam, potensi_budaya, pendapatan, trend, zona, klaster, skor, rekomendasi) 
                VALUES ('$nama', $lon, $lat, $kunjungan, $rating, $aksesibilitas, $fasilitas, 
                $potensi_alam, $potensi_budaya, $pendapatan, $trend, '$zona', $klaster, $skor, '$rekomendasi')";

        if ($conn->query($sql) === TRUE) {
            $importedCount++;
        } else {
            $errorCount++;
        }
    }

    fclose($handle);

    return [
        'success' => true,
        'imported' => $importedCount,
        'failed' => $errorCount,
        'message' => "Import selesai: $importedCount data berhasil, $errorCount data gagal"
    ];
}

// Function to export destinations to CSV
function exportDestinationsToCSV($conn)
{
    $destinations = getAllDestinations($conn);

    $filename = "destinasi_wisata_" . date('Y-m-d_H-i-s') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

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


// Close connection when script ends
register_shutdown_function(function () use ($conn) {
    if (isset($conn)) {
        $conn->close();
    }
});
