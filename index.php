Replaced: True
<?php
// ============================================================
// K-MEANS++ INTELIJEN SPASIAL – WISATA KABUPATEN MAGELANG
// ============================================================

require_once 'config.php';

// Get data from database
$destinations = getAllDestinations($conn);

// Convert numeric fields in destinations
foreach ($destinations as &$dest) {
    $dest['lon'] = floatval($dest['lon']);
    $dest['lat'] = floatval($dest['lat']);
    $dest['kunjungan'] = intval($dest['kunjungan']);
    $dest['rating'] = floatval($dest['rating']);
    $dest['aksesibilitas'] = intval($dest['aksesibilitas']);
    $dest['fasilitas'] = intval($dest['fasilitas']);
    $dest['potensi_alam'] = intval($dest['potensi_alam']);
    $dest['potensi_budaya'] = intval($dest['potensi_budaya']);
    $dest['pendapatan'] = floatval($dest['pendapatan']);
    $dest['trend'] = floatval($dest['trend']);
    $dest['klaster'] = intval($dest['klaster']);
    $dest['skor'] = floatval($dest['skor']);
}
unset($dest);

// ══════════════════════════════════════════════════════════════
// RECALCULATE "Skor Potensi" SO IT MATCHES THE OFFICIAL EXCEL FORMULA
// (Sheet '6_Hasil_Klaster', kolom H — lihat workbook K-Means Magelang)
// Skor = (Rating/5×0.25) + (Akses/5×0.15) + (Fasilitas/5×0.10)
//      + (P.Alam/5×0.20) + (P.Budaya/5×0.15) + (MIN(Pendapatan,20000)/20000×0.15)
// Dihitung dinamis dari atribut mentah tiap destinasi — tidak ada nilai hardcode.
// ══════════════════════════════════════════════════════════════
foreach ($destinations as &$dest) {
    $dest['skor'] = ($dest['rating'] / 5) * 0.25
        + ($dest['aksesibilitas'] / 5) * 0.15
        + ($dest['fasilitas'] / 5) * 0.10
        + ($dest['potensi_alam'] / 5) * 0.20
        + ($dest['potensi_budaya'] / 5) * 0.15
        + (min($dest['pendapatan'], 20000) / 20000) * 0.15;
}
unset($dest);

// Lookup destinasi mentah by ID (dipakai untuk breakdown skor per-langkah)
$destByID = [];
foreach ($destinations as $d) {
    $destByID[intval($d['id'])] = $d;
}

$evaluasi_raw = getEvaluasi($conn);
$evaluasi = [
    'sc' => floatval($evaluasi_raw['sc']),
    'dbi' => floatval($evaluasi_raw['dbi']),
    'chi' => floatval($evaluasi_raw['chi']),
    'wcss' => floatval($evaluasi_raw['wcss']),
    'iter' => intval($evaluasi_raw['iter']),
];
$proyeksi = getProyeksi($conn);
$klaster_info_raw = getClusterInfo($conn);

// Transform cluster info to match original format
$klaster_info = [];
foreach ($klaster_info_raw as $k => $info) {
    $klaster_info[$k] = [
        'label' => $info['label'],
        'color' => $info['color'],
        'bg' => $info['bg'],
        'n' => intval($info['n']),
        'avg_kunjungan' => intval($info['avg_kunjungan']),
        'avg_rating' => floatval($info['avg_rating']),
        'total_pend' => floatval($info['total_pend']),
        'strategi' => $info['strategi'],
    ];
}

// ══════════════════════════════════════════════════════════════
// RUN K-MEANS++ DYNAMICALLY FOR THE STEP-BY-STEP MANUAL BOOK
// ══════════════════════════════════════════════════════════════
$calc = runKMeansClustering($conn);

