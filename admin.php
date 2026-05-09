<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Check if user has admin or manager role
if (!in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman ini.';
    header('Location: login.php');
    exit;
}

// Only admins can manage users
$can_manage_users = ($_SESSION['user_role'] === 'admin');

// Handle add destination
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'add_destination') {
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
    } else if ($action == 'update_destination') {
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
    } else if ($action == 'delete_destination') {
        $id = intval($_POST['id']);
        $sql = "DELETE FROM destinations WHERE id=$id";

        if ($conn->query($sql) === TRUE) {
            $_SESSION['success'] = 'Destinasi berhasil dihapus!';
        } else {
            $_SESSION['error'] = 'Error: ' . $conn->error;
        }
    }

    // User management actions (admin only)
    if ($can_manage_users) {
        if ($action == 'add_user') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $nama_lengkap = $_POST['nama_lengkap'] ?? '';
            $email = $_POST['email'] ?? '';
            $role = $_POST['role'] ?? 'viewer';

            if (addUser($conn, $username, $password, $nama_lengkap, $email, $role)) {
                $_SESSION['success'] = 'User berhasil ditambahkan!';
            } else {
                $_SESSION['error'] = 'Error: ' . $conn->error;
            }
        } else if ($action == 'update_user') {
            $id = intval($_POST['user_id']);
            $username = $_POST['username'] ?? '';
            $nama_lengkap = $_POST['nama_lengkap'] ?? '';
            $email = $_POST['email'] ?? '';
            $role = $_POST['role'] ?? 'viewer';
            $is_active = intval($_POST['is_active'] ?? 1);

            if (updateUser($conn, $id, $username, $nama_lengkap, $email, $role, $is_active)) {
                $_SESSION['success'] = 'User berhasil diperbarui!';
            } else {
                $_SESSION['error'] = 'Error: ' . $conn->error;
            }
        } else if ($action == 'delete_user') {
            $id = intval($_POST['user_id']);

            if (deleteUser($conn, $id)) {
                $_SESSION['success'] = 'User berhasil dihapus!';
            } else {
                $_SESSION['error'] = 'Error: ' . $conn->error;
            }
        }
    }

    header('Location: admin.php?tab=' . ($_POST['tab'] ?? 'destinations'));
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get all destinations and users
$destinations = getAllDestinations($conn);
$users = $can_manage_users ? getAllUsers($conn) : [];
$current_tab = $_GET['tab'] ?? 'destinations';

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

        .header-info {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .user-info {
            text-align: right;
            font-size: 0.9rem;
        }

        .user-name {
            font-weight: 600;
        }

        .user-role {
            font-size: 0.8rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
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

        .btn-success {
            background: var(--k3);
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

        .btn-secondary {
            background: var(--surface2);
            color: var(--text);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--border);
        }

        .btn-small {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0;
            flex-wrap: wrap;
        }

        .tab-button {
            background: none;
            border: none;
            color: var(--muted);
            padding: 1rem 1.5rem;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            font-family: 'Sora', sans-serif;
        }

        .tab-button:hover {
            color: var(--text);
        }

        .tab-button.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
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

        .badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .badge-admin {
            background: rgba(245, 158, 11, 0.15);
            color: var(--k1);
        }

        .badge-manager {
            background: rgba(59, 130, 246, 0.15);
            color: var(--k2);
        }

        .badge-viewer {
            background: rgba(100, 116, 139, 0.15);
            color: #cbd5e1;
        }

        .badge-active {
            background: rgba(16, 185, 129, 0.15);
            color: var(--k3);
        }

        .badge-inactive {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Admin Panel</h1>
        <div class="header-info">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($_SESSION['user_role']); ?></div>
            </div>
            <div class="header-actions">
                <a href="import.php" class="btn btn-success">📥 Import Data</a>
                <a href="admin.php?logout" class="btn btn-danger">Logout</a>
            </div>
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

        <div class="tabs">
            <button class="tab-button <?php echo ($current_tab === 'destinations') ? 'active' : ''; ?>" onclick="switchTab('destinations')">📍 Destinasi Wisata</button>
            <?php if ($can_manage_users): ?>
                <button class="tab-button <?php echo ($current_tab === 'users') ? 'active' : ''; ?>" onclick="switchTab('users')">👥 Manajemen User</button>
            <?php endif; ?>
        </div>

        <!-- TAB: DESTINATIONS -->
        <div class="tab-content <?php echo ($current_tab === 'destinations') ? 'active' : ''; ?>">
            <h2 style="font-size: 1.3rem; margin-bottom: 1.5rem;">Daftar Destinasi Wisata</h2>
            <button class="btn btn-primary" onclick="openModal('destination')">+ Tambah Destinasi</button>

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

        <!-- TAB: USERS (Admin only) -->
        <?php if ($can_manage_users): ?>
            <div class="tab-content <?php echo ($current_tab === 'users') ? 'active' : ''; ?>">
                <h2 style="font-size: 1.3rem; margin-bottom: 1.5rem;">Manajemen User & Akses</h2>
                <button class="btn btn-primary" onclick="openModal('user')">+ Tambah User</button>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Terdaftar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['nama_lengkap']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><span class="badge badge-<?php echo $user['role']; ?>"><?php echo strtoupper($user['role']); ?></span></td>
                                    <td><span class="badge badge-<?php echo ($user['is_active'] ? 'active' : 'inactive'); ?>"><?php echo ($user['is_active'] ? 'Aktif' : 'Nonaktif'); ?></span></td>
                                    <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-secondary btn-small" onclick="editUser(<?php echo $user['id']; ?>)">Edit</button>
                                            <button class="btn btn-danger btn-small" onclick="deleteUser(<?php echo $user['id']; ?>)">Hapus</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- MODAL: DESTINATION -->
    <div id="destinationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="destModalTitle">Tambah Destinasi</h2>
                <button class="close" onclick="closeModal('destination')">&times;</button>
            </div>

            <form id="destinationForm" method="POST">
                <input type="hidden" id="destAction" name="action" value="add_destination">
                <input type="hidden" name="tab" value="destinations">
                <input type="hidden" id="destId" name="id">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Nama Destinasi *</label>
                        <input type="text" id="destNama" name="nama" required>
                    </div>
                    <div class="form-group">
                        <label>Longitude *</label>
                        <input type="number" id="destLon" name="lon" step="0.0001" required>
                    </div>
                    <div class="form-group">
                        <label>Latitude *</label>
                        <input type="number" id="destLat" name="lat" step="0.0001" required>
                    </div>
                    <div class="form-group">
                        <label>Kunjungan *</label>
                        <input type="number" id="destKunjungan" name="kunjungan" required>
                    </div>
                    <div class="form-group">
                        <label>Rating (0-5) *</label>
                        <input type="number" id="destRating" name="rating" min="0" max="5" step="0.1" required>
                    </div>
                    <div class="form-group">
                        <label>Aksesibilitas (1-5) *</label>
                        <input type="number" id="destAksesibilitas" name="aksesibilitas" min="1" max="5" required>
                    </div>
                    <div class="form-group">
                        <label>Fasilitas (1-5) *</label>
                        <input type="number" id="destFasilitas" name="fasilitas" min="1" max="5" required>
                    </div>
                    <div class="form-group">
                        <label>Potensi Alam (1-5) *</label>
                        <input type="number" id="destPotensiAlam" name="potensi_alam" min="1" max="5" required>
                    </div>
                    <div class="form-group">
                        <label>Potensi Budaya (1-5) *</label>
                        <input type="number" id="destPotensinBudaya" name="potensi_budaya" min="1" max="5" required>
                    </div>
                    <div class="form-group">
                        <label>Pendapatan *</label>
                        <input type="number" id="destPendapatan" name="pendapatan" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Trend *</label>
                        <input type="number" id="destTrend" name="trend" step="0.001" required>
                    </div>
                    <div class="form-group">
                        <label>Zona *</label>
                        <select id="destZona" name="zona" required>
                            <option value="">-- Pilih Zona --</option>
                            <option value="Peak Season">Peak Season</option>
                            <option value="Mid Season">Mid Season</option>
                            <option value="Low Season">Low Season</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Klaster *</label>
                        <select id="destKlaster" name="klaster" required>
                            <option value="">-- Pilih Klaster --</option>
                            <option value="1">Klaster 1: Tinggi</option>
                            <option value="2">Klaster 2: Sedang</option>
                            <option value="3">Klaster 3: Rendah</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Skor *</label>
                        <input type="number" id="destSkor" name="skor" min="0" max="1" step="0.0001" required>
                    </div>
                    <div class="form-group full">
                        <label>Rekomendasi *</label>
                        <textarea id="destRekomendasi" name="rekomendasi" required></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('destination')">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: USER -->
    <?php if ($can_manage_users): ?>
        <div id="userModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="userModalTitle">Tambah User</h2>
                    <button class="close" onclick="closeModal('user')">&times;</button>
                </div>

                <form id="userForm" method="POST">
                    <input type="hidden" id="userAction" name="action" value="add_user">
                    <input type="hidden" name="tab" value="users">
                    <input type="hidden" id="userId" name="user_id">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Username *</label>
                            <input type="text" id="userUsername" name="username" required>
                        </div>
                        <div class="form-group">
                            <label>Password *</label>
                            <input type="password" id="userPassword" name="password">
                        </div>
                        <div class="form-group full">
                            <label>Nama Lengkap *</label>
                            <input type="text" id="userNama" name="nama_lengkap" required>
                        </div>
                        <div class="form-group full">
                            <label>Email *</label>
                            <input type="email" id="userEmail" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Role *</label>
                            <select id="userRole" name="role" required>
                                <option value="admin">Admin</option>
                                <option value="manager">Manager</option>
                                <option value="viewer">Viewer</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status *</label>
                            <select id="userActive" name="is_active" required>
                                <option value="1">Aktif</option>
                                <option value="0">Nonaktif</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('user')">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- MODAL: DELETE CONFIRMATION -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h2>Konfirmasi Hapus</h2>
                <button class="close" onclick="closeModal('delete')">&times;</button>
            </div>
            <p>Apakah Anda yakin ingin menghapus item ini?</p>
            <form id="deleteForm" method="POST" style="margin-top: 2rem;">
                <input type="hidden" name="tab" id="deleteTab" value="destinations">
                <input type="hidden" name="action" id="deleteAction" value="delete_destination">
                <input type="hidden" id="deleteId" name="id">
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('delete')">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            window.location.href = '?tab=' + tab;
        }

        function openModal(type) {
            if (type === 'destination') {
                document.getElementById('destModalTitle').textContent = 'Tambah Destinasi';
                document.getElementById('destAction').value = 'add_destination';
                document.getElementById('destinationForm').reset();
                document.getElementById('destId').value = '';
                document.getElementById('destinationModal').classList.add('show');
            } else if (type === 'user') {
                document.getElementById('userModalTitle').textContent = 'Tambah User';
                document.getElementById('userAction').value = 'add_user';
                document.getElementById('userForm').reset();
                document.getElementById('userId').value = '';
                document.getElementById('userPassword').required = true;
                document.getElementById('userModal').classList.add('show');
            }
        }

        function closeModal(type) {
            if (type === 'destination') document.getElementById('destinationModal').classList.remove('show');
            else if (type === 'user') document.getElementById('userModal').classList.remove('show');
            else if (type === 'delete') document.getElementById('deleteModal').classList.remove('show');
        }

        async function editDestination(id) {
            const response = await fetch('get_destination.php?id=' + id);
            const data = await response.json();

            document.getElementById('destModalTitle').textContent = 'Edit Destinasi';
            document.getElementById('destAction').value = 'update_destination';
            document.getElementById('destId').value = data.id;
            document.getElementById('destNama').value = data.nama;
            document.getElementById('destLon').value = data.lon;
            document.getElementById('destLat').value = data.lat;
            document.getElementById('destKunjungan').value = data.kunjungan;
            document.getElementById('destRating').value = data.rating;
            document.getElementById('destAksesibilitas').value = data.aksesibilitas;
            document.getElementById('destFasilitas').value = data.fasilitas;
            document.getElementById('destPotensiAlam').value = data.potensi_alam;
            document.getElementById('destPotensinBudaya').value = data.potensi_budaya;
            document.getElementById('destPendapatan').value = data.pendapatan;
            document.getElementById('destTrend').value = data.trend;
            document.getElementById('destZona').value = data.zona;
            document.getElementById('destKlaster').value = data.klaster;
            document.getElementById('destSkor').value = data.skor;
            document.getElementById('destRekomendasi').value = data.rekomendasi;

            document.getElementById('destinationModal').classList.add('show');
        }

        function deleteDestination(id) {
            document.getElementById('deleteTab').value = 'destinations';
            document.getElementById('deleteAction').value = 'delete_destination';
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').classList.add('show');
        }

        function editUser(id) {
            document.getElementById('userModalTitle').textContent = 'Edit User';
            document.getElementById('userAction').value = 'update_user';
            document.getElementById('userId').value = id;
            document.getElementById('userPassword').required = false;
            document.getElementById('userModal').classList.add('show');
        }

        function deleteUser(id) {
            document.getElementById('deleteTab').value = 'users';
            document.getElementById('deleteAction').value = 'delete_user';
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteId').name = 'user_id';
            document.getElementById('deleteModal').classList.add('show');
        }

        window.onclick = function(event) {
            const destModal = document.getElementById('destinationModal');
            const userModal = document.getElementById('userModal');
            const deleteModal = document.getElementById('deleteModal');

            if (event.target === destModal) destModal.classList.remove('show');
            if (event.target === userModal) userModal.classList.remove('show');
            if (event.target === deleteModal) deleteModal.classList.remove('show');
        }
    </script>
</body>

</html>