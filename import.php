<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $allowed = ['csv', 'txt'];
    $filename = $file['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        $_SESSION['error'] = 'Format file tidak didukung. Gunakan CSV atau TXT.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'Error saat upload: ' . $file['error'];
    } else {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $tempFile = $uploadDir . uniqid() . '_' . $filename;

        if (move_uploaded_file($file['tmp_name'], $tempFile)) {
            // Import data
            $result = importDestinationsFromCSV($conn, $tempFile);

            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
            } else {
                $_SESSION['error'] = $result['message'];
            }

            // Delete temp file
            unlink($tempFile);
        } else {
            $_SESSION['error'] = 'Gagal menyimpan file upload';
        }
    }

    header('Location: import.php');
    exit;
}

// Handle export
if (isset($_GET['action']) && $_GET['action'] == 'export') {
    exportDestinationsToCSV($conn);
    exit;
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Dataset - MUQOROBIN</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0f1e;
            --surface: #111827;
            --surface2: #1a2235;
            --border: #1e2d45;
            --text: #e2e8f0;
            --muted: #64748b;
            --accent: #6366f1;
            --k1: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Sora', sans-serif;
        }

        .header {
            background: rgba(10, 15, 30, 0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header h1 {
            font-size: 1.5rem;
            background: linear-gradient(90deg, var(--k1), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-nav {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        a,
        .btn {
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary {
            background: var(--surface2);
            color: var(--text);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--border);
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: #4f46e5;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .card h2 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: var(--text);
        }

        .card p {
            color: var(--muted);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .upload-area {
            border: 2px dashed var(--border);
            border-radius: 10px;
            padding: 3rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: rgba(99, 102, 241, 0.05);
        }

        .upload-area:hover {
            border-color: var(--accent);
            background: rgba(99, 102, 241, 0.1);
        }

        .upload-area.dragover {
            border-color: var(--accent);
            background: rgba(99, 102, 241, 0.15);
        }

        .upload-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        #csvFile {
            display: none;
        }

        .file-info {
            margin-top: 1rem;
            padding: 1rem;
            background: var(--surface2);
            border-radius: 8px;
            color: var(--muted);
            font-size: 0.9rem;
            display: none;
        }

        .file-info.show {
            display: block;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .info-box {
            background: var(--surface2);
            border-left: 4px solid var(--accent);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--muted);
            line-height: 1.8;
        }

        .info-box strong {
            color: var(--text);
        }

        .template-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
        }

        .template-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.85rem;
        }

        .template-table th {
            background: var(--surface2);
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
        }

        .template-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
            color: var(--muted);
        }

        .template-table td:first-child {
            color: var(--text);
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>📥 Import Dataset Destinasi</h1>
        <div class="header-nav">
            <a href="admin.php" class="btn btn-secondary">← Kembali ke Admin</a>
            <a href="import.php?action=export" class="btn btn-success">⬇ Export CSV</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success'];
                                                unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error'];
                                            unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>📤 Upload File CSV</h2>
            <p>Pilih file CSV untuk mengimpor data destinasi wisata ke database. File harus memiliki format yang sesuai dengan template di bawah.</p>

            <form method="POST" enctype="multipart/form-data">
                <div class="upload-area" id="uploadArea">
                    <div class="upload-icon">📄</div>
                    <p style="font-size: 1rem; margin-bottom: 0.5rem;"><strong>Klik untuk memilih atau drag file di sini</strong></p>
                    <p style="font-size: 0.85rem; color: var(--muted);">Format: CSV atau TXT</p>
                    <input type="file" id="csvFile" name="csv_file" accept=".csv,.txt" required>
                </div>

                <div class="file-info" id="fileInfo">
                    <strong>File terpilih:</strong> <span id="fileName"></span>
                    <br><strong>Ukuran:</strong> <span id="fileSize"></span>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-primary">Impor Data</button>
                    <button type="button" class="btn btn-secondary" onclick="resetFile()">Batal</button>
                </div>
            </form>
        </div>

        <div class="card template-section">
            <h2>📋 Format File CSV</h2>

            <div class="info-box">
                <strong>⚠️ Penting:</strong> File CSV harus memiliki 15 kolom dengan urutan yang benar. Header baris pertama tidak akan diimport. Pastikan format data sesuai dengan tipe field (angka, teks, dll).
            </div>

            <p><strong>Contoh Header CSV:</strong></p>
            <pre style="background: var(--surface2); padding: 1rem; border-radius: 8px; overflow-x: auto; color: var(--accent); font-size: 0.85rem;">Nama,Longitude,Latitude,Kunjungan,Rating,Aksesibilitas,Fasilitas,Potensi Alam,Potensi Budaya,Pendapatan,Trend,Zona,Klaster,Skor,Rekomendasi</pre>

            <table class="template-table">
                <thead>
                    <tr>
                        <th>Kolom</th>
                        <th>Tipe Data</th>
                        <th>Deskripsi</th>
                        <th>Contoh</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Nama</td>
                        <td>Text</td>
                        <td>Nama destinasi wisata</td>
                        <td>Candi Borobudur</td>
                    </tr>
                    <tr>
                        <td>Longitude</td>
                        <td>Decimal</td>
                        <td>Koordinat bujur GPS</td>
                        <td>110.2037</td>
                    </tr>
                    <tr>
                        <td>Latitude</td>
                        <td>Decimal</td>
                        <td>Koordinat lintang GPS</td>
                        <td>-7.6079</td>
                    </tr>
                    <tr>
                        <td>Kunjungan</td>
                        <td>Number</td>
                        <td>Jumlah pengunjung per tahun</td>
                        <td>1250000</td>
                    </tr>
                    <tr>
                        <td>Rating</td>
                        <td>Decimal (0-5)</td>
                        <td>Rating dari review pengunjung</td>
                        <td>4.9</td>
                    </tr>
                    <tr>
                        <td>Aksesibilitas</td>
                        <td>Number (1-5)</td>
                        <td>Tingkat kemudahan akses (1=sulit, 5=mudah)</td>
                        <td>5</td>
                    </tr>
                    <tr>
                        <td>Fasilitas</td>
                        <td>Number (1-5)</td>
                        <td>Kualitas fasilitas (1=buruk, 5=excellent)</td>
                        <td>5</td>
                    </tr>
                    <tr>
                        <td>Potensi Alam</td>
                        <td>Number (1-5)</td>
                        <td>Potensi keindahan alam</td>
                        <td>4</td>
                    </tr>
                    <tr>
                        <td>Potensi Budaya</td>
                        <td>Number (1-5)</td>
                        <td>Potensi nilai budaya & historis</td>
                        <td>5</td>
                    </tr>
                    <tr>
                        <td>Pendapatan</td>
                        <td>Decimal</td>
                        <td>Estimasi pendapatan (dalam ribuan)</td>
                        <td>18500</td>
                    </tr>
                    <tr>
                        <td>Trend</td>
                        <td>Decimal</td>
                        <td>Growth rate / trend pertumbuhan</td>
                        <td>0.123</td>
                    </tr>
                    <tr>
                        <td>Zona</td>
                        <td>Text</td>
                        <td>Peak Season | Mid Season | Low Season</td>
                        <td>Peak Season</td>
                    </tr>
                    <tr>
                        <td>Klaster</td>
                        <td>Number (1-3)</td>
                        <td>Klaster K-Means hasil analisis</td>
                        <td>1</td>
                    </tr>
                    <tr>
                        <td>Skor</td>
                        <td>Decimal (0-1)</td>
                        <td>Skor komposit hasil K-Means</td>
                        <td>0.9438</td>
                    </tr>
                    <tr>
                        <td>Rekomendasi</td>
                        <td>Text</td>
                        <td>Strategi pengembangan wisata</td>
                        <td>Prioritas pengembangan infrastruktur & promosi</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>📝 Contoh Baris Data CSV</h2>
            <pre style="background: var(--surface2); padding: 1rem; border-radius: 8px; overflow-x: auto; color: var(--muted); font-size: 0.8rem; line-height: 1.6;">Candi Borobudur,110.2037,-7.6079,1250000,4.9,5,5,4,5,18500,0.123,Peak Season,1,0.9438,Prioritas pengembangan infrastruktur & promosi
Punthuk Setumbu,110.1952,-7.6025,185000,4.7,3,3,5,4,2800,0.185,Peak Season,3,0.726,Eksplorasi & pengembangan awal
Ketep Pass,110.2564,-7.5847,210000,4.5,3,4,5,3,3100,0.157,Peak Season,2,0.7082,Tingkatkan fasilitas & aksesibilitas</pre>
        </div>
    </div>

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const csvFile = document.getElementById('csvFile');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');

        // Click to upload
        uploadArea.addEventListener('click', () => csvFile.click());

        // File selected
        csvFile.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                updateFileInfo(file);
            }
        });

        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                csvFile.files = files;
                updateFileInfo(files[0]);
            }
        });

        function updateFileInfo(file) {
            fileName.textContent = file.name;
            fileSize.textContent = (file.size / 1024).toFixed(2) + ' KB';
            fileInfo.classList.add('show');
        }

        function resetFile() {
            csvFile.value = '';
            fileInfo.classList.remove('show');
        }
    </script>
</body>

</html>