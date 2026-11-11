<?php declare(strict_types=1);

$adminName  = $_SESSION['admin_name'] ?? 'Admin';
$adminEmail = $_SESSION['admin_email'] ?? '';
$adminInitials = strtoupper(substr(trim($adminName), 0, 2));
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
    ['icon' => 'ph-gear',             'label' => 'Settings',        'file' => 'settings.php',        'section' => 'Safety & Config'],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= $pageTitle ?? 'Admin Panel' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/admin-pro.css?v=5" rel="stylesheet">
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="ph-bold ph-lightning"></i></div>
        <div>
            <div class="brand-text">Adsbooster</div>
            <div class="brand-sub">Operations Console</div>
        </div>
    </div>
    <div class="sidebar-search">
        <i class="ph ph-magnifying-glass"></i>
        <input type="search" id="adminNavFilter" placeholder="Filter menu…  (Ctrl+K)" autocomplete="off" aria-label="Filter admin menu">
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
            <a href="<?= BASE_URL ?>/admin/<?= preg_replace('/\.php$/i', '', $item['file']) ?>"
               class="nav-link<?= $currentPage === $item['file'] ? ' active' : '' ?>"
               data-label="<?= e($item['label']) ?>">
                <i class="ph-bold <?= $item['icon'] ?>"></i>
                <span><?= $item['label'] ?></span>
            </a>
        <?php endforeach; ?>

        <div class="nav-section mt-3">Platform</div>
        <a href="<?= BASE_URL ?>/status" target="_blank" rel="noopener" class="nav-link" data-label="Status page">
            <i class="ph-bold ph-heartbeat"></i> <span>Status Page</span>
        </a>
        <a href="<?= BASE_URL ?>/" target="_blank" rel="noopener" class="nav-link" data-label="Public site">
            <i class="ph-bold ph-arrow-up-right"></i> <span>View Site</span>
        </a>
    </nav>
    <div class="sidebar-user">
        <div class="user-avatar"><?= e($adminInitials) ?></div>
        <div class="min-w-0">
            <div class="user-name"><?= htmlspecialchars($adminName) ?></div>
            <div class="user-email"><?= htmlspecialchars($adminEmail) ?></div>
        </div>
        <a href="<?= url('admin/login') ?>" class="user-logout" onclick="return confirm('Logout from admin panel?')" aria-label="Logout" title="Logout">
            <i class="ph-bold ph-sign-out"></i>
        </a>
    </div>
</aside>

<main class="admin-main">
    <div class="admin-topbar">
        <div class="topbar-left">
            <button class="topbar-icon-btn d-lg-none" onclick="toggleSidebar()" aria-label="Toggle navigation">
                <i class="ph-bold ph-list"></i>
            </button>
            <button class="topbar-icon-btn d-none d-lg-inline-flex" id="adminRailToggle" aria-label="Collapse sidebar">
                <i class="ph-bold ph-arrow-u-right"></i>
            </button>
            <div class="min-w-0">
                <h4><?= $pageTitle ?? 'Admin' ?></h4>
                <div class="admin-breadcrumb">
                    <span>Admin</span>
                    <i class="ph-bold ph-caret-right"></i>
                    <span><?= e($currentPage === 'index.php' ? 'Dashboard' : ucwords(str_replace(['-', '.php'], [' ', ''], $currentPage))) ?></span>
                </div>
            </div>
        </div>
        <div class="topbar-actions">
            <div class="topbar-dd position-relative">
                <button class="topbar-icon-btn" id="adminNotifBtn" aria-label="Notifications">
                    <i class="ph-bold ph-bell"></i>
                    <span class="dot"></span>
                </button>
                <div class="admin-dropdown">
                    <div class="dd-header">Notifications <span class="dd-meta">live</span></div>
                    <a class="dd-item" href="<?= BASE_URL ?>/admin/system-health" target="_self"><i class="ph-bold ph-heartbeat"></i> System health</a>
                    <a class="dd-item" href="<?= BASE_URL ?>/admin/tickets"><i class="ph-bold ph-ticket"></i> Open tickets</a>
                    <a class="dd-item" href="<?= BASE_URL ?>/admin/fraud-watch"><i class="ph-bold ph-warning-circle"></i> Fraud flags</a>
                    <hr class="dd-sep">
                    <a class="dd-item" href="<?= BASE_URL ?>/status" target="_blank" rel="noopener"><i class="ph-bold ph-arrow-up-right"></i> Public status page</a>
                </div>
            </div>
            <div class="topbar-dd position-relative">
                <div class="topbar-user" id="adminUserBtn" role="button" aria-label="Account menu">
                    <div class="chip-avatar"><?= e($adminInitials) ?></div>
                    <div class="chip-name"><?= htmlspecialchars($adminName) ?></div>
                    <i class="ph-bold ph-caret-down" style="font-size:.7rem;color:var(--admin-text-muted)"></i>
                </div>
                <div class="admin-dropdown">
                    <div class="dd-header">Account</div>
                    <div class="dd-item" style="cursor:default">
                        <i class="ph-bold ph-envelope-simple"></i>
                        <span style="overflow:hidden;text-overflow:ellipsis" class="min-w-0"><?= htmlspecialchars($adminEmail) ?></span>
                    </div>
                    <hr class="dd-sep">
                    <a class="dd-item" href="<?= BASE_URL ?>/admin/settings"><i class="ph-bold ph-gear"></i> Settings</a>
                    <a class="dd-item" href="<?= url('admin/login') ?>" onclick="return confirm('Logout from admin panel?')" style="color:var(--admin-red)"><i class="ph-bold ph-sign-out"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($flashSuccess = get_flash('success')): ?>
        <div class="flash-msg flash-success"><i class="ph-bold ph-check-circle"></i> <?= e($flashSuccess) ?> <button type="button" class="flash-close" aria-label="Dismiss"><i class="ph-bold ph-x"></i></button></div>
    <?php endif; ?>
    <?php if ($flashError = get_flash('error')): ?>
        <div class="flash-msg flash-error"><i class="ph-bold ph-x-circle"></i> <?= e($flashError) ?> <button type="button" class="flash-close" aria-label="Dismiss"><i class="ph-bold ph-x"></i></button></div>
    <?php endif; ?>

    <script src="<?= BASE_URL ?>/assets/js/admin-app.js?v=5"></script>

