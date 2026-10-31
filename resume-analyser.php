<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/modules/ai_engine.php';

$userId = current_user_id();
$user   = current_user();

$analysisResult = null;
$rawInputText   = '';
$successMessage = '';
$errorMessage   = '';

// Handle Resume Analysis or 1-Click Portfolio Sync
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'analyze') {
        $extractedText = '';

        // 1. Check file upload (PDF, DOCX, TXT)
        if (!empty($_FILES['resume_file']['name']) && $_FILES['resume_file']['error'] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['resume_file']['tmp_name'];
            $origName = $_FILES['resume_file']['name'];
            $extractedText = AIEngine::extractTextFromFile($tmpPath, $origName);
        }

        // 2. Check pasted text if no file or appended
        $pastedText = trim((string)($_POST['resume_text'] ?? ''));
        if ($pastedText !== '') {
            $extractedText = $extractedText !== '' ? ($extractedText . "\n\n" . $pastedText) : $pastedText;
        }

        $rawInputText = $extractedText;

        if (empty(trim($extractedText))) {
            $errorMessage = 'Please provide resume text by uploading a PDF, DOCX, or TXT file, or by pasting your resume content.';
        } else {
            $analysis = AIEngine::analyzeResume($extractedText, $userId);
            if (!empty($analysis['success']) && !empty($analysis['data'])) {
                $analysisResult = $analysis['data'];
                $_SESSION['last_resume_analysis'] = $analysisResult;
                $successMessage = 'ATS analysis completed successfully! Review your score, competencies, and optimizations below.';
            } else {
                $errorMessage = 'Unable to analyze resume. Please ensure the text contains readable career details or try plain text.';
            }
        }
    } elseif ($action === 'sync_to_portfolio') {
        // Sync previously analyzed data or post data to user_portfolios & user_vcards
        $stored = $_SESSION['last_resume_analysis'] ?? null;
        if (!$stored) {
            $errorMessage = 'No active analysis found to sync. Please run a resume analysis first.';
        } else {
            $cand = $stored['candidate'] ?? [];
            $flatSkills = $stored['flat_skills'] ?? [];
            $services = $stored['services'] ?? [];
            $projects = $stored['portfolio_items'] ?? [];

            $uname = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $cand['email'] ?: $user['email'])[0])));
            if (empty($uname)) $uname = 'user_' . $userId;

            // 1. Sync user_portfolios
            $portfolioData = [
                'user_id'              => $userId,
                'username'             => $uname,
                'display_name'         => $cand['display_name'] ?: $user['full_name'],
                'job_title'            => $cand['job_title'] ?: 'Performance Advertiser & Creator',
                'headline'             => $cand['headline'] ?: '',
                'bio'                  => $cand['bio'] ?: '',
                'phone'                => $cand['phone'] ?: ($user['phone'] ?? ''),
                'email'                => $cand['email'] ?: $user['email'],
                'company'              => $cand['company'] ?: ($user['company'] ?? 'Adsbooster Partner Network'),
                'location'             => $cand['location'] ?: 'Worldwide / Remote',
                'website'              => $cand['website'] ?: BASE_URL . '/portfolio.php?u=' . urlencode($uname),
                'skills_json'          => json_encode(array_values($flatSkills)),
                'services_json'        => json_encode($services),
                'portfolio_items_json' => json_encode($projects),
                'is_public'            => 1,
                'updated_at'           => date('Y-m-d H:i:s'),
            ];

            $existingPortfolio = db()->fetchOne("SELECT id FROM user_portfolios WHERE user_id = ?", [$userId]);
            if ($existingPortfolio) {
                db()->update('user_portfolios', $portfolioData, 'id = ?', [$existingPortfolio['id']]);
            } else {
                $portfolioData['template_style'] = 'dark_luxury';
                $portfolioData['created_at'] = date('Y-m-d H:i:s');
                db()->insert('user_portfolios', $portfolioData);
            }

            // 2. Sync user_vcards
            $vcardData = [
                'user_id'          => $userId,
                'username'         => $uname,
                'headline'         => $cand['headline'] ?: '',
                'bio'              => $cand['bio'] ?: '',
                'phone'            => $cand['phone'] ?: ($user['phone'] ?? ''),
                'whatsapp'         => $cand['phone'] ?: ($user['phone'] ?? ''),
                'company'          => $cand['company'] ?: ($user['company'] ?? 'Adsbooster.net'),
                'job_title'        => $cand['job_title'] ?: 'Performance Marketer',
                'location'         => $cand['location'] ?: 'Worldwide / Remote',
                'website'          => $cand['website'] ?: (BASE_URL . '/portfolio.php?u=' . urlencode($uname)),
                'skills_json'      => json_encode(array_values($flatSkills)),
                'projects_json'    => json_encode($projects),
                'is_public'        => 1,
                'updated_at'       => date('Y-m-d H:i:s'),
            ];

            $existingVcard = db()->fetchOne("SELECT id FROM user_vcards WHERE user_id = ?", [$userId]);
            if ($existingVcard) {
                db()->update('user_vcards', $vcardData, 'id = ?', [$existingVcard['id']]);
            } else {
                $vcardData['template'] = 'dark_luxury';
                $vcardData['created_at'] = date('Y-m-d H:i:s');
                db()->insert('user_vcards', $vcardData);
            }

            // 3. Update users table headline & company
            db()->update('users', [
                'headline' => $cand['headline'] ?: '',
                'bio'      => $cand['bio'] ?: '',
                'company'  => $cand['company'] ?: ($user['company'] ?? ''),
            ], 'id = ?', [$userId]);

            $analysisResult = $stored;
            $successMessage = '✨ 1-Click Sync Complete! Your Public Portfolio and Digital vCard have been updated with your ATS-optimized profile, skills, and accomplishments.';
        }
    }
} elseif (isset($_SESSION['last_resume_analysis']) && is_array($_SESSION['last_resume_analysis'])) {
    $analysisResult = $_SESSION['last_resume_analysis'];
}

