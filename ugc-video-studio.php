<?php
declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/ai_video_engine.php';

if (!is_logged_in()) {
    set_flash('error', 'Please log in to access the AI Video Studio.');
    redirect(url('login'));
}

$pageTitle = 'AI Video Studio — Adsbooster.net';
$user = current_user();
$userId = (int)current_user_id();

AIVideoEngine::ensureTable();

$avatars         = AIVideoEngine::getAvatars();
$voices          = AIVideoEngine::getVoices();
$commercialStyles= AIVideoEngine::getCommercialStyles();
$captionStyles   = AIVideoEngine::getCaptionStyles();
$audioTracks     = AIVideoEngine::getAudioTracks();
$providers       = AIVideoEngine::getProviders();
$activeProvider  = AIVideoEngine::resolveProvider();
$aspects         = AIVideoEngine::getAspectRatios();

/**
 * JSON response helper — always returns valid JSON, never mixed HTML.
 */
function studio_json(array $payload, int $code = 200): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function studio_client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

// ───────────────────────────────────────────────────────────────
//  AJAX API
// ───────────────────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify()) {
        studio_json(['success' => false, 'error' => 'Your session expired. Refresh the page and try again.'], 419);
    }

    $action = (string)($_POST['action'] ?? '');

    try {
        switch ($action) {

            // ── AI script / storyboard / prompt enhancement ──
            case 'generate_script': {
                $productName = trim($_POST['product_name'] ?? '');
                $benefits    = trim($_POST['benefits'] ?? '');
                $avatarId    = trim($_POST['avatar_id'] ?? 'lexi_genz');
                $duration    = (int)($_POST['duration'] ?? 30);
                $idea        = trim($_POST['idea'] ?? '');

                if ($productName === '' && $idea === '') {
                    studio_json(['success' => false, 'error' => 'Tell us the product name or paste your idea first.'], 422);
                }

                $script = AIVideoEngine::generateScript(
                    $productName !== '' ? $productName : $idea,
                    $benefits,
                    $avatarId,
                    $duration
                );
                studio_json(['success' => true, 'script' => $script]);
            }

            case 'enhance_prompt': {
                $prompt = trim($_POST['prompt'] ?? '');
                if ($prompt === '') {
                    studio_json(['success' => false, 'error' => 'Enter a prompt to enhance.'], 422);
                }

                $enhanced = $prompt;
                $key = api_key('GROQ_API_KEY', 'groq_api_key', '');
                if ($key !== '') {
                    $resp = aiStudioChat(
                        "Rewrite this ad video prompt into one vivid cinematic paragraph (45-70 words). "
                        . "Keep it concrete: subject, action, camera, lighting, mood, colour grade. "
                        . "Do not add brand names, text, captions or watermarks. Return only the rewritten prompt.\n\nPROMPT: {$prompt}",
                        $key
                    );
                    if (trim($resp) !== '') {
                        $enhanced = trim($resp);
                    }
                }

                studio_json(['success' => true, 'prompt' => $enhanced]);
            }

            case 'generate_commercial_storyboard': {
                $brandName     = trim($_POST['brand_name'] ?? 'Adsbooster');
                $productConcept= trim($_POST['product_concept'] ?? 'Autonomous Advertising AI');
                $styleId       = trim($_POST['style_id'] ?? 'tech_3d_render');
                $duration      = (int)($_POST['duration'] ?? 30);

                $storyboard = AIVideoEngine::generateCommercialStoryboard($brandName, $productConcept, $styleId, $duration);
                studio_json(['success' => true, 'storyboard' => $storyboard]);
            }

            // ── Start a render ──
            case 'create_video': {
                $provider = trim($_POST['provider'] ?? '') ?: null;

                $result = AIVideoEngine::createVideoCreative($userId, array_merge($_POST, [
                    'provider' => $provider,
                ]));

                if (isset($result['error'])) {
                    studio_json(['success' => false, 'error' => $result['error']], 422);
                }
                studio_json(['success' => true, 'video' => $result]);
            }

            // ── Poll an external-provider job ──
            case 'get_video_status': {
                $id = (int)($_POST['creative_id'] ?? 0);
                if ($id <= 0) {
                    studio_json(['success' => false, 'error' => 'Missing creative id.'], 422);
                }
                $status = AIVideoEngine::getJobStatus($userId, $id);
                if (isset($status['error'])) {
                    studio_json(['success' => false, 'error' => $status['error']], 422);
                }
                studio_json(['success' => true, 'job' => $status]);
            }

            // ── Store the browser-rendered file ──
            case 'save_render': {
                $id = (int)($_POST['creative_id'] ?? 0);
                if ($id <= 0) {
                    studio_json(['success' => false, 'error' => 'Missing creative id.'], 422);
                }
                if (empty($_FILES['video']) || ($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    studio_json(['success' => false, 'error' => 'No video file received. Please render again.'], 422);
                }

                $f = $_FILES['video'];
                if ($f['size'] > 120 * 1024 * 1024) {
                    studio_json(['success' => false, 'error' => 'Video exceeds the 120 MB limit.'], 413);
                }

                // Verify it really is a video container.
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = (string)$finfo->file($f['tmp_name']);
                $allowedMimes = [
                    'video/webm'          => 'webm',
                    'video/x-matroska'    => 'webm',
                    'video/mp4'           => 'mp4',
                    'video/quicktime'     => 'mov',
                    'application/octet-stream' => 'webm',
                ];
                $ext = $allowedMimes[$mime] ?? null;
                if ($ext === null) {
                    $head = (string)file_get_contents($f['tmp_name'], false, null, 0, 16);
                    if (str_starts_with($head, "\x1A\x45\xDF\xA3")) {
                        $ext = 'webm';
                    } elseif (str_starts_with($head, "\x00\x00\x00") && str_contains($head, 'ftyp')) {
                        $ext = 'mp4';
                    } else {
                        studio_json(['success' => false, 'error' => 'Uploaded data is not a valid video file.'], 415);
                    }
                }

                $thumb = isset($_POST['thumbnail']) && is_string($_POST['thumbnail'])
                    ? $_POST['thumbnail'] : null;

                $result = AIVideoEngine::attachRenderedVideo($userId, $id, $f['tmp_name'], $ext, $thumb);
                if (isset($result['error'])) {
                    studio_json(['success' => false, 'error' => $result['error']], 422);
                }
                studio_json(['success' => true, 'video' => $result]);
            }

            // ── Delete one of the user's renders ──
            case 'delete_video': {
                $id = (int)($_POST['creative_id'] ?? 0);
                $row = db()->fetchOne("SELECT * FROM ai_video_creatives WHERE id = ? AND user_id = ?", [$id, $userId]);
                if (!$row) {
                    studio_json(['success' => false, 'error' => 'Creative not found.'], 404);
                }
                foreach ([$row['video_url'] ?? '', $row['thumbnail_url'] ?? ''] as $rel) {
                    if ($rel !== '' && !preg_match('#^https?://#i', $rel)) {
                        $abs = ROOT_PATH . '/' . ltrim($rel, '/');
                        if (is_file($abs)) {
                            @unlink($abs);
                        }
                    }
                }
                db()->delete('ai_video_creatives', 'id = ? AND user_id = ?', [$id, $userId]);
                studio_json(['success' => true]);
            }

            // ── Edit an existing video in place (new params, fresh scenes) ──
            case 'update_video': {
                $id = (int)($_POST['creative_id'] ?? 0);
                $result = AIVideoEngine::rebuildCreative($userId, $id, array_merge($_POST, [
                    'provider' => trim((string)($_POST['provider'] ?? '')) ?: null,
                ]));
                if (isset($result['error'])) {
                    studio_json(['success' => false, 'error' => $result['error']], 422);
                }
                studio_json(['success' => true, 'video' => $result]);
            }

            // ── Regenerate: same params, brand-new scene stills ──
            case 'regenerate_video': {
                $id = (int)($_POST['creative_id'] ?? 0);
                $result = AIVideoEngine::rebuildCreative($userId, $id);
                if (isset($result['error'])) {
                    studio_json(['success' => false, 'error' => $result['error']], 422);
                }
                studio_json(['success' => true, 'video' => $result]);
            }

            default:
                studio_json(['success' => false, 'error' => 'Unknown action.'], 400);
        }
    } catch (\Throwable $e) {
        error_log('video-studio: ' . $e->getMessage());
        studio_json(['success' => false, 'error' => 'Something went wrong. Please try again.'], 500);
    }
}

// ── Page data ──
$history = db()->fetchAll(
    "SELECT * FROM ai_video_creatives WHERE user_id = ? ORDER BY created_at DESC LIMIT 24",
    [$userId]
);

// Client-side map powering Edit / Regenerate on library cards.
$libData = [];
foreach ($history as $vid) {
    $meta = json_decode($vid['metadata'] ?? '{}', true) ?: [];
    $libData[(int)$vid['id']] = [
        'id'            => (int)$vid['id'],
        'mode'          => ($meta['video_type'] ?? '') === 'commercial_cinematic' ? 'commercial' : 'ugc',
        'avatar_id'     => (string)($vid['avatar_id'] ?? 'lexi_genz'),
        'voice_id'      => (string)($vid['voice_id'] ?? 'en_female_energetic'),
        'style_id'      => (string)($meta['style_id'] ?? 'lifestyle_storyboard'),
        'hook'          => (string)($vid['hook_text'] ?? ''),
        'script'        => (string)($vid['script_body'] ?? ''),
        'cta'           => (string)($vid['cta_text'] ?? ''),
        'caption_style' => (string)($vid['caption_style'] ?? 'tiktok_glow'),
        'audio_track'   => (string)($vid['audio_track'] ?? 'viral_lofi_beat'),
        'broll'         => (string)($vid['broll_keyword'] ?? ''),
        'aspect'        => (string)($vid['aspect_ratio'] ?? '9:16'),
        'duration'      => (int)($vid['duration_seconds'] ?? 30),
        'scenes'        => array_values((array)($meta['scenes'] ?? [])),
        'status'        => (string)($vid['status'] ?? ''),
    ];
}

$credits = (int)db()->fetchOne("SELECT ai_credits FROM users WHERE id = ?", [$userId])['ai_credits'];

require_once __DIR__ . '/includes/app_header.php';
?>

