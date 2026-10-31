<?php declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../config/site_setting_and_config.php';
}

require_once __DIR__ . '/tracker.php';

$currentFile = basename($_SERVER['SCRIPT_NAME'] ?? '');

$siteName    = get_setting('site_name', 'Adsbooster.net');
$siteSlogan  = get_setting('site_slogan', 'Digital Marketing Agency');
$siteLogo    = get_setting('site_logo');
$siteLogoUrl = $siteLogo
    ? BASE_URL . '/' . ltrim($siteLogo, '/')
    : BASE_URL . '/assets/images/logo/logo-green.png';
$favicon     = get_setting('site_favicon');
$faviconUrl  = $favicon
    ? BASE_URL . '/' . ltrim($favicon, '/')
    : BASE_URL . '/assets/images/logo/favicon.png';
$metaDesc  = e(get_content('meta', 'seo', 'description', get_setting('meta_description', 'SEO, Social Media, and Digital Marketing Agency.')));
$metaKeys  = e(get_content('meta', 'seo', 'keywords', get_setting('meta_keywords', 'SEO HTML Template, Digital Marketing, Social Media Agency')));

if (!isset($pageTitle)) {
    $pageTitle = e(get_content('meta', 'seo', 'title', $siteName . ' | ' . $siteSlogan));
}
if (!isset($pageDescription)) {
    $pageDescription = $metaDesc;
} else {
    $pageDescription = e($pageDescription);
}

$activeHome    = ($currentFile === 'index.php') ? ' activePage' : '';
$activeServices = ($currentFile === 'create-ad.php' || $currentFile === 'post-creator.php' || $currentFile === 'marketplace.php' || $currentFile === 'analytics.php') ? ' activePage' : '';
$activePages   = in_array($currentFile, ['about.php','pricing-plan.php','faq.php','team.php']) ? ' activePage' : '';
$activeContact = ($currentFile === 'contact.php') ? ' activePage' : '';