$pageTitle = 'AI Resume Analyser & ATS Optimizer — ' . get_setting('site_name', 'Adsbooster.net');
$_appPage = 'resume-analyser';
require_once __DIR__ . '/includes/app_header.php';
?>

<div class="content-body">
<div class="container-fluid py-4">

    <!-- Page head -->
    <div class="fl-stagehead">
        <div>
            <div class="pill-row mb-2">
                <span class="pill on"><i class="ph ph-magic-wand"></i> Groq LPU &amp; ATS algorithm auditor</span>
                <span class="pill"><span class="dot-live"></span> Recruiter screening optimizer</span>
            </div>
            <h1 class="h3 fw-bold mb-1">AI Resume Analyser &amp; ATS Optimizer</h1>
            <p class="text-secondary mb-0" style="max-width:80ch">Audit your resume against modern applicant tracking systems, close high-impact keyword gaps, then sync the polished profile into your public portfolio and digital vCard in one click.</p>
        </div>
        <div class="pill-row">
            <a href="<?= url('portfolio-builder') ?>" class="fl-act"><i class="ph ph-paint-brush"></i> Portfolio studio</a>
            <a href="<?= url('vcard') ?>" target="_blank" rel="noopener" class="fl-act pri"><i class="ph ph-address-book"></i> View my vCard</a>
        </div>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="fl-alert ok">
            <i class="ph ph-check-circle"></i>
            <div style="flex:1"><?= e($successMessage) ?></div>
            <div class="pill-row" style="flex:0 0 auto">
                <a href="<?= url('portfolio') ?>" target="_blank" rel="noopener" class="fl-act" style="padding:5px 12px;font-size:11.5px">View portfolio <i class="ph ph-arrow-square-out"></i></a>
                <a href="<?= url('vcard') ?>" target="_blank" rel="noopener" class="fl-act" style="padding:5px 12px;font-size:11.5px">View digital card</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="fl-alert bad"><i class="ph ph-warning-circle"></i><div><?= e($errorMessage) ?></div></div>
    <?php endif; ?>

    <div class="fl-shell fl-2">

        <!-- ── Uploader ──────────────────────────────────────── -->
        <div class="fl-panel">
            <div class="fl-stagehead" style="margin-bottom:14px">
                <div class="t"><i class="ph ph-file-text" style="color:var(--accent,#c8ff00)"></i> Input resume</div>
                <button type="button" class="fl-act" onclick="loadSampleResume()"><i class="ph ph-lightning"></i> Load sample</button>
            </div>
            <p class="fl-help" style="margin:0 0 14px">Upload a file or paste your resume text. The AI extracts your career history, quantifies commercial achievements and computes an ATS readiness score.</p>

            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="analyze">

                <div class="fl-lbl"><span class="step">1</span> Upload a file</div>
                <label for="resumeFileInput" id="dropzoneBox" style="display:block;border:2px dashed var(--border2,rgba(255,255,255,.14));border-radius:16px;padding:26px 16px;text-align:center;cursor:pointer;transition:border-color .18s,background .18s;background:var(--bg,#080c14)">
                    <input type="file" name="resume_file" id="resumeFileInput" accept=".pdf,.docx,.txt" style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden" onchange="handleFileSelect(this)">
                    <div style="width:46px;height:46px;border-radius:13px;background:var(--accent-dim,rgba(200,255,0,.12));color:var(--accent,#c8ff00);display:inline-flex;align-items:center;justify-content:center;margin-bottom:10px">
                        <i class="ph-bold ph-cloud-arrow-up" style="font-size:23px"></i>
                    </div>
                    <div style="color:var(--fg,#fff);font-weight:700;font-size:13px;margin-bottom:4px" id="fileLabelTitle">Choose a file or drag &amp; drop</div>
                    <div style="color:var(--fg3,#64748b);font-size:11.5px" id="fileLabelSub">PDF, Microsoft Word (.docx) or plain text</div>
                </label>

                <div class="fl-lbl"><span class="step">2</span> Or paste the text</div>
                <textarea name="resume_text" id="resumeTextArea" rows="9" class="fl-in" style="font-family:'JetBrains Mono',monospace;font-size:12px;line-height:1.65;min-height:190px" placeholder="Paste raw resume text, a LinkedIn export, or an experience summary…"><?= e($rawInputText) ?></textarea>

                <button type="submit" class="fl-go">
                    <i class="ph ph-cpu"></i> Run deep ATS analysis
                </button>
            </form>
        </div>

        <!-- ── Report ────────────────────────────────────────── -->
        <div style="display:grid;gap:16px;align-content:start">
        <?php if (!empty($analysisResult)): ?>
            <?php
                $atsScore   = (int)($analysisResult['ats_score'] ?? 85);
                $subScores  = $analysisResult['sub_scores'] ?? [];
                $candidate  = $analysisResult['candidate'] ?? [];
                $skills     = $analysisResult['skills'] ?? [];
                $flatSkills = $analysisResult['flat_skills'] ?? [];
                $strengths  = $analysisResult['strengths'] ?? [];
                $missingKw  = $analysisResult['missing_keywords'] ?? [];
                $improvements = $analysisResult['actionable_improvements'] ?? [];
                $portfolioItems = $analysisResult['portfolio_items'] ?? [];

                $gaugeColor = $atsScore >= 85 ? '#c8ff00' : ($atsScore >= 75 ? '#38bdf8' : '#f59e0b');
                $strokeDash = (int)(2 * M_PI * 45);
                $strokeOffset = $strokeDash - ($strokeDash * $atsScore / 100);
            ?>

            <!-- Score -->
            <div class="fl-panel" style="max-height:none">
                <div style="display:flex;gap:26px;align-items:center;flex-wrap:wrap">
                    <div class="gauge">
                        <svg viewBox="0 0 100 100">
                            <circle class="track" cx="50" cy="50" r="45"></circle>
                            <circle class="bar" cx="50" cy="50" r="45" stroke="<?= $gaugeColor ?>"
                                    stroke-dasharray="<?= $strokeDash ?>" stroke-dashoffset="<?= $strokeOffset ?>"></circle>
                        </svg>
                        <div class="lbl">
                            <span class="n"><?= $atsScore ?></span>
                            <span class="d">ATS score</span>
                        </div>
                    </div>

                    <div style="flex:1;min-width:240px">
                        <div class="pill-row" style="margin-bottom:9px">
                            <span class="pill on" style="font-size:12px">Grade <?= e($analysisResult['grade'] ?? 'A') ?></span>
                            <span class="pill good" style="font-size:12px"><i class="ph ph-check-circle"></i> <?= e($analysisResult['verdict'] ?? 'ATS Ready') ?></span>
                        </div>
                        <div style="font-size:13.5px;color:var(--fg2,#94a3b8);line-height:1.6;margin-bottom:14px">
                            Candidate <strong style="color:var(--fg,#fff)"><?= e($candidate['display_name'] ?? 'Candidate') ?></strong> &bull;
                            targeting <strong style="color:var(--accent,#c8ff00)"><?= e($candidate['job_title'] ?? 'Growth Marketer') ?></strong>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px 22px">
                            <?php
                            $meters = [
                                ['Readability', (int)($subScores['readability'] ?? 90)],
                                ['Keyword density', (int)($subScores['keyword_optimization'] ?? 85)],
                                ['Impact & metrics', (int)($subScores['impact_metrics'] ?? 88)],
                                ['ATS structure', (int)($subScores['structure'] ?? 92)],
                            ];
                            foreach ($meters as $mtr): ?>
                            <div class="meter">
                                <div class="r"><span><?= e($mtr[0]) ?></span><b><?= $mtr[1] ?>%</b></div>
                                <div class="fl-progress"><i style="width:<?= $mtr[1] ?>%"></i></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:20px;padding-top:18px;border-top:1px solid var(--border,rgba(255,255,255,.08))">
                    <div style="font-size:13px;color:var(--fg2,#94a3b8)">
                        <i class="ph ph-sparkle" style="color:var(--accent,#c8ff00)"></i>
                        Apply these ATS-optimized results to your public portfolio &amp; vCard?
                    </div>
                    <form method="POST" class="m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="sync_to_portfolio">
                        <button type="submit" class="fl-act pri"><i class="ph ph-arrows-clockwise"></i> 1-click sync to portfolio &amp; vCard</button>
                    </form>
                </div>
            </div>

            <!-- Executive summary -->
            <div class="fl-panel" style="max-height:none">
                <div class="fl-stagehead" style="margin-bottom:12px">
                    <div class="t"><i class="ph ph-identification-badge" style="color:var(--accent,#c8ff00)"></i> AI-polished executive summary</div>
                    <button type="button" class="fl-act" id="btnCopyBio"><i class="ph ph-copy"></i> Copy bio</button>
                </div>
                <p style="color:var(--accent,#c8ff00);font-weight:700;font-size:14px;margin:0 0 10px">“<?= e($candidate['headline'] ?? '') ?>”</p>
                <p style="color:var(--fg2,#94a3b8);font-size:13.5px;line-height:1.8;margin:0"><?= nl2br(e($candidate['bio'] ?? '')) ?></p>

                <div class="pill-row" style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border,rgba(255,255,255,.08))">
                    <?php if (!empty($candidate['email'])): ?><span class="pill" style="font-size:11.5px"><i class="ph ph-envelope-simple"></i> <?= e($candidate['email']) ?></span><?php endif; ?>
                    <?php if (!empty($candidate['phone'])): ?><span class="pill" style="font-size:11.5px"><i class="ph ph-phone"></i> <?= e($candidate['phone']) ?></span><?php endif; ?>
                    <?php if (!empty($candidate['location'])): ?><span class="pill" style="font-size:11.5px"><i class="ph ph-map-pin"></i> <?= e($candidate['location']) ?></span><?php endif; ?>
                    <?php if (!empty($candidate['company'])): ?><span class="pill" style="font-size:11.5px"><i class="ph ph-buildings"></i> <?= e($candidate['company']) ?></span><?php endif; ?>
                </div>
            </div>

            <!-- Skills -->
            <div class="fl-panel" style="max-height:none">
                <div class="fl-stagehead" style="margin-bottom:12px">
                    <div class="t"><i class="ph ph-check-square-offset" style="color:var(--accent,#c8ff00)"></i> Core competencies &amp; platforms found</div>
                    <div class="m"><?= count($flatSkills) ?> detected</div>
                </div>
                <div class="pill-row">
                    <?php foreach ($flatSkills as $sk): ?>
                        <span class="kw have"><i class="ph ph-check"></i> <?= e($sk) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Case studies -->
            <?php if (!empty($portfolioItems)): ?>
                <div class="fl-panel" style="max-height:none">
                    <div class="fl-stagehead" style="margin-bottom:12px">
                        <div class="t"><i class="ph ph-trophy" style="color:var(--accent,#c8ff00)"></i> Extracted quantifiable case studies</div>
                    </div>
                    <div class="fl-lib">
                        <?php foreach ($portfolioItems as $item): ?>
                            <div class="fl-card" style="min-height:120px">
                                <div class="bd">
                                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:9px;margin-bottom:7px">
                                        <h5 style="margin:0;font-size:13px;font-weight:800;color:var(--fg,#fff)"><?= e($item['title'] ?? 'Campaign result') ?></h5>
                                        <?php if (!empty($item['metric'])): ?>
                                            <span class="pill on" style="font-size:10.5px;font-family:'JetBrains Mono',monospace;flex:0 0 auto"><?= e($item['metric']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <p style="margin:0;color:var(--fg2,#94a3b8);font-size:12.5px;line-height:1.55"><?= e($item['description'] ?? '') ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Gaps + advice -->
            <div class="fl-shell" style="grid-template-columns:1fr 1fr">
                <div class="fl-panel" style="max-height:none">
                    <div class="fl-stagehead" style="margin-bottom:10px">
                        <div class="t"><i class="ph ph-warning-diamond" style="color:#fb923c"></i> Missing high-impact keywords</div>
                    </div>
                    <p class="fl-help" style="margin:0 0 12px">Adding these target terms raises your ATS screening pass rate.</p>
                    <div class="pill-row">
                        <?php foreach ($missingKw as $kw): ?>
                            <span class="kw miss">+ <?= e($kw) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="fl-panel" style="max-height:none">
                    <div class="fl-stagehead" style="margin-bottom:10px">
                        <div class="t"><i class="ph ph-lightbulb" style="color:var(--accent,#c8ff00)"></i> Actionable ATS improvements</div>
                    </div>
                    <ul style="margin:0;padding-left:18px;color:var(--fg2,#94a3b8);font-size:13px;line-height:1.65">
                        <?php foreach ($improvements as $imp): ?>
                            <li style="margin-bottom:7px"><?= e($imp) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

        <?php else: ?>
            <div class="fl-panel" style="max-height:none;display:flex;align-items:center;justify-content:center;min-height:480px">
                <div class="fl-empty" style="max-width:420px">
                    <i class="ph ph-gauge"></i>
                    <h4>No active analysis yet</h4>
                    <p>Upload your resume as PDF or Word, or paste a career summary on the left, to get an instant ATS score, keyword audit and one-click portfolio sync.</p>
                    <button type="button" class="fl-act" style="margin-top:18px" onclick="loadSampleResume()">
                        <i class="ph ph-file-text"></i> Load sample media-buyer resume
                    </button>
                </div>
            </div>
        <?php endif; ?>
        </div>

    </div>
</div>
</div>

<script>
function handleFileSelect(input) {
    if (input.files && input.files[0]) {
        var file = input.files[0];
        document.getElementById('fileLabelTitle').textContent = file.name;
        document.getElementById('fileLabelSub').textContent = (file.size / 1024).toFixed(1) + ' KB — ready for analysis';
        var dz = document.getElementById('dropzoneBox');
        dz.style.borderColor = 'var(--accent)';
        dz.style.background = 'var(--accent-dim, rgba(200,255,0,.07))';
    }
}

(function () {
    var box = document.getElementById('dropzoneBox');
    if (!box) return;
    ['dragenter', 'dragover'].forEach(function (ev) {
        box.addEventListener(ev, function (e) { e.preventDefault(); box.style.borderColor = 'var(--accent)'; });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        box.addEventListener(ev, function (e) { e.preventDefault(); if (ev === 'dragleave') box.style.borderColor = ''; });
    });
    box.addEventListener('drop', function (e) {
        var input = document.getElementById('resumeFileInput');
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            handleFileSelect(input);
        }
    });
})();

