<?php
declare(strict_types=1);

$pageTitle = 'Content Calendar & Planner — Adsbooster.net';
$pageDescription = 'Interactive visual calendar for scheduling, planning, and organizing multi-network social posts and campaigns.';
require_once __DIR__ . '/includes/app_header.php';
require_once __DIR__ . '/modules/social_post_engine.php';

$userId = current_user_id();
$message = '';

// Handle schedule post from calendar modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'calendar_schedule') {
    if (!csrf_verify()) {
        $message = '<div class="alert alert-danger" style="background:rgba(244,63,94,0.15);border:1px solid rgba(244,63,94,0.3);color:#fca5a5;border-radius:12px;padding:14px 18px;margin-bottom:20px;">Invalid security token.</div>';
    } else {
        $caption     = trim($_POST['caption'] ?? '');
        $platform    = trim($_POST['platform'] ?? 'meta');
        $mediaType   = trim($_POST['media_type'] ?? 'text');
        $scheduledAt = trim($_POST['scheduled_at'] ?? '');

        if ($caption === '') {
            $message = '<div class="alert alert-danger" style="background:rgba(244,63,94,0.15);border:1px solid rgba(244,63,94,0.3);color:#fca5a5;border-radius:12px;padding:14px 18px;margin-bottom:20px;">Caption content is required.</div>';
        } elseif ($scheduledAt === '') {
            $message = '<div class="alert alert-danger" style="background:rgba(244,63,94,0.15);border:1px solid rgba(244,63,94,0.3);color:#fca5a5;border-radius:12px;padding:14px 18px;margin-bottom:20px;">Please specify a scheduled date & time.</div>';
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
                $message = '<div class="alert alert-success" style="background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7;border-radius:12px;padding:14px 18px;margin-bottom:20px;"><i class="ph ph-calendar-check" style="vertical-align:-2px;margin-right:6px"></i> Post successfully scheduled for <b>' . htmlspecialchars(date('M d, Y H:i', strtotime($scheduledAt))) . '</b>!</div>';
            } catch (\Throwable $e) {
                $message = '<div class="alert alert-danger" style="background:rgba(244,63,94,0.15);border:1px solid rgba(244,63,94,0.3);color:#fca5a5;border-radius:12px;padding:14px 18px;margin-bottom:20px;">' . htmlspecialchars($e->getMessage()) . '</div>';
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
    "SELECT id, caption, media_type, scheduled_time, status, platform, bundle_post_id FROM scheduled_posts WHERE user_id = ? AND scheduled_time BETWEEN ? AND ? ORDER BY scheduled_time ASC",
    [$userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']
);

$campaigns = db()->fetchAll(
    "SELECT id, campaign_name, campaign_type, created_at, budget_amount, status FROM ad_campaigns WHERE user_id = ? AND created_at BETWEEN ? AND ? ORDER BY created_at ASC",
    [$userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']
);

$platformMeta = [
    'meta'            => ['icon' => 'facebook-logo', 'color' => '#1877F2', 'bg' => 'rgba(24,119,242,0.15)', 'label' => 'Meta'],
    'instagram'       => ['icon' => 'instagram-logo', 'color' => '#E4405F', 'bg' => 'rgba(228,64,95,0.15)', 'label' => 'Instagram'],
    'tiktok'          => ['icon' => 'tiktok-logo', 'color' => '#00f2fe', 'bg' => 'rgba(0,242,254,0.15)', 'label' => 'TikTok'],
    'google_ads'      => ['icon' => 'google-logo', 'color' => '#4285F4', 'bg' => 'rgba(66,133,244,0.15)', 'label' => 'Google Ads'],
    'snapchat'        => ['icon' => 'ghost', 'color' => '#FFFC00', 'bg' => 'rgba(255,252,0,0.15)', 'label' => 'Snapchat'],
    'combo_pack'      => ['icon' => 'sparkle', 'color' => 'var(--accent)', 'bg' => 'rgba(200,255,0,0.15)', 'label' => 'Combo Pack'],
    'google_calendar' => ['icon' => 'calendar', 'color' => '#34A853', 'bg' => 'rgba(52,168,83,0.15)', 'label' => 'Calendar'],
];
?>

<div class="w-100">
    <?= $message ?>

    <!-- Calendar Header -->
    <div class="page-header" style="margin-bottom:28px">
        <div>
            <h1 class="page-title">Content Calendar</h1>
            <p class="page-subtitle">Schedule, plan, and organize posts and multi-network ad campaigns. Click any date to schedule instantly.</p>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <!-- Navigation -->
            <div style="display:inline-flex;align-items:center;background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:4px;">
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

            <button class="btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#scheduleModal" onclick="openScheduleModal('<?= date('Y-m-d') ?>')">
                <i class="ph ph-plus-circle"></i> + Schedule Post
            </button>
        </div>
    </div>

    <!-- Calendar Container -->
    <div class="card-dark" style="padding:0;overflow:hidden;border:1px solid var(--border2);border-radius:16px;">
        <!-- Weekday Headers -->
        <div style="display:grid;grid-template-columns:repeat(7,1fr);background:#090e18;border-bottom:1px solid var(--border);text-align:center;">
            <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $w): ?>
                <div style="padding:12px 6px;font-size:12px;font-weight:700;color:var(--fg3);text-transform:uppercase;letter-spacing:0.5px;">
                    <?= $w ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Month Grid -->
        <div style="display:grid;grid-template-columns:repeat(7,1fr);background:rgba(255,255,255,0.02);gap:1px;background-color:rgba(255,255,255,0.06);">
            <?php
            // Previous month trailing days
            for ($i = $firstDayOfWeek - 1; $i >= 0; $i--):
                $prevDayNum = $daysInPrevMonth - $i;
            ?>
                <div style="min-height:125px;background:#090e18;padding:8px;opacity:0.4;user-select:none;">
                    <span style="font-size:12px;font-weight:600;color:var(--fg3);"><?= $prevDayNum ?></span>
                </div>
            <?php endfor; ?>

            <?php
            // Current month days
            for ($d = 1; $d <= $daysInMonth; $d++):
                $curDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $isToday = $curDate === date('Y-m-d');
            ?>
                <div class="calendar-day-cell"
                     data-date="<?= $curDate ?>"
                     onclick="onCellClick(event, '<?= $curDate ?>')"
                     style="min-height:125px;background:<?= $isToday ? 'rgba(200,255,0,0.03)' : '#0d1524' ?>;padding:8px 10px;position:relative;cursor:pointer;transition:background 0.15s;<?= $isToday ? 'border:1px solid var(--accent);border-radius:4px;box-shadow:inset 0 0 12px rgba(200,255,0,0.1);' : '' ?>">
                    
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <span style="font-size:13px;font-weight:<?= $isToday ? '800' : '600' ?>;color:<?= $isToday ? 'var(--accent)' : 'var(--fg)' ?>;">
                            <?= $d ?>
                        </span>
                        <?php if ($isToday): ?>
                            <span style="font-size:10px;font-weight:800;background:var(--accent);color:#000;padding:1px 6px;border-radius:4px;text-transform:uppercase;">Today</span>
                        <?php else: ?>
                            <span class="day-add-hint" style="font-size:11px;color:var(--fg3);opacity:0;transition:opacity 0.15s;">+</span>
                        <?php endif; ?>
                    </div>

                    <!-- Events Stack -->
                    <div style="display:flex;flex-direction:column;gap:4px;">
                        <?php foreach ($posts as $post):
                            if (date('Y-m-d', strtotime($post['scheduled_time'])) !== $curDate) continue;
                            $meta = $platformMeta[$post['platform']] ?? ['icon' => 'broadcast', 'color' => '#fff', 'bg' => 'rgba(255,255,255,0.1)', 'label' => ucfirst($post['platform'])];
                            $timeStr = date('H:i', strtotime($post['scheduled_time']));
                        ?>
                            <div class="calendar-event-item"
                                 draggable="true"
                                 data-post-id="<?= $post['id'] ?>"
                                 title="<?= htmlspecialchars(strip_tags($post['caption'])) ?>"
                                 style="background:<?= $meta['bg'] ?>;border-left:3px solid <?= $meta['color'] ?>;padding:4px 6px;border-radius:4px;font-size:11px;color:#fff;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;display:flex;align-items:center;gap:4px;cursor:grab;">
                                <i class="ph ph-<?= $meta['icon'] ?>" style="color:<?= $meta['color'] ?>;font-size:12px;flex-shrink:0;"></i>
                                <span style="font-weight:700;color:<?= $meta['color'] ?>;font-size:10px;"><?= $timeStr ?></span>
                                <span style="color:var(--fg);overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars(substr(strip_tags($post['caption']), 0, 24)) ?></span>
                            </div>
                        <?php endforeach; ?>

                        <?php foreach ($campaigns as $camp):
                            if (date('Y-m-d', strtotime($camp['created_at'])) !== $curDate) continue;
                            $meta = $platformMeta[$camp['campaign_type']] ?? ['icon' => 'megaphone', 'color' => '#c8ff00', 'bg' => 'rgba(200,255,0,0.12)', 'label' => ucfirst($camp['campaign_type'])];
                        ?>
                            <div style="background:<?= $meta['bg'] ?>;border-left:3px solid <?= $meta['color'] ?>;padding:4px 6px;border-radius:4px;font-size:11px;color:#fff;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;display:flex;align-items:center;gap:4px;">
                                <i class="ph ph-megaphone" style="color:<?= $meta['color'] ?>;font-size:12px;flex-shrink:0;"></i>
                                <span style="font-weight:700;color:<?= $meta['color'] ?>;font-size:10px;">AD</span>
                                <span style="color:var(--fg);overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($camp['campaign_name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endfor; ?>

            <?php
            // Next month leading days to complete week grid
            $totalCells = $firstDayOfWeek + $daysInMonth;
            $remaining = (7 - ($totalCells % 7)) % 7;
            for ($j = 1; $j <= $remaining; $j++):
            ?>
                <div style="min-height:125px;background:#090e18;padding:8px;opacity:0.4;user-select:none;">
                    <span style="font-size:12px;font-weight:600;color:var(--fg3);"><?= $j ?></span>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Legend -->
    <div class="card-dark mt-4" style="padding:16px 22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;">
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <span style="font-size:13px;font-weight:700;color:var(--fg);">Channels Legend:</span>
            <?php foreach ($platformMeta as $key => $p): ?>
                <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--fg2);">
                    <span style="width:10px;height:10px;border-radius:50%;background:<?= $p['color'] ?>;display:inline-block;"></span>
                    <span><?= $p['label'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="font-size:12px;color:var(--fg3);">
            <i class="ph ph-cursor"></i> Click any day to create an event • Drag events to reschedule
        </div>
    </div>
</div>

<!-- Schedule Modal with CKEditor 5 -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background:#0d1524;border:1px solid rgba(255,255,255,0.12);border-radius:18px;box-shadow:0 25px 60px rgba(0,0,0,0.85);color:#f8fafc;overflow:hidden;">
            <form method="POST" enctype="multipart/form-data" id="calendarScheduleForm">
                <div class="modal-header" style="background:#090e18;border-bottom:1px solid rgba(255,255,255,0.08);padding:20px 24px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:40px;height:40px;border-radius:10px;background:rgba(200,255,0,0.12);display:flex;align-items:center;justify-content:center;color:var(--accent);">
                            <i class="ph ph-calendar-plus" style="font-size:22px;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title" id="scheduleModalLabel" style="font-size:18px;font-weight:700;color:#fff;margin:0;">Schedule Social Post</h5>
                            <p style="font-size:12px;color:#94a3b8;margin:2px 0 0;">Create &amp; schedule rich media posts across your connected channels</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body" style="padding:24px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="calendar_schedule">

                    <!-- Platform & Date row -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:13px;font-weight:600;color:#f8fafc;">Target Channel <span style="color:var(--accent)">*</span></label>
                            <select name="platform" class="form-select" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;border-radius:8px;" required>
                                <option value="meta">Meta / Facebook Page</option>
                                <option value="instagram">Instagram Professional</option>
                                <option value="tiktok">TikTok for Business</option>
                                <option value="twitter">Twitter / X</option>
                                <option value="linkedin">LinkedIn</option>
                                <!-- Paid channels (Google Ads, Snapchat Ads) are owned by the
                                     Zernio gateway and are created from create-ad.php only. -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:13px;font-weight:600;color:#f8fafc;">Scheduled Date &amp; Time <span style="color:var(--accent)">*</span></label>
                            <input type="datetime-local" id="modalScheduledAt" name="scheduled_at" class="form-control" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;border-radius:8px;" required>
                        </div>
                    </div>

                    <!-- Post Caption with CKEditor 5 -->
                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;font-weight:600;color:#f8fafc;">
                            Post Caption &amp; Content (Rich Text) <span style="color:var(--accent)">*</span>
                        </label>
                        <textarea id="modalCaptionEditor" name="caption" rows="5" class="form-control" placeholder="Write your post caption, hooks, and hashtags here..."></textarea>
                    </div>

                    <!-- Media Attachment -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:13px;font-weight:600;color:#f8fafc;">Media Type</label>
                            <select name="media_type" class="form-select" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;border-radius:8px;">
                                <option value="text">Text Only</option>
                                <option value="image">Image Post</option>
                                <option value="ugc_video">UGC Video / Reel</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:13px;font-weight:600;color:#f8fafc;">Upload Creative Media</label>
                            <input type="file" name="media_upload" class="form-control" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;border-radius:8px;">
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="background:#090e18;border-top:1px solid rgba(255,255,255,0.08);padding:18px 24px;display:flex;justify-content:space-between;">
                    <button type="button" class="btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-accent" style="padding:10px 24px;">
                        <i class="ph ph-paper-plane-tilt"></i> Schedule to Calendar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.calendar-day-cell:hover {
    background: #141f33 !important;
}
.calendar-day-cell:hover .day-add-hint {
    opacity: 1 !important;
    color: var(--accent) !important;
}
.calendar-day-cell.drag-over {
    border: 2px dashed var(--accent) !important;
    background: rgba(200,255,0,0.06) !important;
}
</style>

<script>
let calendarCKEditor = null;

// Initialize CKEditor 5 on modal caption
document.addEventListener('DOMContentLoaded', function() {
    if (typeof ClassicEditor !== 'undefined') {
        ClassicEditor
            .create(document.querySelector('#modalCaptionEditor'), {
                toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'undo', 'redo']
            })
            .then(editor => {
                calendarCKEditor = editor;
            })
            .catch(error => {
                console.warn('CKEditor 5 init warning:', error);
            });
    }

    // Drag and drop reschedule
    setupDragAndDrop();
});

function openScheduleModal(dateStr) {
    const timeStr = dateStr + 'T12:00';
    document.getElementById('modalScheduledAt').value = timeStr;
    if (calendarCKEditor) {
        calendarCKEditor.setData('');
    }
}

function onCellClick(e, dateStr) {
    if (e.target.closest('.calendar-event-item')) return;
    openScheduleModal(dateStr);
    const modal = new bootstrap.Modal(document.getElementById('scheduleModal'));
    modal.show();
}

function setupDragAndDrop() {
    document.querySelectorAll('.calendar-event-item').forEach(el => {
        el.addEventListener('dragstart', e => {
            e.dataTransfer.setData('text/plain', el.dataset.postId);
        });
    });

    document.querySelectorAll('.calendar-day-cell').forEach(cell => {
        cell.addEventListener('dragover', e => {
            e.preventDefault();
            cell.classList.add('drag-over');
        });
        cell.addEventListener('dragleave', () => {
            cell.classList.remove('drag-over');
        });
        cell.addEventListener('drop', e => {
            e.preventDefault();
            cell.classList.remove('drag-over');
            const postId = e.dataTransfer.getData('text/plain');
            const newDate = cell.dataset.date;
            if (postId && newDate) {
                reschedulePost(postId, newDate + ' 12:00:00');
            }
        });
    });
}

function reschedulePost(postId, newTime) {
    fetch('<?= BASE_URL ?>/modules/calendar_reschedule.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            post_id: parseInt(postId, 10),
            new_time: newTime
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Failed to reschedule: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error while rescheduling.');
    });
}
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