function _navSubActive(string $page): string {
    global $currentFile;
    return ($currentFile === $page) ? ' activePage' : '';
}
?>
<!DOCTYPE html>
<html lang="en" class="fontSizeStyleNew color-green">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?></title>
  <meta name="description" content="<?= $pageDescription ?>">
  <meta name="keywords" content="<?= $metaKeys ?>, AI Ads, Meta Ads, TikTok Automation, Google Ads Automation, Social Scheduler">
  <meta name="author" content="Adsbooster.net">
  <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
  <meta name="ai-content-declaration" content="verified-platform">

  <!-- Canonical URL -->
  <?php
  $reqScheme = 'http';
  if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['SERVER_PORT'] ?? '') === '443')
      || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
      || preg_match('/^adsbooster\.net(:\d+)?$/i', (string)($_SERVER['HTTP_HOST'] ?? ''))) {
      $reqScheme = 'https';
  }
  $currentCanonical = $reqScheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/'); ?>
  <link rel="canonical" href="<?= htmlspecialchars($currentCanonical) ?>">

  <!-- OpenGraph Metadata for Social & AI Engines (ChatGPT, Perplexity, Facebook) -->
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= e($siteName) ?>">
  <meta property="og:title" content="<?= $pageTitle ?>">
  <meta property="og:description" content="<?= $pageDescription ?>">
  <meta property="og:url" content="<?= htmlspecialchars($currentCanonical) ?>">
  <meta property="og:image" content="<?= $siteLogoUrl ?>">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">

  <!-- Twitter Card Metadata -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= $pageTitle ?>">
  <meta name="twitter:description" content="<?= $pageDescription ?>">
  <meta name="twitter:image" content="<?= $siteLogoUrl ?>">

  <!-- Schema.org JSON-LD for Search Engines & Answer Engines (AEO) -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "Organization",
        "@id": "<?= BASE_URL ?>/#organization",
        "name": "<?= e($siteName) ?>",
        "url": "<?= BASE_URL ?>",
        "logo": "<?= $siteLogoUrl ?>",
        "sameAs": [
          "https://twitter.com/adsbooster",
          "https://facebook.com/adsbooster",
          "https://linkedin.com/company/adsbooster"
        ],
        "description": "Multi-network AI advertising and organic social media automation platform."
      },
      {
        "@type": "SoftwareApplication",
        "@id": "<?= BASE_URL ?>/#application",
        "name": "Adsbooster.net Ad & Social Cloud",
        "applicationCategory": "MarketingApplication",
        "operatingSystem": "Web, Cloud, Windows, macOS, iOS, Android",
        "description": "Comprehensive ad launch platform for Meta, Instagram, TikTok, Google Ads, and Snapchat with creative A/B testing and AI generation.",
        "offers": {
          "@type": "AggregateOffer",
          "lowPrice": "29.00",
          "highPrice": "499.00",
          "priceCurrency": "USD"
        }
      }
    ]
  }
  </script>

  <link rel="icon" href="<?= $faviconUrl ?>" type="image/png">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/aos.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/swiper-bundle.min.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/magnific-popup.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/satoshi.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/main.css">
  <style>
    /* Generous header clearance for inner pages so content never touches or hides under the navbar */
    body.is-innerpage > section:first-of-type,
    body.is-innerpage > main > section:first-of-type,
    body.is-innerpage .section-padding:first-of-type,
    body.is-innerpage .page-content-wrapper {
      padding-top: 135px !important;
    }
    .site-header-logo {
      height: 40px;
      width: auto;
      object-fit: contain;
      display: inline-block;
      transition: opacity 0.2s ease, transform 0.2s ease;
    }
    .site-header-logo:hover {
      opacity: 0.9;
      transform: scale(1.02);
    }
    /* Universal Card & Contrast Overrides */
    .card, .bg-card {
      background-color: #0d1524 !important;
      border: 1px solid rgba(255, 255, 255, 0.08) !important;
      color: #f8fafc !important;
    }
    .text-white-75 {
      color: #cbd5e1 !important;
    }
    .text-white-50 {
      color: #94a3b8 !important;
    }
    /* Light Theme Public Adjustments */
    [data-theme="light"] body {
      background-color: #f8fafc !important;
      color: #0f172a !important;
    }
    [data-theme="light"] .header {
      background: rgba(255, 255, 255, 0.96) !important;
      border-bottom: 1px solid rgba(15, 23, 42, 0.08) !important;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04) !important;
    }
    [data-theme="light"] .nav-menu__link {
      color: #0f172a !important;
    }
    [data-theme="light"] .card, [data-theme="light"] .bg-card {
      background-color: #ffffff !important;
      border-color: rgba(15, 23, 42, 0.09) !important;
      color: #0f172a !important;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05) !important;
    }
    [data-theme="light"] .text-white, [data-theme="light"] .text-heading {
      color: #0f172a !important;
    }
    [data-theme="light"] .text-white-75, [data-theme="light"] .text-white-50 {
      color: #475569 !important;
    }
    [data-theme="light"] .theme-toggle-btn {
      background: #f1f5f9;
      border-color: #cbd5e1;
      color: #0f172a;
    }
    [data-theme="light"] .theme-toggle-btn:hover {
      background: rgba(16, 185, 129, 0.12);
      border-color: #10b981;
      color: #10b981;
    }
    [data-theme="light"] section.section-padding, [data-theme="light"] section.faq {
      background: #f8fafc !important;
    }
    [data-theme="light"] .bg-dark {
      background: #f1f5f9 !important;
      color: #0f172a !important;
      border-color: #cbd5e1 !important;
    }
  </style>
</head>

<body class="<?= ($currentFile === 'index.php') ? 'is-homepage' : 'is-innerpage' ?>">
  <!--==================== Preloader Start ====================-->
  <div class="loader-mask">
    <div class="loader">
      <div></div>
      <div></div>
    </div>
  </div>
  <!--==================== Preloader End ====================-->

  <!--==================== Overlay Start ====================-->
  <div class="overlay"></div>
  <!--==================== Overlay End ====================-->

  <!--==================== Sidebar Overlay End ====================-->
  <div class="side-overlay"></div>
  <!--==================== Sidebar Overlay End ====================-->

  <!-- Custom Toast Message start -->
  <div id="toast-container"></div>
  <!-- Custom Toast Message End -->

  <!-- ==================== Scroll to Top End Here ==================== -->
  <div class="progress-wrap cursor-big">
    <svg class="progress-circle svg-content" width="100%" height="100%" viewBox="-1 -1 102 102">
      <path d="M50,1 a49,49 0 0,1 0,98 a49,49 0 0,1 0,-98" />
    </svg>
  </div>
  <!-- ==================== Scroll to Top End Here ==================== -->

  <!-- Custom Cursor Start -->
  <div class="cursor"></div>
  <span class="dot"></span>

  <span class="view-cursor">View <br>
    details</span>
  <!-- Custom Cursor End -->

  <!-- Search Popup Start -->
  <div class="search-popup bg-white tw-py-17 tw-px-4 d-flex align-items-center justify-content-center bg-white">
    <div class="container">
      <div class="tw-pb-15">
        <button type="button"
          class="search-popup__close d-flex justify-content-center align-items-center text-white tw-text-2xl tw-w-16 tw-h-16 bg-main-600 rounded-circle mx-auto">
          <i class="ph-bold ph-x"></i>
        </button>
      </div>
      <form action="<?= url('search') ?>" method="GET" class="search-popup__form position-relative tw-pt-6">
        <input type="search" name="q" class="search-popup__input" placeholder="<?= e(get_content('search', 'form', 'placeholder', 'Search Here...')) ?>">
        <button type="submit"
          class="tw-text-3xl tw-leading-none text-heading hover-text-main-600 position-absolute tw-end-0 top-50 translate-middle-y">
          <i class="ph-bold ph-magnifying-glass"></i>
        </button>
      </form>
    </div>
  </div>
  <!-- Search Popup End -->

  <!-- ==================== Mobile Menu Start Here ==================== -->
