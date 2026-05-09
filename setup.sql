-- ============================================================
-- DATABASE SETUP: MUQOROBIN WISATA KABUPATEN MAGELANG
-- ============================================================

-- Buat database jika belum ada
CREATE DATABASE IF NOT EXISTS muqorobin_wisata;
USE muqorobin_wisata;

-- Tabel Destinasi
CREATE TABLE IF NOT EXISTS destinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(255) NOT NULL,
    lon DECIMAL(10, 4) NOT NULL,
    lat DECIMAL(10, 4) NOT NULL,
    kunjungan INT NOT NULL,
    rating DECIMAL(3, 1) NOT NULL,
    aksesibilitas INT NOT NULL,
    fasilitas INT NOT NULL,
    potensi_alam INT NOT NULL,
    potensi_budaya INT NOT NULL,
    pendapatan DECIMAL(12, 2) NOT NULL,
    trend DECIMAL(5, 3) NOT NULL,
    zona VARCHAR(50) NOT NULL,
    klaster INT NOT NULL,
    skor DECIMAL(6, 4) NOT NULL,
    rekomendasi TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Users (Admin & Managers)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'manager', 'viewer') DEFAULT 'viewer',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Cluster Info
CREATE TABLE IF NOT EXISTS cluster_info (
    klaster INT PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    color VARCHAR(10) NOT NULL,
    bg VARCHAR(10) NOT NULL,
    n INT NOT NULL,
    avg_kunjungan INT NOT NULL,
    avg_rating DECIMAL(3, 2) NOT NULL,
    total_pend DECIMAL(12, 2) NOT NULL,
    strategi TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Evaluasi
CREATE TABLE IF NOT EXISTS evaluasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sc DECIMAL(6, 4) NOT NULL,
    dbi DECIMAL(6, 4) NOT NULL,
    chi DECIMAL(6, 3) NOT NULL,
    wcss DECIMAL(6, 4) NOT NULL,
    iter INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Proyeksi
CREATE TABLE IF NOT EXISTS proyeksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destination_id INT NOT NULL,
    nama VARCHAR(255) NOT NULL,
    k INT NOT NULL,
    y2024 INT NOT NULL,
    y2025 INT NOT NULL,
    y2026 INT NOT NULL,
    y2027 INT NOT NULL,
    cagr DECIMAL(5, 3) NOT NULL,
    kat VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INSERT DATA AWAL
-- ============================================================

-- Insert Cluster Info
INSERT INTO cluster_info (klaster, label, color, bg, n, avg_kunjungan, avg_rating, total_pend, strategi) VALUES
(1, 'Klaster 1: Tinggi', '#f59e0b', '#fef3c7', 1, 1250000, 4.90, 18500, 'Pertahankan kualitas, ekspansi kapasitas, tingkatkan promosi internasional'),
(2, 'Klaster 2: Sedang', '#3b82f6', '#dbeafe', 8, 68125, 4.21, 5735, 'Investasi infrastruktur, sertifikasi standar wisata, paket wisata kombinasi'),
(3, 'Klaster 3: Rendah', '#10b981', '#d1fae5', 6, 133667, 4.25, 9450, 'Pengembangan aksesibilitas, promosi digital, eco-tourism');

-- Insert Evaluasi (initial values)
INSERT INTO evaluasi (sc, dbi, chi, wcss, iter) VALUES
(0.6294, 0.1573, 7.052, 6.9596, 2);

-- Insert Destinations
INSERT INTO destinations (nama, lon, lat, kunjungan, rating, aksesibilitas, fasilitas, potensi_alam, potensi_budaya, pendapatan, trend, zona, klaster, skor, rekomendasi) VALUES
('Candi Borobudur', 110.2037, -7.6079, 1250000, 4.9, 5, 5, 4, 5, 18500, 0.123, 'Peak Season', 1, 0.9438, 'Prioritas pengembangan infrastruktur & promosi'),
('Punthuk Setumbu', 110.1952, -7.6025, 185000, 4.7, 3, 3, 5, 4, 2800, 0.185, 'Peak Season', 3, 0.726, 'Eksplorasi & pengembangan awal'),
('Candi Pawon', 110.2105, -7.6112, 95000, 4.2, 4, 3, 3, 5, 850, 0.082, 'Mid Season', 3, 0.6664, 'Eksplorasi & pengembangan awal'),
('Candi Mendut', 110.2278, -7.5996, 125000, 4.3, 4, 4, 3, 5, 1200, 0.095, 'Mid Season', 3, 0.694, 'Eksplorasi & pengembangan awal'),
('Ketep Pass', 110.2564, -7.5847, 210000, 4.5, 3, 4, 5, 3, 3100, 0.157, 'Peak Season', 2, 0.7082, 'Tingkatkan fasilitas & aksesibilitas'),
('Kopeng', 110.4012, -7.3985, 98000, 3.8, 3, 3, 4, 2, 780, 0.053, 'Low Season', 2, 0.5659, 'Tingkatkan fasilitas & aksesibilitas'),
('Air Terjun Kedung Kayang', 110.2689, -7.5712, 42000, 4.4, 2, 2, 5, 2, 320, 0.221, 'Low Season', 2, 0.5824, 'Tingkatkan fasilitas & aksesibilitas'),
('Telaga Bleder', 110.3215, -7.4823, 35000, 3.9, 2, 2, 4, 2, 280, 0.118, 'Low Season', 2, 0.5171, 'Tingkatkan fasilitas & aksesibilitas'),
('Bukit Rhema (Gereja Ayam)', 110.2021, -7.6038, 165000, 4.6, 3, 3, 4, 4, 2100, 0.352, 'Peak Season', 3, 0.6758, 'Eksplorasi & pengembangan awal'),
('Sawah Sukomakmur', 110.2334, -7.5698, 28000, 4.1, 2, 2, 5, 3, 210, 0.284, 'Low Season', 2, 0.5966, 'Tingkatkan fasilitas & aksesibilitas'),
('Museum Karmawibhangga', 110.2039, -7.6082, 87000, 4.0, 5, 4, 2, 5, 650, 0.061, 'Mid Season', 3, 0.6649, 'Eksplorasi & pengembangan awal'),
('Taman Kyai Langgeng', 110.2176, -7.4721, 145000, 3.7, 4, 4, 3, 3, 1850, 0.042, 'Mid Season', 3, 0.6089, 'Eksplorasi & pengembangan awal'),
('Gunung Andong', 110.4125, -7.4356, 55000, 4.5, 2, 2, 5, 2, 420, 0.316, 'Low Season', 2, 0.5882, 'Tingkatkan fasilitas & aksesibilitas'),
('Umbul Songo', 110.3876, -7.3654, 32000, 4.2, 2, 2, 5, 2, 245, 0.198, 'Low Season', 2, 0.5718, 'Tingkatkan fasilitas & aksesibilitas'),
('Puthuk Mongkrong', 110.1998, -7.5987, 45000, 4.3, 2, 2, 5, 4, 380, 0.412, 'Low Season', 2, 0.6379, 'Tingkatkan fasilitas & aksesibilitas');

-- Insert Proyeksi
INSERT INTO proyeksi (destination_id, nama, k, y2024, y2025, y2026, y2027, cagr, kat) VALUES
(1, 'Candi Borobudur', 1, 1250000, 1403750, 1576411, 1770310, 0.123, 'Moderat →'),
(2, 'Punthuk Setumbu', 3, 185000, 219225, 259782, 307842, 0.185, 'Cepat ⬆'),
(3, 'Candi Pawon', 3, 95000, 102790, 111219, 120339, 0.082, 'Moderat →'),
(4, 'Candi Mendut', 3, 125000, 136875, 149878, 164116, 0.095, 'Moderat →'),
(5, 'Ketep Pass', 2, 210000, 242970, 281116, 325251, 0.157, 'Cepat ⬆'),
(6, 'Kopeng', 2, 98000, 103194, 108663, 114422, 0.053, 'Moderat →'),
(7, 'Air Terjun Kedung Kayang', 2, 42000, 51282, 62615, 76453, 0.221, 'Cepat ⬆'),
(8, 'Telaga Bleder', 2, 35000, 39130, 43747, 48909, 0.118, 'Moderat →'),
(9, 'Bukit Rhema (Gereja Ayam)', 3, 165000, 223080, 301604, 407769, 0.352, 'Sangat Cepat 🚀'),
(10, 'Sawah Sukomakmur', 2, 28000, 35952, 46162, 59272, 0.284, 'Cepat ⬆'),
(11, 'Museum Karmawibhangga', 3, 87000, 92307, 97938, 103912, 0.061, 'Moderat →'),
(12, 'Taman Kyai Langgeng', 3, 145000, 151090, 157436, 164048, 0.042, 'Lambat ⬇'),
(13, 'Gunung Andong', 2, 55000, 72380, 95252, 125352, 0.316, 'Sangat Cepat 🚀'),
(14, 'Umbul Songo', 2, 32000, 38336, 45927, 55021, 0.198, 'Cepat ⬆'),
(15, 'Puthuk Mongkrong', 2, 45000, 63540, 89718, 126682, 0.412, 'Sangat Cepat 🚀');

-- ============================================================
-- INSERT USERS DEFAULT
-- ============================================================

-- Insert default users (password hashed dengan MD5 - TODO: gunakan bcrypt di production)
-- Password: admin123 (MD5 hash)
INSERT INTO users (username, password, nama_lengkap, email, role, is_active) VALUES
('admin', MD5('admin123'), 'Administrator MUQOROBIN', 'admin@muqorobin.local', 'admin', TRUE),
('manager', MD5('manager123'), 'Manager Wisata', 'manager@muqorobin.local', 'manager', TRUE),
('viewer', MD5('viewer123'), 'Viewer Data', 'viewer@muqorobin.local', 'viewer', TRUE);