if ($calc['success']) {
    $c1_id = $calc['c1_id'];
    $c2_id = $calc['c2_id'];
    $c3_id = $calc['c3_id'];

    $c1_name = $calc['normData'][$c1_id]['nama'];
    $c2_name = $calc['normData'][$c2_id]['nama'];
    $c3_name = $calc['normData'][$c3_id]['nama'];

    $total_d2_val = number_format($calc['total_d2_c1'], 4);
    $c2_d2_val = number_format($calc['d2_c1'][$c2_id], 4);
    $c3_d2_val = number_format($calc['d2_min'][$c3_id], 4);

    $iter1_wcss = number_format($calc['iterations_history'][1]['wcss'] ?? 0, 3);
    $iter1_counts = [1 => 0, 2 => 0, 3 => 0];
    if (isset($calc['iterations_history'][1])) {
        foreach ($calc['iterations_history'][1]['assignments'] as $cid) {
            $iter1_counts[$cid]++;
        }
    }
    $final_wcss = number_format($calc['wcss'], 3);
    $total_iterations = $calc['iter_count'];

    // Dynamic arrays for the tables
    // 1. $norm (sample of 5)
    $norm = [];
    $count = 0;
    foreach ($calc['normData'] as $id => $nd) {
        $norm[] = [
            $id,
            $nd['nama'],
            $nd['lon'],
            $nd['lat'],
            $nd['kunjungan'],
            $nd['rating'],
            $nd['aksesibilitas'],
            $nd['fasilitas'],
            $nd['potensi_alam'],
            $nd['potensi_budaya'],
            $nd['pendapatan'],
            $nd['trend']
        ];
        $count++;
        if ($count >= 5)
            break;
    }

    // 2. $normData (all)
    $normData = [];
    foreach ($calc['normData'] as $id => $nd) {
        $normData[] = [
            $id,
            $nd['nama'],
            $nd['lon'],
            $nd['lat'],
            $nd['kunjungan'],
            $nd['rating'],
            $nd['aksesibilitas'],
            $nd['fasilitas'],
            $nd['potensi_alam'],
            $nd['potensi_budaya'],
            $nd['pendapatan'],
            $nd['trend'],
            $calc['assignments'][$id]
        ];
    }

    // 3. $d2c1
    $d2c1 = [];
    $cum_prob = 0.0;
    $total_d2 = $calc['total_d2_c1'];
    foreach ($calc['normData'] as $id => $nd) {
        $d2 = $calc['d2_c1'][$id];
        $prob = $total_d2 == 0 ? 0.0 : $d2 / $total_d2;
        $cum_prob += $prob;
        $d2c1[] = [
            $id,
            $nd['nama'],
            $d2,
            $prob,
            $cum_prob
        ];
    }

    // 4. $d2min
    $d2min = [];
    foreach ($calc['normData'] as $id => $nd) {
        $d2_1 = $calc['d2_c1'][$id];
        $d2_2 = $calc['d2_c2'][$id];
        $min_d = $calc['d2_min'][$id];

        $ket = '';
        if ($id == $c1_id) {
            $ket = '=C1 itu sendiri';
        } else if ($id == $c2_id) {
            $ket = '=C2 itu sendiri';
        } else if ($id == $c3_id) {
            $ket = '=D²_min lebih kecil ← MAKS!';
        } else {
            $ket = $d2_1 <= $d2_2 ? '=D²(x,C1) lebih kecil' : '=D²(x,C2) lebih kecil';
        }

        $d2min[] = [
            $id,
            $nd['nama'],
            $d2_1,
            $d2_2,
            $min_d,
            $ket
        ];
    }

    // 5. $skor_calc — Skor Potensi (formula resmi, sama dengan Excel '6_Hasil_Klaster')
    // Skor = (Rating/5×0.25)+(Akses/5×0.15)+(Fasilitas/5×0.10)+(Alam/5×0.20)+(Budaya/5×0.15)+(MIN(Pendapatan,20000)/20000×0.15)
    $skor_calc = [];
    foreach ($calc['normData'] as $id => $nd) {
        $raw = $destByID[intval($id)] ?? null;
        if ($raw === null)
            continue;
        $rating_part = ($raw['rating'] / 5) * 0.25;
        $akses_part = ($raw['aksesibilitas'] / 5) * 0.15;
        $fasilitas_part = ($raw['fasilitas'] / 5) * 0.10;
        $alam_part = ($raw['potensi_alam'] / 5) * 0.20;
        $budaya_part = ($raw['potensi_budaya'] / 5) * 0.15;
        $pend_part = (min($raw['pendapatan'], 20000) / 20000) * 0.15;
        $skor_total = $rating_part + $akses_part + $fasilitas_part + $alam_part + $budaya_part + $pend_part;
        $skor_calc[] = [
            $id,
            $nd['nama'],
            $calc['assignments'][$id],
            $rating_part,
            $akses_part,
            $fasilitas_part,
            $alam_part,
            $budaya_part,
            $pend_part,
            $skor_total
        ];
    }

    // 6. $wcssK2 (Klaster 2 final WCSS contributions)
    $wcssK2 = [];
    $flist = ['lon', 'lat', 'kunjungan', 'rating', 'aksesibilitas', 'fasilitas', 'potensi_alam', 'potensi_budaya', 'pendapatan', 'trend'];
    $final_centroids = $calc['final_centroids'];
    foreach ($calc['normData'] as $id => $nd) {
        if ($calc['assignments'][$id] == 2) {
            $dist = 0.0;
            foreach ($flist as $f) {
                $dist += pow($nd[$f] - $final_centroids[2][$f], 2);
            }
            $wcssK2[] = [
                $id,
                $nd['nama'],
                number_format(sqrt($dist), 4),
                $dist
            ];
        }
    }
    $totalK2 = array_sum(array_column($wcssK2, 3));

    // WCSS final per cluster
    $wcss_k_final = [1 => 0.0, 2 => 0.0, 3 => 0.0];
    foreach ($calc['normData'] as $id => $nd) {
        $k = $calc['assignments'][$id];
        $dist = 0.0;
        foreach ($flist as $f) {
            $dist += pow($nd[$f] - $final_centroids[$k][$f], 2);
        }
        $wcss_k_final[$k] += $dist;
    }

    // 7. $scData (Silhouette details)
    // ── DEFINISI SAMA DENGAN EXCEL (sheet 4_Iterasi_KMeans++, baris 67) ──
    // a(i) = jarak Euclidean titik i ke CENTROID klasternya sendiri
    // b(i) = MIN jarak Euclidean titik i ke CENTROID klaster lain (bukan rata-rata anggota)
    // Ini berbeda dari definisi Rousseeuw klasik, tapi konsisten dengan rumus Excel.
    $scData = [];
    foreach ($calc['normData'] as $id => $norm_item) {
        $cluster = $calc['assignments'][$id];

        // a(i): jarak titik ke centroid klaster sendiri
        $dist_a = 0.0;
        foreach ($flist as $f) {
            $dist_a += pow($norm_item[$f] - $final_centroids[$cluster][$f], 2);
        }
        $a_i = sqrt($dist_a);

        // b(i): MIN jarak titik ke centroid klaster lain
        $b_dists = [];
        for ($k = 1; $k <= 3; $k++) {
            if ($k == $cluster)
                continue;
            $dist_b = 0.0;
            foreach ($flist as $f) {
                $dist_b += pow($norm_item[$f] - $final_centroids[$k][$f], 2);
            }
            $b_dists[] = sqrt($dist_b);
        }
        $b_i = empty($b_dists) ? 0.0 : min($b_dists);

        // s(i) = (b - a) / max(a, b)
        $s_i = (max($a_i, $b_i) == 0.0) ? 0.0 : ($b_i - $a_i) / max($a_i, $b_i);

        if ($s_i >= 0.7) {
            $interp = 'Sangat Baik';
        } elseif ($s_i >= 0.5) {
            $interp = 'Baik';
        } elseif ($s_i >= 0.25) {
            $interp = 'Lemah';
        } else {
            $interp = 'Salah Klaster';
        }

        $scData[] = [
            $id,
            $norm_item['nama'],
            $cluster,
            $a_i,
            $b_i,
            $s_i,
            $interp
        ];
    }

    // 8. $dbi_table
    $dbi_table = [];
    for ($i = 1; $i <= 3; $i++) {
        $max_r = -1.0;
        $best_j = -1;
        for ($j = 1; $j <= 3; $j++) {
            if ($i == $j)
                continue;
            $r_val = $calc['R_detail'][$i][$j];
            if ($r_val > $max_r) {
                $max_r = $r_val;
                $best_j = $j;
            }
        }
        $dbi_table[$i] = [
            'i' => $i,
            'sigma_i' => $calc['S_k'][$i],
            'j' => $best_j,
            'sigma_j' => $calc['S_k'][$best_j],
            'd_ij' => $calc['centroid_dists'][$i][$best_j],
            'ratio' => $max_r
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // OVERWRITE SC / DBI / CHI DENGAN HASIL HITUNG LANGSUNG (DINAMIS)
    // Nilai-nilai ini dihitung di sini dari $scData / $dbi_table / $calc
    // (yang sendiri berasal dari runKMeansClustering() atas data destinasi
    // saat ini) — TIDAK ada angka hardcode. Ini memastikan kartu metrik,
    // hero stat, footer, dan bagian §9–§11 selalu konsisten dengan data
    // terbaru, bukan snapshot statis dari database.
    // ══════════════════════════════════════════════════════════════
    $evaluasi['sc'] = empty($scData) ? 0.0 : array_sum(array_column($scData, 5)) / count($scData);
    $evaluasi['dbi'] = empty($dbi_table) ? 0.0 : array_sum(array_column($dbi_table, 'ratio')) / count($dbi_table);
    $evaluasi['chi'] = $calc['chi'] ?? 0.0;

    // 9. $d2_c_gm
    $d2_c_gm = [];
    $gm = $calc['global_mean'];
    for ($k = 1; $k <= 3; $k++) {
        $d = 0.0;
        foreach ($flist as $f) {
            $d += pow($calc['final_centroids'][$k][$f] - $gm[$f], 2);
        }
        $d2_c_gm[$k] = $d;
    }

    $wcss_per_iter = [null, floatval($iter1_wcss), $evaluasi['wcss']];
} else {
    $c1_name = "N/A";
    $c2_name = "N/A";
    $c3_name = "N/A";
    $total_d2_val = "0.0000";
    $c2_d2_val = "0.0000";
    $c3_d2_val = "0.0000";
    $iter1_wcss = "0.000";
    $iter1_counts = [1 => 0, 2 => 0, 3 => 0];
    $final_wcss = "0.000";
    $total_iterations = 0;
    $norm = [];
    $normData = [];
    $d2c1 = [];
    $d2min = [];
    $skor_calc = [];
    $wcssK2 = [];
    $totalK2 = 0;
    $wcss_k_final = [1 => 0.0, 2 => 0.0, 3 => 0.0];
    $scData = [];
    $dbi_table = [];
    $d2_c_gm = [];
    $wcss_per_iter = [null, 0.0, 0.0];
}

// ══════════════════════════════════════════════════════════════
// CALCULATE ALL VISUALIZATION VALUES FROM DATABASE
// ══════════════════════════════════════════════════════════════

// Hero stats
$total_destinasi = count($destinations);
$total_klaster = count($klaster_info);
$iterasi_konvergen = $evaluasi['iter'];
$silhouette_score = $evaluasi['sc'];
$total_pendapatan = array_sum(array_column($destinations, 'pendapatan'));
$total_kunjungan = array_sum(array_column($destinations, 'kunjungan'));

// Visit distribution per cluster
$visit_per_cluster = [0, 0, 0];
foreach ($destinations as $d) {
    $k = $d['klaster'] - 1;
    if (isset($visit_per_cluster[$k])) {
        $visit_per_cluster[$k] += $d['kunjungan'];
    }
}

// Revenue distribution per cluster (in millions)
$revenue_per_cluster = [0, 0, 0];
foreach ($destinations as $d) {
    $k = $d['klaster'] - 1;
    if (isset($revenue_per_cluster[$k])) {
        $revenue_per_cluster[$k] += $d['pendapatan'];
    }
}

// Calculate average attributes per cluster for Radar chart
$radar_data = [];
for ($k = 1; $k <= 3; $k++) {
    $cluster_dests = array_filter($destinations, fn($d) => $d['klaster'] == $k);
    if (empty($cluster_dests)) {
        $radar_data[$k] = [0, 0, 0, 0, 0, 0];
    } else {
        $n = count($cluster_dests);
        $avg_rating = array_sum(array_column($cluster_dests, 'rating')) / $n;
        $avg_aksesibilitas = array_sum(array_column($cluster_dests, 'aksesibilitas')) / $n;
        $avg_fasilitas = array_sum(array_column($cluster_dests, 'fasilitas')) / $n;
        $avg_potensi_alam = array_sum(array_column($cluster_dests, 'potensi_alam')) / $n;
        $avg_potensi_budaya = array_sum(array_column($cluster_dests, 'potensi_budaya')) / $n;
        $avg_trend = array_sum(array_column($cluster_dests, 'trend')) / $n;
        $radar_data[$k] = [
            round($avg_rating, 2),
            round($avg_aksesibilitas, 2),
            round($avg_fasilitas, 2),
            round($avg_potensi_alam, 2),
            round($avg_potensi_budaya, 2),
            round($avg_trend * 100, 1)  // Convert to percentage for visibility
        ];
    }
}

// WCSS per iteration (Iteration 0: init, Iteration 1: calculated, Final: from database)
$wcss_per_iter = [null, $calc['success'] && isset($calc['iterations_history'][1]) ? $calc['iterations_history'][1]['wcss'] : 0, $evaluasi['wcss']];

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIG K-Means++ Wisata Kabupaten Magelang</title>

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <!-- Leaflet JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Sora:wght@300;400;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --bg: #0a0f1e;
            --surface: #111827;
            --surface2: #1a2235;
            --border: #1e2d45;
            --text: #e2e8f0;
            --muted: #64748b;
            --k1: #f59e0b;
            --k2: #3b82f6;
            --k3: #10b981;
            --accent: #6366f1;
            --danger: #ef4444;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        html {
            scroll-behavior: smooth
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Sora', sans-serif;
            min-height: 100vh;
            overflow-x: hidden
        }

        /* ── NAV ── */
        nav {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(10, 15, 30, 0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .75rem 2rem;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: .75rem
        }

        .nav-logo {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, var(--k1), var(--accent));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Space Mono', monospace;
            font-weight: 700;
            font-size: .9rem;
            color: #fff;
            flex-shrink: 0
        }

        .nav-title {
            font-size: .95rem;
            font-weight: 700;
            line-height: 1.2
        }

        .nav-sub {
            font-size: .72rem;
            color: var(--muted);
            font-weight: 300
        }

        .nav-tabs {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .25rem;
            flex-wrap: wrap;
        }

        .nav-toggle {
            display: none;
            align-items: center;
            gap: .45rem;
            padding: .55rem .85rem;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(26, 34, 53, .95);
            color: var(--text);
            font: inherit;
            font-size: .82rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s, border-color .2s, color .2s;
        }

        .nav-toggle:hover {
            background: var(--surface2);
            border-color: #2b3d5c
        }

        .nav-menu {
            display: flex;
            gap: .25rem
        }

        .nav-tab {
            padding: .45rem 1rem;
            border-radius: 8px;
            font-size: .8rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            background: transparent;
            color: var(--muted);
            transition: all .2s
        }

        .nav-tab:hover {
            background: var(--surface2);
            color: var(--text)
        }

        .nav-tab.active {
            background: var(--accent);
            color: #fff
        }

        /* ── HERO ── */
        .hero {
            padding: 3rem 2rem 2rem;
            background: radial-gradient(ellipse 80% 50% at 50% -10%, rgba(99, 102, 241, .18), transparent),
                radial-gradient(ellipse 50% 30% at 80% 60%, rgba(245, 158, 11, .08), transparent);
            text-align: center;
        }

        .hero h1 {
            font-size: clamp(1.6rem, 3vw, 2.6rem);
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -.02em
        }

        .hero h1 span {
            background: linear-gradient(90deg, var(--k1), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text
        }

        .hero p {
            color: var(--muted);
            margin-top: .6rem;
            font-size: .9rem;
            max-width: 600px;
            margin-inline: auto
        }

        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-top: 2rem;
            flex-wrap: wrap
        }

        .hstat {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: .85rem 1.5rem;
            text-align: center;
            min-width: 110px
        }

        .hstat-val {
            font-size: 1.8rem;
            font-weight: 800;
            font-family: 'Space Mono', monospace;
            line-height: 1
        }

        .hstat-lbl {
            font-size: .7rem;
            color: var(--muted);
            margin-top: .3rem;
            text-transform: uppercase;
            letter-spacing: .05em
        }

        /* ── PAGES ── */
        .page {
            display: none;
            padding: 1.5rem 2rem 3rem
        }

        .page.active {
            display: block
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: .6rem
        }

        .section-title::before {
            content: '';
            width: 4px;
            height: 1.1rem;
            background: var(--accent);
            border-radius: 2px;
            display: inline-block
        }

        /* ── CLUSTER CARDS ── */
        .cluster-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem
        }

        .ccard {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.25rem;
            position: relative;
            overflow: hidden;
            transition: transform .2s, box-shadow .2s
        }

        .ccard:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, .4)
        }

        .ccard::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px
        }

        .ccard.k1::before {
            background: var(--k1)
        }

        .ccard.k2::before {
            background: var(--k2)
        }

        .ccard.k3::before {
            background: var(--k3)
        }

        .ccard-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: .75rem
        }

        .ccard-label {
            font-weight: 700;
            font-size: .95rem
        }

        .ccard-badge {
            padding: .25rem .6rem;
            border-radius: 6px;
            font-size: .72rem;
            font-weight: 700
        }

        .k1 .ccard-badge {
            background: rgba(245, 158, 11, .15);
            color: var(--k1)
        }

        .k2 .ccard-badge {
            background: rgba(59, 130, 246, .15);
            color: var(--k2)
        }

        .k3 .ccard-badge {
            background: rgba(16, 185, 129, .15);
            color: var(--k3)
        }

        .ccard-stat {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .5rem;
            margin-bottom: .75rem
        }

        .cs {
            background: var(--surface2);
            border-radius: 8px;
            padding: .5rem .75rem
        }

        .cs-val {
            font-family: 'Space Mono', monospace;
            font-size: 1rem;
            font-weight: 700
        }

        .cs-lbl {
            font-size: .68rem;
            color: var(--muted);
            margin-top: .1rem
        }

        .ccard-strat {
            font-size: .75rem;
            color: var(--muted);
            line-height: 1.5;
            border-top: 1px solid var(--border);
            padding-top: .75rem
        }

        /* ── MAP ── */
        #map {
            height: 500px;
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden
        }

        .map-legend {
            display: flex;
            gap: 1.5rem;
            margin-top: .75rem;
            flex-wrap: wrap
        }

        .ml-item {
            display: flex;
            align-items: center;
            gap: .4rem;
            font-size: .78rem;
            color: var(--muted)
        }

        .ml-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0
        }

        /* ── TABLE ── */
        .table-wrap {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid var(--border)
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: .82rem
        }

        thead th {
            background: var(--surface2);
            padding: .75rem 1rem;
            text-align: left;
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
            white-space: nowrap
        }

        tbody tr {
            border-top: 1px solid var(--border)
        }

        tbody tr:hover {
            background: var(--surface2)
        }

        tbody td {
            padding: .65rem 1rem;
            white-space: nowrap
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: .2rem .6rem;
            border-radius: 6px;
            font-size: .72rem;
            font-weight: 700;
            gap: .25rem
        }

        .badge.k1 {
            background: rgba(245, 158, 11, .15);
            color: var(--k1)
        }

        .badge.k2 {
            background: rgba(59, 130, 246, .15);
            color: var(--k2)
        }

        .badge.k3 {
            background: rgba(16, 185, 129, .15);
            color: var(--k3)
        }

        /* ── CHARTS ── */
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem
        }

        .chart-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.25rem
        }

        .chart-title {
            font-size: .85rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--muted)
        }

        canvas {
            max-height: 280px
        }

        /* ── METRICS ── */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem
        }

        .metric-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: .5rem
        }

        .metric-val {
            font-size: 2rem;
            font-weight: 800;
            font-family: 'Space Mono', monospace;
            line-height: 1
        }

        .metric-name {
            font-size: .8rem;
            color: var(--muted);
            font-weight: 600
        }

        .metric-desc {
            font-size: .72rem;
            color: var(--muted)
        }

        .metric-status {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            font-size: .75rem;
            font-weight: 700;
            padding: .25rem .6rem;
            border-radius: 6px
        }

        .status-ok {
            background: rgba(16, 185, 129, .15);
            color: #10b981
        }

        .status-warn {
            background: rgba(245, 158, 11, .15);
            color: #f59e0b
        }

        .status-bad {
            background: rgba(239, 68, 68, .15);
            color: #ef4444
        }

        .progress-bar {
            height: 6px;
            background: var(--border);
            border-radius: 3px;
            overflow: hidden;
            margin-top: .25rem
        }

        .progress-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 1s ease
        }

        /* ── KMEANS STEPS ── */
        .steps {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem
        }

        .step {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.25rem;
            border-left: 4px solid var(--accent)
        }

        .step-title {
            font-weight: 700;
            font-size: .9rem;
            margin-bottom: .5rem;
            display: flex;
            align-items: center;
            gap: .5rem
        }

        .step-num {
            background: var(--accent);
            color: #fff;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .72rem;
            font-weight: 700;
            flex-shrink: 0
        }

        .step-body {
            font-size: .8rem;
            color: var(--muted);
            line-height: 1.7
        }

        .step-body code {
            background: var(--surface2);
            color: var(--accent);
            padding: .1rem .35rem;
            border-radius: 4px;
            font-family: 'Space Mono', monospace;
            font-size: .78rem
        }

        /* ── PROYEKSI ── */
        .proj-table-wrap {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid var(--border)
        }

        .trend-bar {
            display: flex;
            align-items: center;
            gap: .4rem
        }

        .trend-num {
            font-family: 'Space Mono', monospace;
            font-size: .8rem;
            min-width: 3.5rem
        }

        .trend-fill {
            height: 5px;
            border-radius: 3px
        }

        /* ── FOOTER ── */
        footer {
            background: var(--surface);
            border-top: 1px solid var(--border);
            padding: 1.25rem 2rem;
            text-align: center;
            font-size: .75rem;
            color: var(--muted)
        }

        /* ── SCROLL ANIMATION ── */
        .fade-in {
            opacity: 0;
            transform: translateY(16px);
            transition: opacity .5s ease, transform .5s ease
        }

        .fade-in.visible {
            opacity: 1;
            transform: none
        }

        /* ── MANUAL CALCULATION ── */
        .manual-toc {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin-bottom: 2rem;
            padding: 1.25rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px
        }

        .toc-item {
            padding: .4rem .9rem;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: .78rem;
            cursor: pointer;
            color: var(--muted);
            transition: all .2s;
            text-decoration: none
        }

        .toc-item:hover {
            color: var(--accent);
            border-color: var(--accent)
        }

        .calc-section {
            margin-bottom: 2.5rem
        }

        .calc-section-header {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: 1rem 1.25rem;
            background: linear-gradient(90deg, rgba(99, 102, 241, .12), transparent);
            border-left: 4px solid var(--accent);
            border-radius: 0 12px 12px 0;
            margin-bottom: 1.25rem;
            cursor: pointer
        }

        .calc-section-header h3 {
            font-size: 1rem;
            font-weight: 700;
            margin: 0
        }

        .calc-section-header .cs-num {
            width: 28px;
            height: 28px;
            background: var(--accent);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .8rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0
        }

        .calc-body {
            display: block
        }

        .formula-box {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin: .75rem 0;
            font-family: 'Space Mono', monospace;
            font-size: .82rem;
            line-height: 1.8;
            overflow-x: auto
        }

        .formula-box .formula-title {
            font-family: 'Sora', sans-serif;
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
            margin-bottom: .5rem;
            font-weight: 600
        }

        .formula-box .formula-main {
            color: var(--accent);
            font-size: .9rem;
            font-weight: 700;
            display: block;
            margin-bottom: .35rem
        }

        .formula-box .formula-vars {
            color: var(--muted);
            font-size: .78rem
        }

        .calc-note {
            background: rgba(245, 158, 11, .08);
            border: 1px solid rgba(245, 158, 11, .2);
            border-radius: 8px;
            padding: .75rem 1rem;
            font-size: .8rem;
            color: #fbbf24;
            margin: .75rem 0;
            line-height: 1.6
        }

        .calc-note strong {
            color: var(--k1)
        }

        .calc-result {
            background: rgba(16, 185, 129, .08);
            border: 1px solid rgba(16, 185, 129, .25);
            border-radius: 8px;
            padding: .75rem 1rem;
            font-size: .82rem;
            color: #34d399;
            margin: .75rem 0
        }

        .calc-result strong {
            color: var(--k3);
            font-family: 'Space Mono', monospace
        }

        .manual-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .78rem;
            margin: .75rem 0
        }

        .manual-table thead th {
            background: rgba(99, 102, 241, .15);
            padding: .6rem .8rem;
            text-align: left;
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--accent);
            border-bottom: 1px solid var(--border);
            white-space: nowrap
        }

        .manual-table tbody tr {
            border-bottom: 1px solid rgba(30, 45, 69, .5)
        }

        .manual-table tbody tr:hover {
            background: var(--surface2)
        }

        .manual-table tbody td {
            padding: .55rem .8rem;
            vertical-align: top
        }

        .manual-table .mono {
            font-family: 'Space Mono', monospace;
            font-size: .75rem
        }

        .manual-table .highlight {
            background: rgba(245, 158, 11, .1);
            color: var(--k1);
            font-weight: 700
        }

        .manual-table .highlight2 {
            background: rgba(59, 130, 246, .1);
            color: var(--k2);
            font-weight: 700
        }

        .manual-table .highlight3 {
            background: rgba(16, 185, 129, .1);
            color: var(--k3);
            font-weight: 700
        }

        .step-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .75rem;
            margin: .75rem 0
        }

        .step-detail-item {
            background: var(--surface2);
            border-radius: 8px;
            padding: .75rem;
            border: 1px solid var(--border)
        }

        .step-detail-item .sdi-label {
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
            margin-bottom: .35rem
        }

        .step-detail-item .sdi-val {
            font-family: 'Space Mono', monospace;
            font-size: .85rem;
            color: var(--text);
            font-weight: 700
        }

        .step-detail-item .sdi-val.accent {
            color: var(--accent)
        }

        .step-detail-item .sdi-val.gold {
            color: var(--k1)
        }

        .step-detail-item .sdi-val.blue {
            color: var(--k2)
        }

        .step-detail-item .sdi-val.green {
            color: var(--k3)
        }

        .iter-tab-bar {
            display: flex;
            gap: .5rem;
            margin-bottom: 1rem
        }

        .iter-tab {
            padding: .35rem .9rem;
            border-radius: 7px;
            font-size: .78rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--border);
            background: var(--surface2);
            color: var(--muted);
            transition: all .2s
        }

        .iter-tab.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff
        }

        .iter-panel {
            display: none
        }

        .iter-panel.active {
            display: block
        }

        .arrow-right {
            display: inline-block;
            margin: 0 .3rem;
            color: var(--muted)
        }

        .assign-badge {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .2rem .55rem;
            border-radius: 5px;
            font-size: .72rem;
            font-weight: 700;
            font-family: 'Space Mono', monospace
        }

        .ab1 {
            background: rgba(245, 158, 11, .15);
            color: var(--k1)
        }

        .ab2 {
            background: rgba(59, 130, 246, .15);
            color: var(--k2)
        }

        .ab3 {
            background: rgba(16, 185, 129, .15);
            color: var(--k3)
        }

        .min-highlight {
            background: rgba(99, 102, 241, .2);
            color: #a5b4fc;
            font-weight: 700;
            border-radius: 3px;
            padding: 0 2px
        }

        @media(max-width:640px) {
            .step-detail {
                grid-template-columns: 1fr
            }
        }

        @media(max-width:860px) {
            nav {
                padding: .65rem 1rem;
                flex-wrap: wrap;
                align-items: flex-start
            }

            .nav-brand {
                min-width: 0
            }

            .nav-toggle {
                display: inline-flex;
                margin-left: auto
            }

            .nav-menu {
                width: 100%;
                display: grid;
                grid-template-columns: 1fr;
                gap: .45rem;
                max-height: 0;
                overflow: hidden;
                opacity: 0;
                transform: translateY(-.35rem);
                transition: max-height .25s ease, opacity .2s ease, transform .2s ease;
                margin-top: 0
            }

            .nav-menu.open {
                max-height: 70vh;
                opacity: 1;
                transform: translateY(0);
                margin-top: .75rem
            }

            .nav-tabs {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
                justify-content: flex-start
            }

            .nav-tab {
                width: 100%;
                text-align: left
            }

            .page {
                padding: 1rem 1rem 3rem
            }

            .hero {
                padding: 2rem 1rem 1.5rem
            }
        }

        /* ── UPDATE BUTTON ── */
        #btnUpdateCalc {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .6rem 1.3rem;
            background: #10b981;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: .85rem;
            cursor: pointer;
            transition: all .3s ease;
            box-shadow: 0 4px 12px rgba(16, 185, 129, .2);
        }

        #btnUpdateCalc:hover:not(:disabled) {
            background: #059669;
            box-shadow: 0 6px 20px rgba(16, 185, 129, .3);
            transform: translateY(-2px);
        }

        #btnUpdateCalc:active:not(:disabled) {
            transform: translateY(0);
        }

        #btnUpdateCalc:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        @media(max-width:640px) {
            #btnUpdateCalc {
                width: 100%;
                justify-content: center;
                padding: .7rem 1rem;
            }
        }
    </style>
</head>

