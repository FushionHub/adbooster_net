<?php declare(strict_types=1);
if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../config/site_setting_and_config.php';
}
if (!is_logged_in()) {
    set_flash('error', 'Please log in to access this page.');
    redirect(url('login'));
}
$_app_user = current_user();
$_app_userId = current_user_id();
$_appPage = $_GET['page'] ?? basename($_SERVER['SCRIPT_NAME'], '.php');
$_notifCount = 0;
$_recentNotifs = [];
try {
    $_n = db()->fetchOne("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0", [$_app_userId]);
    $_notifCount = (int)($_n['cnt'] ?? 0);
    $_recentNotifs = db()->fetchAll("SELECT id, title, body, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 8", [$_app_userId]);
} catch (\Throwable $e) {}
$_appName = e($_app_user['full_name'] ?? 'User');
$_appInitial = strtoupper(substr($_app_user['full_name'] ?? 'U', 0, 1));
$_appEmail = e($_app_user['email'] ?? '');
$_adCredits = (float)($_app_user['ad_credits'] ?? 0);
$_aiCredits = (int)($_app_user['ai_credits'] ?? 0);
$_siteName = get_setting('site_name', 'Adsbooster.net');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<title><?= e($pageTitle ?? $_siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/studio-ui.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<!-- CKEditor 5 Free Classic Build CDN -->
<script src="https://cdn.ckeditor.com/ckeditor5/41.2.1/classic/ckeditor.js"></script>

<style>
:root, [data-theme="dark"] {
  --bg: #080c14;
  --bg2: #0f172a;
  --bg3: #1a2438;
  --bg4: #25334e;
  --surface: #0e1626;
  --surface-hover: #162238;
  --surface-card: #0d1524;
  --border: rgba(255, 255, 255, 0.08);
  --border2: rgba(255, 255, 255, 0.15);
  --border-accent: rgba(200, 255, 0, 0.35);
  --fg: #f8fafc;
  --fg2: #94a3b8;
  --fg3: #64748b;
  --accent: #c8ff00;
  --accent2: #b2e600;
  --accent-dim: rgba(200, 255, 0, 0.12);
  --accent-glow: 0 0 20px rgba(200, 255, 0, 0.25);
  --green: #10b981;
  --green-dim: rgba(16, 185, 129, 0.12);
  --red: #f43f5e;
  --red-dim: rgba(244, 63, 94, 0.12);
  --blue: #38bdf8;
  --blue-dim: rgba(56, 189, 248, 0.12);
  --orange: #fb923c;
  --orange-dim: rgba(251, 146, 60, 0.12);
  --purple: #c084fc;
  --purple-dim: rgba(192, 132, 252, 0.12);
  --sidebar: 264px;
  --topbar: 64px;
  --radius: 14px;
  --radius-sm: 8px;
  --radius-lg: 20px;
  --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.3);
  --shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
  --shadow-lg: 0 20px 50px -12px rgba(0, 0, 0, 0.7);
  --transition-fast: 0.15s cubic-bezier(0.4, 0, 0.2, 1);
  --transition-normal: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Light Theme Variables and Surface Overrides */
[data-theme="light"] {
  --bg: #f8fafc;
  --bg2: #f1f5f9;
  --bg3: #e2e8f0;
  --bg4: #cbd5e1;
  --surface: #ffffff;
  --surface-hover: #f1f5f9;
  --surface-card: #ffffff;
  --border: rgba(15, 23, 42, 0.08);
  --border2: rgba(15, 23, 42, 0.15);
  --border-accent: rgba(16, 185, 129, 0.35);
  --fg: #0f172a;
  --fg2: #475569;
  --fg3: #64748b;
  --accent: #10b981;
  --accent2: #059669;
  --accent-dim: rgba(16, 185, 129, 0.12);
  --accent-glow: 0 0 20px rgba(16, 185, 129, 0.2);
  --green: #10b981;
}

[data-theme="light"] body { background: #f8fafc !important; color: #0f172a !important; }
[data-theme="light"] .sidebar { background: #ffffff !important; border-right: 1px solid rgba(15, 23, 42, 0.08) !important; box-shadow: 4px 0 24px rgba(0, 0, 0, 0.04) !important; }
[data-theme="light"] .topbar { background: rgba(255, 255, 255, 0.95) !important; border-bottom: 1px solid rgba(15, 23, 42, 0.08) !important; }
[data-theme="light"] .topbar-btn { background: #f1f5f9 !important; border-color: rgba(15, 23, 42, 0.1) !important; color: #0f172a !important; }
[data-theme="light"] .topbar-pill-wallet { background: #f1f5f9 !important; border-color: rgba(15, 23, 42, 0.1) !important; color: #0f172a !important; }
[data-theme="light"] .user-menu-btn { background: #f1f5f9 !important; border-color: rgba(15, 23, 42, 0.1) !important; color: #0f172a !important; }
[data-theme="light"] .card, [data-theme="light"] .card-dark, [data-theme="light"] .bg-card { background: #ffffff !important; border: 1px solid rgba(15, 23, 42, 0.08) !important; color: #0f172a !important; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04) !important; }
[data-theme="light"] .form-control, [data-theme="light"] .form-select { background-color: #f8fafc !important; border-color: #cbd5e1 !important; color: #0f172a !important; }
[data-theme="light"] .nav-item { color: #475569 !important; }
[data-theme="light"] .nav-item:hover { color: #0f172a !important; background: #f1f5f9 !important; }
[data-theme="light"] .nav-item.active { background: rgba(16, 185, 129, 0.12) !important; color: #059669 !important; }
[data-theme="light"] .nav-group-title { color: #94a3b8 !important; }
[data-theme="light"] .text-white { color: #0f172a !important; }
[data-theme="light"] .text-white-75 { color: #475569 !important; }
[data-theme="light"] .text-white-50 { color: #64748b !important; }
[data-theme="light"] .form-label, [data-theme="light"] label { color: #0f172a !important; }

/* Universal Card & Container Overrides */
.card, .card-dark, .bg-card {
  background: var(--surface-card, #0d1524) !important;
  border: 1px solid var(--border, rgba(255, 255, 255, 0.08)) !important;
  color: var(--fg, #f8fafc) !important;
  border-radius: var(--radius, 14px);
}
.border-card {
  border-color: var(--border, rgba(255, 255, 255, 0.08)) !important;
}
.form-label, label {
  color: var(--fg, #f8fafc) !important;
  font-weight: 600;
  font-size: 13px;
  margin-bottom: 6px;
}
.form-control, .form-select {
  background-color: var(--bg, #080c14) !important;
  border: 1px solid var(--border2, rgba(255, 255, 255, 0.15)) !important;
  color: var(--fg, #f8fafc) !important;
  border-radius: var(--radius-sm, 8px);
}
.form-control:focus, .form-select:focus {
  border-color: var(--accent, #c8ff00) !important;
  box-shadow: 0 0 0 3px var(--accent-dim, rgba(200, 255, 0, 0.15)) !important;
  color: var(--fg, #f8fafc) !important;
}
.form-control::placeholder {
  color: var(--fg3, #64748b) !important;
}
.input-group-text {
  background-color: var(--bg3, #1a2438) !important;
  border-color: var(--border2, rgba(255, 255, 255, 0.15)) !important;
  color: var(--fg2, #94a3b8) !important;
}
.text-white-75 { color: #cbd5e1 !important; }
.text-white-50 { color: #94a3b8 !important; }

* { margin: 0; padding: 0; box-sizing: border-box; }
html { scroll-behavior: smooth; height: 100%; }
body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background: var(--bg);
  color: var(--fg);
  min-height: 100vh;
  overflow-x: hidden;
}

.table, .table > :not(caption) > * > * {
  --bs-table-bg: transparent !important;
  --bs-table-color: var(--fg) !important;
  background-color: transparent !important;
  color: var(--fg) !important;
  box-shadow: none !important;
}
.table tbody tr:hover > * {
  background-color: rgba(255, 255, 255, 0.03) !important;
  color: var(--fg) !important;
}

a { color: var(--accent); text-decoration: none; transition: color var(--transition-fast); }
a:hover { color: var(--accent2); }
::selection { background: var(--accent); color: #000; }
::-webkit-scrollbar { width: 7px; height: 7px; }
::-webkit-scrollbar-track { background: var(--bg); }
::-webkit-scrollbar-thumb { background: var(--bg4); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--fg3); }

/* LAYOUT CONTAINER */
.app-layout {
  display: flex;
  min-height: 100vh;
  width: 100%;
  position: relative;
  background: var(--bg);
}

/* SIDEBAR */
.sidebar {
  width: var(--sidebar);
  background: linear-gradient(180deg, #0b111e 0%, #070b13 100%);
  border-right: 1px solid var(--border);
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  display: flex;
  flex-direction: column;
  z-index: 1050;
  transition: transform var(--transition-normal), width var(--transition-normal);
  user-select: none;
  box-shadow: 4px 0 24px rgba(0, 0, 0, 0.25);
}

.sidebar-header {
  padding: 16px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--border);
  height: var(--topbar);
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
  min-width: 0;
}

.sidebar-brand .logo {
  width: 36px;
  height: 36px;
  background: linear-gradient(135deg, var(--accent) 0%, #8ec700 100%);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(200, 255, 0, 0.3);
}

.sidebar-brand .logo i {
  color: #000;
  font-size: 20px;
  font-weight: 700;
}

.sidebar-brand .brand-meta {
  display: flex;
  flex-direction: column;
  line-height: 1.15;
  overflow: hidden;
}

.sidebar-brand .brand-title {
  font-size: 17px;
  font-weight: 800;
  color: var(--fg);
  letter-spacing: -0.02em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar-brand .brand-badge {
  font-size: 9.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--accent);
}

.sidebar-close-btn {
  display: none;
  background: var(--bg3);
  border: 1px solid var(--border);
  color: var(--fg2);
  width: 34px;
  height: 34px;
  border-radius: 8px;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 18px;
  transition: all var(--transition-fast);
  flex-shrink: 0;
}

.sidebar-close-btn:hover {
  background: var(--red-dim);
  color: var(--red);
  border-color: var(--red);
}

.sidebar-nav {
  flex: 1;
  padding: 14px 12px;
  overflow-y: auto;
  overflow-x: hidden;
  -webkit-overflow-scrolling: touch;
}

/* Sidebar tool search */
.nav-search {
  position: relative;
  margin: 2px 4px 12px;
}
.nav-search i {
  position: absolute;
  left: 11px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 15px;
  color: var(--fg3);
  pointer-events: none;
}
.nav-search input {
  width: 100%;
  background: var(--surface);
  border: 1px solid var(--border);
  color: var(--fg);
  border-radius: 10px;
  padding: 8px 10px 8px 32px;
  font-size: 13px;
  outline: none;
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}
.nav-search input::placeholder { color: var(--fg3); }
.nav-search input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(200, 255, 0, .15);
}

.nav-group { margin-bottom: 14px; }
.nav-group-label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.6px;
  color: var(--fg3);
  padding: 6px 12px;
  margin-bottom: 3px;
}
/* Collapsible section headers */
.nav-group-toggle {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: none;
  border: 0;
  cursor: pointer;
  text-align: left;
  font: inherit;
  border-radius: var(--radius-sm);
  transition: background var(--transition-fast), color var(--transition-fast);
}
.nav-group-toggle:hover { background: var(--surface-hover); color: var(--fg); }
.nav-group-toggle .caret {
  font-size: 12px;
  transition: transform .18s ease;
  opacity: .6;
}
.nav-group[aria-expanded="false"] .nav-group-body { display: none; }
.nav-group[aria-expanded="false"] .nav-group-toggle .caret { transform: rotate(-90deg); }
.nav-group-toggle.has-active .caret { transform: rotate(0deg); }

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 9px 12px;
  border-radius: var(--radius-sm);
  color: var(--fg2);
  font-size: 13.5px;
  font-weight: 500;
  transition: all var(--transition-fast);
  text-decoration: none;
  position: relative;
  margin-bottom: 2px;
}

.nav-item:hover {
  background: var(--surface-hover);
  color: var(--fg);
  transform: translateX(2px);
}

.nav-item.active {
  background: var(--accent);
  color: #05080f;
  font-weight: 700;
  box-shadow: 0 4px 16px rgba(200, 255, 0, 0.25);
}

.nav-item.active i { color: #05080f !important; }
.nav-item i {
  font-size: 19px;
  width: 22px;
  text-align: center;
  flex-shrink: 0;
  color: var(--fg3);
  transition: color var(--transition-fast);
}
.nav-item:hover i { color: var(--fg); }

.nav-item-badge {
  margin-left: auto;
  font-size: 10.5px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 10px;
  background: var(--red);
  color: #fff;
  min-width: 18px;
  text-align: center;
}
.nav-item.active .nav-item-badge { background: #000; color: var(--accent); }

.sidebar-footer {
  padding: 14px 12px;
  border-top: 1px solid var(--border);
  background: rgba(0, 0, 0, 0.15);
}
.sidebar-footer .nav-item { color: var(--red); }
.sidebar-footer .nav-item:hover { background: var(--red-dim); color: var(--red); }
.sidebar-footer .nav-item i { color: var(--red); }

/* MAIN CONTENT AREA */
.main {
  margin-left: var(--sidebar);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  flex: 1;
  width: calc(100% - var(--sidebar));
  min-width: 0;
  transition: margin-left var(--transition-normal), width var(--transition-normal);
  background: var(--bg);
}

/* TOPBAR */
.topbar {
  height: var(--topbar);
  background: rgba(8, 12, 20, 0.85);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 clamp(16px, 2.5vw, 36px);
  position: sticky;
  top: 0;
  z-index: 1020;
}

.topbar-left {
  display: flex;
  align-items: center;
  gap: 16px;
  min-width: 0;
}

.menu-toggle {
  display: inline-flex;
  background: var(--bg2);
  border: 1px solid var(--border);
  color: var(--fg);
  font-size: 20px;
  cursor: pointer;
  width: 40px;
  height: 40px;
  border-radius: var(--radius-sm);
  transition: all var(--transition-fast);
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.menu-toggle:hover {
  border-color: var(--accent);
  color: var(--accent);
  background: var(--surface-hover);
  transform: translateY(-1px);
}

.topbar-breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13.5px;
  min-width: 0;
}

.breadcrumb-root { color: var(--fg3); font-weight: 500; white-space: nowrap; }
.breadcrumb-separator { color: var(--fg3); font-size: 12px; flex-shrink: 0; }
.breadcrumb-current { color: var(--fg); font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.topbar-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}

/* Balance Pill in Topbar */
.topbar-pill-wallet {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--bg2);
  border: 1px solid var(--border);
  border-radius: 99px;
  padding: 6px 14px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--fg);
  text-decoration: none;
  transition: all var(--transition-fast);
}

.topbar-pill-wallet:hover {
  border-color: var(--border-accent);
  background: var(--surface-hover);
  color: var(--fg);
  box-shadow: 0 2px 10px rgba(200, 255, 0, 0.15);
}

.topbar-pill-wallet .wallet-credits { color: var(--accent); font-family: 'JetBrains Mono', monospace; font-weight: 700; }
.topbar-pill-wallet .wallet-divider { width: 1px; height: 14px; background: var(--border); }
.topbar-pill-wallet .wallet-ai { color: var(--blue); font-family: 'JetBrains Mono', monospace; font-weight: 700; }

.topbar-btn {
  background: var(--bg2);
  border: 1px solid var(--border);
  color: var(--fg2);
  width: 40px;
  height: 40px;
  border-radius: var(--radius-sm);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all var(--transition-fast);
  font-size: 18px;
  position: relative;
}

.topbar-btn:hover {
  border-color: var(--accent);
  color: var(--accent);
  background: var(--surface-hover);
  transform: translateY(-1px);
}

/* NOTIFICATION DROPDOWN */
.notif-wrapper { position: relative; }
.notif-btn {
  position: relative;
  background: var(--bg2);
  border: 1px solid var(--border);
  color: var(--fg2);
  width: 40px;
  height: 40px;
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all var(--transition-fast);
  font-size: 19px;
}
.notif-btn:hover, .notif-wrapper.open .notif-btn {
  border-color: var(--accent);
  color: var(--accent);
  background: var(--surface-hover);
}
.notif-btn .badge-count {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 9px;
  background: var(--red);
  color: #fff;
  font-size: 10px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--bg);
}

.notif-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  width: 380px;
  max-height: min(78vh, 500px);
  background: var(--bg2);
  border: 1px solid var(--border2);
  border-radius: var(--radius);
  box-shadow: var(--shadow-lg);
  overflow: hidden;
  display: none;
  z-index: 1100;
  animation: dropdownIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes dropdownIn {
  from { opacity: 0; transform: translateY(-8px) scale(0.98); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

.notif-wrapper.open .notif-dropdown { display: block; }
.notif-header {
  padding: 14px 18px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: rgba(0, 0, 0, 0.2);
}
.notif-header h6 { font-size: 14px; font-weight: 700; margin: 0; color: var(--fg); }
.notif-header a { font-size: 12px; color: var(--accent); font-weight: 600; }
.notif-list { max-height: 360px; overflow-y: auto; }

/* CURRENCY SWITCHER DROPDOWN */
.currency-wrap { position: relative; }
.currency-btn {
  background: var(--bg2);
  border: 1px solid var(--border);
  color: var(--fg2);
  height: 40px;
  padding: 0 12px;
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  gap: 7px;
  cursor: pointer;
  transition: all var(--transition-fast);
}
.currency-btn .currency-btn-code {
  font-size: 12.5px;
  font-weight: 800;
  font-family: 'JetBrains Mono', monospace;
  letter-spacing: 0.4px;
}
.currency-btn .currency-caret { font-size: 12px; transition: transform var(--transition-fast); }
.currency-wrap.open .currency-caret { transform: rotate(180deg); }
.currency-btn:hover, .currency-wrap.open .currency-btn {
  border-color: var(--accent);
  color: var(--accent);
  background: var(--surface-hover);
  transform: translateY(-1px);
}
.currency-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  width: 240px;
  background: var(--bg2);
  border: 1px solid var(--border2);
  border-radius: var(--radius);
  box-shadow: var(--shadow-lg);
  overflow: hidden;
  display: none;
  z-index: 1100;
  animation: dropdownIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.currency-wrap.open .currency-dropdown { display: block; }
.currency-header {
  padding: 12px 16px;
  border-bottom: 1px solid var(--border);
  background: rgba(0, 0, 0, 0.2);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.currency-header h6 { font-size: 13px; font-weight: 700; margin: 0; color: var(--fg); }
.currency-header span { font-size: 11px; color: var(--fg2); }
.currency-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 16px;
  border-bottom: 1px solid var(--border);
  text-decoration: none;
  color: var(--fg2);
  transition: background var(--transition-fast);
}
.currency-item:last-child { border-bottom: none; }
.currency-item:hover { background: var(--surface-hover); color: var(--fg); text-decoration: none; }
.currency-item.active { background: rgba(200, 255, 0, 0.06); }
.currency-item-icon {
  width: 34px;
  height: 34px;
  border-radius: var(--radius-sm);
  background: rgba(146, 168, 205, 0.12);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: var(--fg2);
  flex-shrink: 0;
}
.currency-item.active .currency-item-icon { background: var(--accent); color: #0a0f1a; }
.currency-item-body { display: flex; flex-direction: column; gap: 1px; }
.currency-item-body strong {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--fg);
  font-family: 'JetBrains Mono', monospace;
}
.currency-item-body small { font-size: 11.5px; color: var(--fg2); }
.currency-check { margin-left: auto; color: var(--accent); font-size: 16px; }
.notif-item {
  padding: 12px 18px;
  border-bottom: 1px solid var(--border);
  display: flex;
  gap: 12px;
  cursor: pointer;
  transition: background var(--transition-fast);
}
.notif-item:hover { background: var(--surface-hover); }
.notif-item.unread { background: rgba(200, 255, 0, 0.04); }
.notif-icon {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 17px;
}
.notif-icon.success { background: var(--green-dim); color: var(--green); }
.notif-icon.warning { background: var(--orange-dim); color: var(--orange); }
.notif-icon.danger { background: var(--red-dim); color: var(--red); }
.notif-icon.info { background: var(--blue-dim); color: var(--blue); }
.notif-icon.purple { background: var(--purple-dim); color: var(--purple); }
.notif-body { flex: 1; min-width: 0; }
.notif-body p { font-size: 13px; color: var(--fg); margin: 0; line-height: 1.4; font-weight: 500; }
.notif-body small { font-size: 11.5px; color: var(--fg3); margin-top: 3px; display: block; }
.notif-empty { text-align: center; padding: 40px 16px; color: var(--fg3); font-size: 13px; }
.notif-empty i { font-size: 36px; display: block; margin-bottom: 8px; opacity: 0.4; }

/* USER MENU DROPDOWN */
.user-dropdown-wrap { position: relative; }
.user-menu-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px 12px 4px 4px;
  border-radius: 99px;
  border: 1px solid var(--border);
  background: var(--bg2);
  cursor: pointer;
  transition: all var(--transition-fast);
  color: var(--fg);
}
.user-menu-btn:hover, .user-dropdown-wrap.open .user-menu-btn {
  border-color: var(--border-accent);
  background: var(--surface-hover);
}
.user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--accent) 0%, #a2d600 100%);
  color: #05080f;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 13px;
  flex-shrink: 0;
}
.user-menu-info { display: flex; flex-direction: column; line-height: 1.15; text-align: left; }
.user-menu-name { font-size: 13px; font-weight: 700; color: var(--fg); white-space: nowrap; }
.user-menu-role { font-size: 10.5px; color: var(--fg3); text-transform: capitalize; }
.user-menu-caret { font-size: 12px; color: var(--fg3); margin-left: 2px; transition: transform var(--transition-fast); }
.user-dropdown-wrap.open .user-menu-caret { transform: rotate(180deg); }

.user-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  width: 240px;
  background: var(--bg2);
  border: 1px solid var(--border2);
  border-radius: var(--radius);
  box-shadow: var(--shadow-lg);
  padding: 8px;
  display: none;
  z-index: 1100;
  animation: dropdownIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.user-dropdown-wrap.open .user-dropdown { display: block; }
.user-dropdown-header {
  padding: 10px 12px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 6px;
}
.user-dropdown-header .udh-name { font-size: 13.5px; font-weight: 700; color: var(--fg); }
.user-dropdown-header .udh-email { font-size: 11.5px; color: var(--fg3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-dropdown-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  font-size: 13px;
  font-weight: 500;
  color: var(--fg2);
  border-radius: var(--radius-sm);
  transition: all var(--transition-fast);
  text-decoration: none;
}
.user-dropdown-item:hover { background: var(--surface-hover); color: var(--fg); }
.user-dropdown-item i { font-size: 16px; color: var(--fg3); width: 18px; text-align: center; }
.user-dropdown-item:hover i { color: var(--accent); }
.user-dropdown-item.text-danger:hover { background: var(--red-dim); color: var(--red); }
.user-dropdown-item.text-danger:hover i { color: var(--red); }

/* UNIVERSAL FULL-WIDTH PAGE CANVAS */
.page {
  padding: 28px clamp(16px, 2.5vw, 40px);
  flex: 1;
  width: 100%;
  min-width: 0;
  position: relative;
  box-sizing: border-box;
}

.page > div[style*="max-width"],
.page div[style*="max-width:1200px"],
.page div[style*="max-width: 1200px"],
.page div[style*="max-width:1520px"],
.page div[style*="max-width: 1520px"],
.page div[style*="max-width:1000px"],
.page div[style*="max-width: 1000px"],
.page div[style*="max-width:900px"],
.page div[style*="max-width: 900px"],
.page div[style*="max-width:800px"],
.page div[style*="max-width: 800px"],
.page .container,
.page .container-fluid,
.page-wrap {
  max-width: 100% !important;
  width: 100% !important;
  margin-left: 0 !important;
  margin-right: 0 !important;
  padding-left: 0 !important;
  padding-right: 0 !important;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 26px;
}
.page-title {
  font-size: clamp(22px, 2.2vw, 30px);
  font-weight: 800;
  color: var(--fg);
  letter-spacing: -0.025em;
  margin-bottom: 4px;
  line-height: 1.2;
}
.page-subtitle { font-size: 14px; color: var(--fg2); margin-bottom: 0; }

/* MODERN CARDS & SURFACES */
.card-dark {
  background: var(--surface-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: clamp(18px, 2vw, 26px);
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast), transform var(--transition-fast);
  position: relative;
  box-shadow: var(--shadow-sm);
}
.card-dark:hover { border-color: var(--border2); }
.card-dark h5, .card-dark h6 { color: var(--fg); font-weight: 700; letter-spacing: -0.01em; }

/* STAT CARDS */
.stat-card {
  background: var(--surface-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 22px;
  position: relative;
  overflow: hidden;
  transition: all var(--transition-fast);
  box-shadow: var(--shadow-sm);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.stat-card:hover {
  border-color: var(--border-accent);
  transform: translateY(-2px);
  box-shadow: var(--shadow);
}
.stat-card::after {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0; height: 2px;
  background: linear-gradient(90deg, transparent, var(--border2), transparent);
  opacity: 0.6;
}
.stat-card:hover::after {
  background: linear-gradient(90deg, transparent, var(--accent), transparent);
  opacity: 1;
}
.stat-card .stat-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  margin-bottom: 14px;
  transition: transform var(--transition-fast);
}
.stat-card:hover .stat-icon { transform: scale(1.08); }
.stat-card .stat-value {
  font-size: clamp(24px, 2.2vw, 32px);
  font-weight: 800;
  color: var(--fg);
  line-height: 1.1;
  letter-spacing: -0.025em;
  font-family: 'Inter', sans-serif;
}
.stat-card .stat-label { font-size: 13px; color: var(--fg2); margin-top: 6px; font-weight: 500; }

/* FORMS */
.form-control, .form-select {
  background: #090e18 !important;
  border: 1px solid var(--border) !important;
  color: var(--fg) !important;
  border-radius: var(--radius-sm) !important;
  padding: 11px 16px !important;
  font-size: 14px;
  transition: all var(--transition-fast);
}
.form-control:focus, .form-select:focus {
  border-color: var(--accent) !important;
  box-shadow: 0 0 0 3px rgba(200, 255, 0, 0.15) !important;
  background: #0d1524 !important;
}
.form-control::placeholder { color: var(--fg3) !important; }
.form-label { font-size: 13px; font-weight: 600; color: var(--fg); margin-bottom: 6px; }
.form-text { font-size: 12px; color: var(--fg3); }
.form-check-input { background-color: var(--bg2); border-color: var(--border2); }
.form-check-input:checked { background-color: var(--accent); border-color: var(--accent); }
.form-check-input:focus { box-shadow: 0 0 0 3px rgba(200, 255, 0, 0.15); border-color: var(--accent); }
textarea.form-control { min-height: 110px; resize: vertical; }

/* BUTTONS */
.btn-accent {
  background: linear-gradient(135deg, var(--accent) 0%, #b2e600 100%);
  color: #05080f;
  border: none;
  border-radius: var(--radius-sm);
  padding: 10px 22px;
  font-weight: 700;
  font-size: 13.5px;
  cursor: pointer;
  transition: all var(--transition-fast);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  box-shadow: 0 4px 14px rgba(200, 255, 0, 0.2);
}
.btn-accent:hover {
  background: linear-gradient(135deg, #d4ff33 0%, var(--accent) 100%);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(200, 255, 0, 0.35);
  color: #000;
}
.btn-accent:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

.btn-outline-dark {
  background: var(--bg2);
  color: var(--fg);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 10px 22px;
  font-weight: 600;
  font-size: 13.5px;
  cursor: pointer;
  transition: all var(--transition-fast);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.btn-outline-dark:hover {
  border-color: var(--accent);
  color: var(--accent);
  background: var(--accent-dim);
  transform: translateY(-1px);
}
.btn-danger {
  background: var(--red);
  color: #fff;
  border: none;
  border-radius: var(--radius-sm);
  padding: 10px 22px;
  font-weight: 600;
  font-size: 13.5px;
  cursor: pointer;
  transition: all var(--transition-fast);
}
.btn-danger:hover { background: #e11d48; transform: translateY(-1px); }
.btn-sm { padding: 6px 14px; font-size: 12px; border-radius: 6px; }

/* TABLES */
.table-wrap {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  border-radius: var(--radius);
  border: 1px solid var(--border);
  background: var(--surface-card);
}
.table-dark {
  --bs-table-bg: transparent;
  --bs-table-color: var(--fg);
  --bs-table-border-color: var(--border);
  width: 100%;
  margin-bottom: 0;
  border-collapse: separate;
  border-spacing: 0;
}
.table-dark thead th {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--fg3);
  padding: 13px 18px;
  border-bottom: 1px solid var(--border);
  background: rgba(0, 0, 0, 0.25);
  white-space: nowrap;
}
.table-dark tbody td {
  padding: 13px 18px;
  border-bottom: 1px solid var(--border);
  font-size: 13.5px;
  vertical-align: middle;
}
.table-dark tbody tr:hover td { background: rgba(200, 255, 0, 0.025); }
.table-dark tbody tr:last-child td { border-bottom: none; }

/* BADGES */
.badge-status {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 99px;
  font-size: 11.5px;
  font-weight: 700;
  white-space: nowrap;
}
.badge-status::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.badge-active { background: var(--green-dim); color: var(--green); }
.badge-pending { background: var(--orange-dim); color: var(--orange); }
.badge-inactive { background: rgba(100, 116, 139, 0.15); color: var(--fg3); }
.badge-danger { background: var(--red-dim); color: var(--red); }
.badge-info { background: var(--blue-dim); color: var(--blue); }
.badge-accent { background: var(--accent-dim); color: var(--accent); }
.badge-platform {
  background: var(--bg3);
  color: var(--fg2);
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* ALERTS */
.alert {
  border-radius: var(--radius-sm);
  font-size: 14px;
  padding: 14px 18px;
  border: 1px solid transparent;
  margin-bottom: 20px;
}
.alert-success { background: var(--green-dim); color: var(--green); border-color: rgba(16, 185, 129, 0.2); }
.alert-danger { background: var(--red-dim); color: var(--red); border-color: rgba(244, 63, 94, 0.2); }
.alert-warning { background: var(--orange-dim); color: var(--orange); border-color: rgba(251, 146, 60, 0.2); }
.alert-info { background: var(--blue-dim); color: var(--blue); border-color: rgba(56, 189, 248, 0.2); }

/* TABS */
.nav-pills .nav-link {
  background: var(--bg2);
  color: var(--fg2);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 9px 18px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all var(--transition-fast);
}
.nav-pills .nav-link.active {
  background: var(--accent);
  color: #000;
  border-color: var(--accent);
}
.nav-pills .nav-link:hover:not(.active) { background: var(--surface-hover); color: var(--fg); }

.tabs-row {
  display: flex;
  gap: 4px;
  background: var(--bg2);
  border-radius: 10px;
  padding: 4px;
  margin-bottom: 24px;
  border: 1px solid var(--border);
  width: fit-content;
}
.tabs-row .tab {
  padding: 8px 18px;
  border-radius: 7px;
  font-size: 13px;
  font-weight: 600;
  color: var(--fg2);
  cursor: pointer;
  transition: all var(--transition-fast);
  border: none;
  background: transparent;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.tabs-row .tab.active { background: var(--accent); color: #000; box-shadow: 0 2px 8px rgba(200, 255, 0, 0.2); }
.tabs-row .tab:hover:not(.active) { color: var(--fg); background: var(--surface-hover); }

/* PLATFORM CARDS */
.platform-card {
  cursor: pointer;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  transition: all var(--transition-fast);
  background: var(--surface-card);
  text-decoration: none;
}
.platform-card:hover {
  border-color: var(--border-accent);
  background: var(--surface-hover);
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}
.platform-card.selected { border-color: var(--accent); background: var(--accent-dim); }
.platform-card input[type="radio"] { display: none; }

/* RESPONSIVE GRIDS */
.grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }
.grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
.grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
.grid-main-side { display: grid; grid-template-columns: 2.2fr 1fr; gap: 24px; }
.cal-grid { display: grid; grid-template-columns: repeat(7, minmax(110px, 1fr)); gap: 8px; min-width: 840px; }

/* OVERLAY */
.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(3, 6, 12, 0.75);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  z-index: 1040;
  transition: opacity var(--transition-normal);
}
.sidebar-overlay.active { display: block; }
body.nav-open { overflow: hidden; touch-action: none; }

/* DESKTOP COLLAPSE STATE */
.app-layout.side-hidden .sidebar { transform: translateX(-100%); }
.app-layout.side-hidden .main { margin-left: 0; width: 100%; }

/* GLOBAL DARK MODAL STYLING */
.modal-backdrop.show {
  opacity: 0.8 !important;
  background-color: #030712 !important;
  backdrop-filter: blur(8px) !important;
  -webkit-backdrop-filter: blur(8px) !important;
}
.modal-content {
  background: #0d1524 !important;
  color: #f8fafc !important;
  border: 1px solid rgba(255, 255, 255, 0.15) !important;
  border-radius: var(--radius-lg) !important;
  box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.8) !important;
  overflow: hidden !important;
}
.modal-header {
  border-bottom: 1px solid var(--border) !important;
  padding: 18px 24px !important;
  background: rgba(0, 0, 0, 0.3) !important;
}
.modal-title { color: #f8fafc !important; font-weight: 700 !important; font-size: 17px !important; }
.btn-close-white, .btn-close {
  filter: invert(1) grayscale(100%) brightness(200%) !important;
  opacity: 0.75 !important;
}
.btn-close:hover { opacity: 1 !important; }
.modal-body { padding: 24px !important; background: #0d1524 !important; }
.modal-footer {
  border-top: 1px solid var(--border) !important;
  padding: 16px 24px !important;
  background: rgba(0, 0, 0, 0.25) !important;
}

/* CKEDITOR 5 DARK THEME STYLING */
.ck.ck-editor { width: 100% !important; }
.ck.ck-toolbar {
  background: #111827 !important;
  border: 1px solid var(--border) !important;
  border-radius: var(--radius-sm) var(--radius-sm) 0 0 !important;
}
.ck.ck-toolbar .ck-button { color: var(--fg2) !important; }
.ck.ck-toolbar .ck-button:hover, .ck.ck-toolbar .ck-button.ck-on {
  background: var(--surface-hover) !important;
  color: var(--accent) !important;
}
.ck.ck-editor__editable_inline {
  background: #090e18 !important;
  color: var(--fg) !important;
  border: 1px solid var(--border) !important;
  border-top: none !important;
  border-radius: 0 0 var(--radius-sm) var(--radius-sm) !important;
  min-height: 160px;
  font-family: inherit !important;
  font-size: 14px !important;
}
.ck.ck-editor__editable:not(.ck-editor__nested-editable).ck-focused {
  border-color: var(--accent) !important;
  box-shadow: 0 0 0 3px rgba(200, 255, 0, 0.15) !important;
}

/* RESPONSIVE BREAKPOINTS */
@media (max-width: 1199.98px) {
  .grid-4 { grid-template-columns: repeat(2, 1fr); }
  .grid-main-side { grid-template-columns: 1fr; }
}

@media (max-width: 991.98px) {
  .sidebar {
    transform: translateX(-100%);
    width: min(320px, 86vw);
    box-shadow: 20px 0 60px rgba(0, 0, 0, 0.6);
  }
  .sidebar.open { transform: translateX(0); }
  .sidebar-close-btn { display: inline-flex; }
  .main, .app-layout.side-hidden .main { margin-left: 0; width: 100%; }
  .topbar { padding: 0 16px; }
  .breadcrumb-root, .breadcrumb-separator { display: none; }
  .grid-3 { grid-template-columns: repeat(2, 1fr); }
  .grid-2 { grid-template-columns: 1fr; }
  .page { padding: 20px 16px; }
  .user-menu-info { display: none; }
  .topbar-pill-wallet .wallet-divider,
  .topbar-pill-wallet .wallet-ai,
  .topbar-pill-wallet i.ph-sparkle { display: none; }
  /* Dropdowns become full-width sheets anchored to the viewport and
     respect short landscape screens */
  .notif-dropdown,
  .currency-dropdown {
    position: fixed;
    left: 16px;
    right: 16px;
    top: calc(var(--topbar) + 8px);
    width: auto;
    max-height: min(78vh, 500px);
  }
  .notif-list { max-height: min(58vh, 360px); }
}

@media (max-width: 640px) {
  .grid-4, .grid-3, .grid-2 { grid-template-columns: 1fr; }
  .tabs-row { max-width: 100%; overflow-x: auto; width: 100%; }
  .page-title { font-size: 22px; }
  .stat-card .stat-value { font-size: 24px; }

  /* Compact topbar cluster so every control fits on phones */
  .topbar { padding: 0 12px; }
  .topbar-left { gap: 10px; }
  .topbar-right { gap: 7px; }
  .topbar-breadcrumb { font-size: 12.5px; }
  .menu-toggle, .topbar-btn, .notif-btn { width: 36px; height: 36px; font-size: 17px; }
  .notif-btn .badge-count { top: -3px; right: -3px; }
  #fullscreenBtn { display: none; }

  /* Currency becomes an icon-only trigger; menu turns into a full-width sheet */
  .currency-btn { height: 36px; padding: 0 8px; gap: 5px; }
  .currency-btn .currency-btn-code { display: none; }
  .currency-btn .currency-caret { font-size: 11px; }
  /* Menu sheets pin to the viewport edges with 12px gutters */
  .notif-dropdown,
  .currency-dropdown { left: 12px; right: 12px; top: calc(var(--topbar) + 6px); }
  .notif-header { padding: 12px 14px; }
  .notif-item { padding: 11px 14px; }
  .notif-item .notif-icon { width: 32px; height: 32px; font-size: 16px; }
  .currency-header { padding: 12px 14px; }
  .currency-item { padding: 12px 14px; }
  .currency-item-icon { width: 38px; height: 38px; }
  .currency-item-body strong { font-size: 14px; }
  .currency-item-body small { font-size: 12px; }

  .topbar-pill-wallet { padding: 5px 10px; font-size: 11.5px; gap: 6px; }

  div[style*="grid-template-columns:1fr 1fr"],
  div[style*="grid-template-columns: 1fr 1fr"],
  div[style*="grid-template-columns:2fr 1fr"],
  div[style*="grid-template-columns: 2fr 1fr"],
  div[style*="grid-template-columns:1fr 1fr 2fr"],
  div[style*="grid-template-columns: 1fr 1fr 2fr"] {
    grid-template-columns: 1fr !important;
  }
}

@media (max-width: 480px) {
  /* Tiny phones: drop the filterable breadcrumb title, keep the balance pill */
  .topbar-breadcrumb { display: none; }
  .topbar-pill-wallet { padding: 5px 9px; font-size: 11px; }
  .topbar-right { gap: 6px; }
}

@media (max-width: 380px) {
  /* Ultra-narrow: balance pill becomes icon-only, sheets tighten to 8px gutters */
  .topbar-pill-wallet .wallet-credits { display: none; }
  .notif-dropdown,
  .currency-dropdown { left: 8px; right: 8px; }
}

@media (max-width: 767.98px) {
  .form-control, .form-select, input, select, textarea {
    font-size: 16px !important;
  }
}

/* CKEditor 5 Dark Theme Styling */
.ck.ck-editor {
  width: 100% !important;
}
.ck.ck-editor__top .ck-sticky-panel .ck-toolbar {
  background: #090e18 !important;
  border: 1px solid rgba(255, 255, 255, 0.12) !important;
  border-bottom: none !important;
  border-radius: var(--radius-sm) var(--radius-sm) 0 0 !important;
}
.ck.ck-toolbar .ck-button, .ck.ck-dropdown__button {
  color: #94a3b8 !important;
}
.ck.ck-toolbar .ck-button:hover, .ck.ck-toolbar .ck-button.ck-on,
.ck.ck-dropdown__button:hover {
  background: rgba(200, 255, 0, 0.15) !important;
  color: var(--accent) !important;
}
.ck.ck-editor__main > .ck-editor__editable {
  background: #141f33 !important;
  color: #f8fafc !important;
  border: 1px solid rgba(255, 255, 255, 0.12) !important;
  border-radius: 0 0 var(--radius-sm) var(--radius-sm) !important;
  min-height: 150px !important;
}
.ck.ck-editor__main > .ck-editor__editable:focus {
  border-color: var(--accent) !important;
  box-shadow: 0 0 0 2px rgba(200, 255, 0, 0.2) !important;
}
.ck.ck-list {
  background: #0d1524 !important;
  border: 1px solid rgba(255, 255, 255, 0.12) !important;
}
.ck.ck-list__item .ck-button {
  color: #f8fafc !important;
}
.ck.ck-list__item .ck-button:hover {
  background: rgba(200, 255, 0, 0.15) !important;
  color: var(--accent) !important;
}
</style>
</head>
<body>
<?php if (!empty($_SESSION['impersonator'])): ?>
<div style="background:#eab308;color:#1a1a1a;padding:8px 16px;display:flex;align-items:center;justify-content:center;gap:14px;font-size:13.5px;font-weight:700;position:sticky;top:0;z-index:9999;">
    <i class="ph-bold ph-mask-happy"></i>
    <span>Viewing as <strong><?= e($_app_user['full_name'] ?? $_app_user['email'] ?? 'user') ?></strong> — you are impersonating this account.</span>
    <a href="<?= url('admin/exit-impersonate') ?>" style="background:#1a1a1a;color:#eab308;padding:4px 12px;border-radius:6px;text-decoration:none;font-weight:700;">Exit to Admin</a>
</div>
<?php endif; ?>
<div class="app-layout" id="appLayout">
  <!-- Mobile Backdrop Overlay -->
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileNav()"></div>

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <a href="<?= url('dashboard') ?>" class="sidebar-brand d-flex align-items-center gap-2">
        <img src="<?= BASE_URL ?>/assets/images/logo/logo-green.png" alt="<?= e($_siteName) ?>" style="height: 32px; width: auto; object-fit: contain;">
        <span class="brand-badge" style="font-size: 10px; background: rgba(200,255,0,0.15); color: #c8ff00; padding: 2px 7px; border-radius: 6px; font-weight: 700;">PRO</span>
      </a>
      <button class="sidebar-close-btn" id="sidebarCloseBtn" onclick="closeMobileNav()" title="Close menu" aria-label="Close menu">
        <i class="ph ph-x"></i>
      </button>
    </div>

    <nav class="sidebar-nav">
      <!-- Search: the fastest way for anyone to find a tool -->
      <div class="nav-search">
        <i class="ph ph-magnifying-glass"></i>
        <input type="search" id="navSearch" placeholder="Search tools…" aria-label="Search tools" autocomplete="off">
      </div>

      <!-- â”€â”€ Start here â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
      <div class="nav-group" data-group="start" aria-expanded="true">
        <button class="nav-group-label nav-group-toggle" type="button" onclick="toggleNavGroup(this)">
          <span>Start here</span><i class="ph ph-caret-down caret"></i>
        </button>
        <div class="nav-group-body">
          <a href="<?= url('dashboard') ?>" class="nav-item <?= $_appPage==='dashboard'?'active':'' ?>">
            <i class="ph ph-house"></i> <span>Home</span>
          </a>
          <a href="<?= url('create-ad') ?>" class="nav-item <?= $_appPage==='create-ad'?'active':'' ?>">
            <i class="ph ph-megaphone"></i> <span>Create an ad</span>
          </a>
        </div>
      </div>

      <!-- â”€â”€ Make something â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
      <div class="nav-group" data-group="create" aria-expanded="true">
        <button class="nav-group-label nav-group-toggle" type="button" onclick="toggleNavGroup(this)">
          <span>Make something</span><i class="ph ph-caret-down caret"></i>
        </button>
        <div class="nav-group-body">
          <a href="<?= url('ugc-generator') ?>" class="nav-item <?= $_appPage==='ugc-generator'?'active':'' ?>">
            <i class="ph ph-image-square"></i> <span>Image Studio</span>
          </a>
          <a href="<?= url('ugc-video-studio') ?>" class="nav-item <?= $_appPage==='ugc-video-studio'?'active':'' ?>">
            <i class="ph ph-video-camera"></i> <span>Video Studio</span>
          </a>
          <a href="<?= url('post-creator') ?>" class="nav-item <?= $_appPage==='post-creator'?'active':'' ?>">
            <i class="ph ph-note-pencil"></i> <span>Write a post</span>
          </a>
          <a href="<?= url('ai-settings') ?>" class="nav-item <?= $_appPage==='ai-settings'?'active':'' ?>">
            <i class="ph ph-sparkle"></i> <span>AI voice &amp; style</span>
          </a>
        </div>
      </div>

      <!-- â”€â”€ Grow â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
      <div class="nav-group" data-group="grow" aria-expanded="true">
        <button class="nav-group-label nav-group-toggle" type="button" onclick="toggleNavGroup(this)">
          <span>Grow your sales</span><i class="ph ph-caret-down caret"></i>
        </button>
        <div class="nav-group-body">
          <a href="<?= url('ai-media-buyer') ?>" class="nav-item <?= $_appPage==='ai-media-buyer'?'active':'' ?>">
            <i class="ph ph-robot"></i> <span>Auto-run my ads</span>
          </a>
          <a href="<?= url('analytics') ?>" class="nav-item <?= $_appPage==='analytics'?'active':'' ?>">
            <i class="ph ph-chart-line-up"></i> <span>See results</span>
          </a>
          <a href="<?= url('audience-builder') ?>" class="nav-item <?= $_appPage==='audience-builder'?'active':'' ?>">
            <i class="ph ph-users-three"></i> <span>Choose who sees it</span>
          </a>
          <a href="<?= url('ab-test') ?>" class="nav-item <?= $_appPage==='ab-test'?'active':'' ?>">
            <i class="ph ph-git-fork"></i> <span>Test two versions</span>
          </a>
          <a href="<?= url('ad-spy') ?>" class="nav-item <?= $_appPage==='ad-spy'?'active':'' ?>">
            <i class="ph ph-detective"></i> <span>Look at competitor ads</span>
          </a>
        </div>
      </div>

      <!-- â”€â”€ Connect â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
      <div class="nav-group" data-group="connect" aria-expanded="false">
        <button class="nav-group-label nav-group-toggle" type="button" onclick="toggleNavGroup(this)">
          <span>Connect &amp; publish</span><i class="ph ph-caret-down caret"></i>
        </button>
        <div class="nav-group-body">
          <a href="<?= url('social-connect') ?>" class="nav-item <?= $_appPage==='social-connect'?'active':'' ?>">
            <i class="ph ph-link"></i> <span>Link your accounts</span>
          </a>
          <a href="<?= url('calendar') ?>" class="nav-item <?= $_appPage==='calendar'?'active':'' ?>">
            <i class="ph ph-calendar-blank"></i> <span>Content calendar</span>
          </a>
          <a href="<?= url('ecommerce-sync') ?>" class="nav-item <?= $_appPage==='ecommerce-sync'?'active':'' ?>">
            <i class="ph ph-shopping-bag"></i> <span>Connect your store</span>
          </a>
          <a href="<?= url('send-email') ?>" class="nav-item <?= $_appPage==='send-email'?'active':'' ?>">
            <i class="ph ph-paper-plane-tilt"></i> <span>Email outreach</span>
          </a>
        </div>
      </div>

      <!-- â”€â”€ Earn & hire â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
      <div class="nav-group" data-group="work" aria-expanded="false">
        <button class="nav-group-label nav-group-toggle" type="button" onclick="toggleNavGroup(this)">
          <span>Find work &amp; hire</span><i class="ph ph-caret-down caret"></i>
        </button>
        <div class="nav-group-body">
          <a href="<?= url('bounties') ?>" class="nav-item <?= in_array($_appPage, ['bounties', 'create-bounty', 'bounty-details']) ? 'active' : '' ?>">
            <i class="ph ph-hand-coins"></i> <span>Creator bounties</span>
          </a>
          <a href="<?= url('marketplace') ?>" class="nav-item <?= $_appPage==='marketplace'?'active':'' ?>">
            <i class="ph ph-storefront"></i> <span>Marketplace</span>
          </a>
          <a href="<?= url('jobs') ?>" class="nav-item <?= $_appPage==='jobs'?'active':'' ?>">
            <i class="ph ph-briefcase"></i> <span>Jobs</span>
          </a>
          <a href="<?= url('portfolio-builder') ?>" class="nav-item <?= $_appPage==='portfolio-builder'?'active':'' ?>">
            <i class="ph ph-address-book"></i> <span>My portfolio</span>
          </a>
          <a href="<?= url('blog-manage') ?>" class="nav-item <?= $_appPage==='blog-manage'?'active':'' ?>">
            <i class="ph ph-article"></i> <span>Blog</span>
          </a>
          <a href="<?= url('p2p-chat') ?>" class="nav-item <?= $_appPage==='p2p-chat'?'active':'' ?>">
            <i class="ph ph-chats-circle"></i> <span>Chat with others</span>
          </a>
        </div>
      </div>

      <!-- â”€â”€ Your account â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
      <div class="nav-group" data-group="account" aria-expanded="false">
        <button class="nav-group-label nav-group-toggle" type="button" onclick="toggleNavGroup(this)">
          <span>Your account</span><i class="ph ph-caret-down caret"></i>
        </button>
        <div class="nav-group-body">
          <a href="<?= url('billing') ?>" class="nav-item <?= $_appPage==='billing'?'active':'' ?>">
            <i class="ph ph-wallet"></i> <span>Billing &amp; credits</span>
          </a>
          <a href="<?= url('pricing-plan') ?>" class="nav-item <?= $_appPage==='pricing-plan'?'active':'' ?>">
            <i class="ph ph-crown"></i> <span>Change plan</span>
          </a>
          <a href="<?= url('profile') ?>" class="nav-item <?= $_appPage==='profile'?'active':'' ?>">
            <i class="ph ph-user"></i> <span>My profile</span>
          </a>
          <a href="<?= url('security-2fa') ?>" class="nav-item <?= $_appPage==='security-2fa'?'active':'' ?>">
            <i class="ph ph-lock-key"></i> <span>Security (2FA)</span>
          </a>
          <a href="<?= url('privacy-center') ?>" class="nav-item <?= $_appPage==='privacy-center'?'active':'' ?>">
            <i class="ph ph-shield-check"></i> <span>Privacy</span>
          </a>
          <a href="<?= url('notifications') ?>" class="nav-item <?= $_appPage==='notifications'?'active':'' ?>">
            <i class="ph ph-bell"></i> <span>Notifications</span>
            <?php if ($_notifCount > 0): ?>
              <span class="nav-item-badge"><?= $_notifCount > 99 ? '99+' : $_notifCount ?></span>
            <?php endif; ?>
          </a>
          <a href="<?= url('referrals') ?>" class="nav-item <?= $_appPage==='referrals'?'active':'' ?>">
            <i class="ph ph-share-network"></i> <span>Invite friends, earn credit</span>
          </a>
          <a href="<?= url('team-manage') ?>" class="nav-item <?= $_appPage==='team-manage'?'active':'' ?>">
            <i class="ph ph-user-gear"></i> <span>Team members</span>
          </a>
        </div>
      </div>

      <!-- â”€â”€ Advanced â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
      <div class="nav-group" data-group="advanced" aria-expanded="false">
        <button class="nav-group-label nav-group-toggle" type="button" onclick="toggleNavGroup(this)">
          <span>Advanced</span><i class="ph ph-caret-down caret"></i>
        </button>
        <div class="nav-group-body">
          <a href="<?= url('server-tracking') ?>" class="nav-item <?= $_appPage==='server-tracking'?'active':'' ?>">
            <i class="ph ph-broadcast"></i> <span>Tracking (CAPI)</span>
          </a>
          <a href="<?= url('agency-portal') ?>" class="nav-item <?= $_appPage==='agency-portal'?'active':'' ?>">
            <i class="ph ph-buildings"></i> <span>Agency / white-label</span>
          </a>
          <a href="<?= url('api-settings') ?>" class="nav-item <?= $_appPage==='api-settings'?'active':'' ?>">
            <i class="ph ph-code"></i> <span>API &amp; webhooks</span>
          </a>
          <a href="<?= url('developer-docs') ?>" class="nav-item <?= $_appPage==='developer-docs'?'active':'' ?>">
            <i class="ph ph-book-open"></i> <span>Developer docs</span>
          </a>
          <a href="<?= url('status') ?>" class="nav-item <?= $_appPage==='status'?'active':'' ?>">
            <i class="ph ph-activity"></i> <span>System status</span>
          </a>
          <a href="<?= url('dating-search') ?>" class="nav-item <?= $_appPage==='dating-search'?'active':'' ?>">
            <i class="ph ph-heart"></i> <span>Dating network</span>
          </a>
        </div>
      </div>

      <!-- â”€â”€ Help â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
      <div class="nav-group" data-group="help" aria-expanded="false">
        <button class="nav-group-label nav-group-toggle" type="button" onclick="toggleNavGroup(this)">
          <span>Help</span><i class="ph ph-caret-down caret"></i>
        </button>
        <div class="nav-group-body">
          <a href="<?= url('tickets') ?>" class="nav-item <?= $_appPage==='tickets'?'active':'' ?>">
            <i class="ph ph-lifebuoy"></i> <span>Contact support</span>
          </a>
          <a href="<?= url('ai-disclaimer') ?>" class="nav-item">
            <i class="ph ph-info"></i> <span>How the AI works</span>
          </a>
          <a href="<?= url('refund-policy') ?>" class="nav-item">
            <i class="ph ph-arrow-u-up-left"></i> <span>Refund policy</span>
          </a>
        </div>
      </div>
    </nav>

    <script>
    /* â”€â”€ Sidebar: collapsible groups, remembered between visits â”€â”€ */
    (function () {
      const KEY = 'ab.nav.groups';
      let saved = {};
      try { saved = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { saved = {}; }

      function persist() {
        const out = {};
        document.querySelectorAll('.sidebar-nav .nav-group[data-group]').forEach(g => {
          out[g.dataset.group] = g.getAttribute('aria-expanded') === 'true';
        });
        try { localStorage.setItem(KEY, JSON.stringify(out)); } catch (e) {}
      }

      function apply(g, open) {
        g.setAttribute('aria-expanded', open ? 'true' : 'false');
        const btn = g.querySelector('.nav-group-toggle');
        if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      }

      /* 1. Restore the user's own choice, or fall back to the markup default */
      document.querySelectorAll('.sidebar-nav .nav-group[data-group]').forEach(g => {
        const hasActive = !!g.querySelector('.nav-item.active');
        const def = g.getAttribute('aria-expanded') === 'true';
        apply(g, hasActive || (Object.prototype.hasOwnProperty.call(saved, g.dataset.group) ? saved[g.dataset.group] : def));
      });

      window.toggleNavGroup = function (btn) {
        const g = btn.closest('.nav-group');
        if (!g) return;
        apply(g, g.getAttribute('aria-expanded') !== 'true');
        persist();
      };

      /* 2. Search: filter items, auto-open groups that still have matches */
      const box = document.getElementById('navSearch');
      if (!box) return;
      const groups = Array.from(document.querySelectorAll('.sidebar-nav .nav-group[data-group]'));
      box.addEventListener('input', function () {
        const q = box.value.trim().toLowerCase();
        groups.forEach(g => {
          let hits = 0;
          g.querySelectorAll('.nav-item').forEach(a => {
            const label = (a.textContent || '').toLowerCase();
            const show = !q || label.includes(q);
            a.style.display = show ? '' : 'none';
            if (show) hits++;
          });
          const body = g.querySelector('.nav-group-body');
          if (body) body.style.display = hits ? '' : 'none';
          const toggle = g.querySelector('.nav-group-toggle');
          if (toggle) toggle.style.display = hits ? '' : 'none';
        });
      });
    })();
    </script>

    <div class="sidebar-footer">
      <a href="<?= url('logout') ?>" class="nav-item">
        <i class="ph ph-sign-out"></i> <span>Sign Out</span>
      </a>
    </div>
  </aside>

  <!-- Main Viewport -->
  <div class="main">
    <header class="topbar">
      <div class="topbar-left">
        <button class="menu-toggle" id="menuToggleBtn" onclick="toggleSidebar()" title="Toggle Sidebar (Ctrl+B)" aria-label="Toggle navigation">
          <i class="ph ph-list"></i>
        </button>
        <div class="topbar-breadcrumb">
          <span class="breadcrumb-root"><?= e($_siteName) ?></span>
          <i class="ph ph-caret-right breadcrumb-separator"></i>
          <span class="breadcrumb-current"><?= e($pageTitle ?? ucfirst(str_replace('-', ' ', $_appPage))) ?></span>
        </div>
      </div>

      <div class="topbar-right">
        <!-- Quick Balance / Topup Pill -->
        <a href="<?= url('billing') ?>" class="topbar-pill-wallet" title="Ad & AI Credits - Click to Manage">
          <i class="ph ph-monetization-on" style="color:var(--accent)"></i>
          <span class="wallet-credits"><?= e(price_fmt($_adCredits)) ?></span>
          <span class="wallet-divider"></span>
          <i class="ph ph-sparkle" style="color:var(--blue)"></i>
          <span class="wallet-ai"><?= number_format($_aiCredits) ?></span>
        </a>

        <!-- Currency Switcher Dropdown (USD / NGN) -->
        <div class="currency-wrap" id="currencyWrap">
          <button class="currency-btn" id="currencyBtn" title="Switch currency" aria-label="Switch currency">
            <i class="ph <?= currency_icon(currency_current()) ?>" style="color:var(--accent)"></i>
            <span class="currency-btn-code"><?= e(currency_current()) ?></span>
            <i class="ph ph-caret-down currency-caret"></i>
          </button>
          <div class="currency-dropdown" id="currencyDropdown">
            <div class="currency-header">
              <h6>Currency</h6>
              <span>Applies instantly</span>
            </div>
            <?php foreach (currency_supported() as $__code => $__sym): ?>
              <a href="<?= currency_toggle_url($__code) ?>" class="currency-item <?= currency_current() === $__code ? 'active' : '' ?>">
                <span class="currency-item-icon"><i class="ph <?= currency_icon($__code) ?>"></i></span>
                <span class="currency-item-body">
                  <strong><?= $__sym ?> <?= e($__code) ?></strong>
                  <small><?= e(currency_name($__code)) ?></small>
                </span>
                <?php if (currency_current() === $__code): ?>
                  <i class="ph ph-check-circle currency-check"></i>
                <?php endif; ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Fullscreen Mode Toggle -->
        <button class="topbar-btn" id="fullscreenBtn" onclick="toggleFullScreen()" title="Toggle Fullscreen" aria-label="Toggle Fullscreen">
          <i class="ph ph-arrows-out"></i>
        </button>

        <!-- Notifications Dropdown -->
        <div class="notif-wrapper" id="notifWrapper">
          <button class="notif-btn" id="notifBtn" title="Notifications" aria-label="Notifications">
            <i class="ph ph-bell"></i>
            <?php if ($_notifCount > 0): ?>
              <span class="badge-count" id="notifBadge"><?= $_notifCount > 99 ? '99+' : $_notifCount ?></span>
            <?php endif; ?>
          </button>
          <div class="notif-dropdown" id="notifDropdown">
            <div class="notif-header">
              <h6>Notifications</h6>
              <?php if ($_notifCount > 0): ?>
                <a href="<?= url('notifications') ?>?action=mark_all" id="markAllRead">Mark all read</a>
              <?php endif; ?>
            </div>
            <div class="notif-list" id="notifList">
              <?php if (empty($_recentNotifs)): ?>
                <div class="notif-empty">
                  <i class="ph ph-bell-slash"></i>
                  No unread notifications
                </div>
              <?php else: ?>
                <?php foreach ($_recentNotifs as $n):
                  $_nType = match($n['type'] ?? 'info') { 'success' => 'success', 'warning' => 'warning', 'error' => 'danger', 'payment' => 'success', 'campaign' => 'info', 'referral' => 'purple', default => 'info' };
                  $_nIcon = match($n['type'] ?? 'info') { 'success' => 'ph-check-circle', 'warning' => 'ph-warning', 'error' => 'ph-x-circle', 'payment' => 'ph-currency-dollar', 'campaign' => 'ph-megaphone', 'referral' => 'ph-share-network', default => 'ph-bell' };
                  $_timeAgo = (function_exists('time_ago')) ? time_ago($n['created_at']) : date('M d, H:i', strtotime($n['created_at']));
                ?>
                  <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>" data-id="<?= $n['id'] ?>" onclick="markNotifRead(<?= $n['id'] ?>, this)">
                    <div class="notif-icon <?= $_nType ?>"><i class="ph <?= $_nIcon ?>"></i></div>
                    <div class="notif-body">
                      <p><?= e($n['title'] ?? 'Notification') ?></p>
                      <small><?= e(mb_substr($n['body'] ?? '', 0, 80)) ?> Â· <?= $_timeAgo ?></small>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <?php if (!empty($_recentNotifs)): ?>
              <div style="padding:10px 18px;border-top:1px solid var(--border);text-align:center;background:rgba(0,0,0,0.15)">
                <a href="<?= url('notifications') ?>" style="font-size:12.5px;font-weight:600;color:var(--accent)">View all notifications</a>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- User Profile Dropdown -->
        <div class="user-dropdown-wrap" id="userDropdownWrap">
          <button class="user-menu-btn" id="userMenuBtn" onclick="toggleUserDropdown(event)" aria-label="User Profile Menu">
            <div class="user-avatar"><?= $_appInitial ?></div>
            <div class="user-menu-info">
              <span class="user-menu-name"><?= $_appName ?></span>
              <span class="user-menu-role"><?= e(ucfirst($_app_user['role'] ?? 'user')) ?></span>
            </div>
            <i class="ph ph-caret-down user-menu-caret"></i>
          </button>
          <div class="user-dropdown" id="userDropdown">
            <div class="user-dropdown-header">
              <div class="udh-name"><?= $_appName ?></div>
              <div class="udh-email"><?= $_appEmail ?></div>
            </div>
            <a href="<?= url('profile') ?>" class="user-dropdown-item">
              <i class="ph ph-user"></i> Profile &amp; Account
            </a>
            <a href="<?= url('security-2fa') ?>" class="user-dropdown-item">
              <i class="ph ph-shield-check"></i> 2FA Security
            </a>
            <a href="<?= url('send-email') ?>" class="user-dropdown-item">
              <i class="ph ph-paper-plane-tilt"></i> Email Outreach
            </a>
            <a href="<?= url('billing') ?>" class="user-dropdown-item">
              <i class="ph ph-credit-card"></i> Billing &amp; Wallet
            </a>
            <a href="<?= url('pricing-plan') ?>" class="user-dropdown-item">
              <i class="ph ph-crown"></i> Upgrade Plan
            </a>
            <a href="<?= url('referrals') ?>" class="user-dropdown-item">
              <i class="ph ph-share-network"></i> Referral Earnings
            </a>
            <a href="<?= url('tickets') ?>" class="user-dropdown-item">
              <i class="ph ph-lifebuoy"></i> Help &amp; Support
            </a>
            <div style="height:1px;background:var(--border);margin:6px 0"></div>
            <a href="<?= url('logout') ?>" class="user-dropdown-item text-danger">
              <i class="ph ph-sign-out"></i> Sign Out
            </a>
          </div>
        </div>
      </div>
    </header>

    <main class="page">
      <?php if ($flashSuccess = get_flash('success')): ?>
        <div class="alert alert-success d-flex align-items-center gap-2">
          <i class="ph ph-check-circle" style="font-size:18px"></i>
          <div><?= e($flashSuccess) ?></div>
        </div>
      <?php endif; ?>
      <?php if ($flashError = get_flash('error')): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2">
          <i class="ph ph-x-circle" style="font-size:18px"></i>
          <div><?= e($flashError) ?></div>
        </div>
      <?php endif; ?>
