<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$identifier = trim((string)($_GET['u'] ?? ''));
$targetId   = (int)($_GET['id'] ?? 0);
$currentUserId = is_logged_in() ? current_user_id() : 0;

$portfolio = null;
if ($identifier !== '') {
    $portfolio = db()->fetchOne(
        "SELECT p.*, u.full_name as user_full_name, u.avatar as user_avatar, u.email as user_email, u.phone as user_phone, u.company as user_company
         FROM user_portfolios p 
         JOIN users u ON u.id = p.user_id 
         WHERE p.username = ? OR u.email = ? LIMIT 1",
        [$identifier, $identifier]
    );
} elseif ($targetId > 0) {
    $portfolio = db()->fetchOne(
        "SELECT p.*, u.full_name as user_full_name, u.avatar as user_avatar, u.email as user_email, u.phone as user_phone, u.company as user_company
         FROM user_portfolios p 
         JOIN users u ON u.id = p.user_id 
         WHERE p.user_id = ? LIMIT 1",
        [$targetId]
    );
} elseif ($currentUserId > 0) {
    $portfolio = db()->fetchOne(
        "SELECT p.*, u.full_name as user_full_name, u.avatar as user_avatar, u.email as user_email, u.phone as user_phone, u.company as user_company
         FROM user_portfolios p 
         JOIN users u ON u.id = p.user_id 
         WHERE p.user_id = ? LIMIT 1",
        [$currentUserId]
    );
}

// Fallback to base user details if portfolio not yet explicitly saved
if (!$portfolio) {
    $lookupUser = null;
    if ($targetId > 0) {
        $lookupUser = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$targetId]);
    } elseif ($identifier !== '') {
        $lookupUser = db()->fetchOne("SELECT * FROM users WHERE email = ? OR id = ?", [$identifier, (int)$identifier]);
    } elseif ($currentUserId > 0) {
        $lookupUser = current_user();
    }

    if ($lookupUser) {
        $portfolio = [
            'id'             => 0,
            'user_id'        => (int)$lookupUser['id'],
            'username'       => strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $lookupUser['email'])[0]))),
            'display_name'   => $lookupUser['full_name'] ?: 'Verified Creator',
            'job_title'      => 'Advertising & Growth Specialist',
            'headline'       => $lookupUser['headline'] ?: 'Scaling performance ad campaigns across Meta, TikTok, and Google Ads.',
            'bio'            => $lookupUser['bio'] ?: 'Performance-focused digital marketer with deep expertise in full-funnel media buying, AI-driven creative testing, and ROAS optimization.',
            'template_style' => 'dark_luxury',
            'phone'          => $lookupUser['phone'] ?? '+1 (555) 019-2831',
            'email'          => $lookupUser['email'],
            'company'        => $lookupUser['company'] ?? 'Adsbooster.net Partner Network',
            'location'       => 'Worldwide / Remote',
            'website'        => '',
            'skills_json'    => json_encode(['Meta Ads', 'TikTok Ads', 'UGC Video', 'Copywriting', 'Conversion Rate Optimization (CRO)', 'Google Analytics']),
            'services_json'  => json_encode([
                ['title' => 'Full Omnichannel Ad Scaling Retainer', 'price' => '$2,500/month', 'description' => 'Managing paid ad budgets across Meta, TikTok, and Google.'],
                ['title' => 'Viral UGC Creative Testing Package', 'price' => '$650/package', 'description' => 'Creative strategy, hook testing, and video iterations.'],
            ]),
            'portfolio_items_json' => json_encode([
                ['title' => 'D2C Ecommerce Scale (Nova Luxe)', 'metric' => '4.82x ROAS', 'description' => 'Scaled monthly revenue to $184k with creative iteration.'],
                ['title' => 'B2B SaaS Lead Generation', 'metric' => '-41% CPA', 'description' => 'Restructured search keywords and high-intent landing page copy.']
            ]),
            'avatar_url'     => $lookupUser['avatar'] ?? '',
            'views_count'    => 12,
            'is_public'      => 1,
        ];
    }
}

if (!$portfolio) {
    http_response_code(404);
    die('Portfolio not found.');
}

