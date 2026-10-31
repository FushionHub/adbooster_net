<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/modules/ai_engine.php';

$userId = current_user_id();
$user   = current_user();

$portfolio   = db()->fetchOne("SELECT * FROM user_portfolios WHERE user_id = ?", [$userId]);
$vcardRecord = db()->fetchOne("SELECT * FROM user_vcards WHERE user_id = ?", [$userId]);

$errors = [];
$success = '';

// Handle Actions (AI Parse or Save Full Portfolio & vCard)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'parse_resume') {
        $resumeText = '';

        // Check file upload (PDF, DOCX, TXT)
        if (!empty($_FILES['resume_file']['name']) && $_FILES['resume_file']['error'] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['resume_file']['tmp_name'];
            $origName = $_FILES['resume_file']['name'];
            $resumeText = AIEngine::extractTextFromFile($tmpPath, $origName);
        }

        $pastedText = trim((string)($_POST['resume_text'] ?? ''));
        if ($pastedText !== '') {
            $resumeText = $resumeText !== '' ? ($resumeText . "\n\n" . $pastedText) : $pastedText;
        }

        if (empty(trim($resumeText))) {
            $errors[] = 'Please paste your resume text or upload a resume file (.pdf, .docx, .txt).';
        } else {
            $parsed = AIEngine::analyzeResume($resumeText, $userId);
            if (!empty($parsed['success']) && !empty($parsed['data'])) {
                $d = $parsed['data'];
                $cand = $d['candidate'] ?? [];
                $uname = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $cand['email'] ?: $user['email'])[0])));
                if (empty($uname)) $uname = 'user_' . $userId;

                $tpl = in_array($_POST['template_style'] ?? '', ['dark_luxury', 'creative_neon', 'executive_minimal', 'minimalist_aurora'], true)
                    ? $_POST['template_style']
                    : ($portfolio['template_style'] ?? 'dark_luxury');

                $flatSkills = $d['flat_skills'] ?? ['Meta Ads', 'TikTok Ads', 'UGC Creative', 'Copywriting'];
                $services   = $d['services'] ?? [];
                $projects   = $d['portfolio_items'] ?? [];

                // 1. Update/Insert user_portfolios
                $dataPortfolio = [
                    'user_id'              => $userId,
                    'username'             => $uname,
                    'display_name'         => $cand['display_name'] ?: $user['full_name'],
                    'job_title'            => $cand['job_title'] ?: 'Performance Advertiser & Creator',
                    'headline'             => $cand['headline'] ?: '',
                    'bio'                  => $cand['bio'] ?: '',
                    'template_style'       => $tpl,
                    'phone'                => $cand['phone'] ?: ($user['phone'] ?? '+1 (555) 019-2831'),
                    'email'                => $cand['email'] ?: $user['email'],
                    'company'              => $cand['company'] ?: ($user['company'] ?? 'Adsbooster Partner Network'),
                    'location'             => $cand['location'] ?: 'Worldwide / Remote',
                    'website'              => $cand['website'] ?: (BASE_URL . '/portfolio.php?u=' . urlencode($uname)),
                    'skills_json'          => json_encode(array_values($flatSkills)),
                    'services_json'        => json_encode($services),
                    'portfolio_items_json' => json_encode($projects),
                    'is_public'            => 1,
                    'updated_at'           => date('Y-m-d H:i:s'),
                ];

                if ($portfolio) {
                    db()->update('user_portfolios', $dataPortfolio, 'id = ?', [$portfolio['id']]);
                } else {
                    $dataPortfolio['created_at'] = date('Y-m-d H:i:s');
                    db()->insert('user_portfolios', $dataPortfolio);
                }

                // 2. Update/Insert user_vcards
                $dataVcard = [
                    'user_id'          => $userId,
                    'username'         => $uname,
                    'headline'         => $cand['headline'] ?: '',
                    'bio'              => $cand['bio'] ?: '',
                    'template'         => $tpl,
                    'phone'            => $cand['phone'] ?: ($user['phone'] ?? '+1 (555) 019-2831'),
                    'whatsapp'         => $cand['phone'] ?: ($user['phone'] ?? '+1 (555) 019-2831'),
                    'company'          => $cand['company'] ?: ($user['company'] ?? 'Adsbooster.net'),
                    'job_title'        => $cand['job_title'] ?: 'Performance Advertiser',
                    'location'         => $cand['location'] ?: 'Worldwide / Remote',
                    'website'          => $cand['website'] ?: (BASE_URL . '/portfolio.php?u=' . urlencode($uname)),
                    'skills_json'      => json_encode(array_values($flatSkills)),
                    'projects_json'    => json_encode($projects),
                    'is_public'        => 1,
                    'updated_at'       => date('Y-m-d H:i:s'),
                ];

                if ($vcardRecord) {
                    db()->update('user_vcards', $dataVcard, 'id = ?', [$vcardRecord['id']]);
                } else {
                    $dataVcard['created_at'] = date('Y-m-d H:i:s');
                    db()->insert('user_vcards', $dataVcard);
                }

                // 3. Update users table headline & company
                db()->update('users', [
                    'headline' => $cand['headline'] ?: '',
                    'bio'      => $cand['bio'] ?: '',
                    'company'  => $cand['company'] ?: ($user['company'] ?? ''),
                ], 'id = ?', [$userId]);

                $success = 'AI successfully parsed your resume (ATS Score: ' . ((int)($d['ats_score'] ?? 85)) . '/100) and synced your portfolio & digital vCard!';
                $portfolio   = db()->fetchOne("SELECT * FROM user_portfolios WHERE user_id = ?", [$userId]);
                $vcardRecord = db()->fetchOne("SELECT * FROM user_vcards WHERE user_id = ?", [$userId]);
            } else {
                $errors[] = 'AI parsing could not identify resume details. Please try pasting plain text.';
            }
        }
    } elseif ($action === 'save_portfolio') {
        $displayName   = trim((string)($_POST['display_name'] ?? ''));
        $jobTitle      = trim((string)($_POST['job_title'] ?? ''));
        $headline      = trim((string)($_POST['headline'] ?? ''));
        $bio           = trim((string)($_POST['bio'] ?? ''));
        $phone         = trim((string)($_POST['phone'] ?? ''));
        $whatsapp      = trim((string)($_POST['whatsapp'] ?? $phone));
        $email         = trim((string)($_POST['email'] ?? ''));
        $company       = trim((string)($_POST['company'] ?? ''));
        $location      = trim((string)($_POST['location'] ?? ''));
        $website       = trim((string)($_POST['website'] ?? ''));
        $avatarUrl     = trim((string)($_POST['avatar_url'] ?? ''));
        $templateStyle = in_array($_POST['template_style'] ?? '', ['dark_luxury', 'creative_neon', 'executive_minimal', 'minimalist_aurora'], true) 
            ? $_POST['template_style'] 
            : 'dark_luxury';

        // Skills parse
        $skillsInput = trim((string)($_POST['skills'] ?? ''));
        $skillsArray = array_values(array_filter(array_map('trim', explode(',', $skillsInput))));

        // Social Links parse
        $socials = [
            'linkedin'  => trim((string)($_POST['social_linkedin'] ?? '')),
            'twitter'   => trim((string)($_POST['social_twitter'] ?? '')),
            'instagram' => trim((string)($_POST['social_instagram'] ?? '')),
            'github'    => trim((string)($_POST['social_github'] ?? '')),
            'youtube'   => trim((string)($_POST['social_youtube'] ?? '')),
        ];

        // Projects parse (from repeaters)
        $projectTitles  = $_POST['project_title'] ?? [];
        $projectMetrics = $_POST['project_metric'] ?? [];
        $projectDescs   = $_POST['project_description'] ?? [];
        $projectLinks   = $_POST['project_link'] ?? [];
        $projectsList   = [];

        if (is_array($projectTitles)) {
            for ($i = 0; $i < count($projectTitles); $i++) {
                $pTitle = trim((string)($projectTitles[$i] ?? ''));
                if ($pTitle !== '') {
                    $projectsList[] = [
                        'title'       => $pTitle,
                        'metric'      => trim((string)($projectMetrics[$i] ?? '')),
                        'description' => trim((string)($projectDescs[$i] ?? '')),
                        'link'        => trim((string)($projectLinks[$i] ?? '')),
                    ];
                }
            }
        }

        // Services parse (from repeaters)
        $serviceTitles = $_POST['service_title'] ?? [];
        $servicePrices = $_POST['service_price'] ?? [];
        $serviceDescs  = $_POST['service_description'] ?? [];
        $servicesList  = [];

        if (is_array($serviceTitles)) {
            for ($i = 0; $i < count($serviceTitles); $i++) {
                $sTitle = trim((string)($serviceTitles[$i] ?? ''));
                if ($sTitle !== '') {
                    $servicesList[] = [
                        'title'       => $sTitle,
                        'price'       => trim((string)($servicePrices[$i] ?? '')),
                        'description' => trim((string)($serviceDescs[$i] ?? '')),
                    ];
                }
            }
        }

        if (empty($displayName)) {
            $errors[] = 'Display name is required.';
        }

        if (empty($errors)) {
            $uname = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $email ?: $user['email'])[0])));
            if (empty($uname)) $uname = 'user_' . $userId;

            // 1. Sync user_portfolios
            $dataPortfolio = [
                'user_id'              => $userId,
                'username'             => $uname,
                'display_name'         => $displayName,
                'job_title'            => $jobTitle,
                'headline'             => $headline,
                'bio'                  => $bio,
                'template_style'       => $templateStyle,
                'phone'                => $phone,
                'email'                => $email,
                'company'              => $company,
                'location'             => $location,
                'website'              => $website,
                'avatar_url'           => $avatarUrl,
                'skills_json'          => json_encode($skillsArray),
                'services_json'        => json_encode($servicesList),
                'portfolio_items_json' => json_encode($projectsList),
                'social_links_json'    => json_encode($socials),
                'is_public'            => 1,
                'updated_at'           => date('Y-m-d H:i:s'),
            ];

            if ($portfolio) {
                db()->update('user_portfolios', $dataPortfolio, 'id = ?', [$portfolio['id']]);
            } else {
                $dataPortfolio['created_at'] = date('Y-m-d H:i:s');
                db()->insert('user_portfolios', $dataPortfolio);
            }

            // 2. Sync user_vcards
            $dataVcard = [
                'user_id'          => $userId,
                'username'         => $uname,
                'headline'         => $headline,
                'bio'              => $bio,
                'template'         => $templateStyle,
                'phone'            => $phone,
                'whatsapp'         => $whatsapp,
                'company'          => $company,
                'job_title'        => $jobTitle,
                'location'         => $location,
                'website'          => $website ?: (BASE_URL . '/portfolio.php?u=' . urlencode($uname)),
                'linkedin'         => $socials['linkedin'] ?? null,
                'twitter'          => $socials['twitter'] ?? null,
                'github'           => $socials['github'] ?? null,
                'instagram'        => $socials['instagram'] ?? null,
                'skills_json'      => json_encode($skillsArray),
                'projects_json'    => json_encode($projectsList),
                'is_public'        => 1,
                'updated_at'       => date('Y-m-d H:i:s'),
            ];

            if ($vcardRecord) {
                db()->update('user_vcards', $dataVcard, 'id = ?', [$vcardRecord['id']]);
            } else {
                $dataVcard['created_at'] = date('Y-m-d H:i:s');
                db()->insert('user_vcards', $dataVcard);
            }

            // 3. Update users table
            db()->update('users', [
                'company'  => $company,
                'headline' => $headline,
                'bio'      => $bio,
            ], 'id = ?', [$userId]);

            $success = '✨ Portfolio, Digital NFC vCard, and Contact settings saved and published successfully!';
            $portfolio   = db()->fetchOne("SELECT * FROM user_portfolios WHERE user_id = ?", [$userId]);
            $vcardRecord = db()->fetchOne("SELECT * FROM user_vcards WHERE user_id = ?", [$userId]);
        }
    }
}

