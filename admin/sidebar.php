<?php declare(strict_types=1);

$adminName  = $_SESSION['admin_name'] ?? 'Admin';
$adminEmail = $_SESSION['admin_email'] ?? '';
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');

$navItems = [
    // Overview
    ['icon' => 'ph-chart-line-up',    'label' => 'Dashboard',       'file' => 'index.php',           'section' => 'Overview'],
    ['icon' => 'ph-chart-bar',        'label' => 'Analytics',       'file' => 'analytics.php',       'section' => 'Overview'],
    ['icon' => 'ph-heartbeat',        'label' => 'System Health',   'file' => 'system-health.php',   'section' => 'Overview'],

    // Users & Access
    ['icon' => 'ph-users-three',      'label' => 'Users',           'file' => 'users.php',           'section' => 'Users & Access'],
    ['icon' => 'ph-buildings',        'label' => 'Agency Portals',  'file' => 'agency-approvals.php','section' => 'Users & Access'],
    ['icon' => 'ph-share-network',    'label' => 'Referrals',       'file' => 'referrals.php',       'section' => 'Users & Access'],

    // Content & Marketplace
    ['icon' => 'ph-article',          'label' => 'Blog Posts',      'file' => 'blogs.php',           'section' => 'Content & Marketplace'],
    ['icon' => 'ph-storefront',       'label' => 'Marketplace',     'file' => 'listings.php',        'section' => 'Content & Marketplace'],
    ['icon' => 'ph-briefcase',        'label' => 'Jobs & Bounties', 'file' => 'jobs.php',            'section' => 'Content & Marketplace'],
    ['icon' => 'ph-star',             'label' => 'Client Reviews',  'file' => 'reviews.php',         'section' => 'Content & Marketplace'],
    ['icon' => 'ph-id-card',          'label' => 'Portfolios & vCards', 'file' => 'portfolios-vcards.php', 'section' => 'Content & Marketplace'],
    ['icon' => 'ph-note-pencil',      'label' => 'CMS',             'file' => 'cms.php',             'section' => 'Content & Marketplace'],

    // Revenue
    ['icon' => 'ph-currency-dollar',  'label' => 'Pricing Plans',   'file' => 'pricing.php',         'section' => 'Revenue'],
    ['icon' => 'ph-megaphone',        'label' => 'Campaigns',       'file' => 'campaigns.php',       'section' => 'Revenue'],
    ['icon' => 'ph-hand-coins',       'label' => 'Payouts',         'file' => 'payouts.php',         'section' => 'Revenue'],
    ['icon' => 'ph-receipt',          'label' => 'Transactions',    'file' => 'transactions.php',    'section' => 'Revenue'],

    // AI & Automation
    ['icon' => 'ph-sparkle',          'label' => 'UGC Studio',      'file' => 'ugc-studio.php',      'section' => 'AI & Automation'],
    ['icon' => 'ph-robot',            'label' => 'AI Media Buyer',  'file' => 'ai-media-buyer.php',  'section' => 'AI & Automation'],
    ['icon' => 'ph-paper-plane-tilt', 'label' => 'Social Publishing','file' => 'social-media.php',   'section' => 'AI & Automation'],
    ['icon' => 'ph-radar',            'label' => 'Server Tracking', 'file' => 'server-tracking.php', 'section' => 'AI & Automation'],

    // Communication
    ['icon' => 'ph-ticket',           'label' => 'Tickets',         'file' => 'tickets.php',         'section' => 'Communication'],
    ['icon' => 'ph-chat-circle',      'label' => 'P2P Chat',        'file' => 'p2p-chat.php',        'section' => 'Communication'],

    // Safety & Config
    ['icon' => 'ph-shield-check',     'label' => 'Policy Rules',    'file' => 'policy-rules.php',    'section' => 'Safety & Config'],
    ['icon' => 'ph-warning-circle',   'label' => 'Fraud Watch',     'file' => 'fraud-watch.php',     'section' => 'Safety & Config'],
    ['icon' => 'ph-magnifying-glass', 'label' => 'SEO',             'file' => 'seo.php',             'section' => 'Safety & Config'],
    ['icon' => 'ph-translate',        'label' => 'Translations',    'file' => 'translations.php',    'section' => 'Safety & Config'],
    ['icon' => 'ph-gear',             'label' => 'Settings',        'file' => 'settings.php',        'section' => 'Safety & Config'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Admin Panel' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --admin-bg: #0a0f1a;
            --admin-sidebar: #0d1321;
            --admin-card: #111827;
            --admin-border: #1e293b;
            --admin-green: #22c55e;
            --admin-green-dim: rgba(34,197,94,0.12);
            --admin-text: #e2e8f0;
            --admin-text-muted: #64748b;
            --admin-hover: #1a2332;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--admin-bg);
            color: var(--admin-text);
            min-height: 100vh;
        }
        a { text-decoration: none; color: inherit; }

        /* Sidebar */
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: var(--admin-sidebar);
            border-right: 1px solid var(--admin-border);
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        .sidebar-brand {
            padding: 1.25rem 1.25rem 1rem;
            border-bottom: 1px solid var(--admin-border);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .sidebar-brand .brand-icon {
            width: 40px;
            height: 40px;
            background: var(--admin-green);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #000;
            font-weight: 700;
        }
        .sidebar-brand .brand-text {
            font-weight: 700;
            font-size: 1.05rem;
            color: #fff;
        }
        .sidebar-brand .brand-sub {
            font-size: 0.7rem;
            color: var(--admin-text-muted);
        }
        .sidebar-nav { padding: 0.75rem 0; flex: 1 0 auto; }
        .sidebar-nav .nav-section {
            padding: 0.5rem 1.25rem 0.35rem;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--admin-text-muted);
        }
        .sidebar-nav .nav-link {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            padding: 0.55rem 1.25rem;
            margin: 1px 0.5rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--admin-text-muted);
            transition: all 0.2s;
        }
        .sidebar-nav .nav-link:hover {
            background: var(--admin-hover);
            color: var(--admin-text);
        }
        .sidebar-nav .nav-link.active {
            background: var(--admin-green-dim);
            color: var(--admin-green);
        }
        .sidebar-nav .nav-link i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        .sidebar-user {
            padding: 1rem 1.25rem;
            border-top: 1px solid var(--admin-border);
            position: sticky;
            bottom: 0;
            width: 100%;
            background: var(--admin-sidebar);
            flex-shrink: 0;
        }
        .sidebar-user .user-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: #fff;
        }
        .sidebar-user .user-email {
            font-size: 0.7rem;
            color: var(--admin-text-muted);
        }

        /* Main Content */
        .admin-main {
            margin-left: 250px;
            min-height: 100vh;
            padding: 1.5rem 2rem;
        }
        .admin-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--admin-border);
        }
        .admin-topbar h4 {
            font-weight: 700;
            color: #fff;
            margin: 0;
        }
        .admin-topbar .topbar-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Cards */
        .admin-card {
            background: var(--admin-card);
            border: 1px solid var(--admin-border);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
        }
        .admin-card .card-title {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--admin-text-muted);
            margin-bottom: 0.75rem;
        }
        .admin-card .card-value {
            font-size: 1.75rem;
            font-weight: 800;
            color: #fff;
        }
        .admin-card .card-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        .admin-card .card-icon.green { background: var(--admin-green-dim); color: var(--admin-green); }
        .admin-card .card-icon.blue { background: rgba(59,130,246,0.12); color: #3b82f6; }
        .admin-card .card-icon.yellow { background: rgba(234,179,8,0.12); color: #eab308; }
        .admin-card .card-icon.red { background: rgba(239,68,68,0.12); color: #ef4444; }
        .admin-card .card-icon.purple { background: rgba(168,85,247,0.12); color: #a855f7; }

        /* Tables */
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }
        .admin-table th {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--admin-text-muted);
            padding: 0.75rem 0.75rem;
            border-bottom: 1px solid var(--admin-border);
            text-align: left;
        }
        .admin-table td {
            padding: 0.75rem 0.75rem;
            border-bottom: 1px solid var(--admin-border);
            font-size: 0.85rem;
            vertical-align: middle;
        }
        .admin-table tr:hover td {
            background: var(--admin-hover);
        }
        .admin-table .user-cell {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .admin-table .avatar {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: var(--admin-green-dim);
            color: var(--admin-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        /* Badges */
        .badge-status {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        .badge-status.active, .badge-status.success, .badge-status.rewarded { background: rgba(34,197,94,0.12); color: #22c55e; }
        .badge-status.pending, .badge-status.warning { background: rgba(234,179,8,0.12); color: #eab308; }
        .badge-status.paused, .badge-status.in_progress { background: rgba(59,130,246,0.12); color: #3b82f6; }
        .badge-status.rejected, .badge-status.failed, .badge-status.dismissed { background: rgba(239,68,68,0.12); color: #ef4444; }
        .badge-status.completed, .badge-status.resolved, .badge-status.closed { background: rgba(168,85,247,0.12); color: #a855f7; }
        .badge-status.flagged { background: rgba(239,68,68,0.12); color: #ef4444; }
        .badge-status.reviewed { background: rgba(34,197,94,0.12); color: #22c55e; }
        .badge-status.sent { background: rgba(34,197,94,0.12); color: #22c55e; }
        .badge-status.draft { background: rgba(100,116,139,0.12); color: #64748b; }
        .badge-status.inactive { background: rgba(100,116,139,0.12); color: #64748b; }

        /* Buttons */
        .btn-admin {
            padding: 0.4rem 0.9rem;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .btn-admin-green { background: var(--admin-green); color: #000; }
        .btn-admin-green:hover { background: #16a34a; }
        .btn-admin-blue { background: rgba(59,130,246,0.15); color: #3b82f6; border: 1px solid rgba(59,130,246,0.3); }
        .btn-admin-blue:hover { background: rgba(59,130,246,0.25); }
        .btn-admin-red { background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
        .btn-admin-red:hover { background: rgba(239,68,68,0.25); }
        .btn-admin-yellow { background: rgba(234,179,8,0.15); color: #eab308; border: 1px solid rgba(234,179,8,0.3); }
        .btn-admin-yellow:hover { background: rgba(234,179,8,0.25); }
        .btn-admin-outline {
            background: transparent;
            color: var(--admin-text-muted);
            border: 1px solid var(--admin-border);
        }
        .btn-admin-outline:hover {
            background: var(--admin-hover);
            color: var(--admin-text);
        }

        /* Forms */
        .form-control-admin {
            background: var(--admin-card);
            border: 1px solid var(--admin-border);
            color: var(--admin-text);
            border-radius: 8px;
            padding: 0.55rem 0.85rem;
            font-size: 0.85rem;
            transition: border-color 0.2s;
        }
        .form-control-admin:focus {
            border-color: var(--admin-green);
            box-shadow: 0 0 0 3px rgba(34,197,94,0.15);
            outline: none;
        }
        .form-control-admin::placeholder { color: var(--admin-text-muted); }
        .form-select-admin {
            background: var(--admin-card);
            border: 1px solid var(--admin-border);
            color: var(--admin-text);
            border-radius: 8px;
            padding: 0.55rem 0.85rem;
            font-size: 0.85rem;
        }
        .form-label-admin {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--admin-text-muted);
            margin-bottom: 0.35rem;
        }

        /* Flash messages */
        .flash-msg {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .flash-success { background: rgba(34,197,94,0.12); color: #22c55e; border: 1px solid rgba(34,197,94,0.3); }
        .flash-error { background: rgba(239,68,68,0.12); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }

        /* Pagination */
        .pagination-admin {
            display: flex;
            gap: 0.35rem;
        }
        .pagination-admin .page-btn {
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 500;
            background: var(--admin-card);
            border: 1px solid var(--admin-border);
            color: var(--admin-text-muted);
            cursor: pointer;
            transition: all 0.2s;
        }
        .pagination-admin .page-btn.active {
            background: var(--admin-green);
            color: #000;
            border-color: var(--admin-green);
        }
        .pagination-admin .page-btn:hover:not(.active) {
            background: var(--admin-hover);
            color: var(--admin-text);
        }

        /* Responsive */
        @media (max-width: 991px) {
            .admin-sidebar { transform: translateX(-100%); }
            .admin-sidebar.show { transform: translateX(0); }
            .admin-main { margin-left: 0; }
            .sidebar-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
                display: none;
            }
            .sidebar-overlay.show { display: block; }
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--admin-bg); }
        ::-webkit-scrollbar-thumb { background: var(--admin-border); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #334155; }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="ph-bold ph-lightning"></i></div>
        <div>
            <div class="brand-text">Adsbooster</div>
            <div class="brand-sub">Admin Panel</div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <?php
        $lastSection = '';
        foreach ($navItems as $item):
            if ($item['section'] !== $lastSection):
                $lastSection = $item['section'];
                // Extra breathing room before the lower sections so the long
                // nav stays scannable.
                $extra = in_array($lastSection, ['AI & Automation', 'Safety & Config'], true) ? ' mt-3' : '';
        ?>
            <div class="nav-section<?= $extra ?>"><?= e($lastSection) ?></div>
        <?php endif; ?>
            <a href="<?= BASE_URL ?>/admin/<?= preg_replace('/\.php$/i', '', $item['file']) ?>" class="nav-link<?= $currentPage === $item['file'] ? ' active' : '' ?>">
                <i class="ph-bold <?= $item['icon'] ?>"></i>
                <?= $item['label'] ?>
            </a>
        <?php endforeach; ?>

        <div class="nav-section mt-3">Account</div>
        <a href="<?= url('admin/login') ?>" class="nav-link" onclick="return confirm('Logout from admin panel?')">
            <i class="ph-bold ph-sign-out"></i> Logout
        </a>
    </nav>
    <div class="sidebar-user">
        <div class="user-name"><?= htmlspecialchars($adminName) ?></div>
        <div class="user-email"><?= htmlspecialchars($adminEmail) ?></div>
    </div>
</aside>

<main class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn d-lg-none" onclick="toggleSidebar()" style="color:var(--admin-text);font-size:1.5rem;">
                <i class="ph-bold ph-list"></i>
            </button>
            <h4><?= $pageTitle ?? 'Admin' ?></h4>
        </div>
        <div class="topbar-actions">
            <a href="<?= BASE_URL ?>/" target="_blank" class="btn-admin btn-admin-outline">
                <i class="ph-bold ph-arrow-up-right"></i> View Site
            </a>
        </div>
    </div>

    <?php if ($flashSuccess = get_flash('success')): ?>
        <div class="flash-msg flash-success"><i class="ph-bold ph-check-circle"></i> <?= e($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError = get_flash('error')): ?>
        <div class="flash-msg flash-error"><i class="ph-bold ph-x-circle"></i> <?= e($flashError) ?></div>
    <?php endif; ?>

    <script>
    function toggleSidebar() {
        document.getElementById('adminSidebar').classList.toggle('show');
        document.getElementById('sidebarOverlay').classList.toggle('show');
    }
    </script>
