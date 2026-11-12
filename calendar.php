<?php
declare(strict_types=1);

$pageTitle = 'Content Calendar & Planner — Adsbooster.net';
$pageDescription = 'Interactive visual calendar for scheduling, planning, and organizing multi-network social posts and campaigns.';
require_once __DIR__ . '/includes/app_header.php';
require_once __DIR__ . '/modules/social_post_engine.php';

$userId = current_user_id();

// Legacy form fallback (kept for no-JS clients); the UI itself drives
// everything through modules/calendar_api.php with AJAX — no reloads.
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'calendar_schedule') {
    if (!csrf_verify()) {
        $message = '<div class="cal-alert bad">Invalid security token.</div>';
    } else {
        $caption     = trim($_POST['caption'] ?? '');
        $platform    = trim($_POST['platform'] ?? 'meta');
        $mediaType   = trim($_POST['media_type'] ?? 'text');
        $scheduledAt = trim($_POST['scheduled_at'] ?? '');

        if ($caption === '') {
            $message = '<div class="cal-alert bad">Caption content is required.</div>';
        } elseif ($scheduledAt === '') {
            $message = '<div class="cal-alert bad">Please specify a scheduled date &amp; time.</div>';
        } else {
            $mediaUrl = null;
            if (!empty($_FILES['media_upload']['tmp_name']) && $_FILES['media_upload']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['media_upload']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','gif','webp','mp4','mov','pdf'];
                if (in_array($ext, $allowed)) {
                    $uploadDir = ROOT_PATH . '/uploads/posts/' . date('Y/m');
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $filename = bin2hex(random_bytes(12)) . '.' . $ext;
                    move_uploaded_file($_FILES['media_upload']['tmp_name'], $uploadDir . '/' . $filename);
                    $mediaUrl = 'uploads/posts/' . date('Y/m') . '/' . $filename;
                }
            }

            try {
                SocialPostEngine::createPost(
                    $userId,
                    $platform,
                    $caption,
                    $mediaUrl,
                    $mediaType,
                    $scheduledAt
                );
                $message = '<div class="cal-alert ok"><i class="ph ph-calendar-check"></i> Post successfully scheduled for <b>' . htmlspecialchars(date('M d, Y H:i', strtotime($scheduledAt))) . '</b>!</div>';
            } catch (\Throwable $e) {
                $message = '<div class="cal-alert bad">' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }
}

// Current month calculation
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year'] ?? date('Y'));

if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$firstOfMonth = sprintf('%04d-%02d-01', $year, $month);
$daysInMonth  = (int)date('t', strtotime($firstOfMonth));
$firstDayOfWeek = (int)date('w', strtotime($firstOfMonth)); // 0 = Sun, 6 = Sat

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$daysInPrevMonth = (int)date('t', strtotime(sprintf('%04d-%02d-01', $prevYear, $prevMonth)));

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate   = date('Y-m-t', strtotime($startDate));

// Fetch events
$posts = db()->fetchAll(
    "SELECT id, caption, media_type, scheduled_time, status, platform, bundle_post_id, reminder_count
     FROM scheduled_posts WHERE user_id = ? AND scheduled_time BETWEEN ? AND ? ORDER BY scheduled_time ASC",
    [$userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']
);

// Day notes (sticky notes pinned to a date)
$notes = db()->fetchAll(
    "SELECT id, note_date, note, color FROM calendar_notes WHERE user_id = ? AND note_date BETWEEN ? AND ? ORDER BY created_at ASC",
    [$userId, $startDate, $endDate]
);

$campaigns = db()->fetchAll(
    "SELECT id, campaign_name, campaign_type, created_at, budget_amount, status FROM ad_campaigns WHERE user_id = ? AND created_at BETWEEN ? AND ? ORDER BY created_at ASC",
    [$userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']
);

$platformMeta = [
    'meta'            => ['icon' => 'facebook-logo', 'color' => '#1877F2', 'bg' => 'rgba(24,119,242,0.15)', 'label' => 'Meta'],
    'instagram'       => ['icon' => 'instagram-logo', 'color' => '#E4405F', 'bg' => 'rgba(228,64,95,0.15)', 'label' => 'Instagram'],
    'tiktok'          => ['icon' => 'tiktok-logo', 'color' => '#00f2fe', 'bg' => 'rgba(0,242,254,0.15)', 'label' => 'TikTok'],
    'twitter'         => ['icon' => 'x-logo', 'color' => '#f8fafc', 'bg' => 'rgba(248,250,252,0.12)', 'label' => 'X'],
    'linkedin'        => ['icon' => 'linkedin-logo', 'color' => '#0A66C2', 'bg' => 'rgba(10,102,194,0.15)', 'label' => 'LinkedIn'],
    'google_ads'      => ['icon' => 'google-logo', 'color' => '#4285F4', 'bg' => 'rgba(66,133,244,0.15)', 'label' => 'Google Ads'],
    'snapchat'        => ['icon' => 'ghost', 'color' => '#FFFC00', 'bg' => 'rgba(255,252,0,0.15)', 'label' => 'Snapchat'],
    'combo_pack'      => ['icon' => 'sparkle', 'color' => 'var(--accent)', 'bg' => 'rgba(200,255,0,0.15)', 'label' => 'Combo Pack'],
    'google_calendar' => ['icon' => 'calendar', 'color' => '#34A853', 'bg' => 'rgba(52,168,83,0.15)', 'label' => 'Calendar'],
];

$noteColors = [
    'lime'  => ['bg' => 'rgba(200,255,0,0.10)',  'bar' => '#c8ff00'],
    'blue'  => ['bg' => 'rgba(56,189,248,0.10)', 'bar' => '#38bdf8'],
    'pink'  => ['bg' => 'rgba(244,114,182,0.10)','bar' => '#f472b6'],
    'amber' => ['bg' => 'rgba(251,191,36,0.10)', 'bar' => '#fbbf24'],
];

// JS-ready payloads so the AJAX layer can render events client-side.
$postsJs = [];
foreach ($posts as $p) {
    $postsJs[] = [
        'id'       => (int)$p['id'],
        'date'     => date('Y-m-d', strtotime($p['scheduled_time'])),
        'time'     => date('H:i', strtotime($p['scheduled_time'])),
        'caption'  => mb_substr(strip_tags($p['caption']), 0, 60),
        'platform' => (string)$p['platform'],
        'status'   => (string)$p['status'],
        'media'    => (string)($p['media_type'] ?? 'text'),
        'bundle_post_id' => (string)($p['bundle_post_id'] ?? ''),
        'reminders' => (int)($p['reminder_count'] ?? 3),
    ];
}
$notesJs = [];
foreach ($notes as $n) {
    $notesJs[] = [
        'id'    => (int)$n['id'],
        'date'  => (string)$n['note_date'],
        'note'  => (string)$n['note'],
        'color' => (string)$n['color'],
    ];
}
?>

<div class="w-100 cal-page">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <?= $message ?>

    <!-- Calendar Header -->
    <div class="page-header" style="margin-bottom:24px">
        <div>
            <h1 class="page-title">Content Calendar</h1>
            <p class="page-subtitle">Schedule posts, publish instantly, pin day notes, and get reminders your way. Click any date to start.</p>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <!-- Navigation -->
            <div style="display:inline-flex;align-items:center;background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:4px;">
                <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn-outline-dark btn-sm" style="border:none;padding:6px 12px;" title="Previous Month">
                    <i class="ph ph-caret-left" style="font-size:16px;"></i>
                </a>
                <span style="font-size:15px;font-weight:700;color:var(--fg);padding:0 14px;min-width:140px;text-align:center;">
                    <?= date('F Y', strtotime($firstOfMonth)) ?>
                </span>
                <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn-outline-dark btn-sm" style="border:none;padding:6px 12px;" title="Next Month">
                    <i class="ph ph-caret-right" style="font-size:16px;"></i>
                </a>
            </div>

            <a href="?month=<?= date('n') ?>&year=<?= date('Y') ?>" class="btn-outline-dark btn-sm" style="padding:8px 14px;">
                Today
            </a>

            <button class="btn-accent btn-sm" onclick="openDayModal('<?= date('Y-m-d') ?>', 'schedule')">
                <i class="ph ph-plus-circle"></i> + New Post
            </button>
        </div>
    </div>

    <!-- Calendar Container -->
    <div class="card-dark" style="padding:0;overflow:hidden;border:1px solid var(--border2);border-radius:18px;">
        <!-- Weekday Headers -->
        <div class="cal-weekdays">
            <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $w): ?>
                <div><?= $w ?></div>
            <?php endforeach; ?>
        </div>

        <!-- Month Grid -->
        <div class="cal-grid" id="calGrid" data-posts='<?= e(json_encode($postsJs)) ?>' data-notes='<?= e(json_encode($notesJs)) ?>'>
            <?php
            // Previous month trailing days
            for ($i = $firstDayOfWeek - 1; $i >= 0; $i--):
                $prevDayNum = $daysInPrevMonth - $i;
            ?>
                <div class="cal-cell cal-pad">
                    <span class="cal-daynum"><?= $prevDayNum ?></span>
                </div>
            <?php endfor; ?>

            <?php
            // Current month days
            for ($d = 1; $d <= $daysInMonth; $d++):
                $curDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $isToday = $curDate === date('Y-m-d');
            ?>
                <div class="cal-cell<?= $isToday ? ' cal-today' : '' ?>"
                     data-date="<?= $curDate ?>"
                     onclick="onCellClick(event, '<?= $curDate ?>')"
                     role="button" tabindex="0"
                     aria-label="<?= $curDate ?>">
                    <div class="cal-cellhead">
                        <span class="cal-daynum"><?= $d ?></span>
                        <?php if ($isToday): ?>
                            <span class="cal-todaybadge">Today</span>
                        <?php else: ?>
                            <i class="ph ph-plus cal-addhint"></i>
                        <?php endif; ?>
                    </div>
                    <div class="cal-stack" data-stack></div>
                </div>
            <?php endfor; ?>

            <?php
            // Next month leading days to complete week grid
            $totalCells = $firstDayOfWeek + $daysInMonth;
            $remaining = (7 - ($totalCells % 7)) % 7;
            for ($j = 1; $j <= $remaining; $j++):
            ?>
                <div class="cal-cell cal-pad">
                    <span class="cal-daynum"><?= $j ?></span>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Legend -->
    <div class="card-dark mt-3" style="padding:14px 22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;">
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <span style="font-size:13px;font-weight:700;color:var(--fg);">Legend:</span>
            <?php foreach ($platformMeta as $key => $p): ?>
                <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--fg2);">
                    <span style="width:10px;height:10px;border-radius:50%;background:<?= $p['color'] ?>;display:inline-block;"></span>
                    <span><?= $p['label'] ?></span>
                </div>
            <?php endforeach; ?>
            <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--fg2);">
                <span style="width:10px;height:10px;border-radius:3px;background:#fbbf24;display:inline-block;"></span>
                <span>Day note</span>
            </div>
        </div>
        <div style="font-size:12px;color:var(--fg3);">
            <i class="ph ph-cursor"></i> Click a day to schedule or post now • Drag posts to reschedule
        </div>
    </div>
</div>

<!-- ── Day / Post modal (all actions are AJAX — no reloads) ── -->
<div class="modal fade" id="calModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content cal-modal">
            <div class="modal-header cal-modalhead">
                <div class="d-flex align-items-center gap-3">
                    <div class="cal-modalicon"><i class="ph ph-calendar-plus"></i></div>
                    <div>
                        <h5 class="modal-title" id="calModalTitle" style="font-size:17px;font-weight:800;color:#fff;margin:0;">New Post</h5>
                        <p style="font-size:12px;color:#94a3b8;margin:2px 0 0;" id="calModalSub">Pick what you want to add to this day</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body cal-modalbody">
                <input type="hidden" id="calModalDate" value="">

                <!-- Mode chooser -->
                <div class="cal-modepick" id="calModePick">
                    <button type="button" class="cal-mode" onclick="switchCalMode('schedule')">
                        <i class="ph ph-calendar-plus"></i>
                        <b>Schedule a post</b>
                        <span>Queue it for a date &amp; time</span>
                    </button>
                    <button type="button" class="cal-mode" onclick="switchCalMode('publish')">
                        <i class="ph ph-paper-plane-tilt"></i>
                        <b>Post right now</b>
                        <span>Publish immediately</span>
                    </button>
                    <button type="button" class="cal-mode" onclick="switchCalMode('note')">
                        <i class="ph ph-sticker"></i>
                        <b>Add a day note</b>
                        <span>A sticky note for this date</span>
                    </button>
                </div>

                <!-- ── Schedule form ── -->
                <form id="calScheduleForm" class="cal-pane" style="display:none" enctype="multipart/form-data">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="cal-lbl">Channel</label>
                            <select id="calPlatform" class="cal-in" required>
                                <option value="meta">Meta / Facebook</option>
                                <option value="instagram">Instagram</option>
                                <option value="tiktok">TikTok</option>
                                <option value="twitter">Twitter / X</option>
                                <option value="linkedin">LinkedIn</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="cal-lbl">Scheduled date &amp; time</label>
                            <input type="datetime-local" id="calScheduledAt" class="cal-in" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="cal-lbl">Caption <span class="text-danger">*</span></label>
                        <textarea id="calCaption" rows="4" class="cal-in" placeholder="Write your caption, hooks and hashtags..." required></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="cal-lbl">Media (optional)</label>
                            <input type="file" id="calMedia" accept="image/*,video/mp4,video/quicktime" class="cal-in">
                        </div>
                        <div class="col-md-6">
                            <label class="cal-lbl">Reminders before publishing</label>
                            <select id="calReminders" class="cal-in">
                                <option value="0">No reminders</option>
                                <option value="1">1 reminder (24 h before)</option>
                                <option value="2">2 reminders (24 h, 1 h)</option>
                                <option value="3" selected>3 reminders (24 h, 1 h, 15 min)</option>
                                <option value="4">4 reminders (+ 3 h)</option>
                                <option value="5">5 reminders (+ 1 week)</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 pt-1">
                        <button type="button" class="cal-btn ghost" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="cal-btn pri" id="calScheduleBtn"><i class="ph ph-calendar-check"></i> Schedule Post</button>
                    </div>
                </form>

                <!-- ── Post-now form ── -->
                <form id="calPublishForm" class="cal-pane" style="display:none" enctype="multipart/form-data">
                    <div class="cal-publishnote">
                        <i class="ph ph-info"></i>
                        <span>This queues your post for <b>immediate</b> dispatch through Bundle.social — the worker sends it out of band and emails you when the network confirms.</span>
                    </div>
                    <div class="mb-3">
                        <label class="cal-lbl">Channel</label>
                        <select id="calPubPlatform" class="cal-in" required>
                            <option value="meta">Meta / Facebook</option>
                            <option value="instagram">Instagram</option>
                            <option value="tiktok">TikTok</option>
                            <option value="twitter">Twitter / X</option>
                            <option value="linkedin">LinkedIn</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="cal-lbl">Caption <span class="text-danger">*</span></label>
                        <textarea id="calPubCaption" rows="4" class="cal-in" placeholder="What do you want to publish right now?" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="cal-lbl">Media (optional)</label>
                        <input type="file" id="calPubMedia" accept="image/*,video/mp4,video/quicktime" class="cal-in">
                    </div>
                    <div class="d-flex justify-content-end gap-2 pt-1">
                        <button type="button" class="cal-btn ghost" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="cal-btn pri" id="calPublishBtn"><i class="ph ph-paper-plane-tilt"></i> Post Now</button>
                    </div>
                </form>

                <!-- ── Note form ── -->
                <form id="calNoteForm" class="cal-pane" style="display:none">
                    <div class="mb-3">
                        <label class="cal-lbl">Note for <span id="calNoteDateLbl" style="color:var(--accent)"></span></label>
                        <textarea id="calNoteText" rows="3" class="cal-in" placeholder="e.g. Ship the new product teaser — assets ready in Drive"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="cal-lbl">Colour</label>
                        <div class="cal-colors" id="calNoteColors">
                            <button type="button" class="cal-cdot on" data-color="lime" style="background:#c8ff00" aria-label="Lime"></button>
                            <button type="button" class="cal-cdot" data-color="blue" style="background:#38bdf8" aria-label="Blue"></button>
                            <button type="button" class="cal-cdot" data-color="pink" style="background:#f472b6" aria-label="Pink"></button>
                            <button type="button" class="cal-cdot" data-color="amber" style="background:#fbbf24" aria-label="Amber"></button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 pt-1">
                        <button type="button" class="cal-btn danger" id="calNoteDelete" style="display:none"><i class="ph ph-trash"></i> Delete note</button>
                        <button type="submit" class="cal-btn pri" id="calNoteSave"><i class="ph ph-sticker"></i> Save Note</button>
                    </div>
                </form>

                <!-- ── Post detail panel ── -->
                <div id="calPostPanel" class="cal-pane" style="display:none">
                    <div class="cal-postdetail" id="calPostDetail"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toasts -->
<div id="calToasts"></div>

<style>
.cal-alert{background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7;border-radius:12px;padding:12px 18px;margin-bottom:18px}
.cal-alert.bad{background:rgba(244,63,94,0.12);border-color:rgba(244,63,94,0.3);color:#fca5a5}
.cal-alert i{vertical-align:-2px;margin-right:6px}
.cal-weekdays{display:grid;grid-template-columns:repeat(7,1fr);background:#090e18;border-bottom:1px solid var(--border);text-align:center}
.cal-weekdays div{padding:12px 6px;font-size:12px;font-weight:700;color:var(--fg3);text-transform:uppercase;letter-spacing:.5px}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background-color:rgba(255,255,255,.06)}
.cal-cell{min-height:118px;background:#0d1524;padding:8px 9px;position:relative;cursor:pointer;transition:background .15s}
.cal-cell:hover{background:#141f33}
.cal-cell:focus-visible{outline:2px solid var(--accent);outline-offset:-2px}
.cal-pad{background:#090e18;opacity:.45;user-select:none;cursor:default}
.cal-today{background:rgba(200,255,0,.04);box-shadow:inset 0 0 0 1px var(--accent)}
.cal-cellhead{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
.cal-daynum{font-size:13px;font-weight:700;color:var(--fg)}
.cal-today .cal-daynum{color:var(--accent);font-weight:800}
.cal-todaybadge{font-size:10px;font-weight:800;background:var(--accent);color:#000;padding:1px 6px;border-radius:6px;text-transform:uppercase}
.cal-addhint{font-size:12px;color:var(--accent);opacity:0;transition:opacity .15s}
.cal-cell:hover .cal-addhint{opacity:1}
.cal-stack{display:flex;flex-direction:column;gap:3px}
.cal-item{border-left:3px solid;padding:3px 6px;border-radius:6px;font-size:11px;overflow:hidden;display:flex;align-items:center;gap:4px;line-height:1.5}
.cal-item .t{color:var(--fg3);font-weight:700;font-size:10px;flex-shrink:0}
.cal-item .c{color:var(--fg);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cal-item.done{opacity:.65}
.cal-item .st{margin-left:auto;font-size:9px;font-weight:800;border-radius:4px;padding:0 4px;flex-shrink:0}
.cal-note{background:rgba(251,191,36,.10);border-left:3px solid #fbbf24;padding:3px 6px;border-radius:6px;font-size:11px;overflow:hidden;display:flex;align-items:center;gap:4px;color:var(--fg);cursor:pointer}
.cal-note i{color:#fbbf24;font-size:12px;flex-shrink:0}
.cal-note .c{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cal-note.blue{background:rgba(56,189,248,.10);border-color:#38bdf8}.cal-note.blue i{color:#38bdf8}
.cal-note.pink{background:rgba(244,114,182,.10);border-color:#f472b6}.cal-note.pink i{color:#f472b6}
.cal-note.lime{background:rgba(200,255,0,.10);border-color:#c8ff00}.cal-note.lime i{color:#c8ff00}
.cal-cell.drag-over{box-shadow:inset 0 0 0 2px var(--accent);background:rgba(200,255,0,.05)}
@media (max-width:768px){
  .cal-cell{min-height:72px;padding:5px 6px}
  .cal-item{font-size:9.5px;padding:2px 4px}
  .cal-item .c{display:none}
  .cal-note .c{display:none}
  .cal-weekdays div{font-size:10px;padding:8px 2px}
}
/* Modal */
.cal-modal{background:#0d1524;border:1px solid rgba(255,255,255,.12);border-radius:20px;box-shadow:0 25px 60px rgba(0,0,0,.85);color:#f8fafc;overflow:hidden}
.cal-modalhead{background:#090e18;border-bottom:1px solid rgba(255,255,255,.08);padding:20px 24px}
.cal-modalicon{width:40px;height:40px;border-radius:12px;background:rgba(200,255,0,.12);display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:22px}
.cal-modalbody{padding:22px 24px}
.cal-modepick{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px}
.cal-mode{border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.03);color:var(--fg2);border-radius:14px;padding:14px 10px;font-size:.8rem;cursor:pointer;text-align:center;transition:.15s}
.cal-mode i{font-size:1.3rem;display:block;margin-bottom:6px;color:var(--accent)}
.cal-mode b{display:block;color:#fff;font-size:.82rem;margin-bottom:2px}
.cal-mode span{font-size:.68rem;color:#94a3b8}
.cal-mode:hover{border-color:rgba(200,255,0,.4);background:rgba(200,255,0,.05)}
@media (max-width:576px){.cal-modepick{grid-template-columns:1fr}.cal-mode{display:flex;align-items:center;text-align:left;gap:12px}.cal-mode i{margin:0}}
.cal-lbl{display:block;font-size:12.5px;font-weight:700;color:#f8fafc;margin-bottom:6px}
.cal-in{width:100%;background:#141f33;border:1px solid rgba(255,255,255,.12);color:#fff;border-radius:10px;padding:10px 12px;font-size:.86rem;outline:none}
.cal-in:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(200,255,0,.12)}
textarea.cal-in{resize:vertical;min-height:84px;line-height:1.5}
.cal-btn{border:0;border-radius:999px;padding:10px 20px;font-size:.84rem;font-weight:800;cursor:pointer;transition:.15s;display:inline-flex;align-items:center;gap:7px}
.cal-btn.pri{background:linear-gradient(135deg,var(--accent),#8be100);color:#08101f}
.cal-btn.pri:hover{filter:brightness(1.06)}
.cal-btn.ghost{background:transparent;border:1px solid rgba(255,255,255,.15);color:#cbd5e1}
.cal-btn.danger{background:transparent;border:1px solid rgba(244,63,94,.35);color:#fca5a5}
.cal-btn:disabled{opacity:.55;cursor:not-allowed}
.cal-publishnote{display:flex;gap:10px;background:rgba(56,189,248,.07);border:1px solid rgba(56,189,248,.25);border-radius:12px;padding:12px 14px;font-size:.78rem;color:#93c5fd;margin-bottom:16px;line-height:1.5}
.cal-publishnote i{font-size:1rem;flex-shrink:0;margin-top:1px}
.cal-colors{display:flex;gap:10px}
.cal-cdot{width:26px;height:26px;border-radius:50%;border:2px solid transparent;cursor:pointer;transition:.15s}
.cal-cdot.on{border-color:#fff;transform:scale(1.12)}
.cal-postdetail{background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:16px}
.cal-postdetail h6{color:#fff;font-weight:800;font-size:.92rem;margin-bottom:10px}
.cal-postdetail .cap{color:var(--fg2);font-size:.84rem;line-height:1.55;background:#080c14;border-radius:10px;padding:12px;max-height:180px;overflow:auto;white-space:pre-wrap;margin-bottom:12px}
#calToasts{position:fixed;right:18px;bottom:18px;z-index:9999;display:flex;flex-direction:column;gap:9px;max-width:340px}
.cal-toast{border-radius:12px;padding:12px 14px;font-size:.8rem;font-weight:600;color:#fff;background:#101a2c;border:1px solid rgba(200,255,0,.4);box-shadow:0 14px 40px rgba(0,0,0,.5);animation:calIn .25s ease}
.cal-toast.err{border-color:rgba(244,63,94,.45)}
@keyframes calIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
</style>

<script>
/* ── Client-side calendar state ─────────────────────────────── */
const CAL = {
  posts: JSON.parse(document.getElementById('calGrid').dataset.posts || '[]'),
  notes: JSON.parse(document.getElementById('calGrid').dataset.notes || '[]'),
  csrf: document.querySelector('input[name="csrf_token"]')?.value || '',
  modalDate: '',
  noteColor: 'lime',
  editingNoteId: null,
};

const PLATFORM_META = {
  meta:{icon:'facebook-logo',color:'#1877F2',label:'Meta'}, instagram:{icon:'instagram-logo',color:'#E4405F',label:'IG'},
  tiktok:{icon:'tiktok-logo',color:'#00f2fe',label:'TikTok'}, twitter:{icon:'x-logo',color:'#f8fafc',label:'X'},
  linkedin:{icon:'linkedin-logo',color:'#0A66C2',label:'LinkedIn'},
};
const NOTE_COLORS = { lime:'#c8ff00', blue:'#38bdf8', pink:'#f472b6', amber:'#fbbf24' };

function renderStacks() {
  document.querySelectorAll('.cal-cell[data-date]').forEach(cell => {
    const stack = cell.querySelector('[data-stack]');
    if (!stack) return;
    stack.innerHTML = '';
    const date = cell.dataset.date;

    CAL.posts.filter(p => p.date === date).forEach(p => {
      const m = PLATFORM_META[p.platform] || {icon:'broadcast',color:'#fff',label:p.platform};
      const el = document.createElement('div');
      el.className = 'cal-item' + (p.status !== 'scheduled' ? ' done' : '');
      el.style.borderColor = m.color;
      el.style.background = m.color + '22';
      el.draggable = p.status === 'scheduled';
      el.dataset.postId = p.id;
      el.title = (p.status === 'published' ? 'Published · ' : p.status === 'failed' ? 'Failed · ' : '') + p.caption;
      el.innerHTML =
        '<i class="ph ph-' + m.icon + '" style="color:' + m.color + ';font-size:12px;flex-shrink:0"></i>' +
        '<span class="t">' + p.time + '</span>' +
        '<span class="c">' + escapeHtml(p.caption) + '</span>' +
        (p.status !== 'scheduled' ? '<span class="st" style="background:' + (p.status === 'published' ? 'rgba(74,222,128,.2);color:#4ade80' : 'rgba(248,113,113,.2);color:#f87171') + '">' + p.status + '</span>' : '');
      el.addEventListener('click', ev => { ev.stopPropagation(); openPostModal(p); });
      stack.appendChild(el);
    });

    CAL.notes.filter(n => n.date === date).forEach(n => {
      const el = document.createElement('div');
      el.className = 'cal-note ' + (n.color || 'lime');
      el.title = n.note;
      el.innerHTML = '<i class="ph ph-sticker"></i><span class="c">' + escapeHtml(truncate(n.note, 26)) + '</span>';
      el.addEventListener('click', ev => { ev.stopPropagation(); openNoteModal(date, n); });
      stack.appendChild(el);
    });
  });
  setupDragAndDrop();
}

function escapeHtml(s){ const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
function truncate(s, n){ s = String(s ?? ''); return s.length > n ? s.slice(0, n) + '…' : s; }

/* ── AJAX helper ── */
async function calApi(action, fields = {}, fileInput = null) {
  const fd = new FormData();
  fd.append('action', action);
  Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
  if (fileInput && fileInput.files && fileInput.files[0]) {
    fd.append('media', fileInput.files[0]);
  }
  const res = await fetch('<?= BASE_URL ?>/modules/calendar_api.php', {
    method: 'POST',
    headers: { 'X-CSRF-Token': CAL.csrf },
    body: fd,
    credentials: 'same-origin',
  });
  let data = {};
  try { data = await res.json(); } catch (e) { throw new Error('Network error — please try again.'); }
  if (!res.ok && !data.error) throw new Error('Request failed (' + res.status + ').');
  return data;
}

function calToast(msg, isErr) {
  const wrap = document.createElement('div');
  wrap.className = 'cal-toast' + (isErr ? ' err' : '');
  wrap.textContent = msg;
  document.getElementById('calToasts').appendChild(wrap);
  setTimeout(() => { wrap.style.opacity = '0'; wrap.style.transition = 'opacity .3s'; setTimeout(() => wrap.remove(), 320); }, 4200);
}

/* ── Modal orchestration ── */
let calModalInstance = null;
function showCalModal() {
  calModalInstance = calModalInstance || new bootstrap.Modal(document.getElementById('calModal'));
  calModalInstance.show();
}

function openDayModal(dateStr, mode) {
  CAL.modalDate = dateStr;
  CAL.editingNoteId = null;
  document.getElementById('calModalDate').value = dateStr;
  document.getElementById('calModalTitle').textContent = 'New Post';
  document.getElementById('calModalSub').textContent = prettyDate(dateStr);
  document.getElementById('calModePick').style.display = 'grid';
  ['calScheduleForm','calPublishForm','calNoteForm','calPostPanel'].forEach(id => document.getElementById(id).style.display = 'none');
  document.getElementById('calScheduledAt').value = dateStr + 'T12:00';
  if (mode) switchCalMode(mode); else showCalModal();
}

function switchCalMode(mode) {
  document.getElementById('calModePick').style.display = 'grid';
  ['calScheduleForm','calPublishForm','calNoteForm','calPostPanel'].forEach(id => document.getElementById(id).style.display = 'none');
  const map = { schedule:'calScheduleForm', publish:'calPublishForm', note:'calNoteForm' };
  const pane = document.getElementById(map[mode]);
  if (pane) pane.style.display = 'block';
  if (mode === 'note') {
    document.getElementById('calNoteDateLbl').textContent = prettyDate(CAL.modalDate);
    document.getElementById('calNoteText').value = '';
    CAL.editingNoteId = null;
    document.getElementById('calNoteDelete').style.display = 'none';
    document.getElementById('calNoteSave').innerHTML = '<i class="ph ph-sticker"></i> Save Note';
  }
}

function onCellClick(e, dateStr) {
  if (e.target.closest('.cal-item') || e.target.closest('.cal-note')) return;
  openDayModal(dateStr, null);
}

function prettyDate(iso) {
  const d = new Date(iso + 'T12:00:00');
  return isNaN(d) ? iso : d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
}

/* ── Schedule ── */
document.getElementById('calScheduleForm').addEventListener('submit', async e => {
  e.preventDefault();
  const btn = document.getElementById('calScheduleBtn');
  btn.disabled = true; btn.innerHTML = '<i class="ph ph-circle-notch" style="animation:spin 1s linear infinite"></i> Scheduling…';
  try {
    const data = await calApi('schedule', {
      platform: document.getElementById('calPlatform').value,
      caption: document.getElementById('calCaption').value,
      scheduled_at: document.getElementById('calScheduledAt').value,
      reminder_count: document.getElementById('calReminders').value,
    }, document.getElementById('calMedia'));
    if (!data.success) throw new Error(data.error || 'Scheduling failed.');
    CAL.posts.push({
      id: data.post_id,
      date: document.getElementById('calScheduledAt').value.slice(0, 10),
      time: document.getElementById('calScheduledAt').value.slice(11, 16),
      caption: truncate(document.getElementById('calCaption').value, 60),
      platform: document.getElementById('calPlatform').value,
      status: 'scheduled',
      media: 'text',
      bundle_post_id: '',
      reminders: parseInt(document.getElementById('calReminders').value, 10) || 0,
    });
    renderStacks();
    calToast(data.message || 'Post scheduled.');
    calModalInstance.hide();
  } catch (err) { calToast(err.message, true); }
  finally { btn.disabled = false; btn.innerHTML = '<i class="ph ph-calendar-check"></i> Schedule Post'; }
});

/* ── Post now ── */
document.getElementById('calPublishForm').addEventListener('submit', async e => {
  e.preventDefault();
  const btn = document.getElementById('calPublishBtn');
  btn.disabled = true; btn.innerHTML = '<i class="ph ph-circle-notch" style="animation:spin 1s linear infinite"></i> Publishing…';
  try {
    const data = await calApi('publish_now', {
      platform: document.getElementById('calPubPlatform').value,
      caption: document.getElementById('calPubCaption').value,
    }, document.getElementById('calPubMedia'));
    if (!data.success) throw new Error(data.error || 'Publish failed.');
    const dateStr = CAL.modalDate;
    CAL.posts.push({
      id: data.post_id, date: dateStr, time: new Date().toTimeString().slice(0,5),
      caption: truncate(document.getElementById('calPubCaption').value, 60),
      platform: document.getElementById('calPubPlatform').value,
      status: 'scheduled', media: 'text', bundle_post_id: '', reminders: 0,
    });
    renderStacks();
    calToast(data.message || 'Publish queued.');
    calModalInstance.hide();
  } catch (err) { calToast(err.message, true); }
  finally { btn.disabled = false; btn.innerHTML = '<i class="ph ph-paper-plane-tilt"></i> Post Now'; }
});

/* ── Notes ── */
function openNoteModal(dateStr, note) {
  CAL.modalDate = dateStr;
  CAL.editingNoteId = note ? note.id : null;
  document.getElementById('calModalDate').value = dateStr;
  document.getElementById('calModalTitle').textContent = 'Day Note';
  document.getElementById('calModalSub').textContent = prettyDate(dateStr);
  document.getElementById('calModePick').style.display = 'none';
  ['calScheduleForm','calPublishForm','calPostPanel'].forEach(id => document.getElementById(id).style.display = 'none');
  const form = document.getElementById('calNoteForm');
  form.style.display = 'block';
  document.getElementById('calNoteText').value = note ? note.note : '';
  document.getElementById('calNoteDateLbl').textContent = prettyDate(dateStr);
  document.getElementById('calNoteDelete').style.display = note ? 'inline-flex' : 'none';
  document.getElementById('calNoteSave').innerHTML = note ? '<i class="ph ph-check"></i> Update Note' : '<i class="ph ph-sticker"></i> Save Note';
  CAL.noteColor = note ? (note.color || 'lime') : 'lime';
  document.querySelectorAll('#calNoteColors .cal-cdot').forEach(b => b.classList.toggle('on', b.dataset.color === CAL.noteColor));
  showCalModal();
}

document.querySelectorAll('#calNoteColors .cal-cdot').forEach(b => {
  b.addEventListener('click', () => {
    document.querySelectorAll('#calNoteColors .cal-cdot').forEach(x => x.classList.remove('on'));
    b.classList.add('on');
    CAL.noteColor = b.dataset.color;
  });
});

document.getElementById('calNoteForm').addEventListener('submit', async e => {
  e.preventDefault();
  const text = document.getElementById('calNoteText').value;
  if (!text.trim()) { calToast('Write something in the note first.', true); return; }
  const btn = document.getElementById('calNoteSave');
  btn.disabled = true;
  try {
    const data = CAL.editingNoteId
      ? await calApi('update_note', { note_id: CAL.editingNoteId, note: text, color: CAL.noteColor, note_date: CAL.modalDate })
      : await calApi('add_note', { note: text, color: CAL.noteColor, note_date: CAL.modalDate });
    if (!data.success) throw new Error(data.error || 'Could not save the note.');
    if (CAL.editingNoteId) {
      const n = CAL.notes.find(x => x.id === CAL.editingNoteId);
      if (n) { n.note = text; n.color = CAL.noteColor; }
    } else {
      CAL.notes.push({ id: data.note_id, date: CAL.modalDate, note: text, color: CAL.noteColor });
    }
    renderStacks();
    calToast(data.message || 'Note saved.');
    calModalInstance.hide();
  } catch (err) { calToast(err.message, true); }
  finally { btn.disabled = false; }
});

document.getElementById('calNoteDelete').addEventListener('click', async () => {
  if (!CAL.editingNoteId || !confirm('Delete this note?')) return;
  try {
    const data = await calApi('delete_note', { note_id: CAL.editingNoteId });
    if (!data.success) throw new Error(data.error || 'Could not delete the note.');
    CAL.notes = CAL.notes.filter(n => n.id !== CAL.editingNoteId);
    renderStacks();
    calToast(data.message || 'Note removed.');
    calModalInstance.hide();
  } catch (err) { calToast(err.message, true); }
});

/* ── Post detail (click an event) ── */
function openPostModal(p) {
  document.getElementById('calModalTitle').textContent = 'Scheduled Post';
  document.getElementById('calModalSub').textContent = prettyDate(p.date) + ' at ' + p.time;
  document.getElementById('calModePick').style.display = 'none';
  ['calScheduleForm','calPublishForm','calNoteForm'].forEach(id => document.getElementById(id).style.display = 'none');
  const m = PLATFORM_META[p.platform] || {label: p.platform};
  const done = p.status !== 'scheduled';
  let html = '<h6><i class="ph ph-' + (m.icon || 'broadcast') + '" style="color:' + (m.color || '#fff') + '"></i> ' +
      (m.label || p.platform) + ' · <span style="text-transform:capitalize">' + p.status + '</span></h6>' +
    '<div class="cap">' + escapeHtml(p.caption) + '</div>' +
    (p.bundle_post_id ? '<div style="font-size:.72rem;color:#94a3b8;margin-bottom:12px"><i class="ph ph-link-simple"></i> Network ref: ' + escapeHtml(p.bundle_post_id) + '</div>' : '');

  if (!done) {
    html += '<div class="mb-3"><label class="cal-lbl">Reminders before this goes out</label>' +
      '<select id="calPostReminders" class="cal-in" style="max-width:260px">' +
      [0,1,2,3,4,5].map(n =>
        '<option value="' + n + '"' + (n === (p.reminders ?? 3) ? ' selected' : '') + '>' +
        (n === 0 ? 'No reminders' : n + ' reminder' + (n === 1 ? '' : 's')) + '</option>').join('') +
      '</select><div style="font-size:.7rem;color:#94a3b8;margin-top:5px">Ladder: 1 week → 24 h → 3 h → 1 h → 15 min</div></div>' +
      '<div class="d-flex gap-2 flex-wrap">' +
      '<button type="button" class="cal-btn pri" onclick="postNowFromModal(' + p.id + ')"><i class="ph ph-paper-plane-tilt"></i> Post Now</button>' +
      '<button type="button" class="cal-btn danger" onclick="cancelFromModal(' + p.id + ')"><i class="ph ph-trash"></i> Cancel Post</button>' +
      '<button type="button" class="cal-btn ghost" id="calSaveReminders"><i class="ph ph-bell"></i> Save Reminders</button></div>';
  } else {
    html += '<div style="font-size:.78rem;color:#94a3b8">This post is ' + p.status + ' — nothing left to do.</div>';
  }
  document.getElementById('calPostDetail').innerHTML = html;
  document.getElementById('calPostPanel').style.display = 'block';
  const saveBtn = document.getElementById('calSaveReminders');
  if (saveBtn) saveBtn.addEventListener('click', async () => {
    const count = document.getElementById('calPostReminders').value;
    try {
      const data = await calApi('set_reminders', { post_id: p.id, reminder_count: count });
      if (!data.success) throw new Error(data.error || 'Could not update reminders.');
      p.reminders = parseInt(count, 10);
      calToast(data.message);
    } catch (err) { calToast(err.message, true); }
  });
  showCalModal();
}

async function postNowFromModal(postId) {
  try {
    const data = await calApi('publish_now', { post_id: postId });
    if (!data.success) throw new Error(data.error || 'Publish failed.');
    const p = CAL.posts.find(x => x.id === postId);
    if (p) p.status = 'scheduled'; // worker flips to published; show queued state
    calToast(data.message || 'Publishing now.');
    renderStacks();
    calModalInstance.hide();
  } catch (err) { calToast(err.message, true); }
}

async function cancelFromModal(postId) {
  if (!confirm('Cancel this scheduled post?')) return;
  try {
    const data = await calApi('cancel_post', { post_id: postId });
    if (!data.success) throw new Error(data.error || 'Could not cancel.');
    CAL.posts = CAL.posts.filter(x => x.id !== postId);
    renderStacks();
    calToast(data.message || 'Post cancelled.');
    calModalInstance.hide();
  } catch (err) { calToast(err.message, true); }
}

/* ── Drag to reschedule ── */
function setupDragAndDrop() {
  document.querySelectorAll('.cal-item[draggable="true"]').forEach(el => {
    el.addEventListener('dragstart', e => e.dataTransfer.setData('text/plain', el.dataset.postId));
  });
  document.querySelectorAll('.cal-cell[data-date]').forEach(cell => {
    cell.addEventListener('dragover', e => { e.preventDefault(); cell.classList.add('drag-over'); });
    cell.addEventListener('dragleave', () => cell.classList.remove('drag-over'));
    cell.addEventListener('drop', async e => {
      e.preventDefault();
      cell.classList.remove('drag-over');
      const postId = e.dataTransfer.getData('text/plain');
      const newDate = cell.dataset.date;
      if (!postId || !newDate) return;
      const p = CAL.posts.find(x => String(x.id) === String(postId));
      const newTime = newDate + ' ' + (p ? p.time : '12:00') + ':00';
      try {
        const res = await fetch('<?= BASE_URL ?>/modules/calendar_reschedule.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CAL.csrf },
          body: JSON.stringify({ post_id: parseInt(postId, 10), new_time: newTime }),
        });
        const data = await res.json();
        if (res.ok && !data.error) {
          if (p) p.date = newDate;
          renderStacks();
          calToast('Post rescheduled to ' + prettyDate(newDate) + '.');
        } else {
          calToast('Failed to reschedule: ' + (data.error || 'Unknown error'), true);
        }
      } catch (err) { calToast('Network error while rescheduling.', true); }
    });
  });
}

document.addEventListener('DOMContentLoaded', renderStacks);
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
