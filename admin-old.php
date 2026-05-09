<?php
session_start();
require_once 'config.php';

// Set admin password (change this to a more secure method)
$admin_password = 'admin123'; // TODO: Ganti dengan password yang lebih aman atau gunakan hashing

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
        if ($_POST['password'] === $admin_password) {
            $_SESSION['admin_logged_in'] = true;
        } else {
            $error = 'Password salah!';
        }
    }

    // Show login form
?>
    <!DOCTYPE html>
    <html lang="id">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&display=swap" rel="stylesheet">
        <style>
            :root {
                --bg: #0a0f1e;
                --surface: #111827;
                --border: #1e2d45;
                --text: #e2e8f0;
                --accent: #6366f1;
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
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }

            .login-container {
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: 16px;
                padding: 2.5rem;
                width: 100%;
                max-width: 400px;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
            }

            h1 {
                font-size: 1.8rem;
                margin-bottom: 0.5rem;
                background: linear-gradient(90deg, #f59e0b, #6366f1);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }

            .subtitle {
                color: #94a3b8;
                margin-bottom: 2rem;
                font-size: 0.9rem;
            }

            .form-group {
                margin-bottom: 1.5rem;
            }

            label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 600;
                font-size: 0.9rem;
            }

            input {
                width: 100%;
                padding: 0.75rem;
                background: #1a2235;
                border: 1px solid var(--border);
                border-radius: 8px;
                color: var(--text);
                font-family: 'Sora', sans-serif;
                font-size: 1rem;
                transition: all 0.2s;
            }

            input:focus {
                outline: none;
                border-color: var(--accent);
                background: #1f2a3a;
            }

            button {
                width: 100%;
                padding: 0.75rem;
                background: var(--accent);
                color: white;
                border: none;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
                font-size: 1rem;
            }

            button:hover {
                background: #4f46e5;
            }

            .error {
                background: #fee2e2;
                color: #991b1b;
                padding: 1rem;
                border-radius: 8px;
                margin-bottom: 1.5rem;
                font-size: 0.9rem;
            }
        </style>
    </head>

    <body>
        <div class="login-container">
            <h1></h1>
            <p class="subtitle">Admin Panel</p>

            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autofocus>
                </div>
                <button type="submit" name="login">Masuk</button>
            </form>
        </div>
    </body>

    </html>
<?php
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Handle add destination
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'add') {
        $nama = $conn->real_escape_string($_POST['nama']);
        $lon = floatval($_POST['lon']);
        $lat = floatval($_POST['lat']);
        $kunjungan = intval($_POST['kunjungan']);
        $rating = floatval($_POST['rating']);
        $aksesibilitas = intval($_POST['aksesibilitas']);
        $fasilitas = intval($_POST['fasilitas']);
        $potensi_alam = intval($_POST['potensi_alam']);
        $potensi_budaya = intval($_POST['potensi_budaya']);
        $pendapatan = floatval($_POST['pendapatan']);
        $trend = floatval($_POST['trend']);
        $zona = $conn->real_escape_string($_POST['zona']);
        $klaster = intval($_POST['klaster']);
        $skor = floatval($_POST['skor']);
        $rekomendasi = $conn->real_escape_string($_POST['rekomendasi']);

        $sql = "INSERT INTO destinations (nama, lon, lat, kunjungan, rating, aksesibilitas, fasilitas, potensi_alam, potensi_budaya, pendapatan, trend, zona, klaster, skor, rekomendasi) 
                VALUES ('$nama', $lon, $lat, $kunjungan, $rating, $aksesibilitas, $fasilitas, $potensi_alam, $potensi_budaya, $pendapatan, $trend, '$zona', $klaster, $skor, '$rekomendasi')";

        if ($conn->query($sql) === TRUE) {
            $_SESSION['success'] = 'Destinasi berhasil ditambahkan!';
        } else {
            $_SESSION['error'] = 'Error: ' . $conn->error;
        }
    } else if ($action == 'update') {
        $id = intval($_POST['id']);
        $nama = $conn->real_escape_string($_POST['nama']);
        $lon = floatval($_POST['lon']);
        $lat = floatval($_POST['lat']);
        $kunjungan = intval($_POST['kunjungan']);
        $rating = floatval($_POST['rating']);
        $aksesibilitas = intval($_POST['aksesibilitas']);
        $fasilitas = intval($_POST['fasilitas']);
        $potensi_alam = intval($_POST['potensi_alam']);
        $potensi_budaya = intval($_POST['potensi_budaya']);
        $pendapatan = floatval($_POST['pendapatan']);
        $trend = floatval($_POST['trend']);
        $zona = $conn->real_escape_string($_POST['zona']);
        $klaster = intval($_POST['klaster']);
        $skor = floatval($_POST['skor']);
        $rekomendasi = $conn->real_escape_string($_POST['rekomendasi']);

        $sql = "UPDATE destinations SET 
                nama='$nama', lon=$lon, lat=$lat, kunjungan=$kunjungan, rating=$rating, 
                aksesibilitas=$aksesibilitas, fasilitas=$fasilitas, potensi_alam=$potensi_alam, 
                potensi_budaya=$potensi_budaya, pendapatan=$pendapatan, trend=$trend, 
                zona='$zona', klaster=$klaster, skor=$skor, rekomendasi='$rekomendasi' 
                WHERE id=$id";

        if ($conn->query($sql) === TRUE) {
            $_SESSION['success'] = 'Destinasi berhasil diperbarui!';
        } else {
            $_SESSION['error'] = 'Error: ' . $conn->error;
        }
    } else if ($action == 'delete') {
        $id = intval($_POST['id']);
        $sql = "DELETE FROM destinations WHERE id=$id";

        if ($conn->query($sql) === TRUE) {
            $_SESSION['success'] = 'Destinasi berhasil dihapus!';
        } else {
            $_SESSION['error'] = 'Error: ' . $conn->error;
        }
    }

    header('Location: admin.php');
    exit;
}