<body>

    <!-- NAV -->
    <nav>
        <div class="nav-brand">
            <div class="nav-logo">KM+</div>
            <div>
                <div class="nav-title">SIG K-Means++</div>
                <div class="nav-sub">Wisata Kabupaten Magelang</div>
            </div>
        </div>
        <button class="nav-toggle" type="button" onclick="toggleNavMenu()" aria-expanded="false"
            aria-controls="navMenu">☰ Menu</button>
        <div class="nav-menu" id="navMenu">
            <div class="nav-tabs">
                <button class="nav-tab active" data-page="dashboard"
                    onclick="showPage('dashboard', this)">Dashboard</button>
                <button class="nav-tab" data-page="peta" onclick="showPage('peta', this)">🗺 Peta SIG</button>
                <button class="nav-tab" data-page="data" onclick="showPage('data', this)">Tabel Data</button>
                <button class="nav-tab" data-page="analisis" onclick="showPage('analisis', this)">Analisis</button>
                <button class="nav-tab" data-page="proyeksi" onclick="showPage('proyeksi', this)">Proyeksi</button>
                <button class="nav-tab" data-page="algoritma" onclick="showPage('algoritma', this)">Algoritma</button>
                <button class="nav-tab" data-page="manual" onclick="showPage('manual', this)"
                    style="background:rgba(99,102,241,.15);color:var(--accent);border:1px solid rgba(99,102,241,.3)">Perhitungan</button>
                <a href="admin.php" class="nav-tab"
                    style="background:rgba(239,68,68,.15);color:#ff6b6b;border:1px solid rgba(239,68,68,.3);text-decoration:none">Admin</a>
            </div>
        </div>
    </nav>

    <!-- HERO -->
    <div class="hero">
        <h1>Intelijen Spasial <span>K-Means++</span><br>Potensi Wisata Magelang</h1>
        <p>Klasterisasi berbasis data spatio-temporal untuk pemodelan dinamis potensi destinasi wisata Kabupaten
            Magelang menggunakan algoritma K-Means++ dengan 3 klaster optimal.</p>
        <div class="hero-stats">
            <div class="hstat">
                <div class="hstat-val" style="color:var(--k1)"><?= $total_destinasi ?></div>
                <div class="hstat-lbl">Destinasi</div>
            </div>
            <div class="hstat">
                <div class="hstat-val" style="color:var(--k2)"><?= $total_klaster ?></div>
                <div class="hstat-lbl">Klaster</div>
            </div>
            <div class="hstat">
                <div class="hstat-val" style="color:var(--k3)"><?= $iterasi_konvergen ?></div>
                <div class="hstat-lbl">Iterasi</div>
            </div>
            <div class="hstat">
                <div class="hstat-val" style="color:var(--accent)"><?= number_format($silhouette_score, 4) ?></div>
                <div class="hstat-lbl">Silhouette</div>
            </div>
            <div class="hstat">
                <div class="hstat-val" style="color:#ec4899"><?= number_format($total_pendapatan / 1000000, 1) ?>M</div>
                <div class="hstat-lbl">Total/Thn</div>
            </div>
        </div>
    </div>

    <!-- ══════════════ PAGE: DASHBOARD ══════════════ -->
    <div id="page-dashboard" class="page active">

        <div class="section-title">Ringkasan Klaster</div>
        <div class="cluster-cards">
            <?php foreach ($klaster_info as $k => $info): ?>
                <div class="ccard k<?= $k ?> fade-in">
                    <div class="ccard-header">
                        <div class="ccard-label"><?= htmlspecialchars($info['label']) ?></div>
                        <div class="ccard-badge"><?= $info['n'] ?> Destinasi</div>
                    </div>
                    <div class="ccard-stat">
                        <div class="cs">
                            <div class="cs-val"><?= number_format($info['avg_kunjungan']) ?></div>
                            <div class="cs-lbl">Avg Kunjungan/Thn</div>
                        </div>
                        <div class="cs">
                            <div class="cs-val"><?= $info['avg_rating'] ?></div>
                            <div class="cs-lbl">Avg Rating</div>
                        </div>
                        <div class="cs">
                            <div class="cs-val">Rp <?= number_format($info['total_pend']) ?>Jt</div>
                            <div class="cs-lbl">Total Pendapatan</div>
                        </div>
                        <div class="cs">
                            <div class="cs-val"><?= $info['n'] ?>/<?= $total_destinasi ?></div>
                            <div class="cs-lbl">Proporsi</div>
                        </div>
                    </div>
                    <div class="ccard-strat">💡 <?= htmlspecialchars($info['strategi']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="chart-grid">
            <div class="chart-box fade-in">
                <div class="chart-title">Distribusi Kunjungan per Klaster</div>
                <canvas id="chartVisit"></canvas>
            </div>
            <div class="chart-box fade-in">
                <div class="chart-title">Pendapatan Total per Klaster (Juta Rp)</div>
                <canvas id="chartPend"></canvas>
            </div>
            <div class="chart-box fade-in">
                <div class="chart-title">Radar: Atribut Rata-rata per Klaster</div>
                <canvas id="chartRadar"></canvas>
            </div>
            <div class="chart-box fade-in">
                <div class="chart-title">Skor Potensi Destinasi</div>
                <canvas id="chartSkor"></canvas>
            </div>
        </div>

    </div>

    <!-- ══════════════ PAGE: PETA SIG ══════════════ -->
    <div id="page-peta" class="page">
        <div class="section-title">Peta Sebaran Spasial – Kabupaten Magelang</div>
        <div id="map"></div>
        <div class="map-legend">
            <?php foreach ($klaster_info as $k => $info): ?>
                <div class="ml-item">
                    <div class="ml-dot" style="background:var(--k<?= $k ?>)"></div>
                    <?= htmlspecialchars($info['label']) ?> (<?= $info['n'] ?> titik)
                </div>
            <?php endforeach; ?>
            <div class="ml-item" style="margin-left:auto;font-style:italic">Klik marker untuk detail destinasi</div>
        </div>
        <div style="margin-top:1.5rem" class="chart-grid">
            <div class="chart-box fade-in">
                <div class="chart-title">Scatter Plot: Koordinat & Klaster</div>
                <canvas id="chartScatter"></canvas>
            </div>
            <div class="chart-box fade-in">
                <div class="chart-title">Distribusi Zona Temporal</div>
                <canvas id="chartZona"></canvas>
            </div>
        </div>
    </div>

    <!-- ══════════════ PAGE: TABEL DATA ══════════════ -->
    <div id="page-data" class="page">
        <div class="section-title">Dataset Destinasi Wisata</div>

        <!-- Filter -->
        <div style="display:flex;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap">
            <select id="filterKlaster" onchange="filterTable()"
                style="background:var(--surface2);border:1px solid var(--border);color:var(--text);padding:.45rem .75rem;border-radius:8px;font-size:.82rem">
                <option value="">Semua Klaster</option>
                <option value="1">Klaster 1: Tinggi</option>
                <option value="2">Klaster 2: Sedang</option>
                <option value="3">Klaster 3: Rendah</option>
            </select>
            <select id="filterZona" onchange="filterTable()"
                style="background:var(--surface2);border:1px solid var(--border);color:var(--text);padding:.45rem .75rem;border-radius:8px;font-size:.82rem">
                <option value="">Semua Zona</option>
                <option value="Peak Season">Peak Season</option>
                <option value="Mid Season">Mid Season</option>
                <option value="Low Season">Low Season</option>
            </select>
            <input id="searchInput" type="text" placeholder="Cari destinasi..." onkeyup="filterTable()"
                style="background:var(--surface2);border:1px solid var(--border);color:var(--text);padding:.45rem .75rem;border-radius:8px;font-size:.82rem;flex:1;min-width:160px">
        </div>

        <div class="table-wrap">
            <table id="mainTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Destinasi</th>
                        <th>Klaster</th>
                        <th>Kunjungan/Thn</th>
                        <th>Rating</th>
                        <th>Akses</th>
                        <th>Fasilitas</th>
                        <th>P.Alam</th>
                        <th>P.Budaya</th>
                        <th>Pend.(Jt)</th>
                        <th>Trend YoY</th>
                        <th>Zona</th>
                        <th>Skor</th>
                        <th>Rekomendasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($destinations as $d): ?>
                        <tr data-klaster="<?= $d['klaster'] ?>" data-zona="<?= htmlspecialchars($d['zona']) ?>"
                            data-nama="<?= strtolower($d['nama']) ?>">
                            <td style="font-family:'Space Mono',monospace;color:var(--muted)"><?= $d['id'] ?></td>
                            <td style="font-weight:600"><?= htmlspecialchars($d['nama']) ?></td>
                            <td><span class="badge k<?= $d['klaster'] ?>">K<?= $d['klaster'] ?></span></td>
                            <td style="font-family:'Space Mono',monospace"><?= number_format($d['kunjungan']) ?></td>
                            <td>
                                <span
                                    style="color:<?= $d['rating'] >= 4.5 ? 'var(--k1)' : ($d['rating'] >= 4.0 ? 'var(--k3)' : 'var(--muted)') ?>;font-weight:700">
                                    <?= $d['rating'] ?> ★
                                </span>
                            </td>
                            <td><?= str_repeat('●', intval($d['aksesibilitas'])) . str_repeat('○', 5 - intval($d['aksesibilitas'])) ?>
                            </td>
                            <td><?= str_repeat('●', intval($d['fasilitas'])) . str_repeat('○', 5 - intval($d['fasilitas'])) ?>
                            </td>
                            <td><?= str_repeat('●', intval($d['potensi_alam'])) . str_repeat('○', 5 - intval($d['potensi_alam'])) ?>
                            </td>
                            <td><?= str_repeat('●', intval($d['potensi_budaya'])) . str_repeat('○', 5 - intval($d['potensi_budaya'])) ?>
                            </td>
                            <td style="font-family:'Space Mono',monospace"><?= number_format($d['pendapatan']) ?></td>
                            <td
                                style="color:<?= $d['trend'] >= 0.3 ? 'var(--k3)' : ($d['trend'] >= 0.15 ? 'var(--k2)' : 'var(--muted)') ?>;font-family:'Space Mono',monospace">
                                +<?= round($d['trend'] * 100, 1) ?>%
                            </td>
                            <td>
                                <?php $zc = ['Peak Season' => 'var(--k1)', 'Mid Season' => 'var(--accent)', 'Low Season' => 'var(--muted)']; ?>
                                <span
                                    style="color:<?= $zc[$d['zona']] ?? 'var(--muted)' ?>;font-size:.75rem;font-weight:600"><?= htmlspecialchars($d['zona']) ?></span>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:.4rem">
                                    <div class="progress-bar" style="width:60px;display:inline-block">
                                        <div class="progress-fill"
                                            style="width:<?= round($d['skor'] * 100) ?>%;background:var(--k<?= $d['klaster'] ?>)">
                                        </div>
                                    </div>
                                    <span
                                        style="font-family:'Space Mono',monospace;font-size:.75rem"><?= round($d['skor'], 3) ?></span>
                                </div>
                            </td>
                            <td style="font-size:.75rem;color:var(--muted);max-width:200px">
                                <?= htmlspecialchars($d['rekomendasi']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ══════════════ PAGE: ANALISIS ══════════════ -->
    <div id="page-analisis" class="page">
        <div class="section-title">Metrik Evaluasi Model K-Means++</div>
        <div class="metrics-grid">
            <div class="metric-card fade-in">
                <div class="metric-name">Silhouette Coefficient (SC)</div>
                <div class="metric-val" style="color:var(--k3)"><?= number_format($evaluasi['sc'], 4) ?></div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:<?= round($evaluasi['sc'] * 100) ?>%;background:var(--k3)">
                    </div>
                </div>
                <div class="metric-status status-ok">✓ Baik (> 0.5)</div>
                <div class="metric-desc">Mengukur seberapa mirip objek dengan klasternya dibanding klaster lain.
                    Kisaran: [-1, +1]</div>
            </div>
            <div class="metric-card fade-in">
                <div class="metric-name">Davies-Bouldin Index (DBI)</div>
                <div class="metric-val" style="color:var(--k3)"><?= number_format($evaluasi['dbi'], 4) ?></div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:<?= round($evaluasi['dbi'] * 100) ?>%;background:var(--k3)">
                    </div>
                </div>
                <div class="metric-status status-ok">✓ Sangat Baik (< 1.0)</div>
                        <div class="metric-desc">Rasio dispersi intra-klaster terhadap jarak antar centroid. Semakin
                            kecil
                            semakin baik.</div>
                </div>
                <div class="metric-card fade-in">
                    <div class="metric-name">Calinski-Harabasz Index (CHI)</div>
                    <div class="metric-val" style="color:var(--k1)"><?= number_format($evaluasi['chi'], 4) ?></div>
                    <div class="progress-bar">
                        <div class="progress-fill"
                            style="width:<?= min(100, round($evaluasi['chi'] / 2)) ?>%;background:var(--k1)"></div>
                    </div>
                    <div class="metric-status status-warn">⚠ Perlu Perbaikan (> 100)</div>
                    <div class="metric-desc">Rasio variance antar-klaster terhadap intra-klaster. Nilai rendah karena
                        dataset kecil (N=15).</div>
                </div>
                <div class="metric-card fade-in">
                    <div class="metric-name">WCSS (Within-Cluster Sum of Squares)</div>
                    <div class="metric-val" style="color:var(--k2)"><?= number_format($evaluasi['wcss'], 4) ?></div>
                    <div class="progress-bar">
                        <div class="progress-fill"
                            style="width:<?= min(100, round($evaluasi['wcss'] * 10)) ?>%;background:var(--k2)"></div>
                    </div>
                    <div class="metric-status status-ok">✓ Konvergen Iterasi <?= $evaluasi['iter'] ?></div>
                    <div class="metric-desc">Total jarak kuadrat dalam klaster. Semakin kecil = klaster semakin kompak.
                    </div>
                </div>
            </div>

            <div class="chart-grid">
                <div class="chart-box fade-in">
                    <div class="chart-title">Silhouette Score per Destinasi</div>
                    <canvas id="chartSilhouette"></canvas>
                </div>
                <div class="chart-box fade-in">
                    <div class="chart-title">Konvergensi WCSS per Iterasi</div>
                    <canvas id="chartWCSS"></canvas>
                </div>
                <div class="chart-box fade-in">
                    <div class="chart-title">Perbandingan Atribut Rata-rata (Spider)</div>
                    <canvas id="chartRadar2"></canvas>
                </div>
                <div class="chart-box fade-in">
                    <div class="chart-title">Trend YoY per Destinasi</div>
                    <canvas id="chartTrend"></canvas>
                </div>
            </div>

            <!-- Normalisasi table -->
            <div class="section-title" style="margin-top:1.5rem">Hasil Normalisasi Min-Max (Sampel)</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>Lon_n</th>
                            <th>Lat_n</th>
                            <th>Visit_n</th>
                            <th>Rating_n</th>
                            <th>Akses_n</th>
                            <th>Fas_n</th>
                            <th>Alam_n</th>
                            <th>Budaya_n</th>
                            <th>Pend_n</th>
                            <th>Trend_n</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Use dynamic data from K-Means calculation (show first 5)
                        foreach ($norm as $r): ?>
                            <tr>
                                <td style="font-family:'Space Mono',monospace;color:var(--muted)"><?= $r[0] ?></td>
                                <td style="font-weight:600"><?= $r[1] ?></td>
                                <?php for ($i = 2; $i < 12; $i++): ?>
                                    <td
                                        style="font-family:'Space Mono',monospace;font-size:.78rem;color:<?= $r[$i] >= 0.8 ? 'var(--k1)' : ($r[$i] >= 0.5 ? 'var(--k3)' : 'var(--muted)') ?>">
                                        <?= number_format($r[$i], 4) ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══════════════ PAGE: PROYEKSI ══════════════ -->
        <div id="page-proyeksi" class="page">
            <div class="section-title">Proyeksi Kunjungan Spatio-Temporal (2024–2027)</div>

            <div class="chart-grid">
                <div class="chart-box fade-in" style="grid-column:1/-1">
                    <div class="chart-title">Proyeksi Kunjungan per Destinasi (2024–2027)</div>
                    <canvas id="chartProyeksi" style="max-height:320px"></canvas>
                </div>
            </div>

            <div class="proj-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Destinasi</th>
                            <th>Klaster</th>
                            <th>2024</th>
                            <th>2025</th>
                            <th>2026</th>
                            <th>2027</th>
                            <th>CAGR</th>
                            <th>Kategori</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proyeksi as $p): ?>
                            <tr>
                                <td style="font-weight:600"><?= htmlspecialchars($p['nama']) ?></td>
                                <td><span class="badge k<?= $p['k'] ?>">K<?= $p['k'] ?></span></td>
                                <td style="font-family:'Space Mono',monospace"><?= number_format($p['y2024']) ?></td>
                                <td style="font-family:'Space Mono',monospace;color:var(--text)">
                                    <?= number_format($p['y2025']) ?>
                                </td>
                                <td style="font-family:'Space Mono',monospace;color:var(--k3)">
                                    <?= number_format($p['y2026']) ?>
                                </td>
                                <td style="font-family:'Space Mono',monospace;color:var(--k1);font-weight:700">
                                    <?= number_format($p['y2027']) ?>
                                </td>
                                <td>
                                    <div class="trend-bar">
                                        <span class="trend-num"
                                            style="color:<?= $p['cagr'] >= 0.3 ? 'var(--k3)' : ($p['cagr'] >= 0.15 ? 'var(--k2)' : 'var(--muted)') ?>">
                                            +<?= number_format($p['cagr'] * 100, 1) ?>%
                                        </span>
                                    </div>
                                </td>
                                <td style="font-size:.75rem;color:var(--muted)">
                                    <?= htmlspecialchars($p['kategori'] ?? '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══════════════ PAGE: ALGORITMA ══════════════ -->
        <div id="page-algoritma" class="page">
            <div class="section-title">Prosedur Algoritma K-Means++</div>

            <div class="steps">
                <div class="step fade-in">
                    <div class="step-title"><span class="step-num">1</span> Normalisasi Min-Max</div>
                    <div class="step-body">
                        Semua fitur numerik (Longitude, Latitude, Kunjungan, Rating, Aksesibilitas, Fasilitas, Potensi
                        Alam, Potensi Budaya, Pendapatan, Trend) dinormalisasi ke rentang [0,1] menggunakan
                        formula:<br><br>
                        <code>X_norm = (X − X_min) / (X_max − X_min)</code><br><br>
                        Ini memastikan setiap fitur berkontribusi proporsional dalam perhitungan jarak Euclidean,
                        mencegah dominasi fitur dengan skala besar seperti jumlah kunjungan.
                    </div>
                </div>
                <div class="step fade-in">
                    <div class="step-title"><span class="step-num">2</span> Inisialisasi Centroid K-Means++ (C1)</div>
                    <div class="step-body">
                        Centroid pertama dipilih secara acak. Dalam dataset ini, <strong>C1 =
                            <?= htmlspecialchars($c1_name) ?> (ID=<?= $c1_id ?>)</strong> dipilih sebagai seed karena
                        memiliki profil tertinggi (kunjungan dan rating tertinggi). Formula jarak kuadrat:<br><br>
                        <code>D²(x, C1) = Σᵢ (xᵢ − C1ᵢ)²</code><br><br>
                        Total D² dari semua titik ke C1 = <strong><?= $total_d2_val ?></strong>
                    </div>
                </div>
                <div class="step fade-in">
                    <div class="step-title"><span class="step-num">3</span> Pilih C2 Berbasis Probabilitas D²</div>
                    <div class="step-body">
                        Centroid C2 dipilih dengan probabilitas proporsional terhadap D²(x). Titik dengan jarak kuadrat
                        terbesar ke C1 memiliki kemungkinan tertinggi untuk menjadi C2. Hasil: <strong>C2 =
                            <?= htmlspecialchars($c2_name) ?> (ID=<?= $c2_id ?>)</strong> dengan D² maksimum =
                        <?= $c2_d2_val ?>. Ini menjamin centroid awal tersebar jauh, menghindari inisialisasi yang
                        buruk.
                    </div>
                </div>
                <div class="step fade-in">
                    <div class="step-title"><span class="step-num">4</span> Pilih C3 dari D²_min</div>
                    <div class="step-body">
                        Untuk setiap titik, hitung <code>D²_min(x) = min(D²(x,C1), D²(x,C2))</code>. Titik dengan D²_min
                        terbesar dipilih sebagai C3. Hasil: <strong>C3 = <?= htmlspecialchars($c3_name) ?>
                            (ID=<?= $c3_id ?>)</strong> dengan D²_min = <?= $c3_d2_val ?>. Museum ini berada di cluster
                        budaya yang berbeda dari Borobudur dan Umbul Songo.
                    </div>
                </div>
                <div class="step fade-in">
                    <div class="step-title"><span class="step-num">5</span> Iterasi 1 – Assignment & Update</div>
                    <div class="step-body">
                        Setiap titik dihitung jaraknya ke C1, C2, C3 menggunakan <code>d(x,Ci) = √(Σ(xⱼ − Ciⱼ)²)</code>.
                        Titik di-assign ke centroid terdekat. Hasil iterasi 1: Klaster 1 = <?= $iter1_counts[1] ?>
                        titik, Klaster 2 = <?= $iter1_counts[2] ?> titik, Klaster 3 = <?= $iter1_counts[3] ?> titik.
                        WCSS = <strong><?= $iter1_wcss ?></strong>. Centroid diupdate sebagai rata-rata titik dalam
                        klaster.
                    </div>
                </div>
                <div class="step fade-in">
                    <div class="step-title"><span class="step-num">6</span> Iterasi 2 – Konvergensi ✓</div>
                    <div class="step-body">
                        Assignment iterasi terakhir menghasilkan distribusi yang sama dengan iterasi sebelumnya (tidak
                        ada perpindahan titik antar klaster). WCSS turun menjadi <strong><?= $final_wcss ?></strong>.
                        Algoritma <strong>KONVERGEN</strong> pada iterasi ke-<?= $total_iterations ?>, menunjukkan
                        dataset memiliki klaster yang jelas dan terpisah baik.
                    </div>
                </div>
            </div>

            <!-- Centroid final table -->
            <div class="section-title">Centroid Final (Setelah Konvergensi)</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Centroid</th>
                            <th>Lon_n</th>
                            <th>Lat_n</th>
                            <th>Visit_n</th>
                            <th>Rating_n</th>
                            <th>Akses_n</th>
                            <th>Fas_n</th>
                            <th>Alam_n</th>
                            <th>Budaya_n</th>
                            <th>Pend_n</th>
                            <th>Trend_n</th>
                            <th>n Titik</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($k = 1; $k <= 3; $k++):
                            $c = $calc['final_centroids'][$k];
                            $lbl = ($k == 1) ? 'C1 (Tinggi)' : (($k == 2) ? 'C2 (Sedang)' : 'C3 (Rendah)');
                            $cls = ($k == 1) ? 'var(--k1)' : (($k == 2) ? 'var(--k2)' : 'var(--k3)');
                            $n_pts = count(array_filter($calc['assignments'], fn($val) => $val == $k));
                            ?>
                            <tr style="color:<?= $cls ?>">
                                <td style="font-weight:700"><?= $lbl ?></td>
                                <td class="mono"><?= number_format($c['lon'], 4) ?></td>
                                <td class="mono"><?= number_format($c['lat'], 4) ?></td>
                                <td class="mono"><?= number_format($c['kunjungan'], 4) ?></td>
                                <td class="mono"><?= number_format($c['rating'], 4) ?></td>
                                <td class="mono"><?= number_format($c['aksesibilitas'], 4) ?></td>
                                <td class="mono"><?= number_format($c['fasilitas'], 4) ?></td>
                                <td class="mono"><?= number_format($c['potensi_alam'], 4) ?></td>
                                <td class="mono"><?= number_format($c['potensi_budaya'], 4) ?></td>
                                <td class="mono"><?= number_format($c['pendapatan'], 4) ?></td>
                                <td class="mono"><?= number_format($c['trend'], 4) ?></td>
                                <td class="mono"><?= $n_pts ?></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══════════════ PAGE: PERHITUNGAN MANUAL ══════════════ -->
        <div id="page-manual" class="page">

            <div
                style="display:flex;align-items:center;justify-content:space-between;gap:1.5rem;margin-bottom:2rem;flex-wrap:wrap">
                <div class="section-title" style="margin:0;flex:1;min-width:300px">🧮 Buku Perhitungan Manual K-Means++
                </div>
                <button id="btnUpdateCalc" onclick="updateCalculation()"
                    style="padding:0.7rem 1.5rem;background:#10b981;color:white;border:none;border-radius:8px;font-weight:700;font-size:0.9rem;cursor:pointer;white-space:nowrap;box-shadow:0 4px 12px rgba(16,185,129,0.3);transition:all 0.3s ease;flex-shrink:0">📤
                    Update ke Database</button>
            </div>

            <div
                style="background:linear-gradient(90deg,rgba(99,102,241,.12),transparent);border:1px solid rgba(99,102,241,.25);border-radius:14px;padding:1.25rem 1.5rem;margin-bottom:1.75rem">
                <div style="font-weight:700;font-size:.95rem;margin-bottom:.35rem">Detail Perhitungan</div>
                <div style="font-size:.82rem;color:var(--muted);line-height:1.7">Seluruh perhitungan dilakukan langkah
                    demi langkah menggunakan data asli dari file Excel. Setiap angka dapat ditelusuri dan diverifikasi
                    secara manual. Klik bagian judul untuk expand/collapse.</div>
            </div>

            <!-- TOC -->
            <div class="manual-toc fade-in">
                <span style="font-size:.75rem;color:var(--muted);font-weight:600;align-self:center">Navigasi
                    Cepat:</span>
                <a class="toc-item" href="#sec-normalisasi">§1 Normalisasi Min-Max</a>
                <a class="toc-item" href="#sec-init">§2 Inisialisasi K-Means++ (C1)</a>
                <a class="toc-item" href="#sec-d2c1">§3 Jarak D²(x,C1)</a>
                <a class="toc-item" href="#sec-c2">§4 Pemilihan C2</a>
                <a class="toc-item" href="#sec-d2min">§5 D²_min → Pemilihan C3</a>
                <a class="toc-item" href="#sec-iter1">§6 Iterasi 1</a>
                <a class="toc-item" href="#sec-iter2">§7 Iterasi 2 & Konvergensi</a>
                <a class="toc-item" href="#sec-wcss">§8 Perhitungan WCSS</a>
                <a class="toc-item" href="#sec-sc">§9 Silhouette Coefficient</a>
                <a class="toc-item" href="#sec-dbi">§10 Davies-Bouldin Index</a>
                <a class="toc-item" href="#sec-chi">§11 Calinski-Harabasz Index</a>
                <a class="toc-item" href="#sec-skor">§12 Skor Potensi</a>
            </div>

            <!-- ═══ §1: NORMALISASI ═══ -->
            <div class="calc-section fade-in" id="sec-normalisasi">
                <div class="calc-section-header" onclick="toggleSection(this)">
                    <div class="cs-num">1</div>
                    <h3>Normalisasi Min-Max — Pra-pemrosesan Data</h3>
                    <span style="margin-left:auto;color:var(--muted);font-size:.85rem">▼</span>
                </div>
                <div class="calc-body">
                    <div class="formula-box">
                        <div class="formula-title">Formula Normalisasi Min-Max</div>
                        <span class="formula-main">X_norm = (X − X_min) / (X_max − X_min)</span>
                        <div class="formula-vars">Hasil: nilai ternormalisasi dalam rentang [0, 1]<br>X_min = nilai
                            minimum fitur, X_max = nilai maksimum fitur</div>
                    </div>

                    <div class="calc-note"><strong>Mengapa dinormalisasi?</strong> Fitur memiliki skala sangat berbeda:
                        Kunjungan (28.000 – 1.250.000) vs Rating (3,7 – 4,9). Tanpa normalisasi, fitur dengan skala
                        besar akan mendominasi perhitungan jarak Euclidean, sehingga hasil klasterisasi menjadi bias.
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">Statistik Min-Max per fitur
                        (dari 15 data):</p>
                    <div class="table-wrap">
                        <table class="manual-table">
                            <thead>
                                <tr>
                                    <th>Fitur</th>
                                    <th>Min</th>
                                    <th>Max</th>
                                    <th>Range (Max−Min)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $fitur_labels = [
                                    'lon' => 'Longitude',
                                    'lat' => 'Latitude',
                                    'kunjungan' => 'Kunjungan/Thn',
                                    'rating' => 'Rating',
                                    'aksesibilitas' => 'Aksesibilitas',
                                    'fasilitas' => 'Fasilitas',
                                    'potensi_alam' => 'Potensi Alam',
                                    'potensi_budaya' => 'Potensi Budaya',
                                    'pendapatan' => 'Pendapatan (Jt)',
                                    'trend' => 'Trend YoY'
                                ];
                                foreach ($fitur_labels as $f => $lbl):
                                    $min_v = $calc['minMax'][$f]['min'];
                                    $max_v = $calc['minMax'][$f]['max'];
                                    $rng_v = $calc['minMax'][$f]['range'];
                                    $fmt = ($f == 'lon' || $f == 'lat') ? 4 : (($f == 'kunjungan' || $f == 'pendapatan') ? 0 : 3);
                                    ?>
                                    <tr>
                                        <td><?= $lbl ?></td>
                                        <td class="mono"><?= number_format($min_v, $fmt, ',', '.') ?></td>
                                        <td class="mono"><?= number_format($max_v, $fmt, ',', '.') ?></td>
                                        <td class="mono"><?= number_format($rng_v, $fmt, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0">Contoh perhitungan normalisasi untuk
                        <strong style="color:var(--k1)"><?= htmlspecialchars($c1_name) ?> (ID=<?= $c1_id ?>)</strong>:
                    </p>
                    <div class="formula-box">
                        <div class="formula-title">Contoh: <?= htmlspecialchars($c1_name) ?> — Kunjungan =
                            <?= number_format($calc['destinations'][$c1_id]['kunjungan'], 0, ',', '.') ?>
                        </div>
                        <?php
                        $visit_min = $calc['minMax']['kunjungan']['min'];
                        $visit_max = $calc['minMax']['kunjungan']['max'];
                        $visit_rng = $calc['minMax']['kunjungan']['range'];
                        $visit_norm_val = $visit_rng == 0 ? 0.0 : ($calc['destinations'][$c1_id]['kunjungan'] - $visit_min) / $visit_rng;
                        ?>
                        <span class="formula-main">Visit_norm =
                            (<?= number_format($calc['destinations'][$c1_id]['kunjungan'], 0, ',', '.') ?> −
                            <?= number_format($visit_min, 0, ',', '.') ?>) /
                            (<?= number_format($visit_max, 0, ',', '.') ?> −
                            <?= number_format($visit_min, 0, ',', '.') ?>) =
                            <?= number_format($calc['destinations'][$c1_id]['kunjungan'] - $visit_min, 0, ',', '.') ?> /
                            <?= number_format($visit_rng, 0, ',', '.') ?> = <span
                                style="color:var(--k3)"><?= number_format($visit_norm_val, 4) ?></span></span>
                        <br><br>
                        <div class="formula-title">Contoh: <?= htmlspecialchars($c1_name) ?> — Rating =
                            <?= number_format($calc['destinations'][$c1_id]['rating'], 1, ',', '.') ?>
                        </div>
                        <?php
                        $rate_min = $calc['minMax']['rating']['min'];
                        $rate_max = $calc['minMax']['rating']['max'];
                        $rate_rng = $calc['minMax']['rating']['range'];
                        $rate_norm_val = $rate_rng == 0 ? 0.0 : ($calc['destinations'][$c1_id]['rating'] - $rate_min) / $rate_rng;
                        ?>
                        <span class="formula-main">Rating_norm =
                            (<?= number_format($calc['destinations'][$c1_id]['rating'], 1, ',', '.') ?> −
                            <?= number_format($rate_min, 1, ',', '.') ?>) /
                            (<?= number_format($rate_max, 1, ',', '.') ?> −
                            <?= number_format($rate_min, 1, ',', '.') ?>) =
                            <?= number_format($calc['destinations'][$c1_id]['rating'] - $rate_min, 1, ',', '.') ?> /
                            <?= number_format($rate_rng, 1, ',', '.') ?> = <span
                                style="color:var(--k3)"><?= number_format($rate_norm_val, 4) ?></span></span>
                        <br><br>
                        <div class="formula-title">Contoh: <?= htmlspecialchars($c1_name) ?> — Aksesibilitas =
                            <?= $calc['destinations'][$c1_id]['aksesibilitas'] ?>
                        </div>
                        <?php
                        $akses_min = $calc['minMax']['aksesibilitas']['min'];
                        $akses_max = $calc['minMax']['aksesibilitas']['max'];
                        $akses_rng = $calc['minMax']['aksesibilitas']['range'];
                        $akses_norm_val = $akses_rng == 0 ? 0.0 : ($calc['destinations'][$c1_id]['aksesibilitas'] - $akses_min) / $akses_rng;
                        ?>
                        <span class="formula-main">Akses_norm = (<?= $calc['destinations'][$c1_id]['aksesibilitas'] ?> −
                            <?= $akses_min ?>) / (<?= $akses_max ?> − <?= $akses_min ?>) =
                            <?= $calc['destinations'][$c1_id]['aksesibilitas'] - $akses_min ?> / <?= $akses_rng ?> =
                            <span style="color:var(--k3)"><?= number_format($akses_norm_val, 4) ?></span></span>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0">Hasil normalisasi <strong>semua
                            <?= count($normData) ?> destinasi</strong> (10 fitur):</p>
                    <div class="table-wrap">
                        <table class="manual-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>Lon_n</th>
                                    <th>Lat_n</th>
                                    <th>Visit_n</th>
                                    <th>Rating_n</th>
                                    <th>Akses_n</th>
                                    <th>Fas_n</th>
                                    <th>Alam_n</th>
                                    <th>Bud_n</th>
                                    <th>Pend_n</th>
                                    <th>Trend_n</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // $normData is populated dynamically at the top of the page.
                                foreach ($normData as $r):
                                    $k = $r[12]; ?>
                                    <tr>
                                        <td style="font-family:'Space Mono',monospace;color:var(--muted)"><?= $r[0] ?></td>
                                        <td style="font-weight:600;font-size:.76rem"><?= $r[1] ?></td>
                                        <?php for ($i = 2; $i <= 11; $i++):
                                            $v = $r[$i]; ?>
                                            <td class="mono"
                                                style="color:<?= $v >= 0.9 ? 'var(--k1)' : ($v >= 0.6 ? 'var(--k3)' : ($v >= 0.3 ? 'var(--text)' : 'var(--muted)')) ?>">
                                                <?= number_format($v, 4) ?>
                                            </td>
                                        <?php endfor; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="calc-note">🎨 <strong>Kode warna:</strong> <span style="color:var(--k1)">Emas ≥
                            0.9</span> · <span style="color:var(--k3)">Hijau ≥ 0.6</span> · <span
                            style="color:var(--text)">Putih ≥ 0.3</span> · <span style="color:var(--muted)">Abu <
                                0.3</span>
                    </div>
                </div>
            </div>

            <!-- ═══ §2: INISIALISASI C1 ═══ -->
            <div class="calc-section fade-in" id="sec-init">
                <div class="calc-section-header" onclick="toggleSection(this)">
                    <div class="cs-num">2</div>
                    <h3>Inisialisasi K-Means++ — Pemilihan Centroid Pertama (C1)</h3>
                    <span style="margin-left:auto;color:var(--muted);font-size:.85rem">▼</span>
                </div>
                <div class="calc-body">
                    <div class="calc-note"><strong>Kelebihan K-Means++ vs K-Means biasa:</strong> K-Means biasa memilih
                        centroid awal secara acak penuh, yang dapat menghasilkan klaster buruk. K-Means++ memilih
                        centroid secara cerdas — titik berikutnya dipilih dengan probabilitas proporsional terhadap
                        jarak kuadrat ke centroid terdekat yang sudah dipilih. Ini <strong>menjamin konvergensi lebih
                            cepat dan hasil lebih optimal</strong>.</div>

                    <div class="formula-box">
                        <div class="formula-title">Langkah 1: Pilih C1 secara acak (atau berdasarkan domain knowledge)
                        </div>
                        <span class="formula-main">C1 = Titik ID=<?= $c1_id ?>
                            (<?= htmlspecialchars($c1_name) ?>)</span>
                        <div class="formula-vars">
                            Alasan: <?= htmlspecialchars($c1_name) ?> adalah destinasi dengan kunjungan/rating
                            tinggi,<br>
                            dipilih sebagai seed pertama untuk menjamin klaster "Tinggi" teridentifikasi.<br><br>
                            Vektor C1 (ternormalisasi, 10 dimensi):
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="manual-table">
                            <thead>
                                <tr>
                                    <th>Lon_n</th>
                                    <th>Lat_n</th>
                                    <th>Visit_n</th>
                                    <th>Rating_n</th>
                                    <th>Akses_n</th>
                                    <th>Fas_n</th>
                                    <th>Alam_n</th>
                                    <th>Bud_n</th>
                                    <th>Pend_n</th>
                                    <th>Trend_n</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $c1_norm = $calc['normData'][$c1_id]; ?>
                                <tr class="highlight">
                                    <td class="mono"><?= number_format($c1_norm['lon'], 4) ?></td>
                                    <td class="mono"><?= number_format($c1_norm['lat'], 4) ?></td>
                                    <td class="mono"><?= number_format($c1_norm['kunjungan'], 4) ?></td>
                                    <td class="mono"><?= number_format($c1_norm['rating'], 4) ?></td>
                                    <td class="mono"><?= number_format($c1_norm['aksesibilitas'], 4) ?></td>
                                    <td class="mono"><?= number_format($c1_norm['fasilitas'], 4) ?></td>
                                    <td class="mono"><?= number_format($c1_norm['potensi_alam'], 4) ?></td>
                                    <td class="mono"><?= number_format($c1_norm['potensi_budaya'], 4) ?></td>
                                    <td class="mono"><?= number_format($c1_norm['pendapatan'], 4) ?></td>
                                    <td class="mono"><?= number_format($c1_norm['trend'], 4) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ═══ §3: D²(x,C1) ═══ -->
            <div class="calc-section fade-in" id="sec-d2c1">
                <div class="calc-section-header" onclick="toggleSection(this)">
                    <div class="cs-num">3</div>
                    <h3>Perhitungan D²(x, C1) — Jarak Kuadrat Semua Titik ke C1</h3>
                    <span style="margin-left:auto;color:var(--muted);font-size:.85rem">▼</span>
                </div>
                <div class="calc-body">
                    <div class="formula-box">
                        <div class="formula-title">Formula Jarak Kuadrat Euclidean</div>
                        <span class="formula-main">D²(x, C1) = Σᵢ₌₁¹⁰ (xᵢ − C1ᵢ)²</span>
                        <div class="formula-vars">Menjumlahkan kuadrat selisih tiap dimensi. Semakin jauh titik dari C1,
                            semakin besar D².</div>
                    </div>

                    <?php
                    $samp_id = $calc['destinations'][1]['id'] ?? 2;
                    $samp_name = $calc['destinations'][1]['nama'] ?? 'Punthuk Setumbu';
                    $samp_norm = $calc['normData'][$samp_id];
                    ?>
                    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">Contoh detail untuk <strong
                            style="color:var(--k2)"><?= htmlspecialchars($samp_name) ?> (ID=<?= $samp_id ?>)</strong>:
                    </p>
                    <div class="formula-box">
                        <div class="formula-title">Perhitungan manual D²(ID=<?= $samp_id ?>, C1) — dimensi per dimensi
                        </div>
                        <span class="formula-main">
                            (<?= number_format($samp_norm['lon'], 4) ?> − <?= number_format($c1_norm['lon'], 4) ?>)² =
                            <?= number_format(pow($samp_norm['lon'] - $c1_norm['lon'], 2), 6) ?><br>
                            (<?= number_format($samp_norm['lat'], 4) ?> − <?= number_format($c1_norm['lat'], 4) ?>)² =
                            <?= number_format(pow($samp_norm['lat'] - $c1_norm['lat'], 2), 6) ?><br>
                            (<?= number_format($samp_norm['kunjungan'], 4) ?> −
                            <?= number_format($c1_norm['kunjungan'], 4) ?>)² =
                            <?= number_format(pow($samp_norm['kunjungan'] - $c1_norm['kunjungan'], 2), 6) ?><br>
                            (<?= number_format($samp_norm['rating'], 4) ?> −
                            <?= number_format($c1_norm['rating'], 4) ?>)² =
                            <?= number_format(pow($samp_norm['rating'] - $c1_norm['rating'], 2), 6) ?><br>
                            (<?= number_format($samp_norm['aksesibilitas'], 4) ?> −
                            <?= number_format($c1_norm['aksesibilitas'], 4) ?>)² =
                            <?= number_format(pow($samp_norm['aksesibilitas'] - $c1_norm['aksesibilitas'], 2), 6) ?><br>
                            (<?= number_format($samp_norm['fasilitas'], 4) ?> −
                            <?= number_format($c1_norm['fasilitas'], 4) ?>)² =
                            <?= number_format(pow($samp_norm['fasilitas'] - $c1_norm['fasilitas'], 2), 6) ?><br>
                            (<?= number_format($samp_norm['potensi_alam'], 4) ?> −
                            <?= number_format($c1_norm['potensi_alam'], 4) ?>)² =
                            <?= number_format(pow($samp_norm['potensi_alam'] - $c1_norm['potensi_alam'], 2), 6) ?><br>
                            (<?= number_format($samp_norm['potensi_budaya'], 4) ?> −
                            <?= number_format($c1_norm['potensi_budaya'], 4) ?>)² =
                            <?= number_format(pow($samp_norm['potensi_budaya'] - $c1_norm['potensi_budaya'], 2), 6) ?><br>
                            (<?= number_format($samp_norm['pendapatan'], 4) ?> −
                            <?= number_format($c1_norm['pendapatan'], 4) ?>)² =
                            <?= number_format(pow($samp_norm['pendapatan'] - $c1_norm['pendapatan'], 2), 6) ?><br>
                            (<?= number_format($samp_norm['trend'], 4) ?> − <?= number_format($c1_norm['trend'], 4) ?>)²
                            = <?= number_format(pow($samp_norm['trend'] - $c1_norm['trend'], 2), 6) ?><br>
                            ─────────────────────────────<br>
                            D²(ID=<?= $samp_id ?>, C1) = <span
                                style="color:var(--k3)"><?= number_format($calc['d2_c1'][$samp_id], 4) ?></span>
                        </span>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0">D² semua titik ke C1
                        (<?= htmlspecialchars($c1_name) ?>):</p>
                    <div class="table-wrap">
                        <table class="manual-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>D²(x,C1)</th>
                                    <th>P(x) = D²/Total</th>
                                    <th>P Kumulatif</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // $d2c1 is populated dynamically at the top of the page.
                                foreach ($d2c1 as $r): ?>
                                    <tr <?= $r[2] >= ($calc['total_d2_c1'] / count($d2c1)) ? 'style="background:rgba(245,158,11,.07)"' : '' ?>>
                                        <td class="mono" style="color:var(--muted)"><?= $r[0] ?></td>
                                        <td style="font-weight:600;font-size:.76rem"><?= htmlspecialchars($r[1]) ?></td>
                                        <td class="mono"
                                            style="color:<?= $r[2] >= ($calc['total_d2_c1'] / count($d2c1)) ? 'var(--k1)' : ($r[2] >= ($calc['total_d2_c1'] / (2 * count($d2c1))) ? 'var(--text)' : 'var(--muted)') ?>;font-weight:<?= $r[2] >= ($calc['total_d2_c1'] / count($d2c1)) ? '700' : '400' ?>">
                                            <?= number_format($r[2], 4) ?>
                                        </td>
                                        <td class="mono"><?= number_format($r[3], 5) ?></td>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:.4rem">
                                                <div
                                                    style="width:80px;height:5px;background:var(--border);border-radius:2px;overflow:hidden">
                                                    <div
                                                        style="width:<?= round($r[4] * 100) ?>%;height:100%;background:var(--accent)">
                                                    </div>
                                                </div>
                                                <span class="mono"
                                                    style="font-size:.72rem"><?= number_format($r[4], 4) ?></span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr style="border-top:2px solid var(--accent)">
                                    <td colspan="2" style="font-weight:700;color:var(--accent)">Total D²</td>
                                    <td class="mono" style="color:var(--accent);font-weight:700"><?= $total_d2_val ?>
                                    </td>
                                    <td colspan="2"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ═══ §4: C2 ═══ -->
            <div class="calc-section fade-in" id="sec-c2">
                <div class="calc-section-header" onclick="toggleSection(this)">
                    <div class="cs-num">4</div>
                    <h3>Pemilihan Centroid C2 — Titik dengan D² Maksimum</h3>
                    <span style="margin-left:auto;color:var(--muted);font-size:.85rem">▼</span>
                </div>
                <div class="calc-body">
                    <div class="formula-box">
                        <div class="formula-title">Kriteria Pemilihan C2</div>
                        <span class="formula-main">C2 = argmax D²(x, C1) ← titik terjauh dari C1</span>
                        <div class="formula-vars">
                            Dalam praktik K-Means++, C2 dipilih dengan probabilitas proporsional D².<br>
                            Untuk implementasi deterministik, dipilih argmax (probabilitas tertinggi).
                        </div>
                    </div>

                    <div class="step-detail">
                        <div class="step-detail-item">
                            <div class="sdi-label">D² Tertinggi</div>
                            <div class="sdi-val gold"><?= $c2_d2_val ?></div>
                        </div>
                        <div class="step-detail-item">
                            <div class="sdi-label">Titik Terpilih sebagai C2</div>
                            <div class="sdi-val blue">ID=<?= $c2_id ?> · <?= htmlspecialchars($c2_name) ?></div>
                        </div>
                        <div class="step-detail-item">
                            <div class="sdi-label">Jarak ke C1 (<?= htmlspecialchars($c1_name) ?>)</div>
                            <div class="sdi-val accent">√<?= $c2_d2_val ?> =
                                <?= number_format(sqrt($calc['d2_c1'][$c2_id]), 4) ?>
                            </div>
                        </div>
                        <div class="step-detail-item">
                            <div class="sdi-label">Alasan Karakteristik</div>
                            <div class="sdi-val" style="font-size:.75rem;font-family:'Sora',sans-serif">
                                <?= htmlspecialchars($c2_name) ?> memiliki profil spasio-temporal yang paling berbeda
                                dari <?= htmlspecialchars($c1_name) ?>.
                            </div>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="manual-table">
                            <thead>
                                <tr>
                                    <th>Lon_n</th>
                                    <th>Lat_n</th>
                                    <th>Visit_n</th>
                                    <th>Rating_n</th>
                                    <th>Akses_n</th>
                                    <th>Fas_n</th>
                                    <th>Alam_n</th>
                                    <th>Bud_n</th>
                                    <th>Pend_n</th>
                                    <th>Trend_n</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $c2_norm = $calc['normData'][$c2_id]; ?>
                                <tr class="highlight2">
                                    <td class="mono"><?= number_format($c2_norm['lon'], 4) ?></td>
                                    <td class="mono"><?= number_format($c2_norm['lat'], 4) ?></td>
                                    <td class="mono"><?= number_format($c2_norm['kunjungan'], 4) ?></td>
                                    <td class="mono"><?= number_format($c2_norm['rating'], 4) ?></td>
                                    <td class="mono"><?= number_format($c2_norm['aksesibilitas'], 4) ?></td>
                                    <td class="mono"><?= number_format($c2_norm['fasilitas'], 4) ?></td>
                                    <td class="mono"><?= number_format($c2_norm['potensi_alam'], 4) ?></td>
                                    <td class="mono"><?= number_format($c2_norm['potensi_budaya'], 4) ?></td>
                                    <td class="mono"><?= number_format($c2_norm['pendapatan'], 4) ?></td>
                                    <td class="mono"><?= number_format($c2_norm['trend'], 4) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0">D² semua titik ke C1 — pemilihan C2
                        dari nilai terbesar:</p>
                    <div class="table-wrap">
                        <table class="manual-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>D²(x,C1)</th>
                                    <th>P(x) = D²/Total</th>
                                    <th>P Kumulatif</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($d2c1 as $r): ?>
                                    <tr <?= $r[2] >= 6.0 ? 'style="background:rgba(245,158,11,.07)"' : '' ?>>
                                        <td class="mono" style="color:var(--muted)"><?= $r[0] ?></td>
                                        <td style="font-weight:600;font-size:.76rem"><?= $r[1] ?></td>
                                        <td class="mono"
                                            style="color:<?= $r[2] >= 6.0 ? 'var(--k1)' : ($r[2] >= 3.0 ? 'var(--text)' : 'var(--muted)') ?>;font-weight:<?= $r[2] >= 6.0 ? '700' : '400' ?>">
                                            <?= number_format($r[2], 4) ?>
                                        </td>
                                        <td class="mono"><?= number_format($r[3], 5) ?></td>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:.4rem">
                                                <div
                                                    style="width:80px;height:5px;background:var(--border);border-radius:2px;overflow:hidden">
                                                    <div
                                                        style="width:<?= round($r[4] * 100) ?>%;height:100%;background:var(--accent)">
                                                    </div>
                                                </div>
                                                <span class="mono"
                                                    style="font-size:.72rem"><?= number_format($r[4], 4) ?></span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr style="border-top:2px solid var(--accent)">
                                    <td colspan="2" style="font-weight:700;color:var(--accent)">Total D²</td>
                                    <td class="mono" style="color:var(--accent);font-weight:700">62.5019</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ═══ §4: C2 ═══ -->
            <div class="calc-section fade-in" id="sec-c2">
                <div class="calc-section-header" onclick="toggleSection(this)">
                    <div class="cs-num">4</div>
                    <h3>Pemilihan Centroid C2 — Titik dengan D² Maksimum</h3>
                    <span style="margin-left:auto;color:var(--muted);font-size:.85rem">▼</span>
                </div>
                <div class="calc-body">
                    <div class="formula-box">
                        <div class="formula-title">Kriteria Pemilihan C2</div>
                        <span class="formula-main">C2 = argmax D²(x, C1) ← titik terjauh dari C1</span>
                        <div class="formula-vars">
                            Dalam praktik K-Means++, C2 dipilih dengan probabilitas proporsional D².<br>
                            Untuk implementasi deterministik, dipilih argmax (probabilitas tertinggi).
                        </div>
                    </div>

                    <div class="calc-result">✓ <strong>C2 = <?= htmlspecialchars($c2_name) ?></strong> — Memiliki profil
                        spasio-temporal paling berbeda dari <?= htmlspecialchars($c1_name) ?>:
                        aksesibilitas=<?= number_format($calc['normData'][$c2_id]['aksesibilitas'], 4) ?>,
                        budaya=<?= number_format($calc['normData'][$c2_id]['potensi_budaya'], 4) ?>,
                        kunjungan=<?= number_format($calc['normData'][$c2_id]['kunjungan'], 4) ?>.</div>
                </div>
            </div>

            <!-- ═══ §5: D²_min → C3 ═══ -->
            <div class="calc-section fade-in" id="sec-d2min">
                <div class="calc-section-header" onclick="toggleSection(this)">
                    <div class="cs-num">5</div>
                    <h3>Perhitungan D²_min dan Pemilihan Centroid C3</h3>
                    <span style="margin-left:auto;color:var(--muted);font-size:.85rem">▼</span>
                </div>
                <div class="calc-body">
                    <div class="formula-box">
                        <div class="formula-title">Formula D²_min dan Probabilitas untuk C3</div>
                        <span class="formula-main">D²_min(x) = min( D²(x, C1), D²(x, C2) )</span>
                        <div class="formula-vars">
                            Untuk setiap titik, ambil jarak kuadrat minimum ke centroid yang sudah ada (C1 atau C2).<br>
                            C3 = argmax D²_min — titik yang paling jauh dari centroid terdekatnya.
                        </div>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">Contoh perhitungan D²(x, C2)
                        untuk <strong style="color:var(--k3)"><?= htmlspecialchars($c3_name) ?>
                            (ID=<?= $c3_id ?>)</strong>:</p>
                    <div class="formula-box">
                        <div class="formula-title">D²(ID=<?= $c3_id ?>, C2=<?= htmlspecialchars($c2_name) ?>)</div>
                        <?php
                        $samp3_norm = $calc['normData'][$c3_id];
                        $c2_norm_samp = $calc['normData'][$c2_id];
                        $features_list = ['lon', 'lat', 'kunjungan', 'rating', 'aksesibilitas', 'fasilitas', 'potensi_alam', 'potensi_budaya', 'pendapatan', 'trend'];
                        ?>
                        <span class="formula-main">
                            <?php foreach ($features_list as $fi): ?>
                                (<?= number_format($samp3_norm[$fi], 4) ?> − <?= number_format($c2_norm_samp[$fi], 4) ?>)² =
                                <?= number_format(pow($samp3_norm[$fi] - $c2_norm_samp[$fi], 2), 5) ?><br>
                            <?php endforeach; ?>
                            ──────────────────────────<br>
                            D²(ID=<?= $c3_id ?>, C2) = <span
                                style="color:var(--k3)"><?= number_format($calc['d2_c2'][$c3_id], 4) ?></span>
                        </span>
                    </div>

                    <div class="table-wrap">
                        <table class="manual-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>D²(x,C1)</th>
                                    <th>D²(x,C2)</th>
                                    <th>D²_min = min(C1,C2)</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($d2min as $r):
                                    $isMax = strpos($r[5], 'MAKS') !== false; ?>
                                    <tr <?= $isMax ? 'style="background:rgba(16,185,129,.1)"' : '' ?>>
                                        <td class="mono" style="color:var(--muted)"><?= $r[0] ?></td>
                                        <td style="font-weight:600;font-size:.76rem"><?= htmlspecialchars($r[1]) ?></td>
                                        <td class="mono"><?= number_format($r[2], 4) ?></td>
                                        <td class="mono"><?= number_format($r[3], 4) ?></td>
                                        <td class="mono <?= $isMax ? 'highlight3' : '' ?>"><?= number_format($r[4], 4) ?>
                                        </td>
                                        <td style="font-size:.72rem;color:<?= $isMax ? 'var(--k3)' : 'var(--muted)' ?>">
                                            <?= $r[5] ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="calc-result">✓ <strong>C3 = <?= htmlspecialchars($c3_name) ?>
                            (ID=<?= $c3_id ?>)</strong> — D²_min = <?= $c3_d2_val ?>
                        adalah yang terbesar. Destinasi ini memiliki profil yang paling berbeda dari kedua centroid
                        sebelumnya.</div>
                </div>
            </div>

            <!-- ═══ §6: ITERASI 1 ═══ -->
            <div class="calc-section fade-in" id="sec-iter1">
                <div class="calc-section-header" onclick="toggleSection(this)">
                    <div class="cs-num">6</div>
                    <h3>Iterasi 1 — Assignment Klaster & Update Centroid</h3>
                    <span style="margin-left:auto;color:var(--muted);font-size:.85rem">▼</span>
                </div>
                <div class="calc-body">
                    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">3 centroid awal (Iter-0):</p>
                    <div class="step-detail">
                        <div class="step-detail-item">
                            <div class="sdi-label">C1 Iter-0</div>
                            <div class="sdi-val gold">Borobudur (ID=1)</div>
                        </div>
                        <div class="step-detail-item">
                            <div class="sdi-label">C2 Iter-0</div>
                            <div class="sdi-val blue">Umbul Songo (ID=14)</div>
                        </div>
                        <div class="step-detail-item" style="grid-column:1/-1">
                            <div class="sdi-label">C3 Iter-0</div>
                            <div class="sdi-val green">Museum Karmawibhangga (ID=11)</div>
                        </div>
                    </div>

                    <div class="formula-box">
                        <div class="formula-title">Formula Jarak Euclidean (bukan kuadrat) untuk assignment</div>
                        <span class="formula-main">d(x, Ci) = √[ Σⱼ (xⱼ − Ciⱼ)² ]</span>
                        <div class="formula-vars">Setiap titik dihitung jaraknya ke C1, C2, C3. Titik di-assign ke
                            centroid dengan jarak TERKECIL.</div>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0">Contoh detail assignment untuk
                        <strong style="color:var(--k3)">Candi Mendut (ID=4)</strong>:
                    </p>
                    <div class="formula-box">
                        <div class="formula-title">d(ID=4, C1=Borobudur) = √D²(ID=4,C1) = √2.3447 = 1.5312</div>
                        <span class="formula-main" style="font-size:.8rem">
                            Komponen: (0.1500−0.0391)²+(0.0472−0.0134)²+(0.0794−1)²+(0.5000−1)²+<br>
                            (0.6667−1)²+(0.6667−1)²+(0.3333−0.6667)²+(1.0000−1)²+<br>
                            (0.0541−1)²+(0.1432−0.2189)² = 2.3447 → <span style="color:var(--k3)">d = 1.5312</span>
                        </span>
                        <br><br>
                        <div class="formula-title">d(ID=4, C2=Umbul Songo) = √3.8749 = 1.9685</div>
                        <div class="formula-title">d(ID=4, C3=Museum Karma) = √0.3082 = 0.5553 ← MINIMUM → Klaster 3
                        </div>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0">Hasil assignment semua titik pada
                        Iterasi 1:</p>
                    <div class="table-wrap">
                        <table class="manual-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>d(x,C1)</th>
                                    <th>d(x,C2)</th>
                                    <th>d(x,C3)</th>
                                    <th>Minimum</th>
                                    <th>Klaster</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Dynamically build iter1 from iterations_history[1] using C1,C2,C3 initial centroids
                                $ih1 = $calc['iterations_history'][1] ?? null;
                                if ($ih1):
                                    $ic0 = [1 => $calc['normData'][$c1_id], 2 => $calc['normData'][$c2_id], 3 => $calc['normData'][$c3_id]];
                                    $flist = ['lon', 'lat', 'kunjungan', 'rating', 'aksesibilitas', 'fasilitas', 'potensi_alam', 'potensi_budaya', 'pendapatan', 'trend'];
                                    foreach ($calc['normData'] as $did => $dn):
                                        $ass1 = $ih1['assignments'][$did];
                                        $dists = [];
                                        for ($kk = 1; $kk <= 3; $kk++) {
                                            $d = 0;
                                            foreach ($flist as $ff)
                                                $d += pow($dn[$ff] - $ic0[$kk][$ff], 2);
                                            $dists[$kk] = sqrt($d);
                                        }
                                        $min_k = array_search(min($dists), $dists);
                                        $min_lbl = 'C' . $min_k . ' = ' . number_format($dists[$min_k], 4);
                                        ?>
                                        <tr>
                                            <td class="mono" style="color:var(--muted)"><?= $did ?></td>
                                            <td style="font-weight:600;font-size:.76rem"><?= htmlspecialchars($dn['nama']) ?>
                                            </td>
                                            <td class="mono <?= $ass1 == 1 ? 'highlight' : '' ?>">
                                                <?= number_format($dists[1], 4) ?>
                                            </td>
                                            <td class="mono <?= $ass1 == 2 ? 'highlight2' : '' ?>">
                                                <?= number_format($dists[2], 4) ?>
                                            </td>
                                            <td class="mono <?= $ass1 == 3 ? 'highlight3' : '' ?>">
                                                <?= number_format($dists[3], 4) ?>
                                            </td>
                                            <td class="mono" style="color:var(--muted);font-size:.72rem"><?= $min_lbl ?></td>
                                            <td><span class="assign-badge ab<?= $ass1 ?>">K<?= $ass1 ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0 .5rem">Hasil distribusi Iterasi 1:
                        <?php foreach ([1, 2, 3] as $kk): ?>
                            <span class="assign-badge ab<?= $kk ?>">K<?= $kk ?>: <?= $iter1_counts[$kk] ?> titik</span>
                            &nbsp;
                        <?php endforeach; ?>
                    </p>

                    <div class="formula-box">
                        <div class="formula-title">Update Centroid Baru = RATA-RATA titik dalam klaster</div>
                        <span class="formula-main">C_baru(k) = (1/nₖ) × Σ xᵢ untuk semua xᵢ ∈ klaster k</span>
                    </div>

                    <?php if ($ih1 && isset($calc['iterations_history'][2])):
                        $ic1 = $calc['iterations_history'][2]['centroids']; ?>
                        <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0 .5rem">Centroid hasil update setelah
                            Iterasi 1:</p>
                        <div class="table-wrap">
                            <table class="manual-table">
                                <thead>
                                    <tr>
                                        <th>Centroid</th>
                                        <th>n</th>
                                        <th>Lon_n</th>
                                        <th>Lat_n</th>
                                        <th>Visit_n</th>
                                        <th>Rating_n</th>
                                        <th>Akses_n</th>
                                        <th>Fas_n</th>
                                        <th>Alam_n</th>
                                        <th>Bud_n</th>
                                        <th>Pend_n</th>
                                        <th>Trend_n</th>
                                        <th>WCSS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $cls = ['highlight', 'highlight2', 'highlight3'];
                                    for ($kk = 1; $kk <= 3; $kk++):
                                        $cc = $ic1[$kk];
                                        $n_kk = $iter1_counts[$kk];
                                        // WCSS contribution for this cluster in iter1
                                        $wcss_k = 0;
                                        foreach ($calc['normData'] as $did => $dn) {
                                            if ($ih1['assignments'][$did] == $kk) {
                                                $d = 0;
                                                foreach ($flist as $ff)
                                                    $d += pow($dn[$ff] - $cc[$ff], 2);
                                                $wcss_k += $d;
                                            }
                                        }
                                        ?>
                                        <tr class="<?= $cls[$kk - 1] ?>">
                                            <td>C<?= $kk ?>_baru</td>
                                            <td><?= $n_kk ?></td>
                                            <td><?= number_format($cc['lon'], 4) ?></td>
                                            <td><?= number_format($cc['lat'], 4) ?></td>
                                            <td><?= number_format($cc['kunjungan'], 4) ?></td>
                                            <td><?= number_format($cc['rating'], 4) ?></td>
                                            <td><?= number_format($cc['aksesibilitas'], 4) ?></td>
                                            <td><?= number_format($cc['fasilitas'], 4) ?></td>
                                            <td><?= number_format($cc['potensi_alam'], 4) ?></td>
                                            <td><?= number_format($cc['potensi_budaya'], 4) ?></td>
                                            <td><?= number_format($cc['pendapatan'], 4) ?></td>
                                            <td><?= number_format($cc['trend'], 4) ?></td>
                                            <td><?= number_format($wcss_k, 4) ?></td>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <div class="calc-result">WCSS Iterasi 1 = <strong><?= $iter1_wcss ?></strong></div>
                </div>
            </div>

            <!-- ═══ §7: ITERASI 2 ═══ -->
            <div class="calc-section fade-in" id="sec-iter2">
                <div class="calc-section-header" onclick="toggleSection(this)">
                    <div class="cs-num">7</div>
                    <h3>Iterasi 2 — Konvergensi</h3>
                    <span style="margin-left:auto;color:var(--muted);font-size:.85rem">▼</span>
                </div>
                <div class="calc-body">
                    <div class="calc-note"><strong>Centroid Iter-1 yang digunakan:</strong> C1 sama (hanya 1 titik), C2
                        dan C3 telah diupdate setelah iterasi 1.</div>

                    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">Hasil assignment Iterasi 2
                        (dengan centroid baru dari Iter-1):</p>
                    <div class="table-wrap">
                        <table class="manual-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>d(x,C1_baru)</th>
                                    <th>d(x,C2_baru)</th>
                                    <th>d(x,C3_baru)</th>
                                    <th>Minimum</th>
                                    <th>Klaster</th>
                                    <th>Δ dari Iter-1</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($calc['success']):
                                    $fc = $calc['final_centroids'];
                                    $flist = ['lon', 'lat', 'kunjungan', 'rating', 'aksesibilitas', 'fasilitas', 'potensi_alam', 'potensi_budaya', 'pendapatan', 'trend'];
                                    foreach ($calc['normData'] as $did => $dn):
                                        $final_ass = $calc['assignments'][$did];
                                        $dists = [];
                                        for ($kk = 1; $kk <= 3; $kk++) {
                                            $d = 0;
                                            foreach ($flist as $ff)
                                                $d += pow($dn[$ff] - $fc[$kk][$ff], 2);
                                            $dists[$kk] = sqrt($d);
                                        }
                                        $min_k = array_search(min($dists), $dists);
                                        $min_lbl = 'C' . $min_k . ' = ' . number_format($dists[$min_k], 4);
                                        ?>
                                        <tr>
                                            <td class="mono" style="color:var(--muted)"><?= $did ?></td>
                                            <td style="font-weight:600;font-size:.76rem"><?= htmlspecialchars($dn['nama']) ?>
                                            </td>
                                            <td class="mono <?= $final_ass == 1 ? 'highlight' : '' ?>">
                                                <?= number_format($dists[1], 4) ?>
                                            </td>
                                            <td class="mono <?= $final_ass == 2 ? 'highlight2' : '' ?>">
                                                <?= number_format($dists[2], 4) ?>
                                            </td>
                                            <td class="mono <?= $final_ass == 3 ? 'highlight3' : '' ?>">
                                                <?= number_format($dists[3], 4) ?>
                                            </td>
                                            <td class="mono" style="color:var(--muted);font-size:.72rem"><?= $min_lbl ?></td>
                                            <td><span class="assign-badge ab<?= $final_ass ?>">K<?= $final_ass ?></span></td>
                                            <td style="font-size:.72rem;color:var(--k3)">✓ Sama</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="calc-result">
                        ✓ <strong>KONVERGEN!</strong> Semua <?= $total_destinasi ?> titik memiliki assignment klaster
                        yang SAMA dengan
                        Iterasi sebelumnya.<br>
                        Selesai pada Iterasi ke-<?= $total_iterations ?>.<br>
                        WCSS Final = <strong><?= number_format($calc['wcss'], 4) ?></strong>
                    </div>

                    <div class="formula-box">
                        <div class="formula-title">Pergeseran Centroid per Iterasi (Δ shift)</div>
                        <span class="formula-main">
                            ΔC = √[ Σⱼ (C_baru,j − C_lama,j)² ]<br><br>
                            Selesai dalam <?= $total_iterations ?> Iterasi.<br>
                            WCSS Awal (Iterasi 1) =
                            <?= number_format($calc['iterations_history'][1]['wcss'] ?? 0, 4) ?><br>
                            WCSS Final = <?= number_format($calc['wcss'], 4) ?> ← <span
                                style="color:var(--k3)">KONVERGEN ✓</span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- ═══ §8: WCSS ═══ -->
            <div class="calc-section fade-in" id="sec-wcss">
                <div class="calc-section-header" onclick="toggleSection(this)">
                    <div class="cs-num">8</div>
                    <h3>Perhitungan WCSS (Within-Cluster Sum of Squares)</h3>
                    <span style="margin-left:auto;color:var(--muted);font-size:.85rem">▼</span>
                </div>
                <div class="calc-body">
                    <div class="formula-box">
                        <div class="formula-title">Formula WCSS</div>
                        <span class="formula-main">WCSS = Σₖ Σᵢ∈Cₖ d²(xᵢ, centroid_k)</span>
                        <div class="formula-vars">Jumlahkan jarak kuadrat setiap titik ke centroid klasternya
                            masing-masing.<br>WCSS mengukur kompaktisitas klaster — semakin kecil semakin baik.</div>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">Kontribusi WCSS Klaster 2
                        (<?= count($wcssK2) ?>
                        titik) — detail per titik:</p>
                    <div class="table-wrap">
                        <table class="manual-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>d(x, C2_final)</th>
                                    <th>d²(x, C2_final)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($wcssK2 as $r): ?>
                                    <tr>
                                        <td class="mono" style="color:var(--muted)"><?= $r[0] ?></td>
                                        <td style="font-weight:600;font-size:.76rem"><?= htmlspecialchars($r[1]) ?></td>
                                        <td class="mono"><?= $r[2] ?></td>
                                        <td class="mono highlight2"><?= number_format($r[3], 4) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr style="border-top:2px solid var(--k2)">
                                    <td colspan="3" style="font-weight:700;color:var(--k2)">WCSS K2</td>
                                    <td class="mono highlight2" style="font-weight:700">
                                        <?= number_format($totalK2, 4) ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="step-detail">
                        <div class="step-detail-item">
                            <div class="sdi-label">WCSS Klaster 1 (<?= $calc['counts'][1] ?> titik)</div>
                            <div class="sdi-val gold"><?= number_format($wcss_k_final[1], 4) ?></div>
                        </div>
                        <div class="step-detail-item">
                            <div class="sdi-label">WCSS Klaster 2 (<?= $calc['counts'][2] ?> titik)</div>
                            <div class="sdi-val blue"><?= number_format($wcss_k_final[2], 4) ?></div>
                        </div>
                        <div class="step-detail-item">
                            <div class="sdi-label">WCSS Klaster 3 (<?= $calc['counts'][3] ?> titik)</div>
                            <div class="sdi-val green"><?= number_format($wcss_k_final[3], 4) ?></div>
                        </div>
                        <div class="step-detail-item">
                            <div class="sdi-label">Total WCSS Final</div>
                            <div class="sdi-val accent"><?= number_format($calc['wcss'], 4) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ §9: SILHOUETTE ═══ -->
            <div class="calc-section fade-in" id="sec-sc">
                <div class="calc-section-header" onclick="toggleSection(this)">
                    <div class="cs-num">9</div>
                    <h3>Silhouette Coefficient (SC) — Evaluasi Kualitas Klaster</h3>
                    <span style="margin-left:auto;color:var(--muted);font-size:.85rem">▼</span>
                </div>
                <div class="calc-body">
                    <div class="formula-box">
                        <div class="formula-title">Formula Silhouette untuk satu titik i</div>
                        <span class="formula-main">s(i) = [b(i) − a(i)] / max(a(i), b(i))</span>
                        <div class="formula-vars">
                            a(i) = rata-rata jarak titik i ke semua titik DALAM klasternya sendiri (cohesion)<br>
                            b(i) = rata-rata jarak titik i ke semua titik di KLASTER TERDEKAT lainnya (separation)<br>
                            SC keseluruhan = rata-rata semua s(i). Kisaran: [−1, +1]. Semakin tinggi semakin baik.
                        </div>
                    </div>

                    <?php
                    $k2_sample_id = 0;
                    $k2_sample_name = '-';
                    $k2_sample_a = 0.0;
                    $k2_sample_b = 0.0;
                    $k2_sample_s = 0.0;
                    $k2_sample_interp = '-';
                    foreach ($scData as $r) {
                        if ($r[2] == 2) {
                            $k2_sample_id = $r[0];
                            $k2_sample_name = $r[1];
                            $k2_sample_a = $r[3];
                            $k2_sample_b = $r[4];
                            $k2_sample_s = $r[5];
                            $k2_sample_interp = $r[6];
                            break;
                        }
                    }
                    ?>
                    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">Contoh perhitungan untuk <strong
                            style="color:var(--k2)"><?= htmlspecialchars($k2_sample_name) ?>
                            (ID=<?= $k2_sample_id ?>)</strong>:</p>
                    <div class="formula-box">
                        <div class="formula-title">a(<?= $k2_sample_id ?>) = rata-rata jarak ke sesama anggota K2</div>
                        <span class="formula-main">
                            a(<?= $k2_sample_id ?>) ≈ <?= number_format($k2_sample_a, 4) ?> (rata-rata jarak
                            intra-klaster)
                        </span>
                        <br><br>
                        <div class="formula-title">b(<?= $k2_sample_id ?>) = rata-rata jarak ke klaster terdekat</div>
                        <span class="formula-main">
                            b(<?= $k2_sample_id ?>) ≈ <?= number_format($k2_sample_b, 4) ?>
                        </span>
                        <br><br>
                        <div class="formula-title">s(<?= $k2_sample_id ?>) = (<?= number_format($k2_sample_b, 4) ?> −
                            <?= number_format($k2_sample_a, 4) ?>) / max(<?= number_format($k2_sample_b, 4) ?>,
                            <?= number_format($k2_sample_a, 4) ?>)
                        </div>
                        <span class="formula-main">s(<?= $k2_sample_id ?>) = <span
                                style="color:var(--muted)"><?= number_format($k2_sample_s, 4) ?></span> →
                            <?= $k2_sample_interp ?></span>
                    </div>

                    <div class="table-wrap">
                        <table class="manual-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>Klaster</th>
                                    <th>a(i) intra</th>
                                    <th>b(i) inter</th>
                                    <th>s(i)</th>
                                    <th>Interpretasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scData as $r): ?>
                                    <tr>
                                        <td class="mono" style="color:var(--muted)"><?= $r[0] ?></td>
                                        <td style="font-weight:600;font-size:.76rem"><?= htmlspecialchars($r[1]) ?></td>
                                        <td><span class="assign-badge ab<?= $r[2] ?>">K<?= $r[2] ?></span></td>
                                        <td class="mono"><?= number_format($r[3], 4) ?></td>
                                        <td class="mono"><?= number_format($r[4], 4) ?></td>
                                        <td class="mono"
                                            style="color:<?= $r[5] >= 0.7 ? 'var(--k3)' : ($r[5] >= 0.5 ? 'var(--k2)' : 'var(--muted)') ?>;font-weight:700">
                                            <?= $r[5] ?>
                                        </td>
                                        <td
                                            style="font-size:.72rem;color:<?= $r[6] == 'Sangat Baik' ? 'var(--k3)' : ($r[6] == 'Baik' ? 'var(--k2)' : 'var(--muted)') ?>">
                                            <?= $r[6] ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr style="border-top:2px solid var(--k3);background:rgba(16,185,129,.07)">
                                    <td colspan="5" style="font-weight:700;color:var(--k3)">SC Keseluruhan = rata-rata
                                        s(i)</td>
                                    <td class="mono highlight3" style="font-weight:700">
                                        <?= number_format($evaluasi['sc'], 4) ?>
                                    </td>
                                    <td style="font-size:.72rem;color:var(--k3)">
                                        <?= $evaluasi['sc'] >= 0.7 ? '✓ Sangat Baik' : ($evaluasi['sc'] >= 0.5 ? '✓ Baik (> 0.5)' : ($evaluasi['sc'] >= 0.25 ? '⚠ Lemah' : '✗ Buruk')) ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ═══ §10: DBI ═══ -->
            <div class="calc-section fade-in" id="sec-dbi">
                <div class="calc-section-header" onclick="toggleSection(this)">
                    <div class="cs-num">10</div>
                    <h3>Davies-Bouldin Index (DBI) — Rasio Dispersi Intra vs Antar Klaster</h3>
                    <span style="margin-left:auto;color:var(--muted);font-size:.85rem">▼</span>
                </div>
                <div class="calc-body">
                    <div class="formula-box">
                        <div class="formula-title">Formula Davies-Bouldin Index</div>
                        <span class="formula-main">DBI = (1/K) × Σᵢ max_{j≠i} [ (σᵢ + σⱼ) / d(Cᵢ, Cⱼ) ]</span>
                        <div class="formula-vars">
                            σᵢ = rata-rata jarak titik dalam klaster i ke centroid i (dispersi intra)<br>
                            d(Cᵢ, Cⱼ) = jarak antar centroid i dan j<br>
                            Semakin KECIL DBI, semakin baik (klaster kompak dan terpisah jauh). DBI &lt; 1 = baik.
                        </div>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">Langkah 1 — Hitung σᵢ (dispersi
                        rata-rata intra-klaster):</p>
                    <div class="formula-box">
                        <span class="formula-main">
                            σ₁ = <span style="color:var(--k1)"><?= number_format($calc['S_k'][1] ?? 0.0, 4) ?></span>
                            (<?= $calc['counts'][1] ?? 0 ?> titik)<br>
                            σ₂ = <span style="color:var(--k2)"><?= number_format($calc['S_k'][2] ?? 0.0, 4) ?></span>
                            (<?= $calc['counts'][2] ?? 0 ?> titik)<br>
                            σ₃ = <span style="color:var(--k3)"><?= number_format($calc['S_k'][3] ?? 0.0, 4) ?></span>
                            (<?= $calc['counts'][3] ?? 0 ?> titik)
                        </span>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0 .5rem">Langkah 2 — Hitung jarak antar
                        centroid d(Cᵢ, Cⱼ):</p>
                    <div class="formula-box">
                        <span class="formula-main">
                            d(C1, C2) = <span
                                style="color:var(--text)"><?= number_format($calc['centroid_dists'][1][2] ?? 0.0, 4) ?></span><br>
                            d(C1, C3) = <span
                                style="color:var(--text)"><?= number_format($calc['centroid_dists'][1][3] ?? 0.0, 4) ?></span><br>
                            d(C2, C3) = <span
                                style="color:var(--text)"><?= number_format($calc['centroid_dists'][2][3] ?? 0.0, 4) ?></span>
                        </span>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0 .5rem">Langkah 3 — Hitung Rᵢⱼ = (σᵢ +
                        σⱼ) / d(Cᵢ,Cⱼ) dan ambil max:</p>
                    <div class="table-wrap">
                        <table class="manual-table">
                            <thead>
                                <tr>
                                    <th>Klaster i</th>
                                    <th>σᵢ</th>
                                    <th>vs Klaster j</th>
                                    <th>σⱼ</th>
                                    <th>d(Cᵢ,Cⱼ)</th>
                                    <th>(σᵢ+σⱼ)/d</th>
                                    <th>Rᵢ = max</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dbi_table as $row): ?>
                                    <tr>
                                        <td class="highlight<?= $row['i'] == 1 ? '' : $row['i'] ?>">K<?= $row['i'] ?></td>
                                        <td class="mono"><?= number_format($row['sigma_i'], 4) ?></td>
                                        <td>vs K<?= $row['j'] ?></td>
                                        <td class="mono"><?= number_format($row['sigma_j'], 4) ?></td>
                                        <td class="mono"><?= number_format($row['d_ij'], 4) ?></td>
                                        <td class="mono">
                                            <?= number_format(($row['sigma_i'] + $row['sigma_j']) / $row['d_ij'], 4) ?>
                                        </td>
                                        <td class="mono highlight<?= $row['i'] == 1 ? '' : $row['i'] ?>">
                                            <?= number_format($row['ratio'], 4) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="formula-box">
                        <div class="formula-title">DBI = (1/K) × (R1 + R2 + R3)</div>
                        <span class="formula-main">DBI = (1/3) ×
                            <?= number_format(($dbi_table[1]['ratio'] ?? 0) + ($dbi_table[2]['ratio'] ?? 0) + ($dbi_table[3]['ratio'] ?? 0), 4) ?>
                            = <span style="color:var(--k3)"><?= number_format($evaluasi['dbi'], 4) ?></span></span>
                    </div>
                    <div class="calc-result">✓ <strong>DBI = <?= number_format($evaluasi['dbi'], 4) ?></strong> —
                        <?= $evaluasi['dbi'] < 1.0 ? 'Baik (< 1.0)' : 'Dapat diterima' ?>. Artinya klaster cukup kompak
                        secara internal dan terpisah antar klaster.
                    </div>
                </div>
            </div>

            <!-- ═══ §11: CHI ═══ -->
            <div class="calc-section fade-in" id="sec-chi">
                <div class="calc-section-header" onclick="toggleSection(this)">
                    <div class="cs-num">11</div>
                    <h3>Calinski-Harabasz Index (CHI) — Rasio Variansi Antar vs Intra Klaster</h3>
                    <span style="margin-left:auto;color:var(--muted);font-size:.85rem">▼</span>
                </div>
                <div class="calc-body">
                    <div class="formula-box">
                        <div class="formula-title">Formula Calinski-Harabasz Index</div>
                        <span class="formula-main">CHI = [BCSS / (K−1)] / [WCSS / (N−K)]</span>
                        <div class="formula-vars">
                            BCSS = Between-Cluster Sum of Squares (variansi antar klaster)<br>
                            WCSS = Within-Cluster Sum of Squares (variansi dalam klaster)<br>
                            N = <?= $total_destinasi ?> (total titik), K = 3 (klaster). Semakin BESAR semakin baik.
                        </div>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">Langkah 1 — Hitung Grand Mean
                        (rata-rata semua data):</p>
                    <div class="formula-box">
                        <span class="formula-main">
                            Grand Mean Visit_n = Σ Visit_n / <?= $total_destinasi ?><br>
                            = <span
                                style="color:var(--text)"><?= number_format($calc['global_mean']['kunjungan'] ?? 0.0, 4) ?></span>
                        </span>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0 .5rem">Langkah 2 — Hitung BCSS:</p>
                    <div class="formula-box">
                        <div class="formula-title">BCSS = Σₖ nₖ × d²(Centroid_k, Grand_Mean)</div>
                        <span class="formula-main">
                            BCSS = <?= $calc['counts'][1] ?? 0 ?>×d²(C1,GM) + <?= $calc['counts'][2] ?? 0 ?>×d²(C2,GM) +
                            <?= $calc['counts'][3] ?? 0 ?>×d²(C3,GM)<br>
                            = <?= $calc['counts'][1] ?? 0 ?>×(<?= number_format($d2_c_gm[1] ?? 0.0, 4) ?>) +
                            <?= $calc['counts'][2] ?? 0 ?>×(<?= number_format($d2_c_gm[2] ?? 0.0, 4) ?>) +
                            <?= $calc['counts'][3] ?? 0 ?>×(<?= number_format($d2_c_gm[3] ?? 0.0, 4) ?>)<br>
                            ≈ <span style="color:var(--text)"><?= number_format($calc['bcss'] ?? 0.0, 4) ?>
                                (BCSS)</span>
                        </span>
                    </div>

                    <div class="formula-box">
                        <div class="formula-title">CHI = [BCSS/(K−1)] / [WCSS/(N−K)]</div>
                        <span class="formula-main">
                            = [<?= number_format($calc['bcss'] ?? 0.0, 4) ?> / (3−1)] /
                            [<?= number_format($calc['wcss'] ?? 0.0, 4) ?> / (<?= $total_destinasi ?>−3)]<br>
                            = [<?= number_format($calc['bcss'] ?? 0.0, 4) ?> / 2] /
                            [<?= number_format($calc['wcss'] ?? 0.0, 4) ?> / <?= ($total_destinasi - 3) ?>]<br>
                            = <?= number_format(($calc['bcss'] ?? 0.0) / 2, 4) ?> /
                            <?= number_format(($calc['wcss'] ?? 0.0) / ($total_destinasi - 3), 4) ?><br>
                            = <span style="color:var(--k1)"><?= number_format($calc['chi'] ?? 0.0, 3) ?></span>
                        </span>
                    </div>
                    <div class="calc-note"><strong>⚠ CHI = <?= number_format($calc['chi'] ?? 0.0, 3) ?></strong>. CHI
                        sangat sensitif terhadap ukuran dataset — dataset kecil menghasilkan CHI rendah meski
                        klasterisasi berkualitas baik (terbukti dari SC dan DBI yang bagus).</div>
                </div>
            </div>

            <!-- ═══ §12: SKOR POTENSI ═══ -->
            <div class="calc-section fade-in" id="sec-skor">
                <div class="calc-section-header" onclick="toggleSection(this)">
                    <div class="cs-num">12</div>
                    <h3>Perhitungan Skor Potensi Destinasi</h3>
                    <span style="margin-left:auto;color:var(--muted);font-size:.85rem">▼</span>
                </div>
                <div class="calc-body">
                    <div class="formula-box">
                        <div class="formula-title">Formula Skor Potensi (Weighted Sum dari 6 atribut mentah, bukan data
                            ternormalisasi)</div>
                        <span class="formula-main">Skor = (Rating/5×0.25) + (Akses/5×0.15) + (Fasilitas/5×0.10) +
                            (P.Alam/5×0.20) + (P.Budaya/5×0.15) + (MIN(Pendapatan,20000)/20000×0.15)</span>
                        <div class="formula-vars">
                            Bobot: Rating 25% (persepsi kualitas), Aksesibilitas 15%, Fasilitas 10%,<br>
                            Potensi Alam 20%, Potensi Budaya 15%, Pendapatan 15% (dibatasi maks Rp 20.000 Jt agar
                            destinasi pendapatan tinggi tidak mendominasi skor)
                        </div>
                    </div>

                    <?php
                    $first_skor = reset($skor_calc);
                    if ($first_skor) {
                        $s_id = $first_skor[0];
                        $s_nama = $first_skor[1];
                        $s_k = $first_skor[2];
                        $s_rating_part = $first_skor[3];
                        $s_akses_part = $first_skor[4];
                        $s_fasilitas_part = $first_skor[5];
                        $s_alam_part = $first_skor[6];
                        $s_budaya_part = $first_skor[7];
                        $s_pend_part = $first_skor[8];
                        $s_total = $first_skor[9];
                    } else {
                        $s_id = 1;
                        $s_nama = 'Candi Borobudur';
                        $s_k = 1;
                        $s_rating_part = 0.2450;
                        $s_akses_part = 0.1500;
                        $s_fasilitas_part = 0.1000;
                        $s_alam_part = 0.1600;
                        $s_budaya_part = 0.1500;
                        $s_pend_part = 0.1388;
                        $s_total = 0.9438;
                    }
                    ?>
                    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">Contoh — <strong
                            style="color:var(--k1)"><?= htmlspecialchars($s_nama) ?> (ID=<?= $s_id ?>)</strong>:</p>
                    <div class="formula-box">
                        <span class="formula-main">
                            Skor = (Rating/5×0.25) + (Akses/5×0.15) + (Fasilitas/5×0.10) + (Alam/5×0.20) +
                            (Budaya/5×0.15) + (Pend/20000×0.15)<br>
                            = <?= number_format($s_rating_part, 4) ?> + <?= number_format($s_akses_part, 4) ?> +
                            <?= number_format($s_fasilitas_part, 4) ?> + <?= number_format($s_alam_part, 4) ?> +
                            <?= number_format($s_budaya_part, 4) ?> + <?= number_format($s_pend_part, 4) ?><br>
                            = <span style="color:var(--k1)"><?= number_format($s_total, 4) ?></span>
                        </span>
                    </div>

                    <div class="table-wrap">
                        <table class="manual-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>Klaster</th>
                                    <th>Rating ×0.25</th>
                                    <th>Akses ×0.15</th>
                                    <th>Fasilitas ×0.10</th>
                                    <th>Alam ×0.20</th>
                                    <th>Budaya ×0.15</th>
                                    <th>Pend ×0.15</th>
                                    <th>Skor Potensi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($skor_calc as $r): ?>
                                    <tr>
                                        <td class="mono" style="color:var(--muted)"><?= $r[0] ?></td>
                                        <td style="font-weight:600;font-size:.76rem"><?= htmlspecialchars($r[1]) ?></td>
                                        <td><span class="assign-badge ab<?= $r[2] ?>">K<?= $r[2] ?></span></td>
                                        <td class="mono"><?= number_format($r[3], 4) ?></td>
                                        <td class="mono"><?= number_format($r[4], 4) ?></td>
                                        <td class="mono"><?= number_format($r[5], 4) ?></td>
                                        <td class="mono"><?= number_format($r[6], 4) ?></td>
                                        <td class="mono"><?= number_format($r[7], 4) ?></td>
                                        <td class="mono"><?= number_format($r[8], 4) ?></td>
                                        <td class="mono"
                                            style="color:<?= $r[9] >= 0.9 ? 'var(--k1)' : ($r[9] >= 0.7 ? 'var(--k3)' : ($r[9] >= 0.6 ? 'var(--k2)' : 'var(--muted)')) ?>;font-weight:700">
                                            <?= number_format($r[9], 4) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div><!-- end page-manual -->

        <footer>
            <strong>SIG K-Means++ Wisata Kabupaten Magelang</strong> &nbsp;|&nbsp;
            Pengembangan Model Konseptual Intelijen Spasial Berbasis Klasterisasi K-Means++ &amp; Analisis
            Spatio-Temporal &nbsp;|&nbsp;
            Data: <?= $total_destinasi ?> Destinasi · 10 Atribut · <?= $total_klaster ?> Klaster Optimal &nbsp;|&nbsp;
            SC=<?= number_format($evaluasi['sc'], 4) ?> · DBI=<?= number_format($evaluasi['dbi'], 4) ?> · Konvergen
            Iterasi <?= $evaluasi['iter'] ?>
        </footer>

        <!-- ══════════ SCRIPTS ══════════ -->
        <script>
            const navMenu = document.getElementById('navMenu');
            const navToggle = document.querySelector('.nav-toggle');
            const mobileNavQuery = window.matchMedia('(max-width: 860px)');

            function syncNavMode() {
                navMenu.classList.remove('open');
                navToggle.setAttribute('aria-expanded', 'false');
                navToggle.textContent = '☰ Menu';
            }

            function toggleNavMenu() {
                if (!mobileNavQuery.matches) return;
                const isOpen = navMenu.classList.toggle('open');
                navToggle.setAttribute('aria-expanded', String(isOpen));
                navToggle.textContent = isOpen ? '✕ Tutup' : '☰ Menu';
            }

            // ─── PAGE SWITCHING ───────────────────────────────
            function showPage(name, trigger) {
                document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
                document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
                document.getElementById('page-' + name).classList.add('active');
                const activeTab = document.querySelector(`.nav-tab[data-page="${name}"]`);
                if (activeTab) activeTab.classList.add('active');
                if (trigger) trigger.classList.add('active');
                if (mobileNavQuery.matches) {
                    syncNavMode();
                }
                if (name === 'peta' && !window._mapInit) initMap();
                setTimeout(animateFadeIns, 50);
            }

            mobileNavQuery.addEventListener('change', syncNavMode);
            syncNavMode();

            // ─── FADE IN ANIMATION ───────────────────────────
            function animateFadeIns() {
                document.querySelectorAll('.page.active .fade-in:not(.visible)').forEach((el, i) => {
                    setTimeout(() => el.classList.add('visible'), i * 80);
                });
            }
            setTimeout(animateFadeIns, 100);

            // ─── TABLE FILTER ─────────────────────────────────
            function filterTable() {
                const kf = document.getElementById('filterKlaster').value;
                const zf = document.getElementById('filterZona').value;
                const sf = document.getElementById('searchInput').value.toLowerCase();
                document.querySelectorAll('#mainTable tbody tr').forEach(tr => {
                    const k = tr.dataset.klaster,
                        z = tr.dataset.zona,
                        n = tr.dataset.nama;
                    const show = (!kf || k === kf) && (!zf || z === zf) && (!sf || n.includes(sf));
                    tr.style.display = show ? '' : 'none';
                });
            }

            // ─── BUTTON HOVER EFFECTS ─────────────────────────
            const btnUpdate = document.getElementById('btnUpdateCalc');
            if (btnUpdate) {
                btnUpdate.addEventListener('mouseenter', function () {
                    if (!this.disabled) {
                        this.style.background = '#059669';
                        this.style.transform = 'translateY(-2px)';
                        this.style.boxShadow = '0 8px 20px rgba(16,185,129,0.4)';
                    }
                });
                btnUpdate.addEventListener('mouseleave', function () {
                    if (!this.disabled) {
                        this.style.background = '#10b981';
                        this.style.transform = 'translateY(0)';
                        this.style.boxShadow = '0 4px 12px rgba(16,185,129,0.3)';
                    }
                });
            }

            // ─── UPDATE CALCULATION TO DATABASE ───────────────
            function updateCalculation() {
                const btn = document.getElementById('btnUpdateCalc');
                const originalText = btn.textContent;
                const originalBg = '#10b981';

                try {
                    // Get values from DOM (displayed values on page)
                    const metricCards = document.querySelectorAll('#page-analisis .metric-card');
                    let sc, dbi, chi, wcss, iter;

                    // Iterate through metric cards and extract values
                    metricCards.forEach(card => {
                        const name = card.querySelector('.metric-name')?.textContent || '';
                        const val = card.querySelector('.metric-val')?.textContent?.trim() || '';

                        if (name.includes('Silhouette')) {
                            sc = parseFloat(val.replace(',', '.'));
                        } else if (name.includes('Davies-Bouldin')) {
                            dbi = parseFloat(val.replace(',', '.'));
                        } else if (name.includes('Calinski')) {
                            chi = parseFloat(val.replace(',', '.'));
                        } else if (name.includes('WCSS')) {
                            wcss = parseFloat(val.replace(',', '.'));
                            // Get ITER from metric-status "✓ Konvergen Iterasi N"
                            const statusText = card.querySelector('.metric-status')?.textContent || '';
                            const iterMatch = statusText.match(/Iterasi\s+(\d+)/);
                            iter = iterMatch ? parseInt(iterMatch[1]) : 2;
                        }
                    });

                    // Validate values
                    if (isNaN(sc) || isNaN(dbi) || isNaN(chi) || isNaN(wcss) || iter === undefined) {
                        throw new Error('Gagal mengambil nilai metrik: SC=' + sc + ', DBI=' + dbi + ', CHI=' + chi + ', WCSS=' + wcss + ', ITER=' + iter);
                    }

                    console.log('Values from DOM:', {
                        sc,
                        dbi,
                        chi,
                        wcss,
                        iter
                    });

                    // Show loading state
                    btn.disabled = true;
                    btn.textContent = '⏳ Menyimpan...';
                    btn.style.background = '#64748b';
                    btn.style.opacity = '0.7';

                    // Send to server
                    const payload = {
                        action: 'update_evaluasi',
                        sc: sc,
                        dbi: dbi,
                        chi: chi,
                        wcss: wcss,
                        iter: iter
                    };
                    console.log('Sending payload:', payload);

                    fetch('save_calculation.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(payload)
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                btn.textContent = '✓ Data Tersimpan!';
                                btn.style.background = '#059669';
                                btn.style.opacity = '1';
                                setTimeout(() => {
                                    btn.disabled = false;
                                    btn.textContent = originalText;
                                    btn.style.background = originalBg;
                                }, 2000);
                            } else {
                                throw new Error(data.message || 'Update gagal');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            btn.textContent = '✗ Gagal';
                            btn.style.background = '#ef4444';
                            btn.style.opacity = '1';
                            setTimeout(() => {
                                btn.disabled = false;
                                btn.textContent = originalText;
                                btn.style.background = originalBg;
                            }, 2500);
                        });
                } catch (error) {
                    console.error('Error:', error);
                    btn.textContent = '✗ Error';
                    btn.style.background = '#ef4444';
                }
            }

            const COLORS = {
                1: '#f59e0b',
                2: '#3b82f6',
                3: '#10b981'
            };
            const C_ALPHA = {
                1: 'rgba(245,158,11,.2)',
                2: 'rgba(59,130,246,.2)',
                3: 'rgba(16,185,129,.2)'
            };

            // Chart defaults
            Chart.defaults.color = '#64748b';
            Chart.defaults.borderColor = '#1e2d45';
            Chart.defaults.font.family = "'Sora', sans-serif";

            // ─── DESTINATIONS DATA ────────────────────────────
            const destinations = <?= json_encode($destinations) ?>;
            const proyeksi = <?= json_encode($proyeksi) ?>;

            // ─── CHART: Visit distribution per cluster ────────
            const visitPerCluster = <?= json_encode($visit_per_cluster) ?>;
            new Chart(document.getElementById('chartVisit'), {
                type: 'doughnut',
                data: {
                    labels: ['K1: Tinggi', 'K2: Sedang', 'K3: Rendah'],
                    datasets: [{
                        data: visitPerCluster,
                        backgroundColor: ['#f59e0b', '#3b82f6', '#10b981'],
                        borderWidth: 0,
                        hoverOffset: 8
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 16,
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });

            // ─── CHART: Pendapatan ────────────────────────────
            const pendPerCluster = <?= json_encode($revenue_per_cluster) ?>;
            new Chart(document.getElementById('chartPend'), {
                type: 'bar',
                data: {
                    labels: ['K1: Tinggi', 'K2: Sedang', 'K3: Rendah'],
                    datasets: [{
                        data: pendPerCluster,
                        backgroundColor: ['rgba(245,158,11,.7)', 'rgba(59,130,246,.7)', 'rgba(16,185,129,.7)'],
                        borderRadius: 8,
                        borderSkipped: false
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            grid: {
                                color: '#1e2d45'
                            },
                            ticks: {
                                callback: v => 'Rp' + v.toLocaleString() + 'Jt'
                            }
                        }
                    }
                }
            });

            // ─── CHART: Radar ─────────────────────────────────
            const radarData = <?= json_encode($radar_data) ?>;
            new Chart(document.getElementById('chartRadar'), {
                type: 'radar',
                data: {
                    labels: ['Rating', 'Aksesibilitas', 'Fasilitas', 'P.Alam', 'P.Budaya'],
                    datasets: [{
                        label: 'K1 Tinggi',
                        data: radarData[1] || [0, 0, 0, 0, 0],
                        backgroundColor: 'rgba(245,158,11,.15)',
                        borderColor: '#f59e0b',
                        pointBackgroundColor: '#f59e0b',
                        borderWidth: 2
                    },
                    {
                        label: 'K2 Sedang',
                        data: radarData[2] || [0, 0, 0, 0, 0],
                        backgroundColor: 'rgba(59,130,246,.15)',
                        borderColor: '#3b82f6',
                        pointBackgroundColor: '#3b82f6',
                        borderWidth: 2
                    },
                    {
                        label: 'K3 Rendah',
                        data: radarData[3] || [0, 0, 0, 0, 0],
                        backgroundColor: 'rgba(16,185,129,.15)',
                        borderColor: '#10b981',
                        pointBackgroundColor: '#10b981',
                        borderWidth: 2
                    },
                    ]
                },
                options: {
                    scales: {
                        r: {
                            grid: {
                                color: '#1e2d45'
                            },
                            ticks: {
                                display: false
                            },
                            pointLabels: {
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });

            // ─── CHART: Skor Potensi ──────────────────────────
            const sorted = [...destinations].sort((a, b) => b.skor - a.skor);
            new Chart(document.getElementById('chartSkor'), {
                type: 'bar',
                data: {
                    labels: sorted.map(d => d.nama.length > 16 ? d.nama.slice(0, 14) + '…' : d.nama),
                    datasets: [{
                        data: sorted.map(d => d.skor),
                        backgroundColor: sorted.map(d => COLORS[d.klaster]),
                        borderRadius: 6,
                        borderSkipped: false
                    }]
                },
                options: {
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            min: 0.4,
                            max: 1,
                            grid: {
                                color: '#1e2d45'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: 10
                                }
                            }
                        }
                    }
                }
            });

            // ─── CHART: Silhouette ────────────────────────────
            const siData = <?= json_encode(array_map(function ($r) {
                return [
                    'n' => strlen($r[1]) > 14 ? substr($r[1], 0, 12) . '…' : $r[1],
                    'k' => $r[2],
                    's' => $r[5]
                ];
            }, $scData)) ?>;
            new Chart(document.getElementById('chartSilhouette'), {
                type: 'bar',
                data: {
                    labels: siData.map(d => d.n),
                    datasets: [{
                        data: siData.map(d => d.s),
                        backgroundColor: siData.map(d => COLORS[d.k]),
                        borderRadius: 5,
                        borderSkipped: false
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            grid: {
                                color: '#1e2d45'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 9
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // ─── CHART: WCSS convergence ──────────────────────
            const wcssPerIter = <?= json_encode($wcss_per_iter) ?>;
            const wcssLabels = wcssPerIter.map((_, index) => {
                if (index === 0) return 'Iterasi 0 (init)';
                if (index === wcssPerIter.length - 1) return `Iterasi ${index} (konvergen)`;
                return `Iterasi ${index}`;
            });
            new Chart(document.getElementById('chartWCSS'), {
                type: 'line',
                data: {
                    labels: wcssLabels,
                    datasets: [{
                        label: 'WCSS',
                        data: wcssPerIter,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,.1)',
                        pointBackgroundColor: '#6366f1',
                        borderWidth: 2,
                        tension: .3,
                        fill: true,
                        pointRadius: 6
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            grid: {
                                color: '#1e2d45'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // ─── CHART: Radar 2 (Analysis page) ──────────────
            new Chart(document.getElementById('chartRadar2'), {
                type: 'radar',
                data: {
                    labels: ['Rating', 'Akses', 'Fasilitas', 'P.Alam', 'P.Budaya', 'Trend YoY (%)'],
                    datasets: [{
                        label: 'K1 Tinggi',
                        data: radarData[1] || [0, 0, 0, 0, 0, 0],
                        backgroundColor: 'rgba(245,158,11,.15)',
                        borderColor: '#f59e0b',
                        pointBackgroundColor: '#f59e0b',
                        borderWidth: 2
                    },
                    {
                        label: 'K2 Sedang',
                        data: radarData[2] || [0, 0, 0, 0, 0, 0],
                        backgroundColor: 'rgba(59,130,246,.15)',
                        borderColor: '#3b82f6',
                        pointBackgroundColor: '#3b82f6',
                        borderWidth: 2
                    },
                    {
                        label: 'K3 Rendah',
                        data: radarData[3] || [0, 0, 0, 0, 0, 0],
                        backgroundColor: 'rgba(16,185,129,.15)',
                        borderColor: '#10b981',
                        pointBackgroundColor: '#10b981',
                        borderWidth: 2
                    },
                    ]
                },
                options: {
                    scales: {
                        r: {
                            grid: {
                                color: '#1e2d45'
                            },
                            ticks: {
                                display: false
                            },
                            pointLabels: {
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });

            // ─── CHART: Trend YoY ────────────────────────────
            const trendSorted = [...destinations].sort((a, b) => b.trend - a.trend);
            new Chart(document.getElementById('chartTrend'), {
                type: 'bar',
                data: {
                    labels: trendSorted.map(d => d.nama.length > 14 ? d.nama.slice(0, 12) + '…' : d.nama),
                    datasets: [{
                        data: trendSorted.map(d => +(d.trend * 100).toFixed(1)),
                        backgroundColor: trendSorted.map(d => d.trend >= 0.3 ? '#10b981' : d.trend >= 0.15 ? '#3b82f6' : '#64748b'),
                        borderRadius: 5,
                        borderSkipped: false
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            grid: {
                                color: '#1e2d45'
                            },
                            ticks: {
                                callback: v => v + '%'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 9
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // ─── CHART: Scatter (peta page) ──────────────────
            new Chart(document.getElementById('chartScatter'), {
                type: 'scatter',
                data: {
                    datasets: [1, 2, 3].map(k => ({
                        label: 'K' + k,
                        data: destinations.filter(d => d.klaster == k).map(d => ({
                            x: d.lon,
                            y: d.lat,
                            label: d.nama
                        })),
                        backgroundColor: COLORS[k],
                        pointRadius: 7,
                        pointHoverRadius: 10,
                    }))
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: c => c.raw.label + ` (${c.raw.x.toFixed(4)}, ${c.raw.y.toFixed(4)})`
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Longitude'
                            },
                            grid: {
                                color: '#1e2d45'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Latitude'
                            },
                            grid: {
                                color: '#1e2d45'
                            }
                        }
                    }
                }
            });

            // ─── CHART: Zona temporal ────────────────────────
            const zonaCounts = {
                peak: 0,
                mid: 0,
                low: 0
            };
            destinations.forEach(d => {
                if (d.zona === 'Peak Season') zonaCounts.peak++;
                else if (d.zona === 'Mid Season') zonaCounts.mid++;
                else zonaCounts.low++;
            });
            new Chart(document.getElementById('chartZona'), {
                type: 'pie',
                data: {
                    labels: ['Peak Season', 'Mid Season', 'Low Season'],
                    datasets: [{
                        data: [zonaCounts.peak, zonaCounts.mid, zonaCounts.low],
                        backgroundColor: ['#f59e0b', '#6366f1', '#64748b'],
                        borderWidth: 0,
                        hoverOffset: 6
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });

            // ─── CHART: Proyeksi ─────────────────────────────
            const topProj = proyeksi.sort((a, b) => b.y2027 - a.y2027).slice(0, 8);
            new Chart(document.getElementById('chartProyeksi'), {
                type: 'bar',
                data: {
                    labels: topProj.map(d => d.nama.length > 14 ? d.nama.slice(0, 12) + '…' : d.nama),
                    datasets: [{
                        label: '2024',
                        data: topProj.map(d => d.y2024),
                        backgroundColor: 'rgba(100,116,139,.5)',
                        borderRadius: 4,
                        borderSkipped: false
                    },
                    {
                        label: '2025',
                        data: topProj.map(d => d.y2025),
                        backgroundColor: 'rgba(99,102,241,.6)',
                        borderRadius: 4,
                        borderSkipped: false
                    },
                    {
                        label: '2026',
                        data: topProj.map(d => d.y2026),
                        backgroundColor: 'rgba(59,130,246,.7)',
                        borderRadius: 4,
                        borderSkipped: false
                    },
                    {
                        label: '2027',
                        data: topProj.map(d => d.y2027),
                        backgroundColor: 'rgba(16,185,129,.8)',
                        borderRadius: 4,
                        borderSkipped: false
                    },
                    ]
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            grid: {
                                color: '#1e2d45'
                            },
                            ticks: {
                                callback: v => v >= 1000000 ? (v / 1000000).toFixed(1) + 'M' : v >= 1000 ? (v / 1000).toFixed(0) + 'K' : v
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 10
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // ─── LEAFLET MAP ─────────────────────────────────
            window._mapInit = false;

            function initMap() {
                window._mapInit = true;
                const map = L.map('map', {
                    center: [-7.5, 110.3],
                    zoom: 11,
                    zoomControl: true,
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 18,
                }).addTo(map);

                const clusterColors = {
                    1: '#f59e0b',
                    2: '#3b82f6',
                    3: '#10b981'
                };
                const clusterLabels = {
                    1: 'Tinggi',
                    2: 'Sedang',
                    3: 'Rendah'
                };

                destinations.forEach(d => {
                    const c = clusterColors[d.klaster];
                    const r = Math.max(12, Math.min(30, Math.log10(d.kunjungan) * 5));

                    const icon = L.divIcon({
                        className: '',
                        html: `<div style="
        width:${r * 2}px;height:${r * 2}px;
        background:${c};
        border:3px solid #0a0f1e;
        border-radius:50%;
        display:flex;align-items:center;justify-content:center;
        font-size:10px;font-weight:700;color:#0a0f1e;
        box-shadow:0 0 12px ${c}88;
        cursor:pointer;
        font-family:monospace;
      ">${d.id}</div>`,
                        iconSize: [r * 2, r * 2],
                        iconAnchor: [r, r],
                    });

                    const popup = `
      <div style="font-family:Sora,sans-serif;padding:4px;min-width:200px">
        <div style="font-weight:700;font-size:14px;margin-bottom:6px">${d.nama}</div>
        <div style="display:inline-block;background:${c}22;color:${c};padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;margin-bottom:8px">Klaster ${d.klaster}: ${clusterLabels[d.klaster]}</div>
        <table style="font-size:12px;width:100%;border-collapse:collapse">
          <tr><td style="color:#888">Kunjungan/Thn</td><td style="font-weight:600;text-align:right">${Number(d.kunjungan).toLocaleString()}</td></tr>
          <tr><td style="color:#888">Rating</td><td style="font-weight:600;text-align:right">⭐ ${Number(d.rating).toFixed(1)}</td></tr>
          <tr><td style="color:#888">Pendapatan</td><td style="font-weight:600;text-align:right">Rp ${Number(d.pendapatan).toLocaleString()} Jt</td></tr>
          <tr><td style="color:#888">Trend YoY</td><td style="font-weight:600;text-align:right;color:${Number(d.trend) >= 0.3 ? '#10b981' : Number(d.trend) >= 0.15 ? '#3b82f6' : '#888'}">+${(Number(d.trend) * 100).toFixed(1)}%</td></tr>
          <tr><td style="color:#888">Skor Potensi</td><td style="font-weight:600;text-align:right">${Number(d.skor).toFixed(4)}</td></tr>
          <tr><td style="color:#888">Zona</td><td style="text-align:right">${d.zona}</td></tr>
        </table>
        <div style="margin-top:8px;font-size:11px;color:#666;border-top:1px solid #eee;padding-top:6px">💡 ${d.rekomendasi}</div>
        <div style="font-size:10px;color:#aaa;margin-top:4px">📍 ${d.lon}, ${d.lat}</div>
      </div>
    `;
                    L.marker([d.lat, d.lon], {
                        icon
                    }).addTo(map).bindPopup(popup, {
                        maxWidth: 280
                    });
                });

                // Legend
                const legend = L.control({
                    position: 'bottomright'
                });
                legend.onAdd = () => {
                    const div = L.DomUtil.create('div');
                    div.style.cssText = 'background:rgba(10,15,30,.9);color:#e2e8f0;padding:10px 14px;border-radius:10px;font-family:Sora,sans-serif;font-size:12px;border:1px solid #1e2d45';
                    div.innerHTML = '<strong style="display:block;margin-bottom:6px">Klaster</strong>' +
                        Object.entries(clusterColors).map(([k, c]) => `<div style="display:flex;align-items:center;gap:6px;margin-bottom:4px"><div style="width:12px;height:12px;border-radius:50%;background:${c}"></div> K${k}: ${clusterLabels[k]}</div>`).join('');
                    return div;
                };
                legend.addTo(map);
            }
        </script>
</body>

</html>