<div class="mobile-menu d-lg-none d-block scroll-sm position-fixed bg-white tw-w-300-px tw-h-screen overflow-y-auto tw-p-6 tw-z-999 tw--translate-x-full tw-pb-68 ">

    <button type="button" class="close-button position-absolute tw-end-0 top-0 tw-me-2 tw-mt-2 tw-w-605 tw-h-605 rounded-circle d-flex justify-content-center align-items-center text-neutral-900 bg-neutral-200 hover-bg-neutral-900 hover-text-white"> 
        <i class="ph ph-x"></i>
    </button>

    <div class="mobile-menu__inner">
        <a href="<?= url('index') ?>" class="mobile-menu__logo d-flex align-items-center">
            <img src="<?= BASE_URL ?>/assets/images/logo/logo-green.png" alt="<?= e($siteName) ?>" class="site-header-logo" style="height: 36px; width: auto; object-fit: contain;">
        </a>
        <div class="mobile-menu__menu">
            <!-- Nav menu Start -->
<ul class="nav-menu d-lg-flex align-items-center nav-menu--mobile d-block tw-mt-8">
  <!-- Home -->
  <li class="nav-menu__item<?= $activeHome ?>">
    <a href="<?= url('index') ?>" class="nav-menu__link tw-pe-5 text-heading tw-py-9 fw-medium w-100">Home</a>
  </li>

  <!-- Features -->
  <li class="nav-menu__item has-submenu position-relative<?= $activeServices ?>">
    <a href="javascript:void(0)" class="nav-menu__link tw-pe-5 text-heading tw-py-9 fw-medium w-100">Features</a>
    <ul
      class="nav-submenu scroll-sm position-absolute start-0 top-100 tw-w-max bg-white tw-rounded-md overflow-hidden tw-p-2 tw-z-99">
      <li class="nav-submenu__item d-block tw-rounded tw-duration-200 position-relative">
        <a href="<?= url('create-ad') ?>"
          class="nav-submenu__link hover-bg-neutral-200 text-heading fw-medium w-100 d-block tw-py-2 tw-px-305 tw-rounded">
          Create Ad Campaign
        </a>
      </li>
      <li class="nav-submenu__item d-block tw-rounded tw-duration-200 position-relative">
        <a href="<?= url('post-creator') ?>"
          class="nav-submenu__link hover-bg-neutral-200 text-heading fw-medium w-100 d-block tw-py-2 tw-px-305 tw-rounded">
          Post Creator
        </a>
      </li>
      <li class="nav-submenu__item d-block tw-rounded tw-duration-200 position-relative">
        <a href="<?= url('marketplace') ?>"
          class="nav-submenu__link hover-bg-neutral-200 text-heading fw-medium w-100 d-block tw-py-2 tw-px-305 tw-rounded">
          Marketplace
        </a>
      </li>
      <li class="nav-submenu__item d-block tw-rounded tw-duration-200 position-relative">
        <a href="<?= url('analytics') ?>"
          class="nav-submenu__link hover-bg-neutral-200 text-heading fw-medium w-100 d-block tw-py-2 tw-px-305 tw-rounded">
          Analytics
        </a>
      </li>
    </ul>
  </li>

  <!-- Platform -->
  <li class="nav-menu__item has-submenu position-relative<?= $activePages ?>">
    <a href="javascript:void(0)" class="nav-menu__link tw-pe-5 text-heading tw-py-9 fw-medium w-100">Platform</a>
    <ul
      class="nav-submenu scroll-sm position-absolute start-0 top-100 tw-w-max bg-white tw-rounded-md overflow-hidden tw-p-2 tw-z-99">
      <li class="nav-submenu__item d-block tw-rounded tw-duration-200 position-relative">
        <a href="<?= url('about') ?>"
          class="nav-submenu__link hover-bg-neutral-200 text-heading fw-medium w-100 d-block tw-py-2 tw-px-305 tw-rounded<?= _navSubActive('about.php') ?>">
          About Us
        </a>
      </li>
      <li class="nav-submenu__item d-block tw-rounded tw-duration-200 position-relative">
        <a href="<?= url('pricing-plan') ?>"
          class="nav-submenu__link hover-bg-neutral-200 text-heading fw-medium w-100 d-block tw-py-2 tw-px-305 tw-rounded<?= _navSubActive('pricing-plan.php') ?>">
          Pricing
        </a>
      </li>
      <li class="nav-submenu__item d-block tw-rounded tw-duration-200 position-relative">
        <a href="<?= url('faq') ?>"
          class="nav-submenu__link hover-bg-neutral-200 text-heading fw-medium w-100 d-block tw-py-2 tw-px-305 tw-rounded<?= _navSubActive('faq.php') ?>">
          FAQ
        </a>
      </li>
      <li class="nav-submenu__item d-block tw-rounded tw-duration-200 position-relative">
        <a href="<?= url('team') ?>"
          class="nav-submenu__link hover-bg-neutral-200 text-heading fw-medium w-100 d-block tw-py-2 tw-px-305 tw-rounded<?= _navSubActive('team.php') ?>">
          Team
        </a>
      </li>
    </ul>
  </li>

  <!-- Contact -->
  <li class="nav-menu__item<?= $activeContact ?>">
    <a href="<?= url('contact') ?>" class="nav-menu__link text-heading tw-py-9 fw-medium w-100">Contact</a>
  </li>

  <?php if (is_logged_in()): ?>
  <li class="nav-menu__item">
    <a href="<?= url('dashboard') ?>" class="nav-menu__link text-heading tw-py-9 fw-medium w-100">Dashboard</a>
  </li>
  <?php endif; ?>