$pageTitle = 'Portfolio & Digital vCard Studio — ' . get_setting('site_name', 'Adsbooster.net');
$_appPage = 'portfolio-builder';
require_once __DIR__ . '/includes/app_header.php';

$curSkills    = json_decode($portfolio['skills_json'] ?? '[]', true) ?: ['Meta Ads', 'TikTok Ads', 'UGC Creative', 'ROAS Scaling', 'Copywriting'];
$curServices  = json_decode($portfolio['services_json'] ?? '[]', true) ?: [
    ['title' => 'Full Omnichannel Ad Scaling', 'price' => '$2,500/month', 'description' => 'Directing Meta and TikTok ad budgets for fast-growing brands.'],
    ['title' => 'Viral UGC Creative Testing Package', 'price' => '$650/package', 'description' => 'End-to-end creative testing, scripts, and video iteration.']
];
$curProjects  = json_decode($portfolio['portfolio_items_json'] ?? '[]', true) ?: [
    ['title' => 'DTC Fashion Brand Scaling', 'metric' => '4.8x ROAS', 'description' => 'Scaled monthly spend from $10k to $110k at 4.8x blended ROAS.', 'link' => 'https://adsbooster.net']
];
$curSocials   = json_decode($portfolio['social_links_json'] ?? '[]', true) ?: [];
$curTemplate  = $portfolio['template_style'] ?? 'dark_luxury';