(function () {
    var btn = document.getElementById('btnCopyBio');
    if (!btn) return;
    var el = document.querySelector('p[style*="accent"]');
    btn.addEventListener('click', function () {
        var panels = document.querySelectorAll('.fl-panel p');
        var text = '';
        for (var i = 0; i < panels.length; i++) {
            if (panels[i].textContent.indexOf('“') === 0) { text = panels[i].textContent.trim(); break; }
        }
        if (!text && el) text = el.textContent.trim();
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(text);
        toast('Bio copied');
    });

    var toastEl;
    function toast(msg) {
        if (!toastEl) { toastEl = document.createElement('div'); toastEl.className = 'copy-toast'; document.body.appendChild(toastEl); }
        toastEl.textContent = msg;
        toastEl.classList.add('show');
        clearTimeout(toastEl._t);
        toastEl._t = setTimeout(function () { toastEl.classList.remove('show'); }, 2200);
    }
})();

function loadSampleResume() {
    var sample = 'Alex Rivera\n' +
'Senior Omnichannel Media Buyer & Creative Strategist\n' +
'Email: alex.rivera@adsbooster.net | Phone: +1 (555) 019-2831 | New York, NY / Remote\n' +
'Portfolio: https://adsbooster.net/portfolio.php?u=alexrivera\n' +
'\n' +
'SUMMARY:\n' +
'Results-driven Performance Marketing Director with 6+ years orchestrating profitable omnichannel ad campaigns across Meta Ads, TikTok Ads, and Google Ads. Managed over $3.2M in annual ad spend, delivering an average blended ROAS of 4.4x for fast-growing DTC eCommerce and B2B SaaS brands. Expert in UGC creative iteration, Landing Page CRO, and full-funnel attribution modeling.\n' +
'\n' +
'CORE SKILLS:\n' +
'Meta Ads Manager, TikTok Ads Manager, Google Performance Max, UGC Creative Testing, Copywriting, Conversion Rate Optimization (CRO), Shopify Plus, Triple Whale Attribution, Klaviyo Retention Marketing, Budget Allocation.\n' +
'\n' +
'PROVEN CAMPAIGN HIGHLIGHTS:\n' +
'1. Omnichannel DTC Apparel Brand Scaling: Scaled monthly ad spend from $14k to $145k across Meta and TikTok while increasing blended ROAS from 3.1x to 4.8x.\n' +
'2. B2B SaaS Acquisition Sprint: Revamped creative hooks and landing page sales copy, slashing cost-per-qualified-lead (CPA) by 41% and driving $320k in pipeline value.\n' +
'\n' +
'SERVICES OFFERED:\n' +
'- Full Omnichannel Media Buying Retainer: $2,500/month\n' +
'- High-Converting UGC Creative Testing Sprint: $750/campaign\n' +
'- Paid Ads Account & CRO Audit: $450/audit';

    var ta = document.getElementById('resumeTextArea');
    ta.value = sample;
    ta.focus();
}
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