// Increment views
if (!empty($portfolio['id'])) {
    try {
        db()->query("UPDATE user_portfolios SET views_count = views_count + 1 WHERE id = ?", [$portfolio['id']]);
    } catch (\Throwable $e) {}
}

$pUserId = (int)$portfolio['user_id'];
$vcardRecord = db()->fetchOne("SELECT * FROM user_vcards WHERE user_id = ? LIMIT 1", [$pUserId]);

$isOwner  = $currentUserId > 0 && $pUserId === $currentUserId;
$template = $portfolio['template_style'] ?? 'dark_luxury';

$skills   = json_decode($portfolio['skills_json'] ?? '[]', true) ?: [];
$services = json_decode($portfolio['services_json'] ?? '[]', true) ?: [];
$projects = json_decode($portfolio['portfolio_items_json'] ?? '[]', true) ?: [];
$socials  = json_decode($portfolio['social_links_json'] ?? '[]', true) ?: [];

// Fallback social profiles from user_vcards
if (empty($socials['linkedin']) && !empty($vcardRecord['linkedin']))   $socials['linkedin']  = $vcardRecord['linkedin'];
if (empty($socials['twitter']) && !empty($vcardRecord['twitter']))     $socials['twitter']   = $vcardRecord['twitter'];
if (empty($socials['github']) && !empty($vcardRecord['github']))       $socials['github']    = $vcardRecord['github'];
if (empty($socials['instagram']) && !empty($vcardRecord['instagram'])) $socials['instagram'] = $vcardRecord['instagram'];

$usernameSlug = $portfolio['username'] ?: (string)$portfolio['user_id'];
$vcfUrl       = BASE_URL . '/vcard-download.php?u=' . urlencode($usernameSlug);
$vcardWebUrl  = BASE_URL . '/vcard.php?u=' . urlencode($usernameSlug);
$shareUrl     = BASE_URL . '/portfolio.php?u=' . urlencode($usernameSlug);
$qrCodeUrl    = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&margin=10&data=' . rawurlencode($vcfUrl);

$avatar = $portfolio['avatar_url'] ?: ($portfolio['user_avatar'] ?? '');
$cleanPhone = preg_replace('/[^0-9]/', '', (string)($vcardRecord['whatsapp'] ?? $portfolio['phone'] ?? ''));

$pageTitle = e($portfolio['display_name']) . ' — Portfolio & Digital vCard — ' . get_setting('site_name', 'Adsbooster.net');
$pageDescription = e($portfolio['headline'] ?? $portfolio['bio'] ?? '');

require_once __DIR__ . '/includes/header.php';
?>