$usernameSlug = $portfolio['username'] ?? $vcardRecord['username'] ?? (string)$userId;
$shareUrl     = BASE_URL . '/portfolio.php?u=' . urlencode($usernameSlug);
$vcardWebUrl  = BASE_URL . '/vcard.php?u=' . urlencode($usernameSlug);
$vcardFileUrl = BASE_URL . '/vcard-download.php?u=' . urlencode($usernameSlug);
?>

<div class="content-body">
<div class="container-fluid py-4">

    <!-- Page head -->
    <div class="fl-stagehead">
        <div>
            <div class="pill-row mb-2">
                <span class="pill on"><i class="ph ph-shield-check"></i> Multi-format showcase studio</span>
                <span class="pill"><i class="ph ph-address-book"></i> Public portfolio &bull; NFC vCard &bull; RFC 6350 .vcf</span>
            </div>
            <h1 class="h3 fw-bold mb-1">Digital vCard &amp; Portfolio Studio</h1>
            <p class="text-secondary mb-0" style="max-width:78ch">Design your public showcase, configure the interactive NFC business card, and publish your case studies — every change goes live instantly.</p>
        </div>
        <div class="pill-row">
            <a href="<?= e($vcardWebUrl) ?>" target="_blank" rel="noopener" class="fl-act"><i class="ph ph-wifi-high"></i> Virtual NFC card</a>
            <a href="<?= e($vcardFileUrl) ?>" class="fl-act"><i class="ph ph-download-simple"></i> Download .vcf</a>
            <a href="<?= e($shareUrl) ?>" target="_blank" rel="noopener" class="fl-act pri"><i class="ph ph-arrow-square-out"></i> Live portfolio</a>
        </div>
    </div>

    <?php if (!empty($success)): ?>
        <div class="fl-alert ok">
            <i class="ph ph-check-circle"></i>
            <div style="flex:1"><?= e($success) ?></div>
            <div class="pill-row" style="flex:0 0 auto">
                <a href="<?= e($shareUrl) ?>" target="_blank" rel="noopener" class="fl-act" style="padding:5px 12px;font-size:11.5px">View portfolio <i class="ph ph-arrow-square-out"></i></a>
                <a href="<?= e($vcardWebUrl) ?>" target="_blank" rel="noopener" class="fl-act" style="padding:5px 12px;font-size:11.5px">Open digital vCard</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="fl-alert bad">
            <i class="ph ph-warning-circle"></i>
            <div>
                <div style="font-weight:700;margin-bottom:4px">We couldn't publish that</div>
                <ul style="margin:0;padding-left:16px">
                    <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <!-- Share bar -->
    <div class="fl-panel" style="max-height:none;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:14px;min-width:0">
            <div style="width:46px;height:46px;border-radius:14px;background:var(--accent-dim,rgba(200,255,0,.12));color:var(--accent,#c8ff00);display:flex;align-items:center;justify-content:center;flex:0 0 auto">
                <i class="ph-bold ph-link" style="font-size:22px"></i>
            </div>
            <div style="min-width:0">
                <div style="font-size:11.5px;color:var(--fg3,#64748b);font-weight:700;letter-spacing:.06em;text-transform:uppercase">Your dedicated public showcase</div>
                <div style="font-family:'JetBrains Mono',monospace;font-size:13.5px;font-weight:700;color:var(--accent,#c8ff00);word-break:break-all"><?= e($shareUrl) ?></div>
            </div>
        </div>
        <div class="pill-row">
            <button type="button" class="fl-act" data-copy="<?= e($shareUrl) ?>" data-label="Portfolio link copied"><i class="ph ph-copy"></i> Copy portfolio URL</button>
            <button type="button" class="fl-act" data-copy="<?= e($vcardWebUrl) ?>" data-label="NFC vCard link copied"><i class="ph ph-share-network"></i> Copy NFC vCard URL</button>
        </div>
    </div>

    <div class="fl-shell" style="grid-template-columns:minmax(280px,340px) minmax(0,1fr);margin-top:16px">

        <!-- ── Left: AI import + live card preview ──────────── -->
        <div style="display:grid;gap:16px;align-content:start">
            <div class="fl-panel" style="max-height:none">
                <div class="fl-stagehead" style="margin-bottom:12px">
                    <span class="pill on" style="font-size:11px"><i class="ph ph-magic-wand"></i> Groq AI powered</span>
                    <a href="<?= url('resume-analyser') ?>" style="font-size:11.5px;color:var(--fg2,#94a3b8);text-decoration:none">Full ATS tool &rarr;</a>
                </div>
                <div style="font-size:15px;font-weight:800;color:var(--fg,#fff);margin-bottom:6px">Instant resume import</div>
                <p class="fl-help" style="margin:0 0 14px">Upload a resume or paste text — the AI auto-populates your name, headline, skills and case studies below.</p>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="parse_resume">
                    <label class="cv-label" for="pbFile">Resume file</label>
                    <input type="file" name="resume_file" id="pbFile" accept=".pdf,.docx,.txt" class="fl-in" style="padding:9px 12px;margin-bottom:12px">
                    <label class="cv-label" for="pbText">Or paste the text</label>
                    <textarea name="resume_text" id="pbText" rows="4" class="fl-in" style="min-height:96px;margin-bottom:12px" placeholder="Paste a resume, LinkedIn summary, or experience…"></textarea>
                    <button type="submit" class="fl-go" style="margin-top:0"><i class="ph ph-sparkle"></i> Auto-generate portfolio &amp; vCard</button>
                </form>
            </div>

            <div class="fl-panel" style="max-height:none">
                <div class="fl-stagehead" style="margin-bottom:12px">
                    <div class="t" style="font-size:13px">Interactive card preview</div>
                    <a href="<?= e($vcardWebUrl) ?>" target="_blank" rel="noopener" style="font-size:11.5px;color:var(--accent,#c8ff00);text-decoration:none">Open fullscreen <i class="ph ph-arrow-square-out"></i></a>
                </div>

                <div id="miniNfcCardPreview" style="border-radius:16px;padding:16px;background:linear-gradient(135deg,#131d2e 0%,#080c14 100%);border:1.5px solid rgba(200,255,0,.35);box-shadow:0 14px 34px rgba(0,0,0,.5);transition:all .3s ease">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
                        <div style="width:34px;height:25px;border-radius:5px;background:linear-gradient(135deg,#ffd700,#b8860b);border:1px solid rgba(255,255,255,.4)"></div>
                        <i id="miniNfcWave" class="ph-bold ph-wifi-high" style="color:var(--accent,#c8ff00);font-size:18px;transition:color .3s ease"></i>
                    </div>
                    <div style="display:flex;align-items:center;gap:11px;margin-bottom:12px">
                        <div id="miniNfcAvatarRing" style="width:42px;height:42px;border-radius:50%;border:1.5px solid var(--accent,#c8ff00);background:rgba(200,255,0,.1);display:flex;align-items:center;justify-content:center;font-weight:800;color:var(--accent,#c8ff00);transition:all .3s ease;flex:0 0 auto">
                            <?= strtoupper(substr($portfolio['display_name'] ?? $user['full_name'], 0, 2)) ?>
                        </div>
                        <div style="min-width:0">
                            <div style="color:#fff;font-weight:800;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:170px"><?= e($portfolio['display_name'] ?? $user['full_name']) ?></div>
                            <div id="miniNfcRole" style="font-size:11.5px;color:var(--accent,#c8ff00);transition:color .3s ease"><?= e($portfolio['job_title'] ?? 'Growth Marketer') ?></div>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:space-between;gap:8px;padding-top:11px;border-top:1px solid rgba(255,255,255,.12);font-size:11px;color:var(--fg3,#64748b)">
                        <span><i class="ph ph-map-pin"></i> <?= e($portfolio['location'] ?? 'Remote') ?></span>
                        <span id="miniNfcBadge" style="color:var(--fg2,#94a3b8);font-weight:700;transition:color .3s ease">NFC active</span>
                    </div>
                </div>

                <a href="<?= e($vcardWebUrl) ?>" target="_blank" rel="noopener" class="fl-act" style="width:100%;justify-content:center;margin-top:12px"><i class="ph ph-qr-code"></i> Test flip &amp; QR</a>
            </div>
        </div>

        <!-- ── Right: editor form ───────────────────────────── -->
        <div class="fl-panel" style="max-height:none">
            <div class="fl-stagehead">
                <div class="t"><i class="ph ph-sliders-horizontal" style="color:var(--accent,#c8ff00)"></i> Portfolio &amp; digital vCard settings</div>
                <div class="m">applies live</div>
            </div>
            <p class="fl-help" style="margin:0 0 18px">Pick a theme, set your identity, add case studies and services, then publish. Everything below maps straight to your public portfolio and vCard.</p>

            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_portfolio">

                <!-- 1. Theme -->
                <div class="fl-lbl"><span class="step">1</span> Design theme</div>
                <div class="th-cards" id="themeCards">
                    <?php
                    $themes = [
                        ['dark_luxury',      'Dark Luxury',    'Obsidian &amp; lime',  'background:#c8ff00;box-shadow:0 0 12px rgba(200,255,0,.55)', 'background:linear-gradient(135deg,#0d1524,#080c14)'],
                        ['creative_neon',    'Cyber Neon',     'Purple &amp; cyan',    'background:linear-gradient(135deg,#a855f7,#06b6d4);box-shadow:0 0 12px rgba(168,85,247,.55)', 'background:linear-gradient(135deg,#1f113a,#0d061c)'],
                        ['executive_minimal','Titanium',       'Slate &amp; sky',      'background:#38bdf8;box-shadow:0 0 12px rgba(56,189,248,.55)', 'background:linear-gradient(135deg,#131c2d,#0c121e)'],
                        ['minimalist_aurora','Aurora Glass',   'Emerald &amp; charcoal','background:#10b981;box-shadow:0 0 12px rgba(16,185,129,.55)', 'background:linear-gradient(135deg,#0e2720,#071612)'],
                    ];
                    foreach ($themes as $th): ?>
                    <button type="button" class="th-card theme-select-card <?= $curTemplate === $th[0] ? 'on active' : '' ?>"
                            data-theme="<?= e($th[0]) ?>" onclick="selectTheme('<?= e($th[0]) ?>')">
                        <input type="radio" name="template_style" id="theme_<?= e($th[0]) ?>" value="<?= e($th[0]) ?>" <?= $curTemplate === $th[0] ? 'checked' : '' ?> style="position:absolute;opacity:0;pointer-events:none" tabindex="-1">
                        <span class="ck"><i class="ph ph-check"></i></span>
                        <div class="prev" style="<?= $th[3] ?>">
                            <span class="dot" style="<?= $th[2] ?>"></span>
                            <span class="ln" style="width:56%;background:rgba(255,255,255,.55)"></span>
                            <span class="ln" style="width:36%;background:rgba(255,255,255,.3)"></span>
                        </div>
                        <div class="nm"><?= $th[1] ?><div style="font-size:10.5px;font-weight:600;color:var(--fg3,#64748b);margin-top:2px"><?= $th[2] ?></div></div>
                    </button>
                    <?php endforeach; ?>
                </div>

                <!-- 2. Identity -->
                <div class="fl-lbl"><span class="step">2</span> Personal identity &amp; headline</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label class="cv-label" for="pb_name">Display full name *</label>
                        <input type="text" name="display_name" id="pb_name" required class="fl-in" value="<?= htmlspecialchars($portfolio['display_name'] ?? $user['full_name']) ?>">
                    </div>
                    <div>
                        <label class="cv-label" for="pb_role">Professional role / job title</label>
                        <input type="text" name="job_title" id="pb_role" class="fl-in" value="<?= htmlspecialchars($portfolio['job_title'] ?? 'Performance Media Buyer & Growth Director') ?>">
                    </div>
                    <div style="grid-column:1/-1">
                        <label class="cv-label" for="pb_headline">High-impact positioning headline</label>
                        <input type="text" name="headline" id="pb_headline" class="fl-in" value="<?= htmlspecialchars($portfolio['headline'] ?? 'Scaling omnichannel ad campaigns across Meta, TikTok, and Google Ads with creative testing.') ?>">
                    </div>
                    <div>
                        <label class="cv-label" for="pb_company">Company / agency</label>
                        <input type="text" name="company" id="pb_company" class="fl-in" value="<?= htmlspecialchars($portfolio['company'] ?? $user['company'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="cv-label" for="pb_location">Location / base timezone</label>
                        <input type="text" name="location" id="pb_location" class="fl-in" value="<?= htmlspecialchars($portfolio['location'] ?? 'Worldwide / Remote') ?>">
                    </div>
                    <div style="grid-column:1/-1">
                        <label class="cv-label" for="pb_avatar">Avatar image URL</label>
                        <input type="text" name="avatar_url" id="pb_avatar" class="fl-in" placeholder="https://example.com/avatar.jpg" value="<?= htmlspecialchars($portfolio['avatar_url'] ?? $user['avatar'] ?? '') ?>">
                    </div>
                    <div style="grid-column:1/-1">
                        <label class="cv-label" for="pb_bio">Professional bio &amp; executive summary</label>
                        <textarea name="bio" id="pb_bio" rows="4" class="fl-in" style="min-height:110px"><?= htmlspecialchars($portfolio['bio'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- 3. Skills -->
                <div class="fl-lbl"><span class="step">3</span> Core skills &amp; platforms</div>
                <input type="text" name="skills" id="skillsInputField" class="fl-in" value="<?= htmlspecialchars(implode(', ', $curSkills)) ?>">
                <div class="fl-help" style="margin:8px 0 10px">Separate skills with commas — or quick-add from the chips.</div>
                <div class="fl-chips">
                    <?php foreach (['Meta Ads', 'TikTok Ads', 'Google Ads', 'Shopify Plus', 'UGC Creative', 'Copywriting', 'ROAS Scaling', 'Conversion Rate Optimization (CRO)', 'Klaviyo', 'Triple Whale'] as $suggested): ?>
                        <button type="button" class="fl-chip" onclick="addSkillTag('<?= addslashes($suggested) ?>')">+ <?= $suggested ?></button>
                    <?php endforeach; ?>
                </div>

                <!-- 4. Projects -->
                <div class="fl-lbl"><span class="step">4</span> Case studies &amp; projects</div>
                <div id="projectsRepeaterContainer" style="display:grid;gap:10px;margin-bottom:6px">
                    <?php foreach ($curProjects as $idx => $proj): ?>
                        <div class="project-row" style="position:relative;padding:14px;border-radius:13px;background:var(--bg2,#0f172a);border:1px solid var(--border,rgba(255,255,255,.08))">
                            <button type="button" onclick="this.closest('.project-row').remove()" class="fl-act danger" style="position:absolute;top:10px;right:10px;padding:4px 9px;font-size:11px" title="Remove project"><i class="ph ph-x"></i></button>
                            <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:9px;padding-right:76px">
                                <div>
                                    <label class="cv-label">Project / campaign title</label>
                                    <input type="text" name="project_title[]" class="fl-in" style="padding:8px 11px;font-size:12.5px" value="<?= htmlspecialchars($proj['title'] ?? '') ?>" placeholder="e.g. DTC apparel scale">
                                </div>
                                <div>
                                    <label class="cv-label">Highlight metric</label>
                                    <input type="text" name="project_metric[]" class="fl-in" style="padding:8px 11px;font-size:12.5px" value="<?= htmlspecialchars($proj['metric'] ?? '') ?>" placeholder="e.g. 4.8x ROAS">
                                </div>
                                <div style="grid-column:1/-1">
                                    <label class="cv-label">Description / results</label>
                                    <input type="text" name="project_description[]" class="fl-in" style="padding:8px 11px;font-size:12.5px" value="<?= htmlspecialchars($proj['description'] ?? '') ?>" placeholder="One-sentence accomplishment summary">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" onclick="addProjectRow()" class="fl-act"><i class="ph ph-plus"></i> Add case study</button>

                <!-- 5. Services -->
                <div class="fl-lbl"><span class="step">5</span> Service packages &amp; rates</div>
                <div id="servicesRepeaterContainer" style="display:grid;gap:10px;margin-bottom:6px">
                    <?php foreach ($curServices as $idx => $srv): ?>
                        <div class="service-row" style="position:relative;padding:14px;border-radius:13px;background:var(--bg2,#0f172a);border:1px solid var(--border,rgba(255,255,255,.08))">
                            <button type="button" onclick="this.closest('.service-row').remove()" class="fl-act danger" style="position:absolute;top:10px;right:10px;padding:4px 9px;font-size:11px" title="Remove service"><i class="ph ph-x"></i></button>
                            <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:9px;padding-right:76px">
                                <div>
                                    <label class="cv-label">Service name</label>
                                    <input type="text" name="service_title[]" class="fl-in" style="padding:8px 11px;font-size:12.5px" value="<?= htmlspecialchars($srv['title'] ?? '') ?>" placeholder="e.g. Omnichannel growth retainer">
                                </div>
                                <div>
                                    <label class="cv-label">Pricing / rate</label>
                                    <input type="text" name="service_price[]" class="fl-in" style="padding:8px 11px;font-size:12.5px" value="<?= htmlspecialchars($srv['price'] ?? '') ?>" placeholder="e.g. $2,500/month">
                                </div>
                                <div style="grid-column:1/-1">
                                    <label class="cv-label">Deliverables / scope</label>
                                    <input type="text" name="service_description[]" class="fl-in" style="padding:8px 11px;font-size:12.5px" value="<?= htmlspecialchars($srv['description'] ?? '') ?>" placeholder="Weekly creative briefs, media management, attribution">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" onclick="addServiceRow()" class="fl-act"><i class="ph ph-plus"></i> Add service</button>

                <!-- 6. Contact -->
                <div class="fl-lbl"><span class="step">6</span> Contact details &amp; social links</div>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
                    <div>
                        <label class="cv-label" for="pb_email">Public email</label>
                        <input type="email" name="email" id="pb_email" class="fl-in" value="<?= htmlspecialchars($portfolio['email'] ?? $user['email']) ?>">
                    </div>
                    <div>
                        <label class="cv-label" for="pb_phone">Direct phone</label>
                        <input type="text" name="phone" id="pb_phone" class="fl-in" value="<?= htmlspecialchars($portfolio['phone'] ?? $user['phone'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="cv-label" for="pb_wa">WhatsApp number</label>
                        <input type="text" name="whatsapp" id="pb_wa" class="fl-in" value="<?= htmlspecialchars($vcardRecord['whatsapp'] ?? $portfolio['phone'] ?? $user['phone'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="cv-label" for="pb_li">LinkedIn</label>
                        <input type="url" name="social_linkedin" id="pb_li" class="fl-in" placeholder="https://linkedin.com/in/..." value="<?= htmlspecialchars($curSocials['linkedin'] ?? $vcardRecord['linkedin'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="cv-label" for="pb_tw">Twitter / X</label>
                        <input type="url" name="social_twitter" id="pb_tw" class="fl-in" placeholder="https://x.com/..." value="<?= htmlspecialchars($curSocials['twitter'] ?? $vcardRecord['twitter'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="cv-label" for="pb_ig">Instagram</label>
                        <input type="url" name="social_instagram" id="pb_ig" class="fl-in" placeholder="https://instagram.com/..." value="<?= htmlspecialchars($curSocials['instagram'] ?? $vcardRecord['instagram'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="cv-label" for="pb_gh">GitHub</label>
                        <input type="url" name="social_github" id="pb_gh" class="fl-in" placeholder="https://github.com/..." value="<?= htmlspecialchars($curSocials['github'] ?? $vcardRecord['github'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="cv-label" for="pb_site">Website / blog</label>
                        <input type="url" name="website" id="pb_site" class="fl-in" placeholder="https://example.com" value="<?= htmlspecialchars($portfolio['website'] ?? $vcardRecord['website'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="cv-label" for="pb_yt">YouTube</label>
                        <input type="url" name="social_youtube" id="pb_yt" class="fl-in" placeholder="https://youtube.com/..." value="<?= htmlspecialchars($curSocials['youtube'] ?? '') ?>">
                    </div>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-top:22px;padding-top:20px;border-top:1px solid var(--border,rgba(255,255,255,.08))">
                    <button type="submit" class="fl-go" style="width:auto;margin:0;padding:14px 26px">
                        <i class="ph ph-floppy-disk"></i> Save &amp; publish everything
                    </button>
                    <a href="<?= e($shareUrl) ?>" target="_blank" rel="noopener" class="fl-act"><i class="ph ph-eye"></i> Preview live showcase</a>
                </div>

            </form>
        </div>

    </div>
</div>
</div>

<script>
function addSkillTag(tag) {
    var field = document.getElementById('skillsInputField');
    var current = field.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
    if (current.indexOf(tag) === -1) {
        current.push(tag);
        field.value = current.join(', ');
    }
}

function addProjectRow() {
    var container = document.getElementById('projectsRepeaterContainer');
    var html = '' +
    '<div class="project-row" style="position:relative;padding:14px;border-radius:13px;background:var(--bg2,#0f172a);border:1px solid var(--border,rgba(255,255,255,.08))">' +
        '<button type="button" onclick="this.closest(\'.project-row\').remove()" class="fl-act danger" style="position:absolute;top:10px;right:10px;padding:4px 9px;font-size:11px" title="Remove project"><i class="ph ph-x"></i></button>' +
        '<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:9px;padding-right:76px">' +
            '<div><label class="cv-label">Project / campaign title</label>' +
            '<input type="text" name="project_title[]" class="fl-in" style="padding:8px 11px;font-size:12.5px" placeholder="e.g. Black Friday campaign scaling"></div>' +
            '<div><label class="cv-label">Highlight metric</label>' +
            '<input type="text" name="project_metric[]" class="fl-in" style="padding:8px 11px;font-size:12.5px" placeholder="e.g. 5.2x ROAS"></div>' +
            '<div style="grid-column:1/-1"><label class="cv-label">Description / results</label>' +
            '<input type="text" name="project_description[]" class="fl-in" style="padding:8px 11px;font-size:12.5px" placeholder="Brief result summary"></div>' +
        '</div>' +
    '</div>';
    container.insertAdjacentHTML('beforeend', html);
}

function addServiceRow() {
    var container = document.getElementById('servicesRepeaterContainer');
    var html = '' +
    '<div class="service-row" style="position:relative;padding:14px;border-radius:13px;background:var(--bg2,#0f172a);border:1px solid var(--border,rgba(255,255,255,.08))">' +
        '<button type="button" onclick="this.closest(\'.service-row\').remove()" class="fl-act danger" style="position:absolute;top:10px;right:10px;padding:4px 9px;font-size:11px" title="Remove service"><i class="ph ph-x"></i></button>' +
        '<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:9px;padding-right:76px">' +
            '<div><label class="cv-label">Service name</label>' +
            '<input type="text" name="service_title[]" class="fl-in" style="padding:8px 11px;font-size:12.5px" placeholder="e.g. TikTok paid media sprint"></div>' +
            '<div><label class="cv-label">Pricing / rate</label>' +
            '<input type="text" name="service_price[]" class="fl-in" style="padding:8px 11px;font-size:12.5px" placeholder="e.g. $1,800/month"></div>' +
            '<div style="grid-column:1/-1"><label class="cv-label">Deliverables / scope</label>' +
            '<input type="text" name="service_description[]" class="fl-in" style="padding:8px 11px;font-size:12.5px" placeholder="Deliverable details"></div>' +
        '</div>' +
    '</div>';
    container.insertAdjacentHTML('beforeend', html);
}

function selectTheme(themeName) {
    var radio = document.querySelector('input[name="template_style"][value="' + themeName + '"]');
    if (radio) radio.checked = true;

    document.querySelectorAll('.theme-select-card').forEach(function (c) {
        c.classList.remove('on', 'active');
    });
    var activeCard = document.querySelector('.theme-select-card[data-theme="' + themeName + '"]');
    if (activeCard) activeCard.classList.add('on', 'active');

    var miniCard = document.getElementById('miniNfcCardPreview');
    var miniChip = document.getElementById('miniNfcWave');
    var miniBorder = document.getElementById('miniNfcAvatarRing');
    var miniRole = document.getElementById('miniNfcRole');
    var miniBadge = document.getElementById('miniNfcBadge');

    if (!miniCard) return;
    if (themeName === 'creative_neon') {
        miniCard.style.background = 'linear-gradient(135deg, #1f113a 0%, #0d061c 100%)';
        miniCard.style.borderColor = 'rgba(168, 85, 247, 0.45)';
        if (miniChip) miniChip.style.color = '#a855f7';
        if (miniBorder) miniBorder.style.borderColor = '#a855f7';
        if (miniRole) miniRole.style.color = '#a855f7';
        if (miniBadge) { miniBadge.style.color = '#a855f7'; miniBadge.innerText = 'Cyber Neon'; }
    } else if (themeName === 'executive_minimal') {
        miniCard.style.background = 'linear-gradient(135deg, #131c2d 0%, #0c121e 100%)';
        miniCard.style.borderColor = 'rgba(56, 189, 248, 0.45)';
        if (miniChip) miniChip.style.color = '#38bdf8';
        if (miniBorder) miniBorder.style.borderColor = '#38bdf8';
        if (miniRole) miniRole.style.color = '#38bdf8';
        if (miniBadge) { miniBadge.style.color = '#38bdf8'; miniBadge.innerText = 'Executive Titanium'; }
    } else if (themeName === 'minimalist_aurora') {
        miniCard.style.background = 'linear-gradient(135deg, #0e2720 0%, #071612 100%)';
        miniCard.style.borderColor = 'rgba(16, 185, 129, 0.45)';
        if (miniChip) miniChip.style.color = '#10b981';
        if (miniBorder) miniBorder.style.borderColor = '#10b981';
        if (miniRole) miniRole.style.color = '#10b981';
        if (miniBadge) { miniBadge.style.color = '#10b981'; miniBadge.innerText = 'Aurora Glass'; }
    } else {
        miniCard.style.background = 'linear-gradient(135deg, #131d2e 0%, #080c14 100%)';
        miniCard.style.borderColor = 'rgba(200, 255, 0, 0.35)';
        if (miniChip) miniChip.style.color = '#c8ff00';
        if (miniBorder) miniBorder.style.borderColor = '#c8ff00';
        if (miniRole) miniRole.style.color = '#c8ff00';
        if (miniBadge) { miniBadge.style.color = '#c8ff00'; miniBadge.innerText = 'Dark Luxury'; }
    }
}
window.updateThemePreview = selectTheme;

(function () {
    var toastEl;
    function toast(msg) {
        if (!toastEl) { toastEl = document.createElement('div'); toastEl.className = 'copy-toast'; document.body.appendChild(toastEl); }
        toastEl.textContent = msg;
        toastEl.classList.add('show');
        clearTimeout(toastEl._t);
        toastEl._t = setTimeout(function () { toastEl.classList.remove('show'); }, 2200);
    }
    document.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-copy]');
        if (!b) return;
        var t = b.getAttribute('data-copy') || '';
        var done = function () { toast(b.getAttribute('data-label') || 'Copied'); };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(t).then(done, function () { toast('Could not copy', true); });
        } else {
            var ta = document.createElement('textarea');
            ta.value = t; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); done(); } catch (e) { toast('Could not copy', true); }
            ta.remove();
        }
    });
})();
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