</ul>
<!-- Nav menu End  -->
        </div>
    </div>
</div>
<!-- ==================== Mobile Menu End Here ==================== -->

<!-- ==================== Header Start Here ==================== -->
<header class="header tw-transition-all tw-z-99 headerAbsoluteStyle menuColorWhite menuPositionEnd fixedHeaderBlack active-text-green-600 mega-menu-green-btn">
    <div class="container max-w-1520-px">
        <nav class="d-flex align-items-center justify-content-between position-relative">
            <!-- Logo Start -->
            <div class="logo">
                <a href="<?= url('index') ?>" class="link d-flex align-items-center">
                    <img src="<?= BASE_URL ?>/assets/images/logo/logo-green.png" alt="<?= e($siteName) ?>" class="site-header-logo" style="height: 40px; width: auto; object-fit: contain;">
                </a>
            </div>
            <!-- Logo End  -->

            <!-- Menu Start  -->
            <div class="header-menu d-lg-block d-none">
                <!-- Nav menu Start -->
<ul class="nav-menu d-lg-flex align-items-center tw-gap-6">
  <!-- Home -->
  <li class="nav-menu__item<?= $activeHome ?>">
    <a href="<?= url('index') ?>" class="nav-menu__link tw-pe-5 text-heading tw-py-9 fw-medium w-100">Home</a>
  </li>

  <!-- Features -->
  <li class="nav-menu__item has-submenu position-relative<?= $activeServices ?>">
    <a href="javascript:void(0)" class="nav-menu__link tw-pe-5 text-heading tw-py-9 fw-medium w-100">Features</a>
    <ul
      class="nav-submenu scroll-sm position-absolute start-0 top-100 tw-w-max bg-white tw-rounded-md overflow-hidden tw-p-2 tw-z-99">
      <li class="nav-submenu__item d-block tw-rounded tw-duration-200 position-relative">
        <a href="<?= url('create-ad') ?>"
          class="nav-submenu__link hover-bg-neutral-200 text-heading fw-medium w-100 d-block tw-py-2 tw-px-305 tw-rounded">
          Create Ad Campaign
        </a>
      </li>
      <li class="nav-submenu__item d-block tw-rounded tw-duration-200 position-relative">
        <a href="<?= url('post-creator') ?>"
          class="nav-submenu__link hover-bg-neutral-200 text-heading fw-medium w-100 d-block tw-py-2 tw-px-305 tw-rounded">
          Post Creator
        </a>
      </li>
      <li class="nav-submenu__item d-block tw-rounded tw-duration-200 position-relative">
        <a href="<?= url('marketplace') ?>"
          class="nav-submenu__link hover-bg-neutral-200 text-heading fw-medium w-100 d-block tw-py-2 tw-px-305 tw-rounded">
          Marketplace
        </a>
      </li>
      <li class="nav-submenu__item d-block tw-rounded tw-duration-200 position-relative">
        <a href="<?= url('analytics') ?>"
          class="nav-submenu__link hover-bg-neutral-200 text-heading fw-medium w-100 d-block tw-py-2 tw-px-305 tw-rounded">
          Analytics
        </a>
      </li>
    </ul>
  </li>

  <!-- Pages -->
  <li class="nav-menu__item has-submenu position-relative<?= $activePages ?>">
    <a href="javascript:void(0)" class="nav-menu__link tw-pe-5 text-heading tw-py-9 fw-medium w-100">Platform</a>
    <ul
      class="nav-submenu scroll-sm position-absolute start-0 top-100 tw-w-max bg-white tw-rounded-md overflow-hidden tw-p-2 tw-z-99">
      <li class="nav-submenu__item d-block tw-rounded tw-duration-200 position-relative">
        <a href="<?= url('about') ?>"
          class="nav-submenu__link hover-bg-neutral-200 text-heading fw-medium w-100 d-block tw-py-2 tw-px-305 tw-rounded<?= _navSubActive('about.php') ?>">
          About Us
        </a>
      </li>
      <li class="nav-submenu__item d-block tw-rounded tw-duration-200 position-relative">
        <a href="<?= url('pricing-plan') ?>"
          class="nav-submenu__link hover-bg-neutral-200 text-heading fw-medium w-100 d-block tw-py-2 tw-px-305 tw-rounded<?= _navSubActive('pricing-plan.php') ?>">
          Pricing
        </a>
      </li>
      <li class="nav-submenu__item d-block tw-rounded tw-duration-200 position-relative">
        <a href="<?= url('faq') ?>"
          class="nav-submenu__link hover-bg-neutral-200 text-heading fw-medium w-100 d-block tw-py-2 tw-px-305 tw-rounded<?= _navSubActive('faq.php') ?>">
          FAQ
        </a>
      </li>
      <li class="nav-submenu__item d-block tw-rounded tw-duration-200 position-relative">
        <a href="<?= url('team') ?>"
          class="nav-submenu__link hover-bg-neutral-200 text-heading fw-medium w-100 d-block tw-py-2 tw-px-305 tw-rounded<?= _navSubActive('team.php') ?>">
          Team
        </a>
      </li>
    </ul>
  </li>

  <!-- Contact -->
  <li class="nav-menu__item<?= $activeContact ?>">
    <a href="<?= url('contact') ?>" class="nav-menu__link text-heading tw-py-9 fw-medium w-100">Contact</a>
  </li>

  <?php if (is_logged_in()): ?>
  <li class="nav-menu__item">
    <a href="<?= url('dashboard') ?>" class="nav-menu__link text-heading tw-py-9 fw-medium w-100">Dashboard</a>
  </li>
  <?php endif; ?>