// Get all destinations
$destinations = getAllDestinations($conn);
$clusters = getClusterInfo($conn);

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
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
            --k2: #3b82f6;
            --k3: #10b981;
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

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

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

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: #4f46e5;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-secondary {
            background: var(--surface2);
            color: var(--text);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--border);
        }

        .container {
            max-width: 1200px;
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
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .table-container {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: var(--surface2);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            border-bottom: 1px solid var(--border);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover {
            background: rgba(99, 102, 241, 0.05);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-small {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .modal-header h2 {
            font-size: 1.3rem;
        }

        .close {
            background: none;
            border: none;
            color: var(--text);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            grid-column: span 1;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 0.75rem;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-family: 'Sora', sans-serif;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: var(--accent);
            background: #1f2a3a;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .modal-footer {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            margin-top: 2rem;
        }

        .section-title:first-of-type {
            margin-top: 0;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Admin Panel</h1>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="openModal()">+ Tambah Destinasi</button>
            <a href="admin.php?logout" class="btn btn-danger">Logout</a>
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

        <h2 class="section-title">Daftar Destinasi Wisata</h2>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Kunjungan</th>
                        <th>Rating</th>
                        <th>Klaster</th>
                        <th>Zona</th>
                        <th>Skor</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($destinations as $dest): ?>
                        <tr>
                            <td><?php echo $dest['id']; ?></td>
                            <td><?php echo htmlspecialchars($dest['nama']); ?></td>
                            <td><?php echo number_format($dest['kunjungan'], 0, ',', '.'); ?></td>
                            <td><?php echo number_format($dest['rating'], 1); ?></td>
                            <td><?php echo $dest['klaster']; ?></td>
                            <td><?php echo htmlspecialchars($dest['zona']); ?></td>
                            <td><?php echo number_format($dest['skor'], 4); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-secondary btn-small" onclick="editDestination(<?php echo $dest['id']; ?>)">Edit</button>
                                    <button class="btn btn-danger btn-small" onclick="deleteDestination(<?php echo $dest['id']; ?>)">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal untuk Tambah/Edit -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Tambah Destinasi</h2>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>

            <form id="destinationForm" method="POST">
                <input type="hidden" id="formAction" name="action" value="add">
                <input type="hidden" id="destId" name="id">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="nama">Nama Destinasi *</label>
                        <input type="text" id="nama" name="nama" required>
                    </div>

                    <div class="form-group">
                        <label for="lon">Longitude *</label>
                        <input type="number" id="lon" name="lon" step="0.0001" required>
                    </div>

                    <div class="form-group">
                        <label for="lat">Latitude *</label>
                        <input type="number" id="lat" name="lat" step="0.0001" required>
                    </div>

                    <div class="form-group">
                        <label for="kunjungan">Kunjungan *</label>
                        <input type="number" id="kunjungan" name="kunjungan" required>
                    </div>

                    <div class="form-group">
                        <label for="rating">Rating (0-5) *</label>
                        <input type="number" id="rating" name="rating" min="0" max="5" step="0.1" required>
                    </div>

                    <div class="form-group">
                        <label for="aksesibilitas">Aksesibilitas (1-5) *</label>
                        <input type="number" id="aksesibilitas" name="aksesibilitas" min="1" max="5" required>
                    </div>

                    <div class="form-group">
                        <label for="fasilitas">Fasilitas (1-5) *</label>
                        <input type="number" id="fasilitas" name="fasilitas" min="1" max="5" required>
                    </div>

                    <div class="form-group">
                        <label for="potensi_alam">Potensi Alam (1-5) *</label>
                        <input type="number" id="potensi_alam" name="potensi_alam" min="1" max="5" required>
                    </div>

                    <div class="form-group">
                        <label for="potensi_budaya">Potensi Budaya (1-5) *</label>
                        <input type="number" id="potensi_budaya" name="potensi_budaya" min="1" max="5" required>
                    </div>

                    <div class="form-group">
                        <label for="pendapatan">Pendapatan *</label>
                        <input type="number" id="pendapatan" name="pendapatan" step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label for="trend">Trend *</label>
                        <input type="number" id="trend" name="trend" step="0.001" required>
                    </div>

                    <div class="form-group">
                        <label for="zona">Zona *</label>
                        <select id="zona" name="zona" required>
                            <option value="">-- Pilih Zona --</option>
                            <option value="Peak Season">Peak Season</option>
                            <option value="Mid Season">Mid Season</option>
                            <option value="Low Season">Low Season</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="klaster">Klaster *</label>
                        <select id="klaster" name="klaster" required>
                            <option value="">-- Pilih Klaster --</option>
                            <option value="1">Klaster 1: Tinggi</option>
                            <option value="2">Klaster 2: Sedang</option>
                            <option value="3">Klaster 3: Rendah</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="skor">Skor *</label>
                        <input type="number" id="skor" name="skor" min="0" max="1" step="0.0001" required>
                    </div>

                    <div class="form-group full">
                        <label for="rekomendasi">Rekomendasi *</label>
                        <textarea id="rekomendasi" name="rekomendasi" required></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal untuk Konfirmasi Hapus -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h2>Konfirmasi Hapus</h2>
                <button class="close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <p>Apakah Anda yakin ingin menghapus destinasi ini?</p>
            <form id="deleteForm" method="POST" style="margin-top: 2rem;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" id="deleteId" name="id">
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('modalTitle').textContent = 'Tambah Destinasi';
            document.getElementById('formAction').value = 'add';
            document.getElementById('destinationForm').reset();
            document.getElementById('destId').value = '';
            document.getElementById('modal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('modal').classList.remove('show');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
        }

        async function editDestination(id) {
            // Fetch destination data via AJAX
            const response = await fetch('get_destination.php?id=' + id);
            const data = await response.json();

            document.getElementById('modalTitle').textContent = 'Edit Destinasi';
            document.getElementById('formAction').value = 'update';
            document.getElementById('destId').value = data.id;
            document.getElementById('nama').value = data.nama;
            document.getElementById('lon').value = data.lon;
            document.getElementById('lat').value = data.lat;
            document.getElementById('kunjungan').value = data.kunjungan;
            document.getElementById('rating').value = data.rating;
            document.getElementById('aksesibilitas').value = data.aksesibilitas;
            document.getElementById('fasilitas').value = data.fasilitas;
            document.getElementById('potensi_alam').value = data.potensi_alam;
            document.getElementById('potensi_budaya').value = data.potensi_budaya;
            document.getElementById('pendapatan').value = data.pendapatan;
            document.getElementById('trend').value = data.trend;
            document.getElementById('zona').value = data.zona;
            document.getElementById('klaster').value = data.klaster;
            document.getElementById('skor').value = data.skor;
            document.getElementById('rekomendasi').value = data.rekomendasi;

            document.getElementById('modal').classList.add('show');
        }

        function deleteDestination(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').classList.add('show');
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('modal');
            const deleteModal = document.getElementById('deleteModal');

            if (event.target === modal) {
                modal.classList.remove('show');
            }
            if (event.target === deleteModal) {
                deleteModal.classList.remove('show');
            }
        }
    </script>
</body>

</html>