<style>
:root{
  --vs-bg:#070b12; --vs-panel:#0d1524; --vs-panel-2:#111b2e; --vs-line:rgba(255,255,255,.08);
  --vs-accent:#c8ff00; --vs-accent-2:#00f2fe; --vs-text:#e8eefc; --vs-muted:#8695b3; --vs-danger:#ff5470;
}
.vs-wrap{background:var(--vs-bg);min-height:70vh;border-radius:20px;overflow:hidden;border:1px solid var(--vs-line)}
.vs-top{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 18px;border-bottom:1px solid var(--vs-line);background:linear-gradient(180deg,#0e1626,#0a1120)}
.vs-brand{display:flex;align-items:center;gap:10px;min-width:0}
.vs-dot{width:10px;height:10px;border-radius:50%;background:var(--vs-accent);box-shadow:0 0 12px var(--vs-accent);flex:none}
.vs-title{font-weight:800;letter-spacing:-.3px;font-size:1.02rem;color:var(--vs-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.vs-sub{font-size:.75rem;color:var(--vs-muted)}
.vs-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;border:1px solid var(--vs-line);background:rgba(255,255,255,.03);color:var(--vs-muted);font-size:.74rem;font-weight:600;white-space:nowrap}
.vs-pill b{color:var(--vs-text);font-weight:800}
.vs-pill.ok{border-color:rgba(200,255,0,.35);color:var(--vs-accent);background:rgba(200,255,0,.07)}

.vs-body{display:grid;grid-template-columns:minmax(340px,420px) 1fr;gap:0}
@media (max-width:1100px){.vs-body{grid-template-columns:1fr}}

.vs-left{border-right:1px solid var(--vs-line);padding:16px;display:flex;flex-direction:column;gap:14px;max-height:78vh;overflow:auto}
@media (max-width:1100px){.vs-left{border-right:0;border-bottom:1px solid var(--vs-line);max-height:none}}

.vs-step{border:1px solid var(--vs-line);border-radius:14px;background:var(--vs-panel);overflow:hidden}
.vs-step>summary{list-style:none;cursor:pointer;padding:11px 13px;display:flex;align-items:center;gap:9px;font-weight:700;color:var(--vs-text);font-size:.85rem;user-select:none}
.vs-step>summary::-webkit-details-marker{display:none}
.vs-step[open]>summary{border-bottom:1px solid var(--vs-line)}
.vs-stepno{width:22px;height:22px;border-radius:7px;background:rgba(200,255,0,.12);color:var(--vs-accent);display:grid;place-items:center;font-size:.72rem;font-weight:800;flex:none}
.vs-stepbody{padding:13px}

.vs-lbl{display:block;font-size:.72rem;font-weight:700;color:var(--vs-muted);text-transform:uppercase;letter-spacing:.6px;margin:0 0 6px}
.vs-in,.vs-sel,.vs-ta{width:100%;background:#080d16;border:1px solid var(--vs-line);color:var(--vs-text);border-radius:10px;padding:10px 12px;font-size:.86rem;outline:none;transition:.15s}
.vs-in:focus,.vs-sel:focus,.vs-ta:focus{border-color:var(--vs-accent);box-shadow:0 0 0 3px rgba(200,255,0,.12)}
.vs-ta{resize:vertical;min-height:78px;line-height:1.5}
.vs-row{display:grid;gap:10px}
.vs-row.c2{grid-template-columns:1fr 1fr}
.vs-hint{font-size:.72rem;color:var(--vs-muted);margin-top:6px;line-height:1.45}

.vs-chips{display:flex;flex-wrap:wrap;gap:7px}
.vs-chip{border:1px solid var(--vs-line);background:rgba(255,255,255,.03);color:var(--vs-muted);border-radius:999px;padding:7px 12px;font-size:.76rem;font-weight:700;cursor:pointer;transition:.15s}
.vs-chip:hover{color:var(--vs-text);border-color:rgba(255,255,255,.22)}
.vs-chip.on{background:var(--vs-accent);border-color:var(--vs-accent);color:#08101f}

.vs-mode{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.vs-mode button{border:1px solid var(--vs-line);background:rgba(255,255,255,.03);color:var(--vs-muted);border-radius:12px;padding:12px 10px;font-size:.78rem;font-weight:700;cursor:pointer;text-align:left;transition:.15s}
.vs-mode button .m-t{display:block;color:var(--vs-text);font-size:.82rem;margin-bottom:2px}
.vs-mode button.on{border-color:var(--vs-accent);background:rgba(200,255,0,.08)}
.vs-mode button.on .m-t{color:var(--vs-accent)}

.vs-tiles{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.vs-tile{border:1px solid var(--vs-line);border-radius:11px;overflow:hidden;background:#080d16;cursor:pointer;position:relative;transition:.15s}
.vs-tile img{width:100%;aspect-ratio:1/1;object-fit:cover;display:block;opacity:.85}
.vs-tile span{display:block;padding:6px 7px;font-size:.68rem;font-weight:700;color:var(--vs-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.vs-tile.on{border-color:var(--vs-accent);box-shadow:0 0 0 2px rgba(200,255,0,.25)}
.vs-tile.on span{color:var(--vs-accent)}
.vs-tile.on:after{content:"✓";position:absolute;top:5px;right:5px;width:18px;height:18px;border-radius:50%;background:var(--vs-accent);color:#08101f;font-size:.7rem;font-weight:900;display:grid;place-items:center}

.vs-stylegrid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.vs-style{border:1px solid var(--vs-line);border-radius:11px;overflow:hidden;cursor:pointer;background:#080d16;transition:.15s}
.vs-style img{width:100%;height:64px;object-fit:cover;opacity:.8}
.vs-style div{padding:7px 8px}
.vs-style b{display:block;font-size:.72rem;color:var(--vs-text)}
.vs-style small{color:var(--vs-muted);font-size:.66rem;line-height:1.3;display:block;margin-top:2px}
.vs-style.on{border-color:var(--vs-accent);background:rgba(200,255,0,.06)}

.vs-generate{width:100%;border:0;border-radius:13px;padding:15px;font-size:.95rem;font-weight:900;cursor:pointer;background:linear-gradient(135deg,var(--vs-accent),#8be100);color:#08101f;display:flex;align-items:center;justify-content:center;gap:9px;transition:.15s;letter-spacing:-.2px}
.vs-generate:hover{filter:brightness(1.07);transform:translateY(-1px)}
.vs-generate:disabled{opacity:.55;cursor:not-allowed;transform:none}
.vs-genmeta{display:flex;justify-content:space-between;font-size:.72rem;color:var(--vs-muted);margin-top:8px;font-weight:600}

/* ── Right: canvas stage ── */
.vs-right{padding:16px;display:flex;flex-direction:column;gap:12px;min-width:0}
.vs-stagebar{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.vs-stage{position:relative;margin:0 auto;background:#000;border-radius:16px;overflow:hidden;border:1px solid var(--vs-line);box-shadow:0 24px 70px rgba(0,0,0,.6);width:100%;max-width:340px}
.vs-stage[data-ar="16:9"]{max-width:640px}
.vs-stage[data-ar="1:1"]{max-width:440px}
.vs-stage[data-ar="4:5"]{max-width:400px}
.vs-stage canvas,.vs-stage video{width:100%;display:block;background:#000}
.vs-stageholder{position:relative;width:100%;padding-bottom:177.7%}
.vs-stage[data-ar="16:9"] .vs-stageholder{padding-bottom:56.25%}
.vs-stage[data-ar="1:1"] .vs-stageholder{padding-bottom:100%}
.vs-stage[data-ar="4:5"] .vs-stageholder{padding-bottom:125%}
.vs-stageholder>*{position:absolute;inset:0;width:100%;height:100%}

.vs-empty{position:absolute;inset:0;display:grid;place-items:center;text-align:center;padding:22px;background:radial-gradient(120% 90% at 50% 0%,#132038 0%,#05080f 70%)}
.vs-empty i{font-size:2.1rem;color:var(--vs-accent);display:block;margin-bottom:9px}
.vs-empty b{display:block;color:var(--vs-text);font-size:.92rem;margin-bottom:5px}
.vs-empty p{color:var(--vs-muted);font-size:.76rem;line-height:1.5;margin:0;max-width:250px}

.vs-overlay{position:absolute;inset:0;background:rgba(4,8,14,.86);backdrop-filter:blur(6px);display:none;place-items:center;text-align:center;padding:20px;z-index:6}
.vs-overlay.show{display:grid}
.vs-ring{width:74px;height:74px;border-radius:50%;border:3px solid rgba(255,255,255,.12);border-top-color:var(--vs-accent);animation:vsSpin .9s linear infinite;margin:0 auto 14px}
@keyframes vsSpin{to{transform:rotate(360deg)}}
.vs-ostep{color:var(--vs-text);font-weight:800;font-size:.9rem;margin-bottom:5px}
.vs-osub{color:var(--vs-muted);font-size:.75rem;max-width:260px;line-height:1.5;margin:0 auto}
.vs-oprog{width:230px;max-width:70%;height:6px;border-radius:99px;background:rgba(255,255,255,.1);margin:13px auto 0;overflow:hidden}
.vs-oprog i{display:block;height:100%;width:0%;border-radius:99px;background:linear-gradient(90deg,var(--vs-accent),var(--vs-accent-2));transition:width .3s}

.vs-playerbar{display:flex;align-items:center;gap:10px;justify-content:center}
.vs-ico{width:38px;height:38px;border-radius:50%;border:1px solid var(--vs-line);background:rgba(255,255,255,.04);color:var(--vs-text);display:grid;place-items:center;cursor:pointer;transition:.15s;font-size:1rem}
.vs-ico:hover{border-color:var(--vs-accent);color:var(--vs-accent)}
.vs-ico.play{background:var(--vs-accent);border-color:var(--vs-accent);color:#08101f;width:46px;height:46px}

/* ── Scene board ── */
.vs-board{border-top:1px solid var(--vs-line);padding:14px 16px;background:#080d16}
.vs-board-h{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;flex-wrap:wrap}
.vs-board-h b{color:var(--vs-text);font-size:.82rem}
.vs-board-h small{color:var(--vs-muted);font-size:.72rem}
.vs-film{display:flex;gap:10px;overflow-x:auto;padding-bottom:6px}
.vs-scene{flex:0 0 186px;border:1px solid var(--vs-line);border-radius:12px;overflow:hidden;background:var(--vs-panel);cursor:pointer;transition:.15s}
.vs-scene:hover{border-color:rgba(255,255,255,.22)}
.vs-scene.on{border-color:var(--vs-accent);box-shadow:0 0 0 2px rgba(200,255,0,.22)}
.vs-scene .thumb{position:relative;aspect-ratio:16/10;background:#05080f;overflow:hidden}
.vs-scene .thumb img{width:100%;height:100%;object-fit:cover;opacity:.9}
.vs-scene .thumb .ph{position:absolute;inset:0;display:grid;place-items:center;color:#33415c;font-size:1.2rem}
.vs-scene .thumb .no{position:absolute;top:6px;left:6px;background:rgba(0,0,0,.72);color:var(--vs-accent);border-radius:6px;padding:2px 7px;font-size:.64rem;font-weight:800}
.vs-scene .meta{padding:8px 9px}
.vs-scene .meta b{display:block;font-size:.7rem;color:var(--vs-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.vs-scene .meta small{color:var(--vs-muted);font-size:.65rem}

/* ── Library ── */
.vs-lib{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px}
.vs-card{border:1px solid var(--vs-line);border-radius:14px;overflow:hidden;background:var(--vs-panel);transition:.15s}
.vs-card:hover{border-color:rgba(255,255,255,.2);transform:translateY(-2px)}
.vs-card .v{aspect-ratio:16/9;background:#000;position:relative}
.vs-card .v video,.vs-card .v img{width:100%;height:100%;object-fit:cover;display:block}
.vs-card .v .badge{position:absolute;top:8px;right:8px;background:rgba(0,0,0,.75);color:var(--vs-accent);border-radius:7px;padding:3px 8px;font-size:.66rem;font-weight:800}
.vs-card .v .st{position:absolute;top:8px;left:8px;border-radius:7px;padding:3px 8px;font-size:.63rem;font-weight:800}
.st-completed{background:rgba(200,255,0,.16);color:var(--vs-accent)}
.st-generating{background:rgba(0,242,254,.15);color:var(--vs-accent-2)}
.st-failed{background:rgba(255,84,112,.16);color:var(--vs-danger)}
.st-rendering_client{background:rgba(255,214,0,.14);color:#ffd600}
.vs-card .f{padding:10px 11px}
.vs-card .f b{display:block;font-size:.78rem;color:var(--vs-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.vs-card .f small{color:var(--vs-muted);font-size:.68rem;display:block;margin:3px 0 9px}
.vs-card .f .acts{display:flex;gap:6px}
.vs-btn{flex:1;border-radius:9px;padding:7px 8px;font-size:.7rem;font-weight:800;cursor:pointer;border:1px solid var(--vs-line);background:rgba(255,255,255,.04);color:var(--vs-text);text-align:center;text-decoration:none;transition:.15s;display:inline-flex;align-items:center;justify-content:center;gap:4px}
.vs-btn:hover{border-color:var(--vs-accent);color:var(--vs-accent)}
.vs-btn.primary{background:var(--vs-accent);border-color:var(--vs-accent);color:#08101f}
.vs-btn.danger:hover{border-color:var(--vs-danger);color:var(--vs-danger)}

/* ── Toasts ── */
#vs-toasts{position:fixed;right:18px;bottom:18px;z-index:9999;display:flex;flex-direction:column;gap:9px;max-width:340px}
.vs-toast{border-radius:12px;padding:12px 14px;font-size:.8rem;font-weight:600;color:var(--vs-text);background:#101a2c;border:1px solid var(--vs-line);box-shadow:0 14px 40px rgba(0,0,0,.5);display:flex;gap:9px;align-items:flex-start;animation:vsIn .25s ease}
.vs-toast.ok{border-color:rgba(200,255,0,.4)}
.vs-toast.err{border-color:rgba(255,84,112,.45)}
.vs-toast small{display:block;color:var(--vs-muted);font-weight:500;margin-top:2px}
@keyframes vsIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}

.vs-sec-h{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:22px 0 12px;flex-wrap:wrap}
.vs-sec-h h4{margin:0;font-size:1rem;color:var(--vs-text);font-weight:800}
.vs-sec-h p{margin:2px 0 0;font-size:.76rem;color:var(--vs-muted)}
.spin{animation:vsSpin .9s linear infinite;display:inline-block}
</style>

<div class="content-body">
<div class="container-fluid py-4">

  <div class="vs-wrap">

    <!-- ── Top bar ── -->
    <div class="vs-top">
      <div class="vs-brand">
        <span class="vs-dot"></span>
        <div style="min-width:0">
          <div class="vs-title">AI Video Studio</div>
          <div class="vs-sub">Type an idea, pick a look, render a real video</div>
        </div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <span class="vs-pill ok" id="vs-provider-pill" title="Active rendering engine">
          <i class="ph ph-cpu"></i> <b><?= e($providers[$activeProvider]['label'] ?? 'Storyboard Studio') ?></b>
        </span>
        <span class="vs-pill"><i class="ph ph-lightning"></i> <b id="vs-credits"><?= (int)$credits ?></b> credits</span>
        <a href="<?= url('ugc-generator') ?>" class="vs-pill" style="text-decoration:none"><i class="ph ph-image"></i> Image Studio</a>
      </div>
    </div>

    <!-- ── Body ── -->
    <div class="vs-body">

      <!-- LEFT: controls -->
      <div class="vs-left">

        <details class="vs-step" open>
          <summary><span class="vs-stepno">1</span> What are we making?</summary>
          <div class="vs-stepbody">
            <div class="vs-mode">
              <button type="button" class="on" id="mode-ugc">
                <span class="m-t">Creator UGC</span>
                Talking-head style ad for TikTok, Reels &amp; Shorts
              </button>
              <button type="button" id="mode-commercial">
                <span class="m-t">Cinematic Ad</span>
                Product commercial with dramatic studio visuals
              </button>
            </div>

            <div style="margin-top:12px">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                <span class="vs-lbl" style="margin:0"><i class="ph-bold ph-lightbulb" style="color:var(--vs-accent)"></i> 1-Click Viral Ideas</span>
                <span style="font-size:0.68rem;color:var(--vs-muted)">Novice friendly</span>
              </div>
              <div class="vs-chips" id="vs-video-inspiration-chips" style="margin-bottom:8px">
                <button type="button" class="vs-chip" onclick="applyVideoPreset('serum')">🧴 Skincare Glow</button>
                <button type="button" class="vs-chip" onclick="applyVideoPreset('earbuds')">🎧 Pro Earbuds</button>
                <button type="button" class="vs-chip" onclick="applyVideoPreset('energy')">⚡ Energy Drink</button>
                <button type="button" class="vs-chip" onclick="applyVideoPreset('sneakers')">👟 Sneaker Drop</button>
                <button type="button" class="vs-chip" onclick="applyVideoPreset('saas')">💻 SaaS Tool</button>
              </div>
              <label class="vs-lbl" for="vs-idea">Describe your video in one line</label>
              <textarea id="vs-idea" class="vs-ta" rows="2" placeholder="e.g. A fun creator review of my new matcha powder that clears skin in 7 days, 50% off launch"></textarea>
              <div class="vs-hint">Write it like you would tell a friend. The AI turns it into a full script and shot list.</div>
            </div>

            <div class="vs-row c2" style="margin-top:10px">
              <div>
                <label class="vs-lbl" for="vs-product">Product / brand</label>
                <input id="vs-product" class="vs-in" placeholder="Lumina Glow">
              </div>
              <div>
                <label class="vs-lbl" for="vs-benefits">Key benefit</label>
                <input id="vs-benefits" class="vs-in" placeholder="Clears skin in 7 days">
              </div>
            </div>

            <div style="margin-top:11px">
              <label class="vs-lbl">Length</label>
              <div class="vs-chips" id="vs-dur-chips">
                <button type="button" class="vs-chip" data-dur="15">15s</button>
                <button type="button" class="vs-chip on" data-dur="30">30s</button>
                <button type="button" class="vs-chip" data-dur="45">45s</button>
                <button type="button" class="vs-chip" data-dur="60">60s</button>
                <button type="button" class="vs-chip" data-dur="90">90s</button>
              </div>
            </div>

            <button type="button" class="vs-btn" id="btn-script" style="width:100%;margin-top:11px;padding:10px">
              <i class="ph ph-magic-wand"></i> Write the script for me
            </button>
          </div>
        </details>

        <details class="vs-step" open>
          <summary><span class="vs-stepno">2</span> Script</summary>
          <div class="vs-stepbody">
            <label class="vs-lbl" for="vs-hook">Hook (first 3 seconds)</label>
            <input id="vs-hook" class="vs-in" placeholder="Stop scrolling if you want clearer skin in a week">

            <label class="vs-lbl" for="vs-body" style="margin-top:10px">Main message</label>
            <textarea id="vs-body" class="vs-ta" rows="3" placeholder="I tested it for two weeks and the results were ridiculous…"></textarea>

            <label class="vs-lbl" for="vs-cta" style="margin-top:10px">Call to action</label>
            <input id="vs-cta" class="vs-in" placeholder="Tap the link below to get 50% off today">

            <div style="display:flex;gap:8px;margin-top:10px">
              <button type="button" class="vs-btn" id="btn-enhance"><i class="ph ph-sparkle"></i> Enhance prompt</button>
              <button type="button" class="vs-btn" id="btn-storyboard"><i class="ph ph-clapperboard"></i> Auto storyboard</button>
            </div>
          </div>
        </details>

        <details class="vs-step" open>
          <summary><span class="vs-stepno">3</span> Look &amp; framing</summary>
          <div class="vs-stepbody">
            <label class="vs-lbl">Aspect ratio</label>
            <div class="vs-chips" id="vs-ar-chips">
              <?php foreach ($aspects as $arId => $ar): ?>
              <button type="button" class="vs-chip <?= $arId === '9:16' ? 'on' : '' ?>" data-ar="<?= e($arId) ?>" title="<?= e($ar['label']) ?>"><?= e($arId) ?></button>
              <?php endforeach; ?>
            </div>
            <div class="vs-hint" id="vs-ar-label"><?= e($aspects['9:16']['label']) ?></div>

            <div id="vs-avatar-block" style="margin-top:12px">
              <label class="vs-lbl">Creator</label>
              <div class="vs-tiles" id="vs-avatars">
                <?php foreach ($avatars as $idx => $av): ?>
                <div class="vs-tile <?= $idx === 0 ? 'on' : '' ?>" data-id="<?= e($av['id']) ?>" data-voice="<?= e($av['voice_id']) ?>" data-img="<?= BASE_URL ?>/<?= e($av['avatar_img']) ?>" data-name="<?= e($av['name']) ?>">
                  <img src="<?= BASE_URL ?>/<?= e($av['avatar_img']) ?>" alt="<?= e($av['name']) ?>" loading="lazy">
                  <span><?= e($av['name']) ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </div>

            <div id="vs-style-block" style="margin-top:12px;display:none">
              <label class="vs-lbl">Production style</label>
              <div class="vs-stylegrid" id="vs-styles">
                <?php foreach ($commercialStyles as $idx => $cStyle): ?>
                <div class="vs-style <?= $idx === 0 ? 'on' : '' ?>" data-id="<?= e($cStyle['id']) ?>">
                  <img src="<?= BASE_URL ?>/<?= e($cStyle['sample_thumb']) ?>" alt="" loading="lazy">
                  <div><b><?= e($cStyle['name']) ?></b><small><?= e($cStyle['desc']) ?></small></div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>

            <div style="margin-top:12px">
              <label class="vs-lbl" for="vs-voice">Voice</label>
              <select id="vs-voice" class="vs-sel">
                <?php foreach ($voices as $v): ?>
                <option value="<?= e($v['id']) ?>" data-pitch="<?= $v['pitch'] ?>" data-rate="<?= $v['rate'] ?>" data-lang="<?= e($v['lang']) ?>" data-gender="<?= e($v['gender']) ?>"><?= e($v['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="button" class="vs-btn" id="btn-audition" style="width:100%;margin-top:8px"><i class="ph ph-speaker-high"></i> Preview voice</button>
            </div>
          </div>
        </details>

        <details class="vs-step">
          <summary><span class="vs-stepno">4</span> Captions &amp; music</summary>
          <div class="vs-stepbody">
            <label class="vs-lbl" for="vs-caption">Caption style</label>
            <select id="vs-caption" class="vs-sel">
              <?php foreach ($captionStyles as $cs): ?>
              <option value="<?= e($cs['id']) ?>" data-bg="<?= e($cs['bg']) ?>" data-color="<?= e($cs['color']) ?>" data-stroke="<?= e($cs['stroke']) ?>"><?= e($cs['name']) ?></option>
              <?php endforeach; ?>
            </select>

            <label class="vs-lbl" for="vs-track" style="margin-top:10px">Background beat</label>
            <select id="vs-track" class="vs-sel">
              <?php foreach ($audioTracks as $at): ?>
              <option value="<?= e($at['id']) ?>" data-bpm="<?= (int)$at['bpm'] ?>" data-wave="<?= e($at['wave']) ?>"><?= e($at['name']) ?></option>
              <?php endforeach; ?>
            </select>

            <label class="vs-lbl" for="vs-mix" style="margin-top:10px">Audio mix</label>
            <select id="vs-mix" class="vs-sel">
              <option value="balanced">High voice clarity</option>
              <option value="punchy">Punchy TikTok</option>
              <option value="voice_only">Voice only</option>
            </select>

            <label class="vs-lbl" for="vs-broll" style="margin-top:10px">B-roll subject (optional)</label>
            <input id="vs-broll" class="vs-in" placeholder="e.g. matcha powder being poured">
          </div>
        </details>

        <div>
          <button type="button" class="vs-generate" id="btn-generate">
            <i class="ph-fill ph-sparkle"></i> <span id="vs-gen-label">Generate video</span>
          </button>
          <div class="vs-genmeta">
            <span id="vs-gen-cost">3 credits</span>
            <span id="vs-gen-engine"><?= e($providers[$activeProvider]['label'] ?? '') ?></span>
          </div>
          <div class="vs-hint" style="text-align:center;margin-top:10px;font-size:0.7rem;color:var(--vs-muted);">
            <i class="ph-bold ph-shield-check" style="color:var(--vs-accent);vertical-align:-1px;"></i>
            FTC &amp; Platform Compliant. Automated #ad disclosure &amp; synthetic avatar commercial rights included.
          </div>
        </div>

      </div>

      <!-- RIGHT: stage -->
      <div class="vs-right">
        <div class="vs-stagebar">
          <div style="display:flex;gap:7px;align-items:center;flex-wrap:wrap">
            <span class="vs-pill" id="vs-stage-ar"><i class="ph ph-crop"></i> <b>9:16</b></span>
            <span class="vs-pill" id="vs-stage-dur"><i class="ph ph-timer"></i> <b>30</b>s</span>
            <span class="vs-pill" id="vs-stage-scenes"><i class="ph ph-film-strip"></i> <b>3</b> scenes</span>
          </div>
          <div style="display:flex;gap:7px">
            <button type="button" class="vs-btn" id="btn-download" style="display:none"><i class="ph ph-download-simple"></i> Download</button>
            <a class="vs-btn" id="btn-publish" href="<?= url('post-creator') ?>" style="display:none"><i class="ph ph-paper-plane-tilt"></i> Publish</a>
            <a class="vs-btn primary" id="btn-runad" href="<?= url('create-ad') ?>" style="display:none"><i class="ph ph-megaphone"></i> Run as ad</a>
          </div>
        </div>

        <div class="vs-stage" id="vs-stage" data-ar="9:16">
          <div class="vs-stageholder">
            <canvas id="vs-canvas" width="720" height="1280"></canvas>
            <video id="vs-video" playsinline style="display:none"></video>

            <div class="vs-empty" id="vs-empty">
              <div>
                <i class="ph ph-film-slate"></i>
                <b>Your video appears here</b>
                <p>Fill in step 1 on the left, then hit <strong>Generate video</strong>. You will get a real, downloadable video file.</p>
              </div>
            </div>

            <div class="vs-overlay" id="vs-overlay">
              <div>
                <div class="vs-ring"></div>
                <div class="vs-ostep" id="vs-ostep">Preparing…</div>
                <p class="vs-osub" id="vs-osub">Setting up your render.</p>
                <div class="vs-oprog"><i id="vs-oprog"></i></div>
              </div>
            </div>
          </div>
        </div>

        <div class="vs-playerbar">
          <button type="button" class="vs-ico" id="btn-restart" title="Restart"><i class="ph ph-skip-back"></i></button>
          <button type="button" class="vs-ico play" id="btn-play" title="Play / pause"><i class="ph-fill ph-play" id="vs-play-icon"></i></button>
          <button type="button" class="vs-ico" id="btn-mute" title="Mute"><i class="ph ph-volume-high" id="vs-mute-icon"></i></button>
          <span class="vs-pill" id="vs-time"><b>0:00</b> / <span id="vs-total">0:30</span></span>
        </div>

      </div>
    </div>

    <!-- ── Scene board ── -->
    <div class="vs-board">
      <div class="vs-board-h">
        <div>
          <b>Scene board</b>
          <small> — click a scene to preview it. Edit the prompt and re-render to update.</small>
        </div>
        <button type="button" class="vs-btn" id="btn-regen-scenes" style="flex:none"><i class="ph ph-arrows-clockwise"></i> Regenerate shots</button>
      </div>
      <div class="vs-film" id="vs-film">
        <div style="color:var(--vs-muted);font-size:.78rem;padding:14px 6px" id="vs-film-empty">
          No scenes yet — generate a video to build your shot list.
        </div>
      </div>
    </div>

  </div>

  <!-- ── Library ── -->
  <div class="vs-sec-h">
    <div>
      <h4>Your videos</h4>
      <p>Every render you have created. Click play to watch, or send straight to the post creator or ad launcher.</p>
    </div>
  </div>

  <?php if (empty($history)): ?>
  <div style="border:1px dashed var(--vs-line);border-radius:16px;padding:34px;text-align:center;color:var(--vs-muted);font-size:.85rem">
    <i class="ph ph-video-camera" style="font-size:2rem;color:var(--vs-accent);display:block;margin-bottom:8px"></i>
    Nothing rendered yet. Your first video will show up here.
  </div>
  <?php else: ?>
  <div class="vs-lib">
    <?php foreach ($history as $vid):
        $meta = json_decode($vid['metadata'] ?? '{}', true) ?: [];
        $vAbs = media_url($vid['video_url'] ?? '');
        $tAbs = media_url($vid['thumbnail_url'] ?? '');
        $st   = (string)$vid['status'];
        $hasVideo = $vAbs !== '' && in_array($st, ['completed'], true);
    ?>
    <div class="vs-card" data-id="<?= (int)$vid['id'] ?>">
      <div class="v">
        <?php if ($hasVideo): ?>
          <video src="<?= e($vAbs) ?>" <?= $tAbs !== '' ? 'poster="' . e($tAbs) . '"' : '' ?> controls preload="metadata" playsinline></video>
        <?php elseif ($tAbs !== ''): ?>
          <img src="<?= e($tAbs) ?>" alt="">
        <?php else: ?>
          <div style="display:grid;place-items:center;height:100%;color:#33415c"><i class="ph ph-video-camera" style="font-size:1.7rem"></i></div>
        <?php endif; ?>
        <span class="badge st-<?= e($st) ?>"><?= e(str_replace('_', ' ', $st)) ?></span>
        <span class="badge"><?= (int)$vid['duration_seconds'] ?>s · <?= e($vid['aspect_ratio']) ?></span>
      </div>
      <div class="f">
        <b><?= e($vid['hook_text']) ?></b>
        <small><?= e($meta['provider_label'] ?? 'Storyboard Studio') ?> · <?= dt($vid['created_at'], 'M d, Y · H:i') ?></small>
        <div class="acts">
          <button type="button" class="vs-btn" onclick="editCreative(<?= (int)$vid['id'] ?>)" title="Edit this video — opens its script and scenes back in the composer"><i class="ph ph-pencil-simple"></i></button>
          <button type="button" class="vs-btn" onclick="regenerateCreative(<?= (int)$vid['id'] ?>)" title="Regenerate — same script, brand-new AI scene stills"><i class="ph ph-arrows-clockwise"></i></button>
          <?php if ($hasVideo): ?>
            <a class="vs-btn" href="<?= e($vAbs) ?>" download title="Download MP4/WebM"><i class="ph ph-download-simple"></i></a>
            <a class="vs-btn" href="<?= url('post-creator') ?>?video_id=<?= (int)$vid['id'] ?>" title="Create a social post from this video"><i class="ph ph-paper-plane-tilt"></i></a>
            <a class="vs-btn primary" href="<?= url('create-ad') ?>?video_id=<?= (int)$vid['id'] ?>">Run ad</a>
          <?php endif; ?>
          <button type="button" class="vs-btn danger js-del" data-id="<?= (int)$vid['id'] ?>" title="Delete"><i class="ph ph-trash"></i></button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
</div>

<div id="vs-toasts"></div>

<script>
(function(){
'use strict';

/* ══════════════ CONFIG ══════════════ */
const ENDPOINT   = window.location.pathname + window.location.search;
const CSRF       = <?= json_encode(csrf_token()) ?>;
const LIB        = <?= json_encode((object)$libData) ?>;
const BASE       = <?= json_encode(rtrim(BASE_URL, '/')) ?>;
const PROVIDERS  = <?= json_encode(array_map(fn($p) => ['label'=>$p['label'],'kind'=>$p['kind'],'configured'=>(bool)$p['configured']], $providers)) ?>;
const ACTIVE     = <?= json_encode($activeProvider) ?>;
const VOICES     = <?= json_encode($voices) ?>;

const $  = (s, r) => (r||document).querySelector(s);
const $$ = (s, r) => Array.from((r||document).querySelectorAll(s));

/* ══════════════ 1-CLICK FLOW INSPIRATION ══════════════ */
window.applyVideoPreset = function(type) {
  const presets = {
    serum: {
      idea: "A fun, authentic girl review of Lumina Glow serum showing how it cleared morning redness in 7 days",
      product: "Lumina Glow Serum",
      benefit: "Clears redness and delivers glass skin in 7 days",
      dur: 30,
      hook: "Stop scrolling if you are tired of 10-step skincare routines",
      body: "I was skeptical, but Lumina Glow actually transformed my morning skin in under a week. It is lightweight, non-greasy, and smells amazing.",
      cta: "Tap the link below now to claim your 50% launch discount!",
      avatar: "lexi_genz"
    },
    earbuds: {
      idea: "Tech creator unboxing review of AuraWave Pro ANC earbuds beating $300 alternatives",
      product: "AuraWave Pro Earbuds",
      benefit: "Active noise cancellation and 40-hour battery life",
      dur: 30,
      hook: "I threw out my $300 headphones after testing these for 48 hours",
      body: "The active noise cancellation is completely unreal. Crystal clear audio, zero lag for gaming, and a 40-hour battery that refuses to die.",
      cta: "Grab yours before the weekend flash sale sells out!",
      avatar: "alex_tech"
    },
    energy: {
      idea: "Fitness athlete gym review of VoltClean zero-crash organic energy drink",
      product: "VoltClean Energy",
      benefit: "8 hours of clean organic focus with zero crash",
      dur: 15,
      hook: "If your energy drink gives you the 2 PM crash, you need to switch",
      body: "VoltClean gives you pure, steady focus for 8 hours without any jitters or artificial junk.",
      cta: "Tap below for free express shipping today!",
      avatar: "jordan_fitness"
    },
    sneakers: {
      idea: "Urban street style walk review of StrideZero cloud knit running shoes",
      product: "StrideZero Sneakers",
      benefit: "Cloud comfort for 20,000 daily steps",
      dur: 30,
      hook: "Walking 20,000 steps usually destroys my feet. Not in these.",
      body: "StrideZero feels like literally walking on clouds. Breathable knit, ultra-lightweight, and looks incredible with everything.",
      cta: "Get 40% off your first pair with code CLOUD40!",
      avatar: "lexi_genz"
    },
    saas: {
      idea: "Startup founder breakdown of AdFlow AI autonomous media buyer that scales ROAS",
      product: "AdFlow AI Engine",
      benefit: "Automated anti-burn kill-switch and 24/7 ROAS scaling",
      dur: 45,
      hook: "How we scaled our e-commerce store to $50k a month on autopilot",
      body: "We replaced manual ad management with AdFlow AI. The anti-burn daemon automatically pauses bad creatives and scales winners while we sleep.",
      cta: "Start your 14-day free trial at the link below!",
      avatar: "marcus_founder"
    }
  };

  const p = presets[type];
  if (!p) return;

  const ideaEl = $('#vs-idea');
  const prodEl = $('#vs-product');
  const benEl = $('#vs-benefits');
  const hookEl = $('#vs-hook');
  const bodyEl = $('#vs-body');
  const ctaEl = $('#vs-cta');

  if (ideaEl) ideaEl.value = p.idea;
  if (prodEl) prodEl.value = p.product;
  if (benEl) benEl.value = p.benefit;
  if (hookEl) hookEl.value = p.hook;
  if (bodyEl) bodyEl.value = p.body;
  if (ctaEl) ctaEl.value = p.cta;

  // Set duration chip
  const durBtn = $(`#vs-dur-chips button[data-dur="${p.dur}"]`);
  if (durBtn) durBtn.click();

  // Select avatar if available
  const avEl = $(`#vs-avatars .vs-tile[data-id="${p.avatar}"]`);
  if (avEl) avEl.click();

  // Animate pulse on generate button to guide user
  const genBtn = $('#btn-generate');
  if (genBtn) {
    genBtn.style.transform = 'scale(1.03)';
    setTimeout(() => { genBtn.style.transform = 'scale(1)'; }, 350);
  }
};

/* ══════════════ STATE ══════════════ */
const S = {
  mode: 'ugc',
  duration: 30,
  aspect: '9:16',
  avatarId: <?= json_encode($avatars[0]['id']) ?>,
  styleId: <?= json_encode($commercialStyles[0]['id']) ?>,
  avatarImg: <?= json_encode(BASE_URL . '/' . $avatars[0]['avatar_img']) ?>,
  avatarName: <?= json_encode($avatars[0]['name']) ?>,
  scenes: [],          // {scene_number,label,caption,visual_prompt,image_url,local_url,zoom_from,zoom_to,pan,length,start}
  creativeId: null,
  editing: null,       // creative id while an Edit → Update & re-render is in flight
  provider: ACTIVE,
  status: 'idle',      // idle | rendering_client | generating | completed | failed
  resultUrl: '',
  resultThumb: '',
  images: [],          // decoded ImageBitmaps / HTMLImageElement
  playing: false,
  raf: null,
  playhead: 0,
  muted: false,
  currentScene: 0,
  audioCtx: null,
  beatTimer: null,
};

/* ══════════════ TOASTS ══════════════ */
function toast(msg, kind, sub){
  const wrap = $('#vs-toasts');
  const el = document.createElement('div');
  el.className = 'vs-toast ' + (kind || '');
  el.innerHTML = '<i class="ph ' + (kind==='err'?'ph-warning':'ph-check-circle') + '"></i><div><div>' +
    esc(msg) + '</div>' + (sub ? '<small>' + esc(sub) + '</small>' : '') + '</div>';
  wrap.appendChild(el);
  setTimeout(() => { el.style.opacity='0'; el.style.transform='translateY(8px)'; setTimeout(()=>el.remove(),250); }, kind==='err' ? 6000 : 4000);
}
function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

/* ══════════════ OVERLAY ══════════════ */
function overlay(show, step, sub, pct){
  const o = $('#vs-overlay');
  if (show){ o.classList.add('show'); } else { o.classList.remove('show'); }
  if (step) $('#vs-ostep').textContent = step;
  if (sub)  $('#vs-osub').textContent  = sub;
  if (typeof pct === 'number') $('#vs-oprog').style.width = Math.max(0, Math.min(100, pct)) + '%';
}

/* ══════════════ API ══════════════ */
async function api(fields){
  const fd = new FormData();
  fd.append('csrf_token', CSRF);
  Object.keys(fields || {}).forEach(k => { if (fields[k] !== undefined && fields[k] !== null) fd.append(k, fields[k]); });
  let res;
  try { res = await fetch(ENDPOINT, { method:'POST', body: fd, credentials:'same-origin' }); }
  catch(e){ throw new Error('Network error — check your connection and try again.'); }

  const text = await res.text();
  let data;
  try { data = JSON.parse(text); }
  catch(e){
    if (/<!DOCTYPE|<html/i.test(text)) throw new Error('Your session expired. Refresh the page and log in again.');
    throw new Error('The server sent an unexpected response.');
  }
  if (!data.success) throw new Error(data.error || 'Request failed.');
  return data;
}

/* ══════════════ CREDIT MATH ══════════════ */
function creditCost(d){ return d > 60 ? 8 : (d > 30 ? 5 : 3); }
function fmtTime(sec){ sec = Math.max(0, Math.floor(sec)); return Math.floor(sec/60) + ':' + String(sec%60).padStart(2,'0'); }

function syncMeta(){
  const c = creditCost(S.duration);
  $('#vs-gen-cost').textContent = c + ' credits';
  $('#vs-stage-dur').innerHTML = '<i class="ph ph-timer"></i> <b>' + S.duration + '</b>s';
  $('#vs-stage-ar').innerHTML  = '<i class="ph ph-crop"></i> <b>' + S.aspect + '</b>';
  $('#vs-stage-scenes').innerHTML = '<i class="ph ph-film-strip"></i> <b>' + (S.scenes.length || 3) + '</b> scenes';
  $('#vs-total').textContent = fmtTime(S.duration);
  $('#vs-stage').dataset.ar = S.aspect;
  const res = RESOLUTIONS[S.aspect] || {w:720,h:1280};
  const c2 = $('#vs-canvas');
  if (c2.width !== res.w || c2.height !== res.h){ c2.width = res.w; c2.height = res.h; }
}

const RESOLUTIONS = <?= json_encode($aspects) ?>;

/* ══════════════ CONTROL BINDINGS ══════════════ */
// Mode
$('#mode-ugc').addEventListener('click', () => setMode('ugc'));
$('#mode-commercial').addEventListener('click', () => setMode('commercial'));
function setMode(m){
  S.mode = m;
  $('#mode-ugc').classList.toggle('on', m==='ugc');
  $('#mode-commercial').classList.toggle('on', m==='commercial');
  $('#vs-avatar-block').style.display = m==='ugc' ? '' : 'none';
  $('#vs-style-block').style.display  = m==='commercial' ? '' : 'none';
  if (m==='commercial' && S.aspect==='9:16'){ setAspect('16:9'); }
  if (m==='ugc' && S.aspect==='16:9'){ setAspect('9:16'); }
}

// Duration chips
$$('#vs-dur-chips .vs-chip').forEach(b => b.addEventListener('click', () => {
  $$('#vs-dur-chips .vs-chip').forEach(x => x.classList.remove('on'));
  b.classList.add('on');
  S.duration = parseInt(b.dataset.dur, 10);
  syncMeta();
}));

// Aspect chips
$$('#vs-ar-chips .vs-chip').forEach(b => b.addEventListener('click', () => setAspect(b.dataset.ar)));
function setAspect(ar){
  S.aspect = ar;
  $$('#vs-ar-chips .vs-chip').forEach(x => x.classList.toggle('on', x.dataset.ar === ar));
  const lbl = (RESOLUTIONS[ar]||{}).label || '';
  $('#vs-ar-label').textContent = lbl;
  syncMeta();
  if (S.scenes.length) drawFrame(S.currentScene, 0.5);
}

// Avatars
$$('#vs-avatars .vs-tile').forEach(t => t.addEventListener('click', () => {
  $$('#vs-avatars .vs-tile').forEach(x => x.classList.remove('on'));
  t.classList.add('on');
  S.avatarId  = t.dataset.id;
  S.avatarImg = t.dataset.img;
  S.avatarName= t.dataset.name;
  const vs = $('#vs-voice');
  if (t.dataset.voice && vs) vs.value = t.dataset.voice;
  if (S.scenes.length) { S.scenes[0].image_url = null; S.scenes[0].local_url = null; }
}));

// Styles
$$('#vs-styles .vs-style').forEach(t => t.addEventListener('click', () => {
  $$('#vs-styles .vs-style').forEach(x => x.classList.remove('on'));
  t.classList.add('on');
  S.styleId = t.dataset.id;
}));

// Caption style
const selCaption = $('#vs-caption');
if (selCaption) selCaption.addEventListener('change', () => { if (S.scenes.length) drawFrame(S.currentScene, 0.5); });

/* ══════════════ VOICE PREVIEW ══════════════ */
$('#btn-audition').addEventListener('click', () => {
  const opt = $('#vs-voice').selectedOptions[0];
  const v = VOICES.find(x => x.id === $('#vs-voice').value) || VOICES[0];
  const line = ($('#vs-hook').value || $('#vs-idea').value || ('Hi, I am ' + v.name + ' and I am ready to record your ad.')).slice(0,220);
  if (!('speechSynthesis' in window)){ toast('Voice preview is not supported in this browser.','err'); return; }
  speechSynthesis.cancel();
  const u = new SpeechSynthesisUtterance(line);
  u.pitch = parseFloat(opt.dataset.pitch||1); u.rate = parseFloat(opt.dataset.rate||1); u.lang = opt.dataset.lang||'en-US';
  const all = speechSynthesis.getVoices();
  const want = (opt.dataset.gender === 'female') ? /female|samantha|zira|victoria|serena|karen/i : /male|daniel|alex|david|george/i;
  const hit = all.find(x => want.test(x.name));
  if (hit) u.voice = hit;
  speechSynthesis.speak(u);
  toast('Playing a voice sample…');
});

/* ══════════════ AI SCRIPT ══════════════ */
async function loadScript(){
  const idea = $('#vs-idea').value.trim();
  const product = $('#vs-product').value.trim();
  if (!idea && !product){ toast('Tell us what the video is about first.','err'); return false; }
  overlay(true, 'Writing your script…', 'The AI is crafting a high-converting hook, body and call to action.', 15);
  try{
    const d = await api({ action:'generate_script', idea, product_name: product, benefits: $('#vs-benefits').value.trim(), avatar_id: S.avatarId, duration: S.duration });
    $('#vs-hook').value = d.script.hook || '';
    $('#vs-body').value = d.script.body || '';
    $('#vs-cta').value  = d.script.cta  || '';
    toast('Script ready', 'ok', d.script.source === 'groq' ? 'Written by Groq AI' : 'Smart template');
    return true;
  }catch(e){ toast(e.message,'err'); return false; }
  finally { overlay(false); }
}
$('#btn-script').addEventListener('click', loadScript);

$('#btn-enhance').addEventListener('click', async () => {
  const cur = $('#vs-idea').value.trim();
  if (!cur){ toast('Write a rough idea in step 1 first.','err'); return; }
  const btn = $('#btn-enhance'); const old = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<i class="ph ph-spinner-gap spin"></i> Enhancing…';
  try{
    const d = await api({ action:'enhance_prompt', prompt: cur });
    $('#vs-idea').value = d.prompt;
    toast('Prompt enhanced','ok');
  }catch(e){ toast(e.message,'err'); }
  finally { btn.disabled=false; btn.innerHTML = old; }
});

$('#btn-storyboard').addEventListener('click', async () => {
  const brand = $('#vs-product').value.trim() || ($('#vs-idea').value.trim().split(' ').slice(0,3).join(' ')) || 'Your Brand';
  const concept = $('#vs-benefits').value.trim() || $('#vs-idea').value.trim() || 'a premium product people love';
  const btn = $('#btn-storyboard'); const old = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<i class="ph ph-spinner-gap spin"></i> Storyboarding…';
  try{
    const d = await api({ action:'generate_commercial_storyboard', brand_name: brand, product_concept: concept, style_id: S.styleId, duration: S.duration });
    const sc = d.storyboard.scenes || [];
    S.scenes = sc.map((s,i) => ({
      scene_number: i+1, label: 'Scene ' + (i+1), caption: s.narration || '',
      visual_prompt: s.visual_prompt || '', image_url: '', local_url: '',
      start: 0, length: Math.max(2, Math.round(S.duration / sc.length)),
      zoom_from: i%2===0?1.0:1.18, zoom_to: i%2===0?1.18:1.0, pan: i%3===0?'right':(i%3===1?'left':'up'),
      timing: s.timing || ''
    }));
    if (!$('#vs-hook').value) $('#vs-hook').value = sc[0] ? (sc[0].narration||'') : '';
    if (!$('#vs-body').value && sc[1]) $('#vs-body').value = sc.map(s=>s.narration).filter(Boolean).slice(1).join(' ');
    if (!$('#vs-cta').value && sc.length) $('#vs-cta').value = sc[sc.length-1].narration || '';
    renderFilm();
    toast('Storyboard built','ok', sc.length + ' scenes ready');
  }catch(e){ toast(e.message,'err'); }
  finally { btn.disabled=false; btn.innerHTML = old; }
});

/* ══════════════ GENERATE ══════════════ */
$('#btn-generate').addEventListener('click', async () => {
  const hook = $('#vs-hook').value.trim();
  const body = $('#vs-body').value.trim();
  const cta  = $('#vs-cta').value.trim();
  const idea = $('#vs-idea').value.trim();

  if (!hook && !body && !idea){
    toast('Add a hook or an idea before generating.','err');
    $('#vs-idea').focus();
    return;
  }

  const btn = $('#btn-generate');
  btn.disabled = true;
  $('#vs-gen-label').textContent = 'Starting…';

  // The server writes the script and creates a photo for every scene, which
  // takes ~20s. Show real progress rather than a frozen button.
  overlay(true, 'Building your scenes…', 'Writing the script and making a photo for each scene. About 20 seconds — stay on this page.', 25);
  let creep = 25;
  const creepTimer = setInterval(() => {
    creep = Math.min(85, creep + 4);
    overlay(true, 'Building your scenes…', 'Still working — creating photos for each scene. About 20 seconds.', creep);
  }, 1200);

  const payload = {
    action: S.editing ? 'update_video' : 'create_video',
    video_type: S.mode === 'ugc' ? 'ugc_talking_head' : 'commercial_cinematic',
    avatar_id: S.avatarId,
    style_id: S.styleId,
    voice_id: $('#vs-voice').value,
    hook_text: hook || idea,
    script_body: body,
    cta_text: cta,
    caption_style: $('#vs-caption').value,
    audio_track: $('#vs-track').value,
    broll_keyword: $('#vs-broll').value.trim(),
    aspect_ratio: S.aspect,
    duration_seconds: S.duration
  };
  if (S.editing) payload.creative_id = S.editing;

  try{
    const d = await api(payload);
    // Stop the "building scenes" creep immediately: the next overlay calls
    // come from runClientRender / pollJob and would otherwise be overwritten.
    clearInterval(creepTimer);
    const wasEditing = !!S.editing;
    S.editing = null;
    $('#vs-gen-label').textContent = 'Generate video';
    applyEngineVideo(d.video);
    syncMeta();
    renderFilm();

    if (d.video.status === 'rendering_client'){
      await runClientRender();
    } else if (d.video.status === 'generating'){
      await pollJob(S.creativeId);
    } else if (d.video.status === 'completed'){
      showCompleted(S.resultUrl, S.resultThumb);
    }
    if (wasEditing) setTimeout(() => location.reload(), 1800);
  }catch(e){
    clearInterval(creepTimer);
    toast(e.message, 'err');
    overlay(false);
  }finally{
    clearInterval(creepTimer);
    btn.disabled = false;
    $('#vs-gen-label').textContent = 'Generate video';
  }
});

/**
 * Shared response handler for create_video / update_video / regenerate_video:
 * pushes the engine payload into studio state and paints the scene board.
 */
function applyEngineVideo(v){
  S.creativeId = v.id;
  S.provider   = v.provider || ACTIVE;
  S.status     = v.status;
  S.resultUrl  = v.video_url || '';
  S.resultThumb= v.thumbnail_url || '';
  S.images     = [];
  S.playhead   = 0;
  S.currentScene = 0;
  S.scenes     = (v.scenes || S.scenes).map((s,i) => Object.assign({
    scene_number: i+1, label: 'Scene '+(i+1), caption:'', visual_prompt:'', image_url:'', local_url:'',
    start:0, length: Math.max(2, Math.round(S.duration/((v.scenes||S.scenes).length||1))),
    zoom_from: i%2===0?1.0:1.18, zoom_to: i%2===0?1.18:1.0, pan: i%3===0?'right':(i%3===1?'left':'up')
  }, s));
  updateCredits(-creditCost(S.duration));
}

/* ══════════════ EDIT & REGENERATE (library actions) ══════════════ */
window.editCreative = function(id){
  const c = LIB[id];
  if (!c){ toast('That video is not in your library.','err'); return; }

  $('#vs-hook').value = c.hook || '';
  $('#vs-body').value = c.script || '';
  $('#vs-cta').value  = c.cta || '';
  const ideaEl = $('#vs-idea'); if (ideaEl) ideaEl.value = c.hook || '';
  const brollEl = $('#vs-broll'); if (brollEl) brollEl.value = c.broll || '';
  const capSel = $('#vs-caption'); if (capSel) capSel.value = c.caption_style;
  const voiceSel = $('#vs-voice'); if (voiceSel) voiceSel.value = c.voice_id;
  const trackSel = $('#vs-track'); if (trackSel) trackSel.value = c.audio_track;

  setMode(c.mode === 'commercial' ? 'commercial' : 'ugc');
  setAspect(c.aspect);
  S.duration = c.duration;
  $$('#vs-dur-chips .vs-chip').forEach(x => x.classList.toggle('on', parseInt(x.dataset.dur,10) === c.duration));

  $$('#vs-avatars .vs-tile').forEach(x => x.classList.toggle('on', x.dataset.id === c.avatar_id));
  const tile = $('#vs-avatars .vs-tile[data-id="' + c.avatar_id + '"]');
  if (tile){ S.avatarId = c.avatar_id; S.avatarImg = tile.dataset.img; S.avatarName = tile.dataset.name; }

  $$('#vs-styles .vs-style').forEach(x => x.classList.toggle('on', x.dataset.id === c.style_id));
  S.styleId = c.style_id;

  S.scenes = (c.scenes || []).map((s,i) => Object.assign({
    scene_number: i+1, label: 'Scene '+(i+1), caption:'', visual_prompt:'', image_url:'', local_url:'',
    start:0, length: Math.max(2, Math.round(c.duration/((c.scenes||[]).length||1))),
    zoom_from: i%2===0?1.0:1.18, zoom_to: i%2===0?1.18:1.0, pan: i%3===0?'right':(i%3===1?'left':'up')
  }, s));

  S.creativeId = c.id;
  S.editing    = c.id;
  S.status     = 'idle';
  S.resultUrl  = '';
  S.resultThumb= '';
  S.images     = [];
  S.playhead   = 0;
  S.currentScene = 0;

  syncMeta();
  renderFilm();
  $('#vs-gen-label').textContent = 'Update & re-render';
  toast('Editing “' + ((c.hook||'video').slice(0,60)) + '”', 'ok', 'Change anything, then press Update & re-render.');
  const left = document.querySelector('.vs-left');
  if (left) left.scrollIntoView({ behavior:'smooth', block:'start' });
};

window.regenerateCreative = async function(id){
  const c = LIB[id];
  if (!c){ toast('That video is not in your library.','err'); return; }
  if (!confirm('Regenerate this video? Same script — brand-new AI scene stills. Costs ' + creditCost(c.duration) + ' credits.')) return;

  overlay(true, 'Regenerating scenes…', 'Creating fresh AI stills for every scene. About 20 seconds — stay on this page.', 20);
  try{
    const d = await api({ action:'regenerate_video', creative_id:id });
    applyEngineVideo(d.video);
    syncMeta();
    renderFilm();
    if (d.video.status === 'rendering_client'){
      await runClientRender();
    } else if (d.video.status === 'generating'){
      await pollJob(S.creativeId);
    }
    setTimeout(() => location.reload(), 1800);
  }catch(e){
    toast(e.message, 'err');
    overlay(false);
  }
};

function updateCredits(delta){
  const el = $('#vs-credits');
  const cur = parseInt(el.textContent, 10) || 0;
  el.textContent = Math.max(0, cur + delta);
}

/* ══════════════ SCENE BOARD ══════════════ */
function renderFilm(){
  const film = $('#vs-film');
  const empty = $('#vs-film-empty');
  if (!S.scenes.length){ film.innerHTML = ''; film.appendChild(empty || document.createTextNode('')); if(empty) empty.style.display=''; return; }

  film.innerHTML = '';
  S.scenes.forEach((sc, i) => {
    const img = sc.local_url || sc.image_url || '';
    const el = document.createElement('div');
    el.className = 'vs-scene' + (i === S.currentScene ? ' on' : '');
    el.innerHTML =
      '<div class="thumb">' +
        (img ? '<img src="' + esc(img) + '" alt="">' : '<div class="ph"><i class="ph ph-image"></i></div>') +
        '<span class="no">' + (i+1) + '</span>' +
      '</div>' +
      '<div class="meta"><b>' + esc(sc.label || ('Scene '+(i+1))) + '</b><small>' + esc(fmtTime(sc.start||0)) + ' → ' + esc(fmtTime((sc.start||0)+(sc.length||3))) + '</small></div>';
    el.addEventListener('click', () => { S.currentScene = i; renderFilm(); if (S.images[i]) drawFrame(i, 0.5); });
    film.appendChild(el);
  });
  $('#vs-stage-scenes').innerHTML = '<i class="ph ph-film-strip"></i> <b>' + S.scenes.length + '</b> scenes';
}

$('#btn-regen-scenes').addEventListener('click', () => {
  if (!S.creativeId){ toast('Generate a video first.','err'); return; }
  regenerateCreative(S.creativeId);
});

/* ══════════════ EXTERNAL PROVIDER POLLING ══════════════ */
async function pollJob(id){
  const started = Date.now();
  const LIMIT = 6 * 60 * 1000;
  overlay(true, 'Rendering with ' + (PROVIDERS[S.provider]||{}).label + '…', 'This usually takes 1–3 minutes. Keep this tab open.', 10);

  while (Date.now() - started < LIMIT){
    await new Promise(r => setTimeout(r, 5000));
    let d;
    try { d = await api({ action:'get_video_status', creative_id: id }); }
    catch(e){ overlay(false); toast(e.message,'err'); return; }

    const j = d.job || {};
    const pct = Math.min(92, 10 + ((Date.now()-started)/LIMIT)*85);
    if (j.status === 'completed'){
      overlay(false);
      showCompleted(j.video_url, j.thumbnail_url);
      return;
    }
    if (j.status === 'failed'){
      overlay(false);
      S.status = 'failed';
      toast(j.error || 'The render failed.','err');
      return;
    }
    overlay(true, 'Rendering with ' + (PROVIDERS[S.provider]||{}).label + '…', j.message || 'Waiting for the provider…', pct);
  }
  overlay(false);
  toast('Still rendering — refresh in a minute to see the result.','err');
}

/* ══════════════ CLIENT-SIDE RENDERER ══════════════ */
function pickMime(){
  const candidates = [
    'video/mp4;codecs=avc1.42E01E,mp4a.40.2',
    'video/mp4',
    'video/webm;codecs=vp9,opus',
    'video/webm;codecs=vp8,opus',
    'video/webm'
  ];
  for (const m of candidates){
    if (window.MediaRecorder && MediaRecorder.isTypeSupported(m)) return m;
  }
  return '';
}

async function decodeScene(sc, i){
  // Prefer the server-hosted copy (same-origin → untainted canvas).
  const candidates = [];
  if (sc.local_url) candidates.push(sc.local_url);
  if (sc.image_url) candidates.push(sc.image_url);

  for (const url of candidates){
    try{
      const res = await fetch(url, { mode: url.startsWith('http') && !url.startsWith(location.origin) ? 'cors' : 'same-origin' });
      if (!res.ok) continue;
      const blob = await res.blob();
      if (window.createImageBitmap){
        return await createImageBitmap(blob);
      }
      // Fallback: HTMLImageElement
      return await new Promise((ok, no) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => ok(img);
        img.onerror = () => no(new Error('decode failed'));
        img.src = URL.createObjectURL(blob);
      });
    }catch(e){ /* try next */ }
  }
  return null;
}

function wrapText(ctx, text, maxWidth){
  const words = String(text||'').split(/\s+/).filter(Boolean);
  const lines = []; let line = '';
  for (const w of words){
    const test = line ? line + ' ' + w : w;
    if (ctx.measureText(test).width > maxWidth && line){ lines.push(line); line = w; }
    else line = test;
  }
  if (line) lines.push(line);
  return lines;
}

function drawScene(ctx, img, sc, W, H, t){
  ctx.fillStyle = '#05080f';
  ctx.fillRect(0,0,W,H);

  // Declared here (not inside the caption block) because the creator badge
  // below needs them too — scoping them with `const` inside the caption
  // `if` threw a ReferenceError on every UGC render.
  const pad = Math.round(W * 0.045);
  const fs  = Math.round(W * 0.052);

  const zoom = (sc.zoom_from||1) + ((sc.zoom_to||1.18) - (sc.zoom_from||1)) * t;
  if (img){
    const iw = img.width, ih = img.height;
    const scale = Math.max(W/iw, H/ih) * zoom;
    const dw = iw*scale, dh = ih*scale;
    let dx = (W - dw)/2, dy = (H - dh)/2;
    const pan = sc.pan || 'right';
    if (pan === 'right') dx -= (dw - W)/2 * t;
    else if (pan === 'left') dx += (dw - W)/2 * t;
    else if (pan === 'up')  dy += (dh - H)/2 * t;
    ctx.drawImage(img, dx, dy, dw, dh);
  } else {
    // Placeholder gradient while images load
    const g = ctx.createLinearGradient(0,0,W,H);
    g.addColorStop(0,'#132038'); g.addColorStop(1,'#05080f');
    ctx.fillStyle = g; ctx.fillRect(0,0,W,H);
  }

  // Vignette
  const vg = ctx.createRadialGradient(W/2,H/2,Math.min(W,H)*0.35, W/2,H/2, Math.max(W,H)*0.75);
  vg.addColorStop(0,'rgba(0,0,0,0)'); vg.addColorStop(1,'rgba(0,0,0,0.55)');
  ctx.fillStyle = vg; ctx.fillRect(0,0,W,H);

  // Caption block
  const opt = selCaption.selectedOptions[0] || {};
  const bg = opt.dataset.bg || 'rgba(0,0,0,0.6)';
  const color = opt.dataset.color || '#c8ff00';
  const stroke = opt.dataset.stroke || 'none';
  const text = String(sc.caption||'');
  if (text){
    ctx.font = '800 ' + fs + 'px system-ui, -apple-system, "Segoe UI", sans-serif';
    ctx.textBaseline = 'top';
    const maxW = W - pad*2;
    const lines = wrapText(ctx, text, maxW).slice(0,4);
    const lineH = Math.round(fs * 1.22);
    const boxH = lines.length * lineH + pad*1.4;
    const boxY = H - boxH - Math.round(H*0.10);

    ctx.fillStyle = bg;
    roundRect(ctx, pad*0.5, boxY, W - pad, boxH, Math.round(W*0.03));
    ctx.fill();

    lines.forEach((ln, i) => {
      const y = boxY + pad*0.7 + i*lineH;
      if (stroke && stroke !== 'none'){ ctx.lineWidth = Math.max(3, fs*0.12); ctx.strokeStyle = stroke; ctx.lineJoin='round'; ctx.strokeText(ln, pad, y); }
      ctx.fillStyle = color;
      ctx.fillText(ln, pad, y);
    });
  }

  // Creator badge
  if (S.mode === 'ugc'){
    ctx.font = '700 ' + Math.round(W*0.032) + 'px system-ui, sans-serif';
    const label = (S.avatarName || 'Creator') + '  ·  #ad';
    const tw = ctx.measureText(label).width;
    ctx.fillStyle = 'rgba(0,0,0,0.6)';
    roundRect(ctx, pad, pad, tw + pad, Math.round(W*0.075), Math.round(W*0.02)); ctx.fill();
    ctx.fillStyle = '#c8ff00';
    ctx.textBaseline = 'middle';
    ctx.fillText(label, pad*1.6, pad + Math.round(W*0.0375));
    ctx.textBaseline = 'top';
  }
}

function roundRect(ctx,x,y,w,h,r){
  ctx.beginPath();
  ctx.moveTo(x+r,y);
  ctx.arcTo(x+w,y,x+w,y+h,r);
  ctx.arcTo(x+w,y+h,x,y+h,r);
  ctx.arcTo(x,y+h,x,y,r);
  ctx.arcTo(x,y,x+w,y,r);
  ctx.closePath();
}

function drawFrame(idx, t){
  const cv = $('#vs-canvas');
  const ctx = cv.getContext('2d');
  drawScene(ctx, S.images[idx] || null, S.scenes[idx] || {caption:''}, cv.width, cv.height, t);
}

async function runClientRender(){
  overlay(true, 'Generating your shots…', 'Creating a photoreal frame for each scene.', 5);

  const W = $('#vs-canvas').width, H = $('#vs-canvas').height;
  S.images = [];

  for (let i=0; i<S.scenes.length; i++){
    overlay(true, 'Generating your shots…', 'Scene ' + (i+1) + ' of ' + S.scenes.length, 5 + ((i)/S.scenes.length)*35);
    S.images[i] = await decodeScene(S.scenes[i], i);
    S.currentScene = i;
    drawFrame(i, 0.3);
    renderFilm();
  }

  const missing = S.images.filter(x => !x).length;
  if (missing === S.images.length && S.images.length > 0){
    overlay(false);
    S.status = 'failed';
    toast('Could not load the scene images. Check your connection and try again.','err');
    return;
  }

  // ── Record ──
  const mime = pickMime();
  if (!mime || !window.MediaRecorder){
    overlay(false);
    S.status = 'failed';
    toast('This browser cannot record video. Please use a recent Chrome, Edge or Firefox.','err');
    return;
  }

  overlay(true, 'Recording your video…', 'Compositing scenes, captions and motion in real time.', 45);

  const cv = $('#vs-canvas');
  const stream = cv.captureStream(30);
  const mix = $('#vs-mix').value;

  // Build the beat graph BEFORE creating the recorder and attach its output
  // as a second track. MediaRecorder snapshots the track list at
  // construction time, so adding it later would produce a silent file.
  if (mix !== 'voice_only'){
    const actx = ensureAudioCapture();
    if (actx){
      if (actx.state === 'suspended') actx.resume();
      const audioTracks = S.audioDest ? S.audioDest.stream.getAudioTracks() : [];
      for (const at of audioTracks) stream.addTrack(at);
    }
  }

  let rec;
  try { rec = new MediaRecorder(stream, { mimeType: mime, videoBitsPerSecond: Math.min(8_000_000, Math.max(2_500_000, W*H*4)) }); }
  catch(e){
    try { rec = new MediaRecorder(stream); }
    catch(e2){ overlay(false); toast('Could not start the recorder.','err'); return; }
  }

  const chunks = [];
  rec.ondataavailable = e => { if (e.data && e.data.size) chunks.push(e.data); };

  const finished = new Promise(res => { rec.onstop = () => res(); });
  rec.start(250);

  // Start the beat now that the recorder is attached and capturing.
  if (mix !== 'voice_only') startBeat();

  const total = S.duration;
  const startAt = performance.now();
  const sceneOffsets = [];
  let acc = 0;
  for (const sc of S.scenes){ sceneOffsets.push(acc); acc += (sc.length || 3); }
  const planned = acc || total;

  // Recording a zero-scene storyboard would divide by zero below and hand
  // MediaRecorder an unchanging canvas; refuse instead of producing junk.
  if (!S.scenes.length || !planned || !isFinite(planned)){
    stopBeat();
    rec.stop();
    await finished;
    overlay(false);
    toast('This storyboard has no scenes to record. Generate the shots again.','err');
    return;
  }

  await new Promise(resolve => {
    function tick(now){
      const el = (now - startAt)/1000;
      const pct = Math.min(1, el/planned);
      overlay(true, 'Recording your video…', 'Compositing scenes, captions and motion in real time.', 45 + pct*45);
      S.playhead = Math.min(el, planned);
      $('#vs-time').innerHTML = '<b>' + fmtTime(S.playhead) + '</b> / ' + fmtTime(planned);

      // find active scene
      let idx = 0;
      for (let i=0; i<sceneOffsets.length; i++){ if (el >= sceneOffsets[i]) idx = i; }
      const scene = S.scenes[idx] || { length: 1, caption: '' };
      const local = (el - sceneOffsets[idx]) / Math.max(0.001, (scene.length || 3));
      S.currentScene = idx;
      drawFrame(idx, Math.max(0, Math.min(1, local)));

      if (el >= planned){ resolve(); return; }
      S.raf = requestAnimationFrame(tick);
    }
    S.raf = requestAnimationFrame(tick);
  });

  stopBeat();
  rec.stop();
  await finished;
  if (S.raf) cancelAnimationFrame(S.raf);

  overlay(true, 'Saving your video…', 'Uploading the finished file to your library.', 95);

  const blob = new Blob(chunks, { type: mime.split(';')[0] });
  if (blob.size < 1024){
    overlay(false);
    toast('The recorder produced an empty file. Please try again.','err');
    return;
  }

  let thumb = null;
  try { thumb = cv.toDataURL('image/jpeg', 0.72); } catch(e){}

  const fd = new FormData();
  fd.append('csrf_token', CSRF);
  fd.append('action', 'save_render');
  fd.append('creative_id', S.creativeId);
  if (thumb) fd.append('thumbnail', thumb);
  fd.append('video', blob, 'render.' + (mime.includes('mp4') ? 'mp4' : 'webm'));

  try{
    const res = await fetch(ENDPOINT, { method:'POST', body: fd, credentials:'same-origin' });
    const txt = await res.text();
    let data; try { data = JSON.parse(txt); } catch(e){ throw new Error('Unexpected server response.'); }
    if (!data.success) throw new Error(data.error || 'Could not save the video.');
    overlay(false);
    S.recordedSeconds = planned;
    showCompleted(data.video.video_url, data.video.thumbnail_url, blob);
    toast('Your video is ready','ok', Math.round(blob.size/1024) + ' KB · ' + Math.round(planned) + 's');
  }catch(e){
    overlay(false);
    toast(e.message,'err');
  }
}

/* ══════════════ BEAT ══════════════ */
/**
 * Set up an AudioContext whose output is a MediaStream we can hand to
 * MediaRecorder. Without this the beat only reached the speakers and the
 * exported file came out silent.
 */
function ensureAudioCapture(){
  if (S.audioCtx) return S.audioCtx;
  const AC = window.AudioContext || window.webkitAudioContext;
  if (!AC) return null;
  try{
    S.audioCtx  = new AC();
    S.audioDest = S.audioCtx.createMediaStreamDestination();
  }catch(e){ return null; }
  return S.audioCtx;
}

function startBeat(){
  try{
    const ctx = ensureAudioCapture();
    if (!ctx) return;
    if (ctx.state === 'suspended') ctx.resume();

    // Everything is wired to the capture node so it lands in the file.
    // Nothing is sent to the speakers: monitoring would bleed an echo into
    // the recording on machines with a live mic.
    const sink = S.audioDest || ctx.destination;

    const opt = $('#vs-track').selectedOptions[0] || {};
    const bpm = parseInt(opt.dataset.bpm||100,10);
    const wave = opt.dataset.wave||'sine';
    const vol = $('#vs-mix').value === 'punchy' ? 0.10 : 0.05;
    const stepMs = (60/bpm)*1000/2;
    let step = 0;
    S.beatTimer = setInterval(() => {
      if (!S.audioCtx) return;
      const now = S.audioCtx.currentTime;
      if (step % 2 === 0){
        const o = S.audioCtx.createOscillator(), g = S.audioCtx.createGain();
        o.type='sine'; o.frequency.setValueAtTime(130, now); o.frequency.exponentialRampToValueAtTime(35, now+0.12);
        g.gain.setValueAtTime(vol*1.6, now); g.gain.exponentialRampToValueAtTime(0.0001, now+0.14);
        o.connect(g); g.connect(sink); o.start(now); o.stop(now+0.15);
      }
      const h = S.audioCtx.createOscillator(), hg = S.audioCtx.createGain();
      h.type = wave==='sine' ? 'triangle' : 'square';
      h.frequency.setValueAtTime(3800, now);
      hg.gain.setValueAtTime(vol*0.32, now); hg.gain.exponentialRampToValueAtTime(0.0001, now+0.04);
      h.connect(hg); hg.connect(sink); h.start(now); h.stop(now+0.05);
      step++;
    }, stepMs);
  }catch(e){}
}
function stopBeat(){ if (S.beatTimer){ clearInterval(S.beatTimer); S.beatTimer=null; } }

/* ══════════════ PLAYBACK ══════════════ */
function showCompleted(url, thumb, blob){
  S.status = 'completed';
  S.resultUrl = url || (blob ? URL.createObjectURL(blob) : '');
  S.resultThumb = thumb || '';
  S.resultMime = blob && blob.type ? blob.type : (S.resultMime || '');

  $('#vs-empty').style.display = 'none';
  const video = $('#vs-video');
  $('#vs-canvas').style.display = 'none';
  video.style.display = 'block';
  video.src = S.resultUrl;
  video.controls = true;
  video.muted = S.muted;
  video.playsInline = true;
  if (S.resultThumb) video.poster = S.resultThumb;
  video.play().catch(()=>{});

  // A blob: URL has no usable file extension — take it from the recorded
  // MIME type instead so the download is always named render.mp4/webm.
  const recordedExt = (String(S.resultMime||'').indexOf('mp4') > -1) ? 'mp4' : 'webm';

  $('#btn-download').style.display = '';
  $('#btn-download').onclick = () => {
    const a = document.createElement('a');
    a.href = S.resultUrl;
    a.download = 'adsbooster-video.' + recordedExt;
    document.body.appendChild(a); a.click(); a.remove();
  };
  $('#btn-publish').style.display = '';
  $('#btn-runad').style.display = '';

  const total = Math.max(1, S.recordedSeconds || S.duration);
  $('#vs-total').textContent = fmtTime(total);
}

$('#btn-play').addEventListener('click', () => {
  const v = $('#vs-video');
  if (S.status === 'completed' && v.src){
    if (v.paused){ v.play(); $('#vs-play-icon').className='ph-fill ph-pause'; }
    else { v.pause(); $('#vs-play-icon').className='ph-fill ph-play'; }
    return;
  }
  if (S.scenes.length){
    // Preview the storyboard on the canvas
    if (S.playing){
      S.playing = false;
      if (S.raf) cancelAnimationFrame(S.raf);
      stopBeat();
      $('#vs-play-icon').className = 'ph-fill ph-play';
    } else {
      S.playing = true;
      $('#vs-play-icon').className = 'ph-fill ph-pause';
      if (!$('#vs-empty').style || $('#vs-empty').style.display !== 'none') $('#vs-empty').style.display = 'none';
      if ($('#vs-mix').value !== 'voice_only') startBeat();
      const startAt = performance.now() - S.playhead*1000;
      const offs = []; let acc=0;
      for (const sc of S.scenes){ offs.push(acc); acc += (sc.length||3); }
      const planned = acc || S.duration;
      function tick(now){
        if (!S.playing) return;
        const el = (now-startAt)/1000;
        if (el >= planned){ S.playing=false; S.playhead=0; stopBeat(); $('#vs-play-icon').className='ph-fill ph-play'; drawFrame(0,0); $('#vs-time').innerHTML='<b>0:00</b> / '+fmtTime(planned); return; }
        S.playhead = el;
        let idx=0; for (let i=0;i<offs.length;i++){ if (el>=offs[i]) idx=i; }
        const local = (el-offs[idx])/Math.max(0.001,(S.scenes[idx].length||3));
        S.currentScene = idx;
        drawFrame(idx, Math.max(0,Math.min(1,local)));
        $('#vs-time').innerHTML = '<b>'+fmtTime(el)+'</b> / '+fmtTime(planned);
        S.raf = requestAnimationFrame(tick);
      }
      S.raf = requestAnimationFrame(tick);
    }
  }
});

$('#btn-restart').addEventListener('click', () => {
  const v = $('#vs-video');
  if (S.status === 'completed' && v.src){ v.currentTime = 0; v.play(); return; }
  S.playhead = 0; S.currentScene = 0;
  if (S.scenes.length) drawFrame(0,0);
  $('#vs-time').innerHTML = '<b>0:00</b> / ' + fmtTime(S.duration);
});

$('#btn-mute').addEventListener('click', () => {
  S.muted = !S.muted;
  $('#vs-video').muted = S.muted;
  $('#vs-mute-icon').className = S.muted ? 'ph ph-volume-x' : 'ph ph-volume-high';
});

$('#vs-video').addEventListener('timeupdate', () => {
  const v = $('#vs-video');
  if (v.duration) $('#vs-time').innerHTML = '<b>'+fmtTime(v.currentTime)+'</b> / '+fmtTime(v.duration);
});

/* ══════════════ DELETE ══════════════ */
$$('.js-del').forEach(b => b.addEventListener('click', async () => {
  if (!confirm('Delete this render? This cannot be undone.')) return;
  try{
    await api({ action:'delete_video', creative_id: b.dataset.id });
    b.closest('.vs-card').remove();
    toast('Render deleted','ok');
  }catch(e){ toast(e.message,'err'); }
}));

/* ══════════════ INIT ══════════════ */
syncMeta();
setMode('ugc');
if (PROVIDERS[ACTIVE]) $('#vs-gen-engine').textContent = PROVIDERS[ACTIVE].label;
if (!(PROVIDERS[ACTIVE] && PROVIDERS[ACTIVE].kind !== 'storyboard')){
  $('#vs-provider-pill').title = 'Rendering with the free built-in Studio: photoreal stills generated via Gemini / NVIDIA NIM / Pollinations (auto-routed, keyless fallback), composited into a real video in your browser. Add a Veo, Replicate, fal.ai or Runway key in Admin → Settings to unlock premium cloud rendering.';
}
window.addEventListener('beforeunload', e => {
  if (S.status === 'rendering_client' || S.playing){ e.preventDefault(); e.returnValue = ''; }
});

})();
</script>

<?php require_once __DIR__ . '/includes/app_footer.php';

/**
 * Small Groq helper used by the prompt-enhance action.
 */
function aiStudioChat(string $prompt, string $key): string
{
    $ssl = (function_exists('ini_get') && (string)ini_get('curl.cainfo') !== '');
    $c = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($c, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'model'       => 'openai/gpt-oss-120b',
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.8,
            'max_tokens'  => 320,
        ]),
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => $ssl,
        CURLOPT_SSL_VERIFYHOST => $ssl ? 2 : 0,
    ]);
    $r = curl_exec($c);
    curl_close($c);
    $j = json_decode((string)$r, true) ?: [];
    return trim((string)($j['choices'][0]['message']['content'] ?? ''));
}