<!-- ═══════ Public portfolio theme (scoped — public header has no studio-ui.css) ═══════ -->
<style>
  .pf {
    --pf-accent: #c8ff00;
    --pf-accent-2: #b2e600;
    --pf-bg: #080c14;
    --pf-surface: rgba(13, 21, 36, .78);
    --pf-surface-2: #0b111c;
    --pf-border: rgba(255, 255, 255, .09);
    --pf-border-2: rgba(255, 255, 255, .14);
    --pf-text: #f8fafc;
    --pf-dim: #94a3b8;
    --pf-dim-2: #64748b;
    --pf-radius: 22px;
    background: var(--pf-bg);
    color: var(--pf-text);
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    min-height: 100vh;
    padding: 108px 20px 60px;
  }
  <?php if ($template === 'creative_neon'): ?>
    .pf { --pf-accent: #a855f7; --pf-accent-2: #06b6d4; --pf-bg: #090314;
          --pf-surface: rgba(26, 16, 48, .72); --pf-surface-2: #120a24;
          --pf-border: rgba(168, 85, 247, .32); --pf-border-2: rgba(168, 85, 247, .45); }
    .pf-hero { background: linear-gradient(135deg, #090314 0%, #15092a 50%, #061124 100%) !important; }
  <?php elseif ($template === 'executive_minimal'): ?>
    .pf { --pf-accent: #38bdf8; --pf-accent-2: #0ea5e9; --pf-bg: #0c1017;
          --pf-surface: #131924; --pf-surface-2: #0f141e;
          --pf-border: rgba(255, 255, 255, .10); --pf-border-2: rgba(56, 189, 248, .40); }
    .pf-hero { background: #0c1017 !important; }
  <?php elseif ($template === 'minimalist_aurora'): ?>
    .pf { --pf-accent: #10b981; --pf-accent-2: #34d399; --pf-bg: #071512;
          --pf-surface: #0d211b; --pf-surface-2: #0a1a16;
          --pf-border: rgba(16, 185, 129, .28); --pf-border-2: rgba(16, 185, 129, .42); }
    .pf-hero { background: #071512 !important; }
  <?php endif; ?>

  .pf a { color: inherit; }
  .pf-wrap { max-width: 1120px; margin: 0 auto; }

  /* sticky top bar */
  .pf-top {
    position: fixed; top: 0; left: 0; right: 0; z-index: 50;
    background: rgba(8, 12, 20, .82); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--pf-border);
  }
  .pf-top .in { max-width: 1120px; margin: 0 auto; padding: 11px 20px; display: flex; align-items: center; gap: 12px; }
  .pf-top .who { display: flex; align-items: center; gap: 10px; min-width: 0; }
  .pf-top .av { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; background: var(--pf-surface-2);
                display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 12px;
                color: var(--pf-accent); border: 1px solid var(--pf-border-2); flex: 0 0 auto; }
  .pf-top .nm { font-weight: 800; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .pf-top .grow { flex: 1; }
  .pf-btn {
    display: inline-flex; align-items: center; gap: 7px; border-radius: 999px; padding: 9px 16px;
    font-size: 12.5px; font-weight: 700; text-decoration: none; border: 1px solid var(--pf-border-2);
    color: var(--pf-text); background: rgba(255, 255, 255, .03); transition: all .15s; cursor: pointer; white-space: nowrap;
  }
  .pf-btn:hover { background: rgba(255, 255, 255, .09); transform: translateY(-1px); color: #fff; }
  .pf-btn.pri {
    background: var(--pf-accent); border-color: var(--pf-accent); color: #080c14; font-weight: 800;
    box-shadow: 0 6px 22px -6px var(--pf-accent);
  }
  .pf-btn.pri:hover { filter: brightness(1.08); }
  .pf-btn i { font-size: 15px; }

  /* cards */
  .pf-card {
    background: var(--pf-surface); border: 1px solid var(--pf-border); border-radius: var(--pf-radius);
    padding: 26px; backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
  }
  .pf-sec { margin-top: 22px; }
  .pf-h {
    display: flex; align-items: center; gap: 9px; margin: 0 0 16px;
    font-size: 17px; font-weight: 800; letter-spacing: -.02em;
  }
  .pf-h i { color: var(--pf-accent); font-size: 19px; }
  .pf-sub { color: var(--pf-dim); font-size: 12.5px; margin: 0 0 16px; line-height: 1.55; }

  .pf-pill {
    display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px;
    background: rgba(255, 255, 255, .04); border: 1px solid var(--pf-border);
    color: var(--pf-dim); font-size: 11.5px; font-weight: 700; white-space: nowrap;
  }
  .pf-pill.acc { background: color-mix(in srgb, var(--pf-accent) 14%, transparent);
                 border-color: color-mix(in srgb, var(--pf-accent) 38%, transparent); color: var(--pf-accent); }
  .pf-pill.good { background: rgba(16, 185, 129, .14); border-color: rgba(16, 185, 129, .32); color: #34d399; }
  .pf-pill-row { display: flex; flex-wrap: wrap; gap: 7px; }

  /* hero */
  .pf-hero {
    position: relative; overflow: hidden; border-radius: var(--pf-radius);
    background: radial-gradient(1100px 420px at 12% -10%, color-mix(in srgb, var(--pf-accent) 12%, transparent), transparent 62%), var(--pf-bg);
    border: 1px solid var(--pf-border); padding: 34px;
  }
  .pf-hero .row { display: grid; grid-template-columns: auto 1fr auto; gap: 28px; align-items: center; }
  @media (max-width: 900px) { .pf-hero .row { grid-template-columns: 1fr; text-align: center; justify-items: center; } }
  .pf-av {
    width: 124px; height: 124px; border-radius: 50%; object-fit: cover; flex: 0 0 auto;
    border: 3px solid color-mix(in srgb, var(--pf-accent) 55%, transparent);
    box-shadow: 0 0 0 6px color-mix(in srgb, var(--pf-accent) 12%, transparent), 0 22px 50px rgba(0, 0, 0, .55);
    background: var(--pf-surface-2); display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 38px; color: var(--pf-accent);
  }
  .pf-name { font-size: 30px; font-weight: 800; letter-spacing: -.03em; margin: 0; line-height: 1.15; }
  .pf-role { color: var(--pf-accent); font-size: 15px; font-weight: 700; margin: 6px 0 10px; }
  .pf-head { color: var(--pf-dim); font-size: 14.5px; line-height: 1.65; margin: 0 0 14px; max-width: 62ch; }
  @media (max-width: 900px) { .pf-head { margin-left: auto; margin-right: auto; } }
  .pf-cta { display: flex; flex-direction: column; gap: 9px; min-width: 220px; }
  .pf-cta .pf-btn { justify-content: center; }

  /* NFC card */
  .pf-nfc {
    position: relative; border-radius: 18px; padding: 22px; min-height: 232px; cursor: pointer;
    background: linear-gradient(140deg, color-mix(in srgb, var(--pf-accent) 12%, var(--pf-surface-2)), var(--pf-surface-2));
    border: 1px solid var(--pf-border-2); overflow: hidden; display: flex; flex-direction: column; justify-content: space-between;
    transition: transform .25s ease, box-shadow .25s ease; transform-style: preserve-3d;
  }
  .pf-nfc:hover { transform: translateY(-4px) rotateX(4deg) rotateY(-4deg); box-shadow: 0 26px 60px -18px rgba(0, 0, 0, .75); }
  .pf-nfc::after {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background: linear-gradient(115deg, transparent 40%, rgba(255, 255, 255, .07) 50%, transparent 60%);
  }
  .pf-chip {
    width: 38px; height: 28px; border-radius: 5px; position: relative;
    background: linear-gradient(135deg, #d4af37, #ffd700 50%, #aa820a);
    border: 1px solid rgba(255, 255, 255, .4);
    box-shadow: inset 0 1px 2px rgba(255, 255, 255, .5), 0 2px 4px rgba(0, 0, 0, .4);
  }
  .pf-chip::before { content: ''; position: absolute; top: 50%; left: 0; width: 100%; height: 1px; background: rgba(0, 0, 0, .3); }
  .pf-chip::after  { content: ''; position: absolute; top: 0; left: 50%; width: 1px; height: 100%; background: rgba(0, 0, 0, .3); }

  .pf-qr { background: var(--pf-surface-2); border: 1px solid var(--pf-border); border-radius: 18px; padding: 22px; text-align: center; }
  .pf-qr .box { background: #fff; border-radius: 14px; padding: 9px; width: 138px; height: 138px; margin: 0 auto 14px; box-shadow: 0 14px 34px rgba(0, 0, 0, .45); }
  .pf-qr .box img { width: 100%; height: 100%; object-fit: contain; display: block; }

  /* grids */
  .pf-2 { display: grid; grid-template-columns: 1.05fr .95fr; gap: 18px; }
  @media (max-width: 900px) { .pf-2 { grid-template-columns: 1fr; } }
  .pf-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }

  .pf-item { background: var(--pf-surface-2); border: 1px solid var(--pf-border); border-radius: 16px; padding: 20px;
             display: flex; flex-direction: column; gap: 10px; transition: transform .16s, border-color .16s; }
  .pf-item:hover { transform: translateY(-3px); border-color: var(--pf-border-2); }
  .pf-item h4 { margin: 0; font-size: 15px; font-weight: 800; letter-spacing: -.01em; }
  .pf-item p { margin: 0; color: var(--pf-dim); font-size: 13px; line-height: 1.6; }
  .pf-item .price { color: var(--pf-accent); font-weight: 800; font-size: 15px; font-family: 'JetBrains Mono', monospace; }

  .pf-bio { color: var(--pf-dim); font-size: 14.5px; line-height: 1.85; }
  .pf-skills { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--pf-border); }

  .pf-social { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }

  .pf-foot { text-align: center; margin-top: 30px; padding-top: 26px; border-top: 1px solid var(--pf-border); }
  .pf-foot .cta { display: flex; gap: 11px; justify-content: center; flex-wrap: wrap; margin-top: 16px; }

  .pf-owner {
    display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;
    background: color-mix(in srgb, var(--pf-accent) 8%, var(--pf-surface));
    border: 1px solid color-mix(in srgb, var(--pf-accent) 30%, transparent);
    border-radius: 16px; padding: 14px 18px; margin-bottom: 18px;
  }
  .pf-owner .lbl { font-size: 13.5px; font-weight: 800; }
  .pf-owner .url { font-size: 11.5px; color: var(--pf-dim-2); font-family: 'JetBrains Mono', monospace; word-break: break-all; }

  .pf-copy {
    position: fixed; bottom: 22px; left: 50%; transform: translate(-50%, 14px); opacity: 0; pointer-events: none;
    background: var(--pf-accent); color: #080c14; font-size: 12.5px; font-weight: 800; padding: 10px 18px;
    border-radius: 999px; box-shadow: 0 12px 34px rgba(0, 0, 0, .5); z-index: 3000; transition: all .22s cubic-bezier(.16, 1, .3, 1);
  }
  .pf-copy.show { opacity: 1; transform: translate(-50%, 0); }

  /* fullscreen QR modal */
  .pf-modal-card { background: var(--pf-surface-2); border: 1px solid var(--pf-border-2); border-radius: 22px; color: var(--pf-text); }
</style>

<div class="pf">
  <div class="pf-wrap">

    <!-- Sticky identity bar -->
    <div class="pf-top">
      <div class="in">
        <div class="who">
          <?php if (!empty($avatar)): ?>
            <img class="av" src="<?= e($avatar) ?>" alt="<?= e($portfolio['display_name']) ?>">
          <?php else: ?>
            <div class="av"><?= strtoupper(substr($portfolio['display_name'], 0, 2)) ?></div>
          <?php endif; ?>
          <div style="min-width:0">
            <div class="nm"><?= e($portfolio['display_name']) ?></div>
            <div style="font-size:11px;color:var(--pf-dim-2)"><?= e($portfolio['job_title']) ?></div>
          </div>
        </div>
        <div class="grow"></div>
        <button type="button" class="pf-btn" id="pfShare"><i class="ph ph-share-network"></i> Share</button>
        <a href="<?= e($vcfUrl) ?>" class="pf-btn pri"><i class="ph ph-address-book"></i> Save contact</a>
      </div>
    </div>

    <!-- Owner toolbar -->
    <?php if ($isOwner): ?>
      <div class="pf-owner">
        <div>
          <div class="lbl"><i class="ph ph-user-circle" style="color:var(--pf-accent)"></i> This is your public portfolio &amp; digital vCard</div>
          <div class="url"><?= e($shareUrl) ?></div>
        </div>
        <div class="pf-pill-row">
          <a href="<?= url('portfolio-builder') ?>" class="pf-btn"><i class="ph ph-paint-brush"></i> Edit portfolio &amp; templates</a>
          <a href="<?= url('resume-analyser') ?>" class="pf-btn"><i class="ph ph-file-text"></i> ATS analyser</a>
        </div>
      </div>
    <?php endif; ?>

    <!-- Hero -->
    <div class="pf-hero">
      <div class="row">
        <div>
          <?php if (!empty($avatar)): ?>
            <img class="pf-av" src="<?= e($avatar) ?>" alt="<?= e($portfolio['display_name']) ?>">
          <?php else: ?>
            <div class="pf-av"><?= strtoupper(substr($portfolio['display_name'], 0, 2)) ?></div>
          <?php endif; ?>
        </div>

        <div>
          <div class="pf-pill-row" style="margin-bottom:10px">
            <span class="pf-pill good"><i class="ph-fill ph-seal-check"></i> Verified creator</span>
            <span class="pf-pill"><i class="ph ph-eye"></i> <?= number_format((int)$portfolio['views_count']) ?> views</span>
          </div>
          <h1 class="pf-name"><?= e($portfolio['display_name']) ?></h1>
          <div class="pf-role"><?= e($portfolio['job_title']) ?></div>
          <p class="pf-head"><?= e($portfolio['headline']) ?></p>
          <div class="pf-pill-row">
            <?php if (!empty($portfolio['company'])): ?>
              <span class="pf-pill"><i class="ph ph-buildings"></i> <?= e($portfolio['company']) ?></span>
            <?php endif; ?>
            <?php if (!empty($portfolio['location'])): ?>
              <span class="pf-pill"><i class="ph ph-map-pin"></i> <?= e($portfolio['location']) ?></span>
            <?php endif; ?>
            <?php if (!empty($portfolio['email'])): ?>
              <span class="pf-pill"><i class="ph ph-envelope-simple"></i> <?= e($portfolio['email']) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <div class="pf-cta">
          <a href="<?= e($vcfUrl) ?>" class="pf-btn pri"><i class="ph ph-address-book"></i> Download vCard (.vcf)</a>
          <a href="<?= e($vcardWebUrl) ?>" target="_blank" rel="noopener" class="pf-btn"><i class="ph ph-wifi-high"></i> Virtual NFC card</a>
          <a href="<?= url('p2p-chat') ?>?user=<?= $portfolio['user_id'] ?>" class="pf-btn"><i class="ph ph-chat-teardrop-dots"></i> Message on platform</a>
          <?php if (!empty($cleanPhone)): ?>
            <a href="https://wa.me/<?= e($cleanPhone) ?>" target="_blank" rel="noopener" class="pf-btn"><i class="ph ph-whatsapp-logo"></i> WhatsApp chat</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Digital business card + QR -->
    <div class="pf-sec pf-2">
      <div class="pf-card">
        <h2 class="pf-h"><i class="ph ph-credit-card"></i> Interactive virtual business card</h2>
        <p class="pf-sub">Tap the card to open the full-screen interactive version, or scan the QR code with any phone camera to save the contact instantly.</p>

        <div class="pf-nfc" onclick="window.open('<?= e($vcardWebUrl) ?>', '_blank')" role="button" tabindex="0"
             onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();window.open('<?= e($vcardWebUrl) ?>','_blank')}">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
            <div style="display:flex;align-items:center;gap:10px">
              <div class="pf-chip"></div>
              <i class="ph-bold ph-wifi-high" style="color:var(--pf-accent);font-size:19px" title="Contactless NFC"></i>
            </div>
            <span class="pf-pill acc" style="font-size:10px;letter-spacing:.06em">VERIFIED NFC CARD</span>
          </div>

          <div style="display:flex;align-items:center;gap:13px;margin:18px 0">
            <?php if (!empty($avatar)): ?>
              <img src="<?= e($avatar) ?>" alt="" style="width:54px;height:54px;border-radius:50%;object-fit:cover;border:2px solid var(--pf-accent)">
            <?php else: ?>
              <div style="width:54px;height:54px;border-radius:50%;border:2px solid var(--pf-accent);display:flex;align-items:center;justify-content:center;font-weight:800;color:var(--pf-accent)">
                <?= strtoupper(substr($portfolio['display_name'], 0, 2)) ?>
              </div>
            <?php endif; ?>
            <div style="min-width:0">
              <div style="font-size:16px;font-weight:800"><?= e($portfolio['display_name']) ?></div>
              <div style="font-size:12.5px;color:var(--pf-accent);font-weight:700"><?= e($portfolio['job_title']) ?></div>
              <?php if (!empty($portfolio['company'])): ?>
                <div style="font-size:11px;color:var(--pf-dim-2)"><i class="ph ph-buildings"></i> <?= e($portfolio['company']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding-top:14px;border-top:1px solid var(--pf-border);font-size:11.5px;color:var(--pf-dim-2)">
            <span><i class="ph ph-map-pin"></i> <?= e($portfolio['location']) ?></span>
            <span style="color:var(--pf-text);font-weight:700">Tap to open card &rarr;</span>
          </div>
        </div>

        <div style="display:flex;gap:9px;flex-wrap:wrap;margin-top:16px">
          <button type="button" class="pf-btn" data-bs-toggle="modal" data-bs-target="#qrScanModal"><i class="ph ph-qr-code"></i> Fullscreen QR code</button>
          <button type="button" class="pf-btn" id="pfCopy"><i class="ph ph-copy"></i> Copy profile link</button>
        </div>
      </div>

      <div class="pf-qr">
        <div class="box"><img src="<?= e($qrCodeUrl) ?>" alt="vCard QR code"></div>
        <div style="font-size:14.5px;font-weight:800;margin-bottom:6px">Scan to save contact</div>
        <p style="font-size:12.5px;color:var(--pf-dim);line-height:1.6;margin:0 0 14px">
          Aim your iPhone or Android camera at the code to download <?= e($portfolio['display_name']) ?>'s card straight into your address book.
        </p>
        <div style="display:flex;gap:9px;justify-content:center;flex-wrap:wrap">
          <a href="<?= e($vcfUrl) ?>" class="pf-btn pri"><i class="ph ph-download-simple"></i> Download .vcf</a>
          <a href="<?= e($vcardWebUrl) ?>" target="_blank" rel="noopener" class="pf-btn"><i class="ph ph-arrows-clockwise"></i> Flip virtual card</a>
        </div>
      </div>
    </div>

    <!-- About + skills -->
    <div class="pf-sec pf-card">
      <h2 class="pf-h"><i class="ph ph-user-focus"></i> Professional background</h2>
      <div class="pf-bio"><?= nl2br(e($portfolio['bio'])) ?></div>
      <?php if (!empty($skills)): ?>
        <div class="pf-skills">
          <?php foreach ($skills as $sk): ?>
            <span class="pf-pill"><i class="ph ph-check" style="color:var(--pf-accent)"></i> <?= e($sk) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Case studies -->
    <?php if (!empty($projects)): ?>
      <div class="pf-sec">
        <h2 class="pf-h"><i class="ph ph-trophy"></i> Proven case studies &amp; projects</h2>
        <div class="pf-grid">
          <?php foreach ($projects as $proj): ?>
            <div class="pf-item">
              <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px">
                <h4><?= e($proj['title'] ?? 'Campaign case study') ?></h4>
                <?php if (!empty($proj['metric'])): ?>
                  <span class="pf-pill acc" style="font-family:'JetBrains Mono',monospace;flex:0 0 auto"><?= e($proj['metric']) ?></span>
                <?php endif; ?>
              </div>
              <p><?= e($proj['description'] ?? '') ?></p>
              <?php if (!empty($proj['link'])): ?>
                <a href="<?= e($proj['link']) ?>" target="_blank" rel="noopener" style="margin-top:auto;color:var(--pf-accent);font-size:12.5px;font-weight:700;text-decoration:none">
                  View project results <i class="ph ph-arrow-up-right"></i>
                </a>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Services -->
    <?php if (!empty($services)): ?>
      <div class="pf-sec pf-card">
        <h2 class="pf-h"><i class="ph ph-sparkle"></i> Service packages &amp; rates</h2>
        <div class="pf-grid">
          <?php foreach ($services as $srv): ?>
            <div class="pf-item">
              <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px">
                <h4><?= e($srv['title'] ?? 'Service offering') ?></h4>
                <span class="price"><?= e($srv['price'] ?? 'Inquire') ?></span>
              </div>
              <?php if (!empty($srv['description'])): ?>
                <p><?= e($srv['description']) ?></p>
              <?php endif; ?>
              <a href="<?= url('p2p-chat') ?>?user=<?= $portfolio['user_id'] ?>" class="pf-btn" style="margin-top:auto;align-self:flex-start">
                Inquire about package <i class="ph ph-arrow-right"></i>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Socials -->
    <?php if (!empty($socials)): ?>
      <div class="pf-sec pf-card" style="text-align:center">
        <div style="font-size:12.5px;color:var(--pf-dim-2);margin-bottom:14px">
          Connect with <?= e($portfolio['display_name']) ?> across social platforms
        </div>
        <div class="pf-social">
          <?php if (!empty($socials['linkedin'])): ?>
            <a href="<?= e($socials['linkedin']) ?>" target="_blank" rel="noopener" class="pf-btn"><i class="ph-fill ph-linkedin-logo" style="color:#0a66c2"></i> LinkedIn</a>
          <?php endif; ?>
          <?php if (!empty($socials['twitter'])): ?>
            <a href="<?= e($socials['twitter']) ?>" target="_blank" rel="noopener" class="pf-btn"><i class="ph-fill ph-x-logo"></i> Twitter / X</a>
          <?php endif; ?>
          <?php if (!empty($socials['instagram'])): ?>
            <a href="<?= e($socials['instagram']) ?>" target="_blank" rel="noopener" class="pf-btn"><i class="ph-fill ph-instagram-logo" style="color:#e1306c"></i> Instagram</a>
          <?php endif; ?>
          <?php if (!empty($socials['github'])): ?>
            <a href="<?= e($socials['github']) ?>" target="_blank" rel="noopener" class="pf-btn"><i class="ph-fill ph-github-logo"></i> GitHub</a>
          <?php endif; ?>
          <?php if (!empty($socials['youtube'])): ?>
            <a href="<?= e($socials['youtube']) ?>" target="_blank" rel="noopener" class="pf-btn"><i class="ph-fill ph-youtube-logo" style="color:#ff0000"></i> YouTube</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Closing CTA -->
    <div class="pf-foot">
      <div style="font-size:14.5px;color:var(--pf-dim)">Want to collaborate or hire <?= e($portfolio['display_name']) ?>?</div>
      <div class="cta">
        <a href="<?= url('p2p-chat') ?>?user=<?= $portfolio['user_id'] ?>" class="pf-btn pri" style="padding:13px 24px;font-size:13.5px">
          <i class="ph ph-chat-teardrop-dots"></i> Start a direct conversation
        </a>
        <a href="<?= e($vcfUrl) ?>" class="pf-btn" style="padding:13px 22px"><i class="ph ph-download-simple"></i> Save to contacts (.vcf)</a>
        <a href="<?= e($vcardWebUrl) ?>" target="_blank" rel="noopener" class="pf-btn" style="padding:13px 22px"><i class="ph ph-wifi-high"></i> Virtual NFC card</a>
      </div>
    </div>

  </div>
</div>

<!-- Fullscreen QR modal -->
<div class="modal fade" id="qrScanModal" tabindex="-1" aria-labelledby="qrScanModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content pf-modal-card p-4 text-center">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px">
        <h5 id="qrScanModalLabel" style="margin:0;font-size:15px;font-weight:800">
          <i class="ph-bold ph-qr-code" style="color:var(--pf-accent)"></i> Scan digital vCard
        </h5>
        <button type="button" class="pf-btn" data-bs-dismiss="modal" aria-label="Close" style="padding:6px 10px"><i class="ph ph-x"></i></button>
      </div>
      <div style="background:#fff;padding:14px;border-radius:16px;width:236px;height:236px;margin:0 auto 16px">
        <img src="<?= e($qrCodeUrl) ?>" alt="vCard QR code" style="width:100%;height:100%;object-fit:contain;display:block">
      </div>
      <div style="font-size:15px;font-weight:800"><?= e($portfolio['display_name']) ?></div>
      <div style="font-size:12.5px;color:var(--pf-accent);font-weight:700;margin-bottom:10px"><?= e($portfolio['job_title']) ?></div>
      <p style="font-size:12px;color:var(--pf-dim);margin:0 0 16px">
        Open your camera app on iPhone or Android to instantly save this contact card, or share it over NFC.
      </p>
      <div style="display:flex;gap:9px;justify-content:center;flex-wrap:wrap">
        <a href="<?= e($vcfUrl) ?>" class="pf-btn pri"><i class="ph ph-download-simple"></i> Download .vcf</a>
        <button type="button" class="pf-btn" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var toastEl;
  function toast(msg) {
    if (!toastEl) { toastEl = document.createElement('div'); toastEl.className = 'pf-copy'; document.body.appendChild(toastEl); }
    toastEl.textContent = msg;
    toastEl.classList.add('show');
    clearTimeout(toastEl._t);
    toastEl._t = setTimeout(function () { toastEl.classList.remove('show'); }, 2200);
  }

  var shareUrl = <?= json_encode($shareUrl) ?>;
  function copyLink() {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(shareUrl).then(function () { toast('Profile link copied'); }, function () { toast('Could not copy', true); });
    } else {
      var ta = document.createElement('textarea');
      ta.value = shareUrl; document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); toast('Profile link copied'); } catch (e) { toast('Could not copy', true); }
      ta.remove();
    }
  }

  var c = document.getElementById('pfCopy'); if (c) c.addEventListener('click', copyLink);
  var s = document.getElementById('pfShare');
  if (s) s.addEventListener('click', function () {
    if (navigator.share) {
      navigator.share({ title: <?= json_encode($portfolio['display_name']) ?>, url: shareUrl }).catch(function () {});
    } else { copyLink(); }
  });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
