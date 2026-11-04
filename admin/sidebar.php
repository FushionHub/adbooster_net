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
    <link href="<?= BASE_URL ?>/assets/css/admin-pro.css?v=1" rel="stylesheet">
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
    <div class="sidebar-search">
        <i class="ph ph-magnifying-glass"></i>
        <input type="search" id="adminNavFilter" placeholder="Filter menu…" autocomplete="off" aria-label="Filter admin menu">
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
            <button class="btn d-lg-none btn-admin btn-admin-outline" onclick="toggleSidebar()" aria-label="Toggle navigation" style="padding:.4rem .6rem;font-size:1.15rem;">
                <i class="ph-bold ph-list"></i>
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
            <a href="<?= BASE_URL ?>/status" target="_blank" rel="noopener" class="btn-admin btn-admin-outline" title="Platform status">
                <i class="ph-bold ph-heartbeat"></i> Status
            </a>
            <a href="<?= BASE_URL ?>/" target="_blank" rel="noopener" class="btn-admin btn-admin-outline">
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

    // Filter the admin menu by label — long menus stay scannable.
    (function () {
        var input = document.getElementById('adminNavFilter');
        if (!input) return;
        var links = Array.prototype.slice.call(
            document.querySelectorAll('.sidebar-nav .nav-link')
        );
        var sections = Array.prototype.slice.call(
            document.querySelectorAll('.sidebar-nav .nav-section')
        );
        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            links.forEach(function (link) {
                var match = q === '' || link.textContent.toLowerCase().indexOf(q) !== -1;
                link.classList.toggle('hidden', !match);
            });
            // Hide a section heading when every link under it is hidden.
            sections.forEach(function (section) {
                var node = section.nextElementSibling;
                var anyVisible = false;
                while (node && node.classList.contains('nav-link')) {
                    if (!node.classList.contains('hidden')) { anyVisible = true; break; }
                    node = node.nextElementSibling;
                }
                section.style.display = anyVisible ? '' : 'none';
            });
        });
    })();
    </script>
