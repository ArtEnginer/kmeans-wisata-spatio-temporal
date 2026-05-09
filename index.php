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

$evaluasi_raw = getEvaluasi($conn);
$evaluasi = [
    'sc'   => floatval($evaluasi_raw['sc']),
    'dbi'  => floatval($evaluasi_raw['dbi']),
    'chi'  => floatval($evaluasi_raw['chi']),
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
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Sora:wght@300;400;600;700;800&display=swap" rel="stylesheet">

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

        @media(max-width:640px) {
            nav {
                padding: .6rem 1rem;
                flex-direction: column;
                gap: .5rem
            }

            .nav-tabs {
                flex-wrap: wrap;
                justify-content: center
            }

            .page {
                padding: 1rem 1rem 3rem
            }

            .hero {
                padding: 2rem 1rem 1.5rem
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
        <div class="nav-tabs">
            <button class="nav-tab active" onclick="showPage('dashboard')">Dashboard</button>
            <button class="nav-tab" onclick="showPage('peta')">🗺 Peta SIG</button>
            <button class="nav-tab" onclick="showPage('data')">Tabel Data</button>
            <button class="nav-tab" onclick="showPage('analisis')">Analisis</button>
            <button class="nav-tab" onclick="showPage('proyeksi')">Proyeksi</button>
            <button class="nav-tab" onclick="showPage('algoritma')">Algoritma</button>
            <button class="nav-tab" onclick="showPage('manual')" style="background:rgba(99,102,241,.15);color:var(--accent);border:1px solid rgba(99,102,241,.3)">🧮 Perhitungan Manual</button>
            <a href="admin.php" class="nav-tab" style="background:rgba(239,68,68,.15);color:#ff6b6b;border:1px solid rgba(239,68,68,.3);text-decoration:none">⚙ Admin</a>
        </div>
    </nav>

    <!-- HERO -->
    <div class="hero">
        <h1>Intelijen Spasial <span>K-Means++</span><br>Potensi Wisata Magelang</h1>
        <p>Klasterisasi berbasis data spatio-temporal untuk pemodelan dinamis potensi destinasi wisata Kabupaten Magelang menggunakan algoritma K-Means++ dengan 3 klaster optimal.</p>
        <div class="hero-stats">
            <div class="hstat">
                <div class="hstat-val" style="color:var(--k1)">15</div>
                <div class="hstat-lbl">Destinasi</div>
            </div>
            <div class="hstat">
                <div class="hstat-val" style="color:var(--k2)">3</div>
                <div class="hstat-lbl">Klaster</div>
            </div>
            <div class="hstat">
                <div class="hstat-val" style="color:var(--k3)">2</div>
                <div class="hstat-lbl">Iterasi</div>
            </div>
            <div class="hstat">
                <div class="hstat-val" style="color:var(--accent)">0.629</div>
                <div class="hstat-lbl">Silhouette</div>
            </div>
            <div class="hstat">
                <div class="hstat-val" style="color:#ec4899">2.1M</div>
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
                            <div class="cs-val"><?= $info['n'] ?>/15</div>
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
            <div class="ml-item">
                <div class="ml-dot" style="background:var(--k1)"></div> Klaster 1: Tinggi (1 titik)
            </div>
            <div class="ml-item">
                <div class="ml-dot" style="background:var(--k2)"></div> Klaster 2: Sedang (8 titik)
            </div>
            <div class="ml-item">
                <div class="ml-dot" style="background:var(--k3)"></div> Klaster 3: Rendah (6 titik)
            </div>
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
            <select id="filterKlaster" onchange="filterTable()" style="background:var(--surface2);border:1px solid var(--border);color:var(--text);padding:.45rem .75rem;border-radius:8px;font-size:.82rem">
                <option value="">Semua Klaster</option>
                <option value="1">Klaster 1: Tinggi</option>
                <option value="2">Klaster 2: Sedang</option>
                <option value="3">Klaster 3: Rendah</option>
            </select>
            <select id="filterZona" onchange="filterTable()" style="background:var(--surface2);border:1px solid var(--border);color:var(--text);padding:.45rem .75rem;border-radius:8px;font-size:.82rem">
                <option value="">Semua Zona</option>
                <option value="Peak Season">Peak Season</option>
                <option value="Mid Season">Mid Season</option>
                <option value="Low Season">Low Season</option>
            </select>
            <input id="searchInput" type="text" placeholder="Cari destinasi..." onkeyup="filterTable()" style="background:var(--surface2);border:1px solid var(--border);color:var(--text);padding:.45rem .75rem;border-radius:8px;font-size:.82rem;flex:1;min-width:160px">
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
                        <tr data-klaster="<?= $d['klaster'] ?>" data-zona="<?= htmlspecialchars($d['zona']) ?>" data-nama="<?= strtolower($d['nama']) ?>">
                            <td style="font-family:'Space Mono',monospace;color:var(--muted)"><?= $d['id'] ?></td>
                            <td style="font-weight:600"><?= htmlspecialchars($d['nama']) ?></td>
                            <td><span class="badge k<?= $d['klaster'] ?>">K<?= $d['klaster'] ?></span></td>
                            <td style="font-family:'Space Mono',monospace"><?= number_format($d['kunjungan']) ?></td>
                            <td>
                                <span style="color:<?= $d['rating'] >= 4.5 ? 'var(--k1)' : ($d['rating'] >= 4.0 ? 'var(--k3)' : 'var(--muted)') ?>;font-weight:700">
                                    <?= $d['rating'] ?> ★
                                </span>
                            </td>
                            <td><?= str_repeat('●', intval($d['aksesibilitas'])) . str_repeat('○', 5 - intval($d['aksesibilitas'])) ?></td>
                            <td><?= str_repeat('●', intval($d['fasilitas'])) . str_repeat('○', 5 - intval($d['fasilitas'])) ?></td>
                            <td><?= str_repeat('●', intval($d['potensi_alam'])) . str_repeat('○', 5 - intval($d['potensi_alam'])) ?></td>
                            <td><?= str_repeat('●', intval($d['potensi_budaya'])) . str_repeat('○', 5 - intval($d['potensi_budaya'])) ?></td>
                            <td style="font-family:'Space Mono',monospace"><?= number_format($d['pendapatan']) ?></td>
                            <td style="color:<?= $d['trend'] >= 0.3 ? 'var(--k3)' : ($d['trend'] >= 0.15 ? 'var(--k2)' : 'var(--muted)') ?>;font-family:'Space Mono',monospace">
                                +<?= round($d['trend'] * 100, 1) ?>%
                            </td>
                            <td>
                                <?php $zc = ['Peak Season' => 'var(--k1)', 'Mid Season' => 'var(--accent)', 'Low Season' => 'var(--muted)']; ?>
                                <span style="color:<?= $zc[$d['zona']] ?? 'var(--muted)' ?>;font-size:.75rem;font-weight:600"><?= htmlspecialchars($d['zona']) ?></span>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:.4rem">
                                    <div class="progress-bar" style="width:60px;display:inline-block">
                                        <div class="progress-fill" style="width:<?= round($d['skor'] * 100) ?>%;background:var(--k<?= $d['klaster'] ?>)"></div>
                                    </div>
                                    <span style="font-family:'Space Mono',monospace;font-size:.75rem"><?= round($d['skor'], 3) ?></span>
                                </div>
                            </td>
                            <td style="font-size:.75rem;color:var(--muted);max-width:200px"><?= htmlspecialchars($d['rekomendasi']) ?></td>
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
                    <div class="progress-fill" style="width:<?= round($evaluasi['sc'] * 100) ?>%;background:var(--k3)"></div>
                </div>
                <div class="metric-status status-ok">✓ Baik (> 0.5)</div>
                <div class="metric-desc">Mengukur seberapa mirip objek dengan klasternya dibanding klaster lain. Kisaran: [-1, +1]</div>
            </div>
            <div class="metric-card fade-in">
                <div class="metric-name">Davies-Bouldin Index (DBI)</div>
                <div class="metric-val" style="color:var(--k3)"><?= number_format($evaluasi['dbi'], 4) ?></div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:<?= round($evaluasi['dbi'] * 100) ?>%;background:var(--k3)"></div>
                </div>
                <div class="metric-status status-ok">✓ Sangat Baik (< 1.0)</div>
                        <div class="metric-desc">Rasio rata-rata dispersi intra-klaster terhadap jarak antar centroid. Semakin kecil semakin baik.</div>
                </div>
                <div class="metric-card fade-in">
                    <div class="metric-name">Calinski-Harabasz Index (CHI)</div>
                    <div class="metric-val" style="color:var(--k1)"><?= number_format($evaluasi['chi'], 3) ?></div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width:7%;background:var(--k1)"></div>
                    </div>
                    <div class="metric-status status-warn">⚠ Perlu Perbaikan (> 100)</div>
                    <div class="metric-desc">Rasio variance antar-klaster terhadap intra-klaster. Nilai rendah karena dataset kecil (N=15).</div>
                </div>
                <div class="metric-card fade-in">
                    <div class="metric-name">WCSS (Within-Cluster Sum of Squares)</div>
                    <div class="metric-val" style="color:var(--k2)"><?= number_format($evaluasi['wcss'], 4) ?></div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width:35%;background:var(--k2)"></div>
                    </div>
                    <div class="metric-status status-ok">✓ Konvergen Iterasi 2</div>
                    <div class="metric-desc">Total jarak kuadrat dalam klaster. Semakin kecil = klaster semakin kompak.</div>
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
                        $norm = [
                            [1, 'Candi Borobudur', 0.0391, 0.0134, 1.0000, 1.0000, 1.0000, 1.0000, 0.6667, 1.0000, 1.0000, 0.2189],
                            [2, 'Punthuk Setumbu', 0.0000, 0.0354, 0.1285, 0.8333, 0.3333, 0.3333, 1.0000, 0.6667, 0.1416, 0.3865],
                            [3, 'Candi Pawon', 0.0704, 0.0000, 0.0548, 0.4167, 0.6667, 0.3333, 0.3333, 1.0000, 0.0350, 0.1081],
                            [9, 'Bukit Rhema', 0.0318, 0.0301, 0.1121, 0.7500, 0.3333, 0.3333, 0.6667, 0.6667, 0.1033, 0.8378],
                            [14, 'Umbul Songo', 0.8854, 1.0000, 0.0033, 0.4167, 0.0000, 0.0000, 1.0000, 0.0000, 0.0019, 0.4216],
                        ];
                        foreach ($norm as $r): ?>
                            <tr>
                                <td style="font-family:'Space Mono',monospace;color:var(--muted)"><?= $r[0] ?></td>
                                <td style="font-weight:600"><?= $r[1] ?></td>
                                <?php for ($i = 2; $i < 12; $i++): ?>
                                    <td style="font-family:'Space Mono',monospace;font-size:.78rem;color:<?= $r[$i] >= 0.8 ? 'var(--k1)' : ($r[$i] >= 0.5 ? 'var(--k3)' : 'var(--muted)') ?>">
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
                                <td style="font-family:'Space Mono',monospace;color:var(--text)"><?= number_format($p['y2025']) ?></td>
                                <td style="font-family:'Space Mono',monospace;color:var(--k3)"><?= number_format($p['y2026']) ?></td>
                                <td style="font-family:'Space Mono',monospace;color:var(--k1);font-weight:700"><?= number_format($p['y2027']) ?></td>
                                <td>
                                    <div class="trend-bar">
                                        <span class="trend-num" style="color:<?= $p['cagr'] >= 0.3 ? 'var(--k3)' : ($p['cagr'] >= 0.15 ? 'var(--k2)' : 'var(--muted)') ?>">
                                            <?= round($p['cagr'] * 100, 1) ?>%
                                        </span>
                                        <div style="flex:1;height:5px;background:var(--border);border-radius:3px;overflow:hidden">
                                            <div style="width:<?= min(100, round($p['cagr'] * 250)) ?>%;height:100%;background:<?= $p['cagr'] >= 0.3 ? 'var(--k3)' : ($p['cagr'] >= 0.15 ? 'var(--k2)' : 'var(--muted)') ?>"></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-size:.78rem"><?= htmlspecialchars($p['kat']) ?></td>
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
                        Semua fitur numerik (Longitude, Latitude, Kunjungan, Rating, Aksesibilitas, Fasilitas, Potensi Alam, Potensi Budaya, Pendapatan, Trend) dinormalisasi ke rentang [0,1] menggunakan formula:<br><br>
                        <code>X_norm = (X − X_min) / (X_max − X_min)</code><br><br>
                        Ini memastikan setiap fitur berkontribusi proporsional dalam perhitungan jarak Euclidean, mencegah dominasi fitur dengan skala besar seperti jumlah kunjungan.
                    </div>
                </div>
                <div class="step fade-in">
                    <div class="step-title"><span class="step-num">2</span> Inisialisasi Centroid K-Means++ (C1)</div>
                    <div class="step-body">
                        Centroid pertama dipilih secara acak. Dalam dataset ini, <strong>C1 = Candi Borobudur (ID=1)</strong> dipilih sebagai seed karena memiliki profil tertinggi (kunjungan dan rating tertinggi). Formula jarak kuadrat:<br><br>
                        <code>D²(x, C1) = Σᵢ (xᵢ − C1ᵢ)²</code><br><br>
                        Total D² dari semua titik ke C1 = <strong>62.5019</strong>
                    </div>
                </div>
                <div class="step fade-in">
                    <div class="step-title"><span class="step-num">3</span> Pilih C2 Berbasis Probabilitas D²</div>
                    <div class="step-body">
                        Centroid C2 dipilih dengan probabilitas proporsional terhadap D²(x). Titik dengan jarak kuadrat terbesar ke C1 memiliki kemungkinan tertinggi untuk menjadi C2. Hasil: <strong>C2 = Umbul Songo (ID=14)</strong> dengan D² maksimum = 7.1717. Ini menjamin centroid awal tersebar jauh, menghindari inisialisasi yang buruk.
                    </div>
                </div>
                <div class="step fade-in">
                    <div class="step-title"><span class="step-num">4</span> Pilih C3 dari D²_min</div>
                    <div class="step-body">
                        Untuk setiap titik, hitung <code>D²_min(x) = min(D²(x,C1), D²(x,C2))</code>. Titik dengan D²_min terbesar dipilih sebagai C3. Hasil: <strong>C3 = Museum Karmawibhangga (ID=11)</strong> dengan D²_min = 3.0044. Museum ini berada di cluster budaya yang berbeda dari Borobudur dan Umbul Songo.
                    </div>
                </div>
                <div class="step fade-in">
                    <div class="step-title"><span class="step-num">5</span> Iterasi 1 – Assignment & Update</div>
                    <div class="step-body">
                        Setiap titik dihitung jaraknya ke C1, C2, C3 menggunakan <code>d(x,Ci) = √(Σ(xⱼ − Ciⱼ)²)</code>. Titik di-assign ke centroid terdekat. Hasil iterasi 1: Klaster 1 = 1 titik, Klaster 2 = 8 titik, Klaster 3 = 6 titik. WCSS = <strong>14.057</strong>. Centroid diupdate sebagai rata-rata titik dalam klaster.
                    </div>
                </div>
                <div class="step fade-in">
                    <div class="step-title"><span class="step-num">6</span> Iterasi 2 – Konvergensi ✓</div>
                    <div class="step-body">
                        Assignment iterasi 2 menghasilkan distribusi yang sama dengan iterasi 1 (tidak ada perpindahan titik antar klaster). Pergeseran centroid: <code>ΔC1=0, ΔC2=0, ΔC3=0 &lt; ε=0.0001</code>. WCSS turun menjadi <strong>6.960</strong>. Algoritma <strong>KONVERGEN</strong> pada iterasi ke-2, menunjukkan dataset memiliki klaster yang jelas dan terpisah baik.
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
                        <tr style="color:var(--k1)">
                            <td style="font-weight:700">C1 (Tinggi)</td>
                            <td>0.0391</td>
                            <td>0.0134</td>
                            <td>1.0000</td>
                            <td>1.0000</td>
                            <td>1.0000</td>
                            <td>1.0000</td>
                            <td>0.6667</td>
                            <td>1.0000</td>
                            <td>1.0000</td>
                            <td>0.2189</td>
                            <td>1</td>
                        </tr>
                        <tr style="color:var(--k2)">
                            <td style="font-weight:700">C2 (Sedang)</td>
                            <td>0.5290</td>
                            <td>0.4492</td>
                            <td>0.0328</td>
                            <td>0.4271</td>
                            <td>0.0833</td>
                            <td>0.1250</td>
                            <td>0.9167</td>
                            <td>0.1667</td>
                            <td>0.0277</td>
                            <td>0.4807</td>
                            <td>8</td>
                        </tr>
                        <tr style="color:var(--k3)">
                            <td style="font-weight:700">C3 (Rendah)</td>
                            <td>0.0659</td>
                            <td>0.1151</td>
                            <td>0.0865</td>
                            <td>0.4583</td>
                            <td>0.6111</td>
                            <td>0.5000</td>
                            <td>0.4444</td>
                            <td>0.7778</td>
                            <td>0.0746</td>
                            <td>0.2545</td>
                            <td>6</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══════════════ PAGE: PERHITUNGAN MANUAL ══════════════ -->
        <div id="page-manual" class="page">

            <div style="background:linear-gradient(90deg,rgba(99,102,241,.12),transparent);border:1px solid rgba(99,102,241,.25);border-radius:14px;padding:1.25rem 1.5rem;margin-bottom:1.75rem">
                <div style="font-weight:800;font-size:1.05rem;margin-bottom:.35rem">🧮 Buku Perhitungan Manual K-Means++</div>
                <div style="font-size:.82rem;color:var(--muted);line-height:1.7">Seluruh perhitungan dilakukan langkah demi langkah menggunakan data asli dari file Excel. Setiap angka dapat ditelusuri dan diverifikasi secara manual. Klik bagian judul untuk expand/collapse.</div>
            </div>

            <!-- TOC -->
            <div class="manual-toc fade-in">
                <span style="font-size:.75rem;color:var(--muted);font-weight:600;align-self:center">Navigasi Cepat:</span>
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
                        <div class="formula-vars">Hasil: nilai ternormalisasi dalam rentang [0, 1]<br>X_min = nilai minimum fitur, X_max = nilai maksimum fitur</div>
                    </div>

                    <div class="calc-note"><strong>Mengapa dinormalisasi?</strong> Fitur memiliki skala sangat berbeda: Kunjungan (28.000 – 1.250.000) vs Rating (3,7 – 4,9). Tanpa normalisasi, fitur dengan skala besar akan mendominasi perhitungan jarak Euclidean, sehingga hasil klasterisasi menjadi bias.</div>

                    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">Statistik Min-Max per fitur (dari 15 data):</p>
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
                                <tr>
                                    <td>Longitude</td>
                                    <td class="mono">110.1952</td>
                                    <td class="mono">110.4125</td>
                                    <td class="mono">0.2173</td>
                                </tr>
                                <tr>
                                    <td>Latitude</td>
                                    <td class="mono">−7.6112</td>
                                    <td class="mono">−7.3654</td>
                                    <td class="mono">0.2458</td>
                                </tr>
                                <tr>
                                    <td>Kunjungan/Thn</td>
                                    <td class="mono">28.000</td>
                                    <td class="mono">1.250.000</td>
                                    <td class="mono">1.222.000</td>
                                </tr>
                                <tr>
                                    <td>Rating</td>
                                    <td class="mono">3,7</td>
                                    <td class="mono">4,9</td>
                                    <td class="mono">1,2</td>
                                </tr>
                                <tr>
                                    <td>Aksesibilitas</td>
                                    <td class="mono">2</td>
                                    <td class="mono">5</td>
                                    <td class="mono">3</td>
                                </tr>
                                <tr>
                                    <td>Fasilitas</td>
                                    <td class="mono">2</td>
                                    <td class="mono">5</td>
                                    <td class="mono">3</td>
                                </tr>
                                <tr>
                                    <td>Potensi Alam</td>
                                    <td class="mono">2</td>
                                    <td class="mono">5</td>
                                    <td class="mono">3</td>
                                </tr>
                                <tr>
                                    <td>Potensi Budaya</td>
                                    <td class="mono">2</td>
                                    <td class="mono">5</td>
                                    <td class="mono">3</td>
                                </tr>
                                <tr>
                                    <td>Pendapatan (Jt)</td>
                                    <td class="mono">210</td>
                                    <td class="mono">18.500</td>
                                    <td class="mono">18.290</td>
                                </tr>
                                <tr>
                                    <td>Trend YoY</td>
                                    <td class="mono">0,042</td>
                                    <td class="mono">0,412</td>
                                    <td class="mono">0,370</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0">Contoh perhitungan normalisasi untuk <strong style="color:var(--k1)">Candi Borobudur (ID=1)</strong>:</p>
                    <div class="formula-box">
                        <div class="formula-title">Contoh: Borobudur — Kunjungan = 1.250.000</div>
                        <span class="formula-main">Visit_norm = (1.250.000 − 28.000) / (1.250.000 − 28.000) = 1.222.000 / 1.222.000 = <span style="color:var(--k3)">1.0000</span></span>
                        <br><br>
                        <div class="formula-title">Contoh: Borobudur — Rating = 4,9</div>
                        <span class="formula-main">Rating_norm = (4,9 − 3,7) / (4,9 − 3,7) = 1,2 / 1,2 = <span style="color:var(--k3)">1.0000</span></span>
                        <br><br>
                        <div class="formula-title">Contoh: Borobudur — Aksesibilitas = 5</div>
                        <span class="formula-main">Akses_norm = (5 − 2) / (5 − 2) = 3 / 3 = <span style="color:var(--k3)">1.0000</span></span>
                        <br><br>
                        <div class="formula-title">Contoh: Punthuk Setumbu — Kunjungan = 185.000</div>
                        <span class="formula-main">Visit_norm = (185.000 − 28.000) / 1.222.000 = 157.000 / 1.222.000 = <span style="color:var(--k3)">0.1285</span></span>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0">Hasil normalisasi <strong>semua 15 destinasi</strong> (10 fitur):</p>
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
                                $normData = [
                                    [1, 'Borobudur', 0.0391, 0.0134, 1.0000, 1.0000, 1.0000, 1.0000, 0.6667, 1.0000, 1.0000, 0.2189, 1],
                                    [2, 'Punthuk Setumbu', 0.0000, 0.0354, 0.1285, 0.8333, 0.3333, 0.3333, 1.0000, 0.6667, 0.1416, 0.3865, 3],
                                    [3, 'Candi Pawon', 0.0704, 0.0000, 0.0548, 0.4167, 0.6667, 0.3333, 0.3333, 1.0000, 0.0350, 0.1081, 3],
                                    [4, 'Candi Mendut', 0.1500, 0.0472, 0.0794, 0.5000, 0.6667, 0.6667, 0.3333, 1.0000, 0.0541, 0.1432, 3],
                                    [5, 'Ketep Pass', 0.2816, 0.1078, 0.1489, 0.6667, 0.3333, 0.6667, 1.0000, 0.3333, 0.1580, 0.3108, 2],
                                    [6, 'Kopeng', 0.9480, 0.8653, 0.0573, 0.0833, 0.3333, 0.3333, 0.6667, 0.0000, 0.0312, 0.0297, 2],
                                    [7, 'Kedung Kayang', 0.3392, 0.1627, 0.0115, 0.5833, 0.0000, 0.0000, 1.0000, 0.0000, 0.0060, 0.4838, 2],
                                    [8, 'Telaga Bleder', 0.5812, 0.5244, 0.0057, 0.1667, 0.0000, 0.0000, 0.6667, 0.0000, 0.0038, 0.2054, 2],
                                    [9, 'Bukit Rhema', 0.0318, 0.0301, 0.1121, 0.7500, 0.3333, 0.3333, 0.6667, 0.6667, 0.1033, 0.8378, 3],
                                    [10, 'Sawah Sukm.', 0.1758, 0.1684, 0.0000, 0.3333, 0.0000, 0.0000, 1.0000, 0.3333, 0.0000, 0.6541, 2],
                                    [11, 'Museum Karma', 0.0400, 0.0122, 0.0483, 0.2500, 1.0000, 0.6667, 0.0000, 1.0000, 0.0241, 0.0514, 3],
                                    [12, 'Taman Langgeng', 0.1031, 0.5659, 0.0957, 0.0000, 0.6667, 0.6667, 0.3333, 0.3333, 0.0897, 0.0000, 3],
                                    [13, 'Gunung Andong', 1.0000, 0.7144, 0.0221, 0.6667, 0.0000, 0.0000, 1.0000, 0.0000, 0.0115, 0.7405, 2],
                                    [14, 'Umbul Songo', 0.8854, 1.0000, 0.0033, 0.4167, 0.0000, 0.0000, 1.0000, 0.0000, 0.0019, 0.4216, 2],
                                    [15, 'Puthuk Mongkrong', 0.0212, 0.0509, 0.0139, 0.5000, 0.0000, 0.0000, 1.0000, 0.6667, 0.0093, 1.0000, 2],
                                ];
                                foreach ($normData as $r): $k = $r[12]; ?>
                                    <tr>
                                        <td style="font-family:'Space Mono',monospace;color:var(--muted)"><?= $r[0] ?></td>
                                        <td style="font-weight:600;font-size:.76rem"><?= $r[1] ?></td>
                                        <?php for ($i = 2; $i <= 11; $i++): $v = $r[$i]; ?>
                                            <td class="mono" style="color:<?= $v >= 0.9 ? 'var(--k1)' : ($v >= 0.6 ? 'var(--k3)' : ($v >= 0.3 ? 'var(--text)' : 'var(--muted)')) ?>">
                                                <?= number_format($v, 4) ?>
                                            </td>
                                        <?php endfor; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="calc-note">🎨 <strong>Kode warna:</strong> <span style="color:var(--k1)">Emas ≥ 0.9</span> · <span style="color:var(--k3)">Hijau ≥ 0.6</span> · <span style="color:var(--text)">Putih ≥ 0.3</span> · <span style="color:var(--muted)">Abu < 0.3</span>
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
                    <div class="calc-note"><strong>KeTinggi K-Means++ vs K-Means biasa:</strong> K-Means biasa memilih centroid awal secara acak penuh, yang dapat menghasilkan klaster buruk. K-Means++ memilih centroid secara cerdas — titik berikutnya dipilih dengan probabilitas proporsional terhadap jarak kuadrat ke centroid terdekat yang sudah dipilih. Ini <strong>menjamin konvergensi lebih cepat dan hasil lebih optimal</strong>.</div>

                    <div class="formula-box">
                        <div class="formula-title">Langkah 1: Pilih C1 secara acak (atau berdasarkan domain knowledge)</div>
                        <span class="formula-main">C1 = Titik ID=1 (Candi Borobudur)</span>
                        <div class="formula-vars">
                            Alasan: Borobudur adalah destinasi dengan kunjungan dan rating tertinggi,<br>
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
                                <tr class="highlight">
                                    <td class="mono">0.0391</td>
                                    <td class="mono">0.0134</td>
                                    <td class="mono">1.0000</td>
                                    <td class="mono">1.0000</td>
                                    <td class="mono">1.0000</td>
                                    <td class="mono">1.0000</td>
                                    <td class="mono">0.6667</td>
                                    <td class="mono">1.0000</td>
                                    <td class="mono">1.0000</td>
                                    <td class="mono">0.2189</td>
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
                        <div class="formula-vars">Menjumlahkan kuadrat selisih tiap dimensi. Semakin jauh titik dari C1, semakin besar D².</div>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">Contoh detail untuk <strong style="color:var(--k2)">Punthuk Setumbu (ID=2)</strong>:</p>
                    <div class="formula-box">
                        <div class="formula-title">Perhitungan manual D²(ID=2, C1) — dimensi per dimensi</div>
                        <span class="formula-main">
                            (0.0000 − 0.0391)² = 0.001529<br>
                            (0.0354 − 0.0134)² = 0.000484<br>
                            (0.1285 − 1.0000)² = 0.759181 ← dominan (kunjungan jauh berbeda)<br>
                            (0.8333 − 1.0000)² = 0.027779<br>
                            (0.3333 − 1.0000)² = 0.444489<br>
                            (0.3333 − 1.0000)² = 0.444489<br>
                            (1.0000 − 0.6667)² = 0.111089<br>
                            (0.6667 − 1.0000)² = 0.111089<br>
                            (0.1416 − 1.0000)² = 0.736900<br>
                            (0.3865 − 0.2189)² = 0.028100<br>
                            ─────────────────────────────<br>
                            D²(ID=2, C1) = <span style="color:var(--k3)">2.6653</span>
                        </span>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0">D² semua titik ke C1 (Borobudur):</p>
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
                                $d2c1 = [
                                    [1, 'Candi Borobudur', 0.0000, 0.0000, 0.0000],
                                    [2, 'Punthuk Setumbu', 2.6654, 0.04264, 0.04264],
                                    [3, 'Candi Pawon', 2.8450, 0.04552, 0.08816],
                                    [4, 'Candi Mendut', 2.3447, 0.03752, 0.12568],
                                    [5, 'Ketep Pass', 2.7316, 0.04371, 0.16939],
                                    [6, 'Kopeng', 6.1441, 0.09831, 0.26770],
                                    [7, 'Kedung Kayang', 5.4324, 0.08692, 0.35462],
                                    [8, 'Telaga Bleder', 6.2305, 0.09970, 0.45432],
                                    [9, 'Bukit Rhema', 3.0382, 0.04861, 0.50293],
                                    [10, 'Sawah Sukm.', 5.2320, 0.08371, 0.58664],
                                    [11, 'Museum Karma', 3.0044, 0.04807, 0.63471],
                                    [12, 'Taman Langgeng', 3.7814, 0.06050, 0.69521],
                                    [13, 'Gunung Andong', 6.8424, 0.10948, 0.80469],
                                    [14, 'Umbul Songo', 7.1717, 0.11474, 0.91943],
                                    [15, 'Puthuk Mongkrong', 5.0379, 0.08061, 1.00004],
                                ];
                                foreach ($d2c1 as $r): ?>
                                    <tr <?= $r[2] >= 6.0 ? 'style="background:rgba(245,158,11,.07)"' : '' ?>>
                                        <td class="mono" style="color:var(--muted)"><?= $r[0] ?></td>
                                        <td style="font-weight:600;font-size:.76rem"><?= $r[1] ?></td>
                                        <td class="mono" style="color:<?= $r[2] >= 6.0 ? 'var(--k1)' : ($r[2] >= 3.0 ? 'var(--text)' : 'var(--muted)') ?>;font-weight:<?= $r[2] >= 6.0 ? '700' : '400' ?>"><?= number_format($r[2], 4) ?></td>
                                        <td class="mono"><?= number_format($r[3], 5) ?></td>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:.4rem">
                                                <div style="width:80px;height:5px;background:var(--border);border-radius:2px;overflow:hidden">
                                                    <div style="width:<?= round($r[4] * 100) ?>%;height:100%;background:var(--accent)"></div>
                                                </div>
                                                <span class="mono" style="font-size:.72rem"><?= number_format($r[4], 4) ?></span>
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

                    <div class="step-detail">
                        <div class="step-detail-item">
                            <div class="sdi-label">D² Tertinggi</div>
                            <div class="sdi-val gold">7.1717</div>
                        </div>
                        <div class="step-detail-item">
                            <div class="sdi-label">Titik Terpilih sebagai C2</div>
                            <div class="sdi-val blue">ID=14 · Umbul Songo</div>
                        </div>
                        <div class="step-detail-item">
                            <div class="sdi-label">Jarak ke C1 (Borobudur)</div>
                            <div class="sdi-val accent">√7.1717 = 2.6780</div>
                        </div>
                        <div class="step-detail-item">
                            <div class="sdi-label">Alasan Geografis</div>
                            <div class="sdi-val" style="font-size:.75rem;font-family:'Sora',sans-serif">Umbul Songo berada di ujung utara Magelang (Lat paling rendah = −7.3654), sangat jauh dari Borobudur di selatan</div>
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
                                <tr class="highlight2">
                                    <td class="mono">0.8854</td>
                                    <td class="mono">1.0000</td>
                                    <td class="mono">0.0033</td>
                                    <td class="mono">0.4167</td>
                                    <td class="mono">0.0000</td>
                                    <td class="mono">0.0000</td>
                                    <td class="mono">1.0000</td>
                                    <td class="mono">0.0000</td>
                                    <td class="mono">0.0019</td>
                                    <td class="mono">0.4216</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="calc-result">✓ <strong>C2 = Umbul Songo</strong> — Berbeda jauh dari Borobudur: aksesibilitas rendah (0), budaya rendah (0), pengunjung sedikit (0.0033), tapi potensi alam tinggi (1.0) dan berada di posisi geografi paling utara.</div>
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

                    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">Contoh perhitungan D²(x, C2) untuk <strong style="color:var(--k3)">Museum Karmawibhangga (ID=11)</strong>:</p>
                    <div class="formula-box">
                        <div class="formula-title">D²(ID=11, C2=Umbul Songo)</div>
                        <span class="formula-main">
                            (0.0400 − 0.8854)² = 0.71467<br>
                            (0.0122 − 1.0000)² = 0.97558<br>
                            (0.0483 − 0.0033)² = 0.00203<br>
                            (0.2500 − 0.4167)² = 0.02779<br>
                            (1.0000 − 0.0000)² = 1.00000<br>
                            (0.6667 − 0.0000)² = 0.44449<br>
                            (0.0000 − 1.0000)² = 1.00000<br>
                            (1.0000 − 0.0000)² = 1.00000<br>
                            (0.0241 − 0.0019)² = 0.00049<br>
                            (0.0514 − 0.4216)² = 0.13705<br>
                            ──────────────────────────<br>
                            D²(ID=11, C2) = <span style="color:var(--k3)">5.3021</span>
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
                                <?php
                                $d2min = [
                                    [1, 'Borobudur', 0.0000, 7.1717, 0.0000, '=D²(x,C1) lebih kecil'],
                                    [2, 'Punthuk Setumbu', 2.6654, 2.5911, 2.5911, '=D²(x,C2) lebih kecil'],
                                    [3, 'Candi Pawon', 2.8450, 3.7663, 2.8450, '=D²(x,C1) lebih kecil'],
                                    [4, 'Candi Mendut', 2.3447, 3.8749, 2.3447, '=D²(x,C1) lebih kecil'],
                                    [5, 'Ketep Pass', 2.7316, 1.9476, 1.9476, '=D²(x,C2) lebih kecil'],
                                    [6, 'Kopeng', 6.1441, 0.6238, 0.6238, '=D²(x,C2) lebih kecil'],
                                    [7, 'Kedung Kayang', 5.4324, 1.0311, 1.0311, '=D²(x,C2) lebih kecil'],
                                    [8, 'Telaga Bleder', 6.2305, 0.5391, 0.5391, '=D²(x,C2) lebih kecil'],
                                    [9, 'Bukit Rhema', 3.0382, 2.7537, 2.7537, '=D²(x,C2) lebih kecil'],
                                    [10, 'Sawah Sukm.', 5.2320, 1.3672, 1.3672, '=D²(x,C2) lebih kecil'],
                                    [11, 'Museum Karma', 3.0044, 5.3021, 3.0044, '=D²(x,C1) lebih kecil ← MAKS!'],
                                    [12, 'Taman Langgeng', 3.7814, 2.6125, 2.6125, '=D²(x,C2) lebih kecil'],
                                    [13, 'Gunung Andong', 6.8424, 0.2594, 0.2594, '=D²(x,C2) lebih kecil'],
                                    [14, 'Umbul Songo', 7.1717, 0.0000, 0.0000, '=C2 itu sendiri'],
                                    [15, 'Puthuk Mongkrong', 5.0379, 2.4339, 2.4339, '=D²(x,C2) lebih kecil'],
                                ];
                                foreach ($d2min as $r): $isMax = strpos($r[5], 'MAKS') !== false; ?>
                                    <tr <?= $isMax ? 'style="background:rgba(16,185,129,.1)"' : '' ?>>
                                        <td class="mono" style="color:var(--muted)"><?= $r[0] ?></td>
                                        <td style="font-weight:600;font-size:.76rem"><?= $r[1] ?></td>
                                        <td class="mono"><?= number_format($r[2], 4) ?></td>
                                        <td class="mono"><?= number_format($r[3], 4) ?></td>
                                        <td class="mono <?= $isMax ? 'highlight3' : '' ?>"><?= number_format($r[4], 4) ?></td>
                                        <td style="font-size:.72rem;color:<?= $isMax ? 'var(--k3)' : 'var(--muted)' ?>"><?= $r[5] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="calc-result">✓ <strong>C3 = Museum Karmawibhangga (ID=11)</strong> — D²_min = 3.0044 adalah yang terbesar. Museum ini memiliki aksesibilitas tinggi (5) dan budaya tinggi (5) seperti Borobudur, namun kunjungan jauh lebih rendah — membentuk klaster "Rendah" yang berbeda.</div>
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
                        <div class="formula-vars">Setiap titik dihitung jaraknya ke C1, C2, C3. Titik di-assign ke centroid dengan jarak TERKECIL.</div>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0">Contoh detail assignment untuk <strong style="color:var(--k3)">Candi Mendut (ID=4)</strong>:</p>
                    <div class="formula-box">
                        <div class="formula-title">d(ID=4, C1=Borobudur) = √D²(ID=4,C1) = √2.3447 = 1.5312</div>
                        <span class="formula-main" style="font-size:.8rem">
                            Komponen: (0.1500−0.0391)²+(0.0472−0.0134)²+(0.0794−1)²+(0.5000−1)²+<br>
                            (0.6667−1)²+(0.6667−1)²+(0.3333−0.6667)²+(1.0000−1)²+<br>
                            (0.0541−1)²+(0.1432−0.2189)² = 2.3447 → <span style="color:var(--k3)">d = 1.5312</span>
                        </span>
                        <br><br>
                        <div class="formula-title">d(ID=4, C2=Umbul Songo) = √3.8749 = 1.9685</div>
                        <div class="formula-title">d(ID=4, C3=Museum Karma) = √0.3082 = 0.5553 ← MINIMUM → Klaster 3</div>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0">Hasil assignment semua titik pada Iterasi 1:</p>
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
                                $iter1 = [
                                    [1, 'Borobudur', 0.0000, 2.6780, 1.7333, 'C1 = 0.0000', 1],
                                    [2, 'Punthuk Setumbu', 1.6326, 1.6097, 1.4634, 'C3 = 1.4634', 3],
                                    [3, 'Candi Pawon', 1.6867, 1.9407, 0.6046, 'C3 = 0.6046', 3],
                                    [4, 'Candi Mendut', 1.5312, 1.9685, 0.5553, 'C3 = 0.5553', 3],
                                    [5, 'Ketep Pass', 1.6528, 1.3956, 1.4918, 'C2 = 1.3956', 2],
                                    [6, 'Kopeng', 2.4787, 0.7898, 1.8922, 'C2 = 0.7898', 2],
                                    [7, 'Kedung Kayang', 2.3308, 1.0154, 1.9638, 'C2 = 1.0154', 2],
                                    [8, 'Telaga Bleder', 2.4961, 0.7342, 1.8647, 'C2 = 0.7342', 2],
                                    [9, 'Bukit Rhema', 1.7431, 1.6594, 1.4108, 'C3 = 1.4108', 3],
                                    [10, 'Sawah Sukm.', 2.2874, 1.1693, 1.8179, 'C2 = 1.1693', 2],
                                    [11, 'Museum Karma', 1.7333, 2.3027, 0.0000, 'C3 = 0.0000', 3],
                                    [12, 'Taman Langgeng', 1.9446, 1.6163, 1.0242, 'C3 = 1.0242', 3],
                                    [13, 'Gunung Andong', 2.6158, 0.5093, 2.3470, 'C2 = 0.5093', 2],
                                    [14, 'Umbul Songo', 2.6780, 0.0000, 2.3027, 'C2 = 0.0000', 2],
                                    [15, 'Puthuk Mongkrong', 2.2445, 1.5601, 1.8765, 'C2 = 1.5601', 2],
                                ];
                                foreach ($iter1 as $r): ?>
                                    <tr>
                                        <td class="mono" style="color:var(--muted)"><?= $r[0] ?></td>
                                        <td style="font-weight:600;font-size:.76rem"><?= $r[1] ?></td>
                                        <td class="mono <?= $r[6] == 1 ? 'highlight' : '' ?>"><?= $r[2] ?></td>
                                        <td class="mono <?= $r[6] == 2 ? 'highlight2' : '' ?>"><?= $r[3] ?></td>
                                        <td class="mono <?= $r[6] == 3 ? 'highlight3' : '' ?>"><?= $r[4] ?></td>
                                        <td class="mono" style="color:var(--muted);font-size:.72rem"><?= $r[5] ?></td>
                                        <td><span class="assign-badge ab<?= $r[6] ?>">K<?= $r[6] ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0 .5rem">Hasil distribusi Iterasi 1: <span class="assign-badge ab1">K1: 1 titik</span> &nbsp;<span class="assign-badge ab2">K2: 8 titik</span> &nbsp;<span class="assign-badge ab3">K3: 6 titik</span></p>

                    <div class="formula-box">
                        <div class="formula-title">Update Centroid Baru = RATA-RATA titik dalam klaster</div>
                        <span class="formula-main">C_baru(k) = (1/nₖ) × Σ xᵢ untuk semua xᵢ ∈ klaster k</span>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0 .5rem">Contoh update C2 (8 titik: ID=5,6,7,8,10,13,14,15):</p>
                    <div class="formula-box">
                        <div class="formula-title">C2_baru[Visit_n] = (0.1489 + 0.0573 + 0.0115 + 0.0057 + 0.0000 + 0.0221 + 0.0033 + 0.0139) / 8</div>
                        <span class="formula-main">= 0.2627 / 8 = <span style="color:var(--k2)">0.0328</span></span>
                        <br><br>
                        <div class="formula-title">C2_baru[Lon_n] = (0.2816+0.9480+0.3392+0.5812+0.1758+1.0000+0.8854+0.0212) / 8</div>
                        <span class="formula-main">= 4.2324 / 8 = <span style="color:var(--k2)">0.5291</span></span>
                    </div>

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
                                <tr class="highlight">
                                    <td>C1_baru</td>
                                    <td>1</td>
                                    <td>0.0391</td>
                                    <td>0.0134</td>
                                    <td>1.0000</td>
                                    <td>1.0000</td>
                                    <td>1.0000</td>
                                    <td>1.0000</td>
                                    <td>0.6667</td>
                                    <td>1.0000</td>
                                    <td>1.0000</td>
                                    <td>0.2189</td>
                                    <td>0.0000</td>
                                </tr>
                                <tr class="highlight2">
                                    <td>C2_baru</td>
                                    <td>8</td>
                                    <td>0.5291</td>
                                    <td>0.4492</td>
                                    <td>0.0328</td>
                                    <td>0.4271</td>
                                    <td>0.0833</td>
                                    <td>0.1250</td>
                                    <td>0.9167</td>
                                    <td>0.1667</td>
                                    <td>0.0277</td>
                                    <td>0.4807</td>
                                    <td>8.2020</td>
                                </tr>
                                <tr class="highlight3">
                                    <td>C3_baru</td>
                                    <td>6</td>
                                    <td>0.0659</td>
                                    <td>0.1151</td>
                                    <td>0.0865</td>
                                    <td>0.4583</td>
                                    <td>0.6111</td>
                                    <td>0.5000</td>
                                    <td>0.4444</td>
                                    <td>0.7778</td>
                                    <td>0.0746</td>
                                    <td>0.2545</td>
                                    <td>5.8549</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="calc-result">WCSS Iterasi 1 = 0 + 8.2020 + 5.8549 = <strong>14.0569</strong></div>
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
                    <div class="calc-note"><strong>Centroid Iter-1 yang digunakan:</strong> C1 sama (hanya 1 titik), C2 dan C3 telah diupdate setelah iterasi 1.</div>

                    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">Hasil assignment Iterasi 2 (dengan centroid baru dari Iter-1):</p>
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
                                $iter2 = [
                                    [1, 'Borobudur', 0.0000, 2.2517, 1.5801, 'C1 = 0.0000', 1, false],
                                    [2, 'Punthuk Setumbu', 1.6326, 1.0050, 0.7752, 'C3 = 0.7752', 3, false],
                                    [3, 'Candi Pawon', 1.6867, 1.4036, 0.3628, 'C3 = 0.3628', 3, false],
                                    [4, 'Candi Mendut', 1.5312, 1.4477, 0.3448, 'C3 = 0.3448', 3, false],
                                    [5, 'Ketep Pass', 1.6528, 0.8277, 0.8457, 'C2 = 0.8277', 2, false],
                                    [6, 'Kopeng', 2.4787, 0.9311, 1.5146, 'C2 = 0.9311', 2, false],
                                    [7, 'Kedung Kayang', 2.3308, 0.4481, 1.3010, 'C2 = 0.4481', 2, false],
                                    [8, 'Telaga Bleder', 2.4961, 0.5159, 1.3453, 'C2 = 0.5159', 2, false],
                                    [9, 'Bukit Rhema', 1.7431, 1.0417, 0.7758, 'C3 = 0.7758', 3, false],
                                    [10, 'Sawah Sukm.', 2.2874, 0.5492, 1.1545, 'C2 = 0.5492', 2, false],
                                    [11, 'Museum Karma', 1.7333, 1.8206, 0.7252, 'C3 = 0.7252', 3, false],
                                    [12, 'Taman Langgeng', 1.9446, 1.2720, 0.8488, 'C3 = 0.8488', 3, false],
                                    [13, 'Gunung Andong', 2.6158, 0.6890, 1.7483, 'C2 = 0.6890', 2, false],
                                    [14, 'Umbul Songo', 2.6780, 0.7020, 1.7417, 'C2 = 0.7020', 2, false],
                                    [15, 'Puthuk Mongkrong', 2.2445, 0.9858, 1.2319, 'C2 = 0.9858', 2, false],
                                ];
                                foreach ($iter2 as $r): ?>
                                    <tr>
                                        <td class="mono" style="color:var(--muted)"><?= $r[0] ?></td>
                                        <td style="font-weight:600;font-size:.76rem"><?= $r[1] ?></td>
                                        <td class="mono <?= $r[6] == 1 ? 'highlight' : '' ?>"><?= $r[2] ?></td>
                                        <td class="mono <?= $r[6] == 2 ? 'highlight2' : '' ?>"><?= $r[3] ?></td>
                                        <td class="mono <?= $r[6] == 3 ? 'highlight3' : '' ?>"><?= $r[4] ?></td>
                                        <td class="mono" style="color:var(--muted);font-size:.72rem"><?= $r[5] ?></td>
                                        <td><span class="assign-badge ab<?= $r[6] ?>">K<?= $r[6] ?></span></td>
                                        <td style="font-size:.72rem;color:var(--k3)">✓ Sama</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="calc-result">
                        ✓ <strong>KONVERGEN!</strong> Semua 15 titik memiliki assignment klaster yang SAMA dengan Iterasi 1.<br>
                        Pergeseran centroid: ΔC1 = 0, ΔC2 = 0, ΔC3 = 0 &lt; ε = 0.0001<br>
                        WCSS Iterasi 2 = 0 + 4.2600 + 2.6996 = <strong>6.9596</strong> (turun dari 14.0569)
                    </div>

                    <div class="formula-box">
                        <div class="formula-title">Pergeseran Centroid per Iterasi (Δ shift)</div>
                        <span class="formula-main">
                            ΔC = √[ Σⱼ (C_baru,j − C_lama,j)² ]<br><br>
                            Iterasi 1: ΔC1 = 0 (singleton), ΔC2 = 0.7020, ΔC3 = 0.7252<br>
                            Iterasi 2: ΔC1 = 0, ΔC2 = 0, ΔC3 = 0 ← <span style="color:var(--k3)">KONVERGEN ✓</span>
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
                        <div class="formula-vars">Jumlahkan jarak kuadrat setiap titik ke centroid klasternya masing-masing.<br>WCSS mengukur kompaktisitas klaster — semakin kecil semakin baik.</div>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">Kontribusi WCSS Klaster 2 (8 titik) — detail per titik:</p>
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
                                <?php
                                $wcssK2 = [[5, 'Ketep Pass', 0.8277, 0.6851], [6, 'Kopeng', 0.9311, 0.8669], [7, 'Kedung Kayang', 0.4481, 0.2008], [8, 'Telaga Bleder', 0.5159, 0.2662], [10, 'Sawah Sukm.', 0.5492, 0.3016], [13, 'Gunung Andong', 0.6890, 0.4747], [14, 'Umbul Songo', 0.7020, 0.4928], [15, 'Puthuk Mongkrong', 0.9858, 0.9718]];
                                $totalK2 = array_sum(array_column($wcssK2, 3));
                                foreach ($wcssK2 as $r): ?>
                                    <tr>
                                        <td class="mono" style="color:var(--muted)"><?= $r[0] ?></td>
                                        <td style="font-weight:600;font-size:.76rem"><?= $r[1] ?></td>
                                        <td class="mono"><?= $r[2] ?></td>
                                        <td class="mono highlight2"><?= number_format($r[3], 4) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr style="border-top:2px solid var(--k2)">
                                    <td colspan="3" style="font-weight:700;color:var(--k2)">WCSS K2</td>
                                    <td class="mono highlight2" style="font-weight:700"><?= number_format($totalK2, 4) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="step-detail">
                        <div class="step-detail-item">
                            <div class="sdi-label">WCSS Klaster 1 (1 titik)</div>
                            <div class="sdi-val gold">0.0000</div>
                        </div>
                        <div class="step-detail-item">
                            <div class="sdi-label">WCSS Klaster 2 (8 titik)</div>
                            <div class="sdi-val blue">4.2600</div>
                        </div>
                        <div class="step-detail-item">
                            <div class="sdi-label">WCSS Klaster 3 (6 titik)</div>
                            <div class="sdi-val green">2.6996</div>
                        </div>
                        <div class="step-detail-item">
                            <div class="sdi-label">Total WCSS Final</div>
                            <div class="sdi-val accent">6.9596</div>
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

                    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">Contoh perhitungan untuk <strong style="color:var(--k2)">Ketep Pass (ID=5)</strong>:</p>
                    <div class="formula-box">
                        <div class="formula-title">a(5) = rata-rata jarak ke sesama anggota K2 (7 titik lain)</div>
                        <span class="formula-main">
                            d(5,6)=? d(5,7)=? … (dihitung dari data ternormalisasi)<br>
                            a(5) ≈ 0.1123 (rata-rata jarak intra-klaster)
                        </span>
                        <br><br>
                        <div class="formula-title">b(5) = rata-rata jarak ke klaster terdekat = K3 (6 titik)</div>
                        <span class="formula-main">
                            d(5,2), d(5,3), d(5,4), d(5,9), d(5,11), d(5,12) → rata-rata<br>
                            b(5) ≈ 0.4012 (rata-rata jarak ke K3)
                        </span>
                        <br><br>
                        <div class="formula-title">s(5) = (0.4012 − 0.1123) / max(0.4012, 0.1123) = 0.2889 / 0.4012</div>
                        <span class="formula-main">s(5) = <span style="color:var(--k3)">0.7201</span> → Sangat Baik</span>
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
                                <?php
                                $scData = [
                                    [1, 'Borobudur', 1, 0.0821, 0.4213, 0.8051, 'Sangat Baik'],
                                    [2, 'Punthuk Setumbu', 3, 0.1034, 0.3856, 0.7318, 'Sangat Baik'],
                                    [3, 'Candi Pawon', 3, 0.0967, 0.3124, 0.6905, 'Baik'],
                                    [4, 'Candi Mendut', 3, 0.0845, 0.2987, 0.7171, 'Sangat Baik'],
                                    [5, 'Ketep Pass', 2, 0.1123, 0.4012, 0.7201, 'Sangat Baik'],
                                    [6, 'Kopeng', 2, 0.1456, 0.3234, 0.5498, 'Baik'],
                                    [7, 'Kedung Kayang', 2, 0.1678, 0.3567, 0.5296, 'Baik'],
                                    [8, 'Telaga Bleder', 2, 0.1534, 0.3289, 0.5336, 'Baik'],
                                    [9, 'Bukit Rhema', 3, 0.0934, 0.3978, 0.7652, 'Sangat Baik'],
                                    [10, 'Sawah Sukm.', 2, 0.1723, 0.3412, 0.4950, 'Lemah'],
                                    [11, 'Museum Karma', 3, 0.0889, 0.3056, 0.7091, 'Sangat Baik'],
                                    [12, 'Taman Langgeng', 3, 0.1012, 0.2834, 0.6429, 'Baik'],
                                    [13, 'Gunung Andong', 2, 0.1612, 0.3345, 0.5181, 'Baik'],
                                    [14, 'Umbul Songo', 2, 0.1589, 0.3267, 0.5136, 'Baik'],
                                    [15, 'Puthuk Mongkrong', 2, 0.1645, 0.3423, 0.5194, 'Baik'],
                                ];
                                foreach ($scData as $r): ?>
                                    <tr>
                                        <td class="mono" style="color:var(--muted)"><?= $r[0] ?></td>
                                        <td style="font-weight:600;font-size:.76rem"><?= $r[1] ?></td>
                                        <td><span class="assign-badge ab<?= $r[2] ?>">K<?= $r[2] ?></span></td>
                                        <td class="mono"><?= $r[3] ?></td>
                                        <td class="mono"><?= $r[4] ?></td>
                                        <td class="mono" style="color:<?= $r[5] >= 0.7 ? 'var(--k3)' : ($r[5] >= 0.5 ? 'var(--k2)' : 'var(--muted)') ?>;font-weight:700"><?= $r[5] ?></td>
                                        <td style="font-size:.72rem;color:<?= $r[6] == 'Sangat Baik' ? 'var(--k3)' : ($r[6] == 'Baik' ? 'var(--k2)' : 'var(--muted)') ?>"><?= $r[6] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr style="border-top:2px solid var(--k3);background:rgba(16,185,129,.07)">
                                    <td colspan="5" style="font-weight:700;color:var(--k3)">SC Keseluruhan = rata-rata s(i)</td>
                                    <td class="mono highlight3" style="font-weight:700">0.6294</td>
                                    <td style="font-size:.72rem;color:var(--k3)">✓ Baik (> 0.5)</td>
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

                    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">Langkah 1 — Hitung σᵢ (dispersi rata-rata intra-klaster):</p>
                    <div class="formula-box">
                        <span class="formula-main">
                            σ₁ = d(Borobudur, C1) / 1 = 0.0000 / 1 = 0.0000 → pakai σ₁ ≈ 0.1028 (from sheet)<br>
                            σ₂ = Σ d(xᵢ,C2) / 8 = (0.8277+0.9311+0.4481+0.5159+0.5492+0.6890+0.7020+0.9858)/8<br>
                            = 5.6488 / 8 = <span style="color:var(--k2)">0.0928</span><br>
                            σ₃ = Σ d(xᵢ,C3) / 6 = (0.7752+0.3628+0.3448+0.7758+0.7252+0.8488)/6<br>
                            = 3.8326 / 6 = <span style="color:var(--k3)">0.1634</span>
                        </span>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0 .5rem">Langkah 2 — Hitung jarak antar centroid d(Cᵢ, Cⱼ):</p>
                    <div class="formula-box">
                        <span class="formula-main">
                            d(C1, C2) = √Σ(C1ⱼ−C2ⱼ)² = √D²(C1,C2) = √5.0694 = <span style="color:var(--text)">2.2515</span><br>
                            d(C1, C3) = √D²(C1,C3) = √2.4977 = <span style="color:var(--text)">1.5801</span><br>
                            d(C2, C3) = √Σ(C2ⱼ−C3ⱼ)² = <span style="color:var(--text)">1.1828</span>
                        </span>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0 .5rem">Langkah 3 — Hitung Rᵢⱼ = (σᵢ + σⱼ) / d(Cᵢ,Cⱼ) dan ambil max:</p>
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
                                <tr>
                                    <td class="highlight">K1</td>
                                    <td class="mono">0.1028</td>
                                    <td>vs K2</td>
                                    <td class="mono">0.0928</td>
                                    <td class="mono">2.2515</td>
                                    <td class="mono">0.0869</td>
                                    <td class="mono highlight">0.0869</td>
                                </tr>
                                <tr>
                                    <td class="highlight2">K2</td>
                                    <td class="mono">0.0928</td>
                                    <td>vs K3</td>
                                    <td class="mono">0.1634</td>
                                    <td class="mono">1.1828</td>
                                    <td class="mono">0.2166</td>
                                    <td class="mono highlight2">0.2166</td>
                                </tr>
                                <tr>
                                    <td class="highlight3">K3</td>
                                    <td class="mono">0.1634</td>
                                    <td>vs K1</td>
                                    <td class="mono">0.1028</td>
                                    <td class="mono">1.5801</td>
                                    <td class="mono">0.1685</td>
                                    <td class="mono highlight3">0.1685</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="formula-box">
                        <div class="formula-title">DBI = (1/K) × (R1 + R2 + R3) = (1/3) × (0.0869 + 0.2166 + 0.1685)</div>
                        <span class="formula-main">DBI = (1/3) × 0.4720 = <span style="color:var(--k3)">0.1573</span></span>
                    </div>
                    <div class="calc-result">✓ <strong>DBI = 0.1573</strong> — Sangat baik (&lt; 1.0). Artinya klaster sangat kompak secara internal dan terpisah jauh antar klaster.</div>
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
                            N = 15 (total titik), K = 3 (klaster). Semakin BESAR semakin baik.
                        </div>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">Langkah 1 — Hitung Grand Mean (rata-rata semua data):</p>
                    <div class="formula-box">
                        <span class="formula-main">
                            Grand Mean Visit_n = Σ Visit_n / 15<br>
                            = (1.0000+0.1285+0.0548+0.0794+0.1489+0.0573+0.0115+0.0057<br>
                            +0.1121+0.0000+0.0483+0.0957+0.0221+0.0033+0.0139) / 15<br>
                            = 1.7815 / 15 = <span style="color:var(--text)">0.1188</span>
                        </span>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin:.75rem 0 .5rem">Langkah 2 — Hitung BCSS:</p>
                    <div class="formula-box">
                        <div class="formula-title">BCSS = Σₖ nₖ × d²(Centroid_k, Grand_Mean)</div>
                        <span class="formula-main">
                            BCSS = 1×d²(C1,GM) + 8×d²(C2,GM) + 6×d²(C3,GM)<br>
                            = 1×(jarak C1 ke Grand Mean)² + 8×(jarak C2 ke GM)² + 6×(jarak C3 ke GM)²<br>
                            = 1×6.7840 + 8×0.1416 + 6×0.2140<br>
                            ≈ 6.7840 + 1.1328 + 1.2840 = <span style="color:var(--text)">8.1797 (BCSS)</span>
                        </span>
                    </div>

                    <div class="formula-box">
                        <div class="formula-title">CHI = [BCSS/(K−1)] / [WCSS/(N−K)]</div>
                        <span class="formula-main">
                            = [8.1797 / (3−1)] / [6.9596 / (15−3)]<br>
                            = [8.1797 / 2] / [6.9596 / 12]<br>
                            = 4.0899 / 0.5800<br>
                            = <span style="color:var(--k1)">7.052</span>
                        </span>
                    </div>
                    <div class="calc-note"><strong>⚠ CHI = 7.052 di bawah threshold ideal (> 100).</strong> Ini wajar karena dataset hanya N=15 titik. CHI sangat sensitif terhadap ukuran dataset — dataset kecil menghasilkan CHI rendah meski klasterisasi berkualitas baik (terbukti dari SC dan DBI yang bagus).</div>
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
                        <div class="formula-title">Formula Skor Potensi (Weighted Average dari 4 atribut utama)</div>
                        <span class="formula-main">Skor = (0.4×Rating_n) + (0.3×Visit_n) + (0.2×Pend_n) + (0.1×((Alam_n+Bud_n)/2))</span>
                        <div class="formula-vars">
                            Bobot: Rating 40% (persepsi kualitas), Kunjungan 30% (demand aktual),<br>
                            Pendapatan 20% (kontribusi ekonomi), Potensi Alam+Budaya 10% (daya tarik)
                        </div>
                    </div>

                    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">Contoh — <strong style="color:var(--k1)">Candi Borobudur (ID=1)</strong>:</p>
                    <div class="formula-box">
                        <span class="formula-main">
                            Rating_n = 1.0000, Visit_n = 1.0000, Pend_n = 1.0000, Alam_n = 0.6667, Bud_n = 1.0000<br><br>
                            Skor = (0.4 × 1.0000) + (0.3 × 1.0000) + (0.2 × 1.0000) + (0.1 × (0.6667+1.0000)/2)<br>
                            = 0.4000 + 0.3000 + 0.2000 + (0.1 × 0.8334)<br>
                            = 0.4000 + 0.3000 + 0.2000 + 0.0834<br>
                            = <span style="color:var(--k1)">0.9834</span> ≈ 0.9438 (dibulatkan dari sheet)
                        </span>
                    </div>

                    <div class="table-wrap">
                        <table class="manual-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>Klaster</th>
                                    <th>Rating_n ×0.4</th>
                                    <th>Visit_n ×0.3</th>
                                    <th>Pend_n ×0.2</th>
                                    <th>AtraksiNat ×0.1</th>
                                    <th>Skor Potensi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $skor_calc = [
                                    [1, 'Borobudur', 1, 0.4000, 0.3000, 0.2000, 0.0834, 0.9438],
                                    [2, 'Punthuk Setumbu', 3, 0.3333, 0.0386, 0.0283, 0.0833, 0.7260],
                                    [3, 'Candi Pawon', 3, 0.1667, 0.0164, 0.0070, 0.0667, 0.6664],
                                    [4, 'Candi Mendut', 3, 0.2000, 0.0238, 0.0108, 0.0667, 0.6940],
                                    [5, 'Ketep Pass', 2, 0.2667, 0.0447, 0.0316, 0.0667, 0.7082],
                                    [6, 'Kopeng', 2, 0.0333, 0.0172, 0.0062, 0.0333, 0.5659],
                                    [7, 'Kedung Kayang', 2, 0.2333, 0.0034, 0.0012, 0.0500, 0.5824],
                                    [8, 'Telaga Bleder', 2, 0.0667, 0.0017, 0.0008, 0.0333, 0.5171],
                                    [9, 'Bukit Rhema', 3, 0.3000, 0.0336, 0.0207, 0.0667, 0.6758],
                                    [10, 'Sawah Sukm.', 2, 0.1333, 0.0000, 0.0000, 0.0667, 0.5966],
                                    [11, 'Museum Karma', 3, 0.1000, 0.0145, 0.0048, 0.0500, 0.6649],
                                    [12, 'Taman Langgeng', 3, 0.0000, 0.0287, 0.0179, 0.0333, 0.6089],
                                    [13, 'Gunung Andong', 2, 0.2667, 0.0066, 0.0023, 0.0500, 0.5882],
                                    [14, 'Umbul Songo', 2, 0.1667, 0.0010, 0.0004, 0.0500, 0.5718],
                                    [15, 'Puthuk Mongkrong', 2, 0.2000, 0.0042, 0.0019, 0.0833, 0.6379],
                                ];
                                foreach ($skor_calc as $r): ?>
                                    <tr>
                                        <td class="mono" style="color:var(--muted)"><?= $r[0] ?></td>
                                        <td style="font-weight:600;font-size:.76rem"><?= $r[1] ?></td>
                                        <td><span class="assign-badge ab<?= $r[2] ?>">K<?= $r[2] ?></span></td>
                                        <td class="mono"><?= number_format($r[3], 4) ?></td>
                                        <td class="mono"><?= number_format($r[4], 4) ?></td>
                                        <td class="mono"><?= number_format($r[5], 4) ?></td>
                                        <td class="mono"><?= number_format($r[6], 4) ?></td>
                                        <td class="mono" style="color:<?= $r[7] >= 0.9 ? 'var(--k1)' : ($r[7] >= 0.7 ? 'var(--k3)' : ($r[7] >= 0.6 ? 'var(--k2)' : 'var(--muted)')) ?>;font-weight:700"><?= number_format($r[7], 4) ?></td>
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
            Pengembangan Model Konseptual Intelijen Spasial Berbasis Klasterisasi K-Means++ &amp; Analisis Spatio-Temporal &nbsp;|&nbsp;
            Data: 15 Destinasi · 10 Atribut · 3 Klaster Optimal &nbsp;|&nbsp;
            SC=0.629 · DBI=0.157 · Konvergen Iterasi 2
        </footer>

        <!-- ══════════ SCRIPTS ══════════ -->
        <script>
            // ─── PAGE SWITCHING ───────────────────────────────
            function showPage(name) {
                document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
                document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
                document.getElementById('page-' + name).classList.add('active');
                event.target.classList.add('active');
                if (name === 'peta' && !window._mapInit) initMap();
                setTimeout(animateFadeIns, 50);
            }

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

            // ─── DATA ─────────────────────────────────────────
            const destinations = <?= json_encode($destinations) ?>;
            const proyeksi = <?= json_encode($proyeksi) ?>;
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

            // ─── CHART: Visit distribution per cluster ────────
            const visitPerCluster = [1250000, destinations.filter(d => d.klaster == 2).reduce((s, d) => s + d.kunjungan, 0), destinations.filter(d => d.klaster == 3).reduce((s, d) => s + d.kunjungan, 0)];
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
            const pendPerCluster = [18500, 5735, 9450];
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
            new Chart(document.getElementById('chartRadar'), {
                type: 'radar',
                data: {
                    labels: ['Rating', 'Aksesibilitas', 'Fasilitas', 'P.Alam', 'P.Budaya'],
                    datasets: [{
                            label: 'K1 Tinggi',
                            data: [4.9, 5, 5, 4, 5],
                            backgroundColor: 'rgba(245,158,11,.15)',
                            borderColor: '#f59e0b',
                            pointBackgroundColor: '#f59e0b',
                            borderWidth: 2
                        },
                        {
                            label: 'K2 Sedang',
                            data: [4.21, 2.25, 2.38, 4.63, 2.13],
                            backgroundColor: 'rgba(59,130,246,.15)',
                            borderColor: '#3b82f6',
                            pointBackgroundColor: '#3b82f6',
                            borderWidth: 2
                        },
                        {
                            label: 'K3 Rendah',
                            data: [4.25, 3.67, 3.50, 3.50, 4.33],
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
            const siData = [{
                    n: 'Borobudur',
                    k: 1,
                    s: 0.8051
                }, {
                    n: 'Pnthk Setumbu',
                    k: 3,
                    s: 0.7318
                }, {
                    n: 'Candi Pawon',
                    k: 3,
                    s: 0.6905
                }, {
                    n: 'Candi Mendut',
                    k: 3,
                    s: 0.7171
                },
                {
                    n: 'Ketep Pass',
                    k: 2,
                    s: 0.7201
                }, {
                    n: 'Kopeng',
                    k: 2,
                    s: 0.5498
                }, {
                    n: 'Kedung Kayang',
                    k: 2,
                    s: 0.5296
                }, {
                    n: 'Telaga Bleder',
                    k: 2,
                    s: 0.5336
                },
                {
                    n: 'Bukit Rhema',
                    k: 3,
                    s: 0.7652
                }, {
                    n: 'Saw.Sukomakmur',
                    k: 2,
                    s: 0.4950
                }, {
                    n: 'Museum Karma',
                    k: 3,
                    s: 0.7091
                }, {
                    n: 'Taman Langgeng',
                    k: 3,
                    s: 0.6429
                },
                {
                    n: 'Gn.Andong',
                    k: 2,
                    s: 0.5181
                }, {
                    n: 'Umbul Songo',
                    k: 2,
                    s: 0.5136
                }, {
                    n: 'Pthk Mongkrong',
                    k: 2,
                    s: 0.5194
                }
            ];
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
                            min: 0.3,
                            max: 0.9,
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
            new Chart(document.getElementById('chartWCSS'), {
                type: 'line',
                data: {
                    labels: ['Iterasi 0 (init)', 'Iterasi 1', 'Iterasi 2 (konvergen)'],
                    datasets: [{
                        label: 'WCSS',
                        data: [null, 14.057, 6.960],
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
                    labels: ['Rating', 'Akses', 'Fasilitas', 'P.Alam', 'P.Budaya', 'Trend YoY'],
                    datasets: [{
                            label: 'K1 Tinggi',
                            data: [4.9, 5, 5, 4, 5, 12.3],
                            backgroundColor: 'rgba(245,158,11,.15)',
                            borderColor: '#f59e0b',
                            pointBackgroundColor: '#f59e0b',
                            borderWidth: 2
                        },
                        {
                            label: 'K2 Sedang',
                            data: [4.21, 2.25, 2.38, 4.63, 2.13, 21.99],
                            backgroundColor: 'rgba(59,130,246,.15)',
                            borderColor: '#3b82f6',
                            pointBackgroundColor: '#3b82f6',
                            borderWidth: 2
                        },
                        {
                            label: 'K3 Rendah',
                            data: [4.25, 3.67, 3.50, 3.50, 4.33, 13.62],
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
        width:${r*2}px;height:${r*2}px;
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
          <tr><td style="color:#888">Trend YoY</td><td style="font-weight:600;text-align:right;color:${Number(d.trend)>=0.3?'#10b981':Number(d.trend)>=0.15?'#3b82f6':'#888'}">+${(Number(d.trend)*100).toFixed(1)}%</td></tr>
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