</ul>
<!-- Nav menu End  -->
            </div>
            <!-- Menu End  -->

            <!-- Header Right start -->
            <div class="d-lg-block d-none tw-ms-8">
                <div class="d-flex align-items-center tw-gap-6">
                    <button type="button"
                        class="search-popup__button tw-text-2xl tw-leading-none text-white hover-text-main-600" title="Search">
                        <i class="ph-bold ph-magnifying-glass"></i>
                    </button>

                    <?php if (is_logged_in() && ($_SESSION['account_type'] ?? '') === 'agency_owner'): ?>
                    <!-- Workspace Switcher for Agency Owners -->
                    <div class="dropdown">
                        <button type="button" class="btn btn-outline-main hover-style-one button--stroke active-scale-094 tw-duration-100 d-inline-flex align-items-center justify-content-center tw-gap-205 active--translate-y-2 rounded-pill text-heading dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="button__flair"></span>
                            <span class="button__label">Workspace</span>
                            <i class="ph-bold ph-caret-down tw-text-sm"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end bg-white tw-rounded-md tw-p-2 tw-z-99 border border-neutral-200 shadow-lg">
                            <li><a class="dropdown-item hover-bg-neutral-200 text-heading fw-medium d-block tw-py-2 tw-px-305 tw-rounded" href="<?= url('dashboard') ?>?workspace=personal">Personal Workspace</a></li>
                            <li><a class="dropdown-item hover-bg-neutral-200 text-heading fw-medium d-block tw-py-2 tw-px-305 tw-rounded" href="<?= url('dashboard') ?>?workspace=agency">Agency Workspace</a></li>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (is_logged_in()): ?>
                    <!-- Notification Bell -->
                    <div class="dropdown">
                        <button type="button" class="position-relative tw-text-2xl tw-leading-none text-white hover-text-main-600" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="ph-bold ph-bell"></i>
                            <?php
                            $unreadCount = 0;
                            try {
                                $stmt = db()->fetch("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0", [current_user_id()]);
                                $unreadCount = (int)($stmt['cnt'] ?? 0);
                            } catch (Throwable $e) {
                                // silently fail
                            }
                            if ($unreadCount > 0): ?>
                            <span class="position-absolute top-0 tw-end-0 tw--mt-1 tw--me-1 tw-w-18 tw-h-18 bg-main-600 rounded-circle d-flex justify-content-center align-items-center tw-text-xs text-white fw-bold"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end bg-white tw-rounded-md tw-p-4 tw-z-99 border border-neutral-200 shadow-lg" style="min-width: 300px;">
                            <h6 class="fw-bold text-heading tw-mb-3">Notifications</h6>
                            <div id="notification-list">
                                <p class="text-neutral-500 tw-text-sm">No unread notifications</p>
                            </div>
                            <div class="tw-mt-3 text-center">
                                <a href="<?= url('dashboard') ?>?page=notifications" class="text-main-600 fw-semibold tw-text-sm">View All</a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Auth buttons: Login + Register (logged out) or Dashboard (logged in) -->
            <?php if (!is_logged_in()): ?>
            <div class="d-lg-flex d-none align-items-center tw-gap-4 tw-ms-4">
                <a href="<?= url('login') ?>"
                    class="btn btn-outline-main hover-style-one button--stroke active-scale-094 tw-duration-100 d-inline-flex align-items-center justify-content-center tw-gap-205 active--translate-y-2 rounded-pill text-heading"
                    data-block="button">
                    <span class="button__flair"></span>
                    <span class="button__label">Login</span>
                </a>
                <a href="<?= url('register') ?>"
                    class="btn btn-main hover-style-one button--stroke active-scale-094 tw-duration-100 d-inline-flex align-items-center justify-content-center tw-gap-205 active--translate-y-2 rounded-pill text-heading"
                    data-block="button">
                    <span class="button__flair"></span>
                    <span class="button__label">Register</span>
                </a>
            </div>
            <?php else: ?>
            <div class="d-lg-flex d-none align-items-center tw-gap-4 tw-ms-4">
                <a href="<?= url('dashboard') ?>"
                    class="btn btn-main hover-style-one button--stroke active-scale-094 tw-duration-100 d-inline-flex align-items-center justify-content-center tw-gap-205 active--translate-y-2 rounded-pill text-heading"
                    data-block="button">
                    <span class="button__flair"></span>
                    <span class="button__label">Dashboard</span>
                </a>
            </div>
            <?php endif; ?>

            <!-- Mobile Header Right -->
            <div class="d-flex align-items-center tw-gap-4 d-lg-none">
                <button type="button"
                    class="search-popup__button tw-text-2xl tw-leading-none text-white hover-text-main-600">
                    <i class="ph-bold ph-magnifying-glass"></i>
                </button>
                <button type="button" class="toggle-mobileMenu leading-none text-white tw-text-9">
                    <i class="ph ph-list"></i>
                </button>
            </div>
            <!-- Header Right End  -->
        </nav>
    </div>
</header>
<!-- ==================== Header End Here ==================== -->

<div id="smooth-wrapper" class="">
    <div id="smooth-content">
        <main>
