<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';

$userId = current_user_id();
$currentUser = current_user();

$chatUserId = (int)($_GET['user'] ?? 0);
if ($chatUserId > 0 && $chatUserId === $userId) {
    $chatUserId = 1;
}

$blockedByMe = array_column(
    db()->fetchAll("SELECT blocked_id FROM dating_blocks WHERE blocker_id = ?", [$userId]),
    'blocked_id'
);
$blockedByThem = array_column(
    db()->fetchAll("SELECT blocker_id FROM dating_blocks WHERE blocked_id = ?", [$userId]),
    'blocker_id'
);
$allBlocked = array_unique(array_merge($blockedByMe, $blockedByThem));

const AI_BOT_ID = 99999;

// Ensure AI Bot user exists in DB
$aiUser = db()->fetchOne("SELECT id, full_name, email FROM users WHERE id = ?", [AI_BOT_ID]);
if (!$aiUser) {
    try {
        db()->query(
            "INSERT INTO users (id, full_name, email, password, role, status, created_at) 
             VALUES (?, 'Adsbooster AI Consultant', 'ai@adsbooster.net', 'ai_bot_protected', 'admin', 'active', NOW()) 
             ON DUPLICATE KEY UPDATE full_name = 'Adsbooster AI Consultant'",
            [AI_BOT_ID]
        );
    } catch (\Throwable $e) {}
    $aiUser = ['id' => AI_BOT_ID, 'full_name' => 'Adsbooster AI Consultant', 'email' => 'ai@adsbooster.net', 'role' => 'admin'];
}

// Pre-seed a default welcome conversation if user has zero conversations
$totalConvs = (int)(db()->fetchOne("SELECT COUNT(*) as cnt FROM p2p_conversations WHERE user_a = ? OR user_b = ?", [$userId, $userId])['cnt'] ?? 0);
if ($totalConvs === 0) {
    // Seed AI Conversation as first chat
    $convId = db()->insert('p2p_conversations', [
        'user_a'     => min($userId, AI_BOT_ID),
        'user_b'     => max($userId, AI_BOT_ID),
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    db()->insert('p2p_messages', [
        'conversation_id' => $convId,
        'sender_id'       => AI_BOT_ID,
        'receiver_id'     => $userId,
        'message'         => "ðŸ‘‹ Hello {$currentUser['full_name']}! I'm your Adsbooster AI Growth Consultant. How can I assist you with your ad campaigns, copywriting, or marketplace listings today?",
        'is_read'         => 0,
        'created_at'      => date('Y-m-d H:i:s'),
    ]);
}

/**
 * Format modern AI agent messages with rich graphical representations:
 * Charts, KPI Metrics, Platform Badges, Images, Roadmap Step Cards, and Clean Typography
 */
function formatChatMessage(string $raw): string {
    $text = $raw;

    // 1. Process custom AI tags first
    // Metrics: [METRICS:Label:Val:Sub|...]
    $text = preg_replace_callback('/\[METRICS:([^\]]+)\]/i', function($m) {
        $pairs = explode('|', $m[1]);
        $cards = '';
        foreach ($pairs as $pair) {
            $parts = explode(':', trim($pair));
            if (count($parts) >= 2) {
                $lbl = htmlspecialchars(trim($parts[0]));
                $val = htmlspecialchars(trim($parts[1]));
                $sub = isset($parts[2]) ? htmlspecialchars(trim($parts[2])) : '';
                $cards .= '<div style="flex:1 1 120px;min-width:110px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:10px 14px;">' .
                            '<div style="font-size:10.5px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;margin-bottom:3px;">' . $lbl . '</div>' .
                            '<div style="font-size:18px;color:#c8ff00;font-weight:800;letter-spacing:-0.5px;line-height:1.2;">' . $val . '</div>' .
                            ($sub ? '<div style="font-size:10px;color:#38bdf8;margin-top:2px;">' . $sub . '</div>' : '') .
                          '</div>';
            }
        }
        return '<div class="d-flex flex-wrap gap-2 my-2">' . $cards . '</div>';
    }, $text);

    // Chart: [CHART:type:Title:Items]
    $text = preg_replace_callback('/\[CHART:(doughnut|pie|bar|line):([^:]+):([^\]]+)\]/i', function($m) {
        static $cIndex = 0;
        $cIndex++;
        $type = strtolower($m[1]);
        $title = htmlspecialchars(trim($m[2]));
        $items = explode(',', $m[3]);
        $labels = [];
        $values = [];
        foreach ($items as $item) {
            $parts = explode(':', trim($item));
            if (count($parts) === 2) {
                $labels[] = trim($parts[0]);
                $values[] = (float)trim($parts[1]);
            }
        }
        $canvasId = 'aiChart_' . $cIndex . '_' . substr(md5(uniqid()), 0, 6);
        $labelsJson = htmlspecialchars(json_encode($labels), ENT_QUOTES, 'UTF-8');
        $valuesJson = htmlspecialchars(json_encode($values), ENT_QUOTES, 'UTF-8');

        return '<div class="ai-chart-widget my-3" style="background:#0a0f1d;border:1px solid rgba(255,255,255,0.1);border-radius:14px;padding:16px 18px;">' .
                 '<div class="d-flex align-items-center justify-content-between mb-2">' .
                   '<div class="d-flex align-items-center gap-2">' .
                     '<span style="width:24px;height:24px;border-radius:6px;background:rgba(200,255,0,0.15);color:#c8ff00;display:flex;align-items:center;justify-content:center;font-size:13px;"><i class="ph-bold ph-chart-pie-slice"></i></span>' .
                     '<span style="color:#fff;font-weight:700;font-size:13px;">' . $title . '</span>' .
                   '</div>' .
                   '<span style="font-size:10px;color:#94a3b8;background:rgba(255,255,255,0.06);padding:2px 7px;border-radius:100px;">Data Visual</span>' .
                 '</div>' .
                 '<div style="position:relative;height:200px;width:100%;max-width:440px;margin:0 auto;">' .
                   '<canvas id="' . $canvasId . '" class="js-ai-chart" data-type="' . $type . '" data-labels="' . $labelsJson . '" data-values="' . $valuesJson . '"></canvas>' .
                 '</div>' .
               '</div>';
    }, $text);

    // Platforms: [PLATFORMS:Name:Pct%:Desc|...]
    $text = preg_replace_callback('/\[PLATFORMS:([^\]]+)\]/i', function($m) {
        $platforms = explode('|', $m[1]);
        $html = '<div class="my-3 p-3" style="background:#0a0f1d;border:1px solid rgba(255,255,255,0.1);border-radius:14px;">' .
                '<div style="font-size:12px;font-weight:700;color:#fff;margin-bottom:10px;display:flex;align-items:center;gap:6px;">' .
                    '<i class="ph-fill ph-squares-four" style="color:#c8ff00;"></i> Channel Allocation Strategy' .
                '</div>' .
                '<div class="d-flex flex-column gap-2">';
        foreach ($platforms as $p) {
            $parts = explode(':', trim($p));
            $name = trim($parts[0] ?? '');
            $pct = trim($parts[1] ?? '');
            $desc = trim($parts[2] ?? '');

            $icon = 'ph-share-network';
            $color = '#c8ff00';
            $bg = 'rgba(200,255,0,0.15)';
            if (stripos($name, 'meta') !== false || stripos($name, 'facebook') !== false || stripos($name, 'instagram') !== false) {
                $icon = 'ph-facebook-logo';
                $color = '#1877f2';
                $bg = 'rgba(24,119,242,0.18)';
            } elseif (stripos($name, 'tiktok') !== false) {
                $icon = 'ph-tiktok-logo';
                $color = '#00f2fe';
                $bg = 'rgba(0,242,254,0.18)';
            } elseif (stripos($name, 'google') !== false || stripos($name, 'youtube') !== false) {
                $icon = 'ph-google-logo';
                $color = '#ea4335';
                $bg = 'rgba(234,67,53,0.18)';
            }

            $html .= '<div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;gap:12px;">' .
                        '<div class="d-flex align-items-center gap-3">' .
                            '<div style="width:34px;height:34px;border-radius:8px;background:' . $bg . ';color:' . $color . ';display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">' .
                                '<i class="ph-bold ' . $icon . '"></i>' .
                            '</div>' .
                            '<div>' .
                                '<div style="font-size:13px;font-weight:700;color:#fff;">' . htmlspecialchars($name) . '</div>' .
                                '<div style="font-size:11.5px;color:#94a3b8;line-height:1.3;">' . htmlspecialchars($desc) . '</div>' .
                            '</div>' .
                        '</div>' .
                        ($pct ? '<div style="text-align:right;flex-shrink:0;"><span style="font-size:14px;font-weight:800;color:' . $color . ';">' . htmlspecialchars($pct) . '</span><div style="font-size:9.5px;color:#64748b;">Allocation</div></div>' : '') .
                     '</div>';
        }
        $html .= '</div></div>';
        return $html;
    }, $text);

    // Image Graphic Widget: [IMAGE:url:caption]
    $text = preg_replace_callback('/\[IMAGE:([^:]+):([^\]]+)\]/i', function($m) {
        $url = htmlspecialchars(trim($m[1]));
        $caption = htmlspecialchars(trim($m[2]));
        return '<div class="ai-image-widget my-3" style="background:#0a0f1d;border:1px solid rgba(255,255,255,0.1);border-radius:14px;overflow:hidden;max-width:540px;">' .
                 '<img src="' . $url . '" alt="' . $caption . '" style="width:100%;height:auto;max-height:260px;object-fit:cover;display:block;" loading="lazy" />' .
                 '<div style="padding:10px 14px;font-size:11.5px;color:#94a3b8;border-top:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;gap:6px;">' .
                   '<i class="ph-bold ph-image text-accent"></i> ' . $caption .
                 '</div>' .
               '</div>';
    }, $text);

    // Steps Roadmap: [STEPS:Num:Title:Description:Tools|...]
    $text = preg_replace_callback('/\[STEPS:([^\]]+)\]/i', function($m) {
        $steps = explode('|', $m[1]);
        $html = '<div class="my-3 p-3" style="background:#0a0f1d;border:1px solid rgba(255,255,255,0.1);border-radius:14px;">' .
                '<div style="font-size:12px;font-weight:700;color:#fff;margin-bottom:12px;display:flex;align-items:center;gap:6px;">' .
                    '<i class="ph-bold ph-path" style="color:#c8ff00;"></i> Execution Roadmap' .
                '</div>' .
                '<div class="d-flex flex-column gap-2">';
        foreach ($steps as $s) {
            $parts = explode(':', trim($s));
            $num = trim($parts[0] ?? 'â€¢');
            $title = trim($parts[1] ?? '');
            $desc = trim($parts[2] ?? '');
            $tool = trim($parts[3] ?? '');

            $html .= '<div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:12px 14px;">' .
                        '<div class="d-flex align-items-center justify-content-between gap-2 mb-1">' .
                            '<div class="d-flex align-items-center gap-2">' .
                                '<span style="width:22px;height:22px;border-radius:50%;background:rgba(200,255,0,0.18);color:#c8ff00;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;">' . htmlspecialchars($num) . '</span>' .
                                '<span style="font-size:13px;font-weight:700;color:#fff;">' . htmlspecialchars($title) . '</span>' .
                            '</div>' .
                            ($tool ? '<span style="font-size:10.5px;color:#38bdf8;background:rgba(56,189,248,0.12);padding:2px 8px;border-radius:6px;border:1px solid rgba(56,189,248,0.25);">' . htmlspecialchars($tool) . '</span>' : '') .
                        '</div>' .
                        ($desc ? '<div style="font-size:12px;color:#94a3b8;line-height:1.4;margin-left:30px;">' . htmlspecialchars($desc) . '</div>' : '') .
                     '</div>';
        }
        $html .= '</div></div>';
        return $html;
    }, $text);

    // Action CTA buttons: [ACTION:Title:URL:Icon]
    $text = preg_replace_callback('/\[ACTION:([^:]+):([^:]+):([^\]]+)\]/i', function($m) {
        $title = htmlspecialchars(trim($m[1]));
        $rawUrl = trim($m[2]);
        $icon = htmlspecialchars(trim($m[3]));
        $cleanUrl = preg_replace('/\.php(\?|$)/i', '$1', $rawUrl);
        $finalUrl = (str_starts_with($cleanUrl, 'http://') || str_starts_with($cleanUrl, 'https://')) ? $cleanUrl : url($cleanUrl);
        return '<a href="' . htmlspecialchars($finalUrl) . '" class="btn btn-accent btn-sm rounded-pill fw-bold text-dark font-size-12 px-3 py-2 me-2 my-1 d-inline-flex align-items-center gap-2" style="box-shadow:0 4px 14px rgba(200,255,0,0.2);">' .
                   '<i class="' . $icon . '"></i> ' . $title .
               '</a>';
    }, $text);

    // 2. Normalize and clean newlines
    $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // 3. Auto-convert any legacy pipe tables into modern Execution Roadmap Step Cards!
    if (str_contains($text, '|')) {
        $text = preg_replace('/([^\n\|])\s*(\|[\s\w*#]+(?:\|[\s\w*#]+)+\|)/', "$1\n\n$2", $text);
        $text = preg_replace('/\|\s*\|+/', "|\n|", $text);
        $text = preg_replace('/(\|[\s\-:]+\|)\s*([^\n\|])/', "$1\n| $2", $text);

        $lines = explode("\n", $text);
        $tableCards = [];
        $nonTable = [];
        $isTable = false;

        foreach ($lines as $line) {
            $t = trim($line);
            if (str_starts_with($t, '|') && str_ends_with($t, '|')) {
                if (preg_match('/^\|[\s\-:]+(\|[\s\-:]+)+\|$/', $t)) continue;
                $cols = array_values(array_filter(array_map('trim', explode('|', trim($t, '|'))), fn($v) => $v !== ''));
                if (!empty($cols)) {
                    $firstColLower = strtolower($cols[0]);
                    if (str_contains($firstColLower, 'step') || str_contains($firstColLower, 'platform') || str_contains($firstColLower, 'phase') || str_contains($firstColLower, 'objective') || str_contains($firstColLower, 'feature') || str_contains($firstColLower, 'channel')) {
                        $isTable = true;
                        continue;
                    }
                    $isTable = true;
                    $stepNum = $cols[0] ?? 'â€¢';
                    $stepTitle = $cols[1] ?? '';
                    $stepDesc = $cols[2] ?? '';
                    $stepTool = $cols[3] ?? '';

                    $cleanNum = trim(str_replace(['*', '#', ':'], '', $stepNum));
                    $cleanTitle = trim(str_replace(['*'], '', $stepTitle));
                    $cleanDesc = trim(str_replace(['*'], '', $stepDesc));
                    $cleanTool = trim(str_replace(['*'], '', $stepTool));

                    $tableCards[] = '<div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:12px 14px;margin-bottom:8px;">' .
                                        '<div class="d-flex align-items-center justify-content-between gap-2 mb-1">' .
                                            '<div class="d-flex align-items-center gap-2">' .
                                                '<span style="width:22px;height:22px;border-radius:50%;background:rgba(200,255,0,0.18);color:#c8ff00;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;">' . htmlspecialchars($cleanNum) . '</span>' .
                                                '<span style="font-size:13px;font-weight:700;color:#fff;">' . htmlspecialchars($cleanTitle) . '</span>' .
                                            '</div>' .
                                            ($cleanTool ? '<span style="font-size:10.5px;color:#38bdf8;background:rgba(56,189,248,0.12);padding:2px 8px;border-radius:6px;border:1px solid rgba(56,189,248,0.25);">' . htmlspecialchars($cleanTool) . '</span>' : '') .
                                        '</div>' .
                                        ($cleanDesc ? '<div style="font-size:12px;color:#94a3b8;line-height:1.4;margin-left:30px;">' . htmlspecialchars($cleanDesc) . '</div>' : '') .
                                     '</div>';
                    continue;
                }
            }

            if ($isTable && !empty($tableCards)) {
                $nonTable[] = '<div class="my-3 p-3" style="background:#0a0f1d;border:1px solid rgba(255,255,255,0.1);border-radius:14px;"><div style="font-size:12px;font-weight:700;color:#fff;margin-bottom:12px;display:flex;align-items:center;gap:6px;"><i class="ph-bold ph-path" style="color:#c8ff00;"></i> Execution Roadmap</div>' . implode('', $tableCards) . '</div>';
                $tableCards = [];
                $isTable = false;
            }

            $nonTable[] = $line;
        }

        if ($isTable && !empty($tableCards)) {
            $nonTable[] = '<div class="my-3 p-3" style="background:#0a0f1d;border:1px solid rgba(255,255,255,0.1);border-radius:14px;"><div style="font-size:12px;font-weight:700;color:#fff;margin-bottom:12px;display:flex;align-items:center;gap:6px;"><i class="ph-bold ph-path" style="color:#c8ff00;"></i> Execution Roadmap</div>' . implode('', $tableCards) . '</div>';
        }

        $text = implode("\n", $nonTable);
    }

    // 4. Clean out excessive ASCII dashes and delimiter rows
    $text = preg_replace('/^\s*\|?[\s\-:]+(\|[\s\-:]+)+\|?\s*$/m', '', $text);
    $text = preg_replace('/^[-=_*]{3,}$/m', '', $text);

    // 5. Clean section headers: ## Header or ### Header
    $text = preg_replace('/^#{1,3}\s+(.+)$/m', '<div class="fw-bold text-white font-size-14 mt-3 mb-2 pb-1 d-flex align-items-center gap-2" style="border-bottom:1px solid rgba(255,255,255,0.12);"><i class="ph-bold ph-caret-circle-right text-accent font-size-15"></i> $1</div>', $text);

    // 6. Clean bullets and numbered lists
    $text = preg_replace('/^[â€¢\-*]\s+(.+)$/m', '<div class="d-flex align-items-baseline gap-2 my-1" style="padding-left:4px;"><span style="color:#c8ff00;font-size:9px;">â—</span><span style="flex:1;font-size:13px;color:#e2e8f0;">$1</span></div>', $text);
    $text = preg_replace('/^(\d+)\.\s+(.+)$/m', '<div class="d-flex align-items-baseline gap-2 my-1" style="padding-left:4px;"><span style="color:#c8ff00;font-size:11px;font-weight:700;">$1.</span><span style="flex:1;font-size:13px;color:#e2e8f0;">$2</span></div>', $text);

    // 7. Clean bold: **text**
    $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong class="text-white fw-bold">$1</strong>', $text);

    // 8. Clean code: `code`
    $text = preg_replace('/`([^`]+)`/', '<code style="background:rgba(255,255,255,0.1);color:#c8ff00;padding:2px 6px;border-radius:4px;font-size:12px;">$1</code>', $text);

    // 9. Remove dangling double asterisks or pipes
    $text = str_replace(['**', '||'], '', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);

    // 10. Clean newlines and remove extraneous br tags around block containers
    $text = nl2br($text);
    $text = preg_replace('/(<\/?(?:div|canvas|a|p|ul|ol|li|img|span)[^>]*>)\s*<br\s*\/?>/i', '$1', $text);
    $text = preg_replace('/<br\s*\/?>\s*(<\/?(?:div|canvas|a|p|ul|ol|li|img)[^>]*>)/i', '$1', $text);

    return $text;
}

// Handle AJAX or Form submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_message') {
        $targetUserId = (int)($_POST['recipient_id'] ?? $chatUserId);
        $message = trim($_POST['message'] ?? '');

        if ($targetUserId > 0 && $message !== '' && !in_array($targetUserId, $allBlocked, true)) {
            $existing = db()->fetchOne(
                "SELECT id FROM p2p_conversations WHERE (user_a = ? AND user_b = ?) OR (user_a = ? AND user_b = ?)",
                [$userId, $targetUserId, $targetUserId, $userId]
            );

            if (!$existing) {
                $convId = db()->insert('p2p_conversations', [
                    'user_a'     => min($userId, $targetUserId),
                    'user_b'     => max($userId, $targetUserId),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $convId = (int)$existing['id'];
            }

            $newMsgId = db()->insert('p2p_messages', [
                'conversation_id' => $convId,
                'sender_id'       => $userId,
                'receiver_id'     => $targetUserId,
                'message'         => $message,
                'is_read'         => 0,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);

            $aiReply = null;
            if ($targetUserId === AI_BOT_ID) {
                require_once __DIR__ . '/modules/ai_engine.php';

                // If the message contains a URL, scrape live page context via Firecrawl
                // so the AI can answer questions about that specific page/product.
                $contextMessage = $message;
                if (preg_match_all('#https?://[^\s<>"\']+#i', $message, $urlMatches)) {
                    require_once __DIR__ . '/modules/firecrawl_scraper.php';
                    $urlContexts = [];
                    foreach (array_slice(array_unique($urlMatches[0]), 0, 2) as $foundUrl) {
                        $scraped = FirecrawlScraper::scrapeUrl($foundUrl, ['onlyMainContent' => true]);
                        if (!empty($scraped['success'])) {
                            $pageText = trim(preg_replace('/\s+/', ' ', strip_tags($scraped['content'] ?? '')));
                            if ($pageText !== '') {
                                $urlContexts[] = "[Live page context from {$foundUrl}]: " . mb_substr($pageText, 0, 2500);
                            }
                        }
                    }
                    if (!empty($urlContexts)) {
                        $contextMessage = $message . "\n\n" . implode("\n\n", $urlContexts) .
                            "\n\nUse the live page context above to answer accurately. If context is missing info, say so.";
                    }
                }

                $aiResponseText = AIEngine::chatConsultant($contextMessage, $userId);

                $aiMsgId = db()->insert('p2p_messages', [
                    'conversation_id' => $convId,
                    'sender_id'       => AI_BOT_ID,
                    'receiver_id'     => $userId,
                    'message'         => $aiResponseText,
                    'is_read'         => 1,
                    'created_at'      => date('Y-m-d H:i:s'),
                ]);

                $aiReply = [
                    'id'                => $aiMsgId,
                    'message'           => $aiResponseText,
                    'formatted_message' => formatChatMessage($aiResponseText),
                    'sender_id'         => AI_BOT_ID,
                    'sender_name'       => 'Adsbooster AI Consultant',
                    'time_formatted'    => date('g:i A'),
                ];
            } else {
                // Email-first notification to recipient
                $recipient = db()->fetchOne("SELECT email, full_name FROM users WHERE id = ?", [$targetUserId]);
                if ($recipient && !empty($recipient['email'])) {
                    require_once __DIR__ . '/modules/email_service.php';
                    EmailService::sendTransactional($recipient['email'], 'p2p_message', [
                        'name'        => $recipient['full_name'] ?: 'User',
                        'sender_name' => $currentUser['full_name'] ?? 'Adsbooster Member',
                        'preview'     => mb_substr($message, 0, 150),
                        'sender_id'   => $userId,
                    ]);
                }
            }

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_POST['ajax']) && $_POST['ajax'] === '1')) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success'        => true,
                    'message_id'     => $newMsgId,
                    'message'        => $message,
                    'sender_id'      => $userId,
                    'sender_name'    => $currentUser['full_name'] ?? 'You',
                    'created_at'     => date('Y-m-d H:i:s'),
                    'time_formatted' => date('g:i A'),
                    'ai_reply'       => $aiReply,
                ]);
                exit;
            }

            redirect(url('p2p-chat') . "?user={$targetUserId}");
        }
    }

    if ($action === 'propose_deal') {
        $targetUserId = (int)($_POST['recipient_id'] ?? $chatUserId);
        $dealTitle = trim($_POST['deal_title'] ?? '');
        $dealAmount = (float)($_POST['deal_amount'] ?? 0);
        $dealTerms = trim($_POST['deal_terms'] ?? '');

        if ($targetUserId > 0 && !empty($dealTitle) && $dealAmount > 0) {
            $existing = db()->fetchOne(
                "SELECT id FROM p2p_conversations WHERE (user_a = ? AND user_b = ?) OR (user_a = ? AND user_b = ?)",
                [$userId, $targetUserId, $targetUserId, $userId]
            );
            $convId = $existing ? (int)$existing['id'] : db()->insert('p2p_conversations', [
                'user_a'     => min($userId, $targetUserId),
                'user_b'     => max($userId, $targetUserId),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            db()->insert('p2p_deals', [
                'conversation_id' => $convId,
                'proposer_id'     => $userId,
                'receiver_id'     => $targetUserId,
                'title'           => $dealTitle,
                'amount'          => $dealAmount,
                'currency'        => 'USD',
                'terms'           => $dealTerms,
                'status'          => 'proposed',
                'created_at'      => date('Y-m-d H:i:s'),
            ]);

            $dealMsg = "ðŸ’¼ CONTRACT & DEAL PROPOSAL:\nâ€¢ Title: {$dealTitle}\nâ€¢ Total Amount: \$" . number_format($dealAmount, 2) . " USD\nâ€¢ Deliverables & Terms: {$dealTerms}\nâ€¢ Status: Proposed (Pending Agreement)";
            db()->insert('p2p_messages', [
                'conversation_id' => $convId,
                'sender_id'       => $userId,
                'receiver_id'     => $targetUserId,
                'message'         => $dealMsg,
                'is_read'         => 0,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
            set_flash('success', "Contract proposal dispatched into the chat thread!");
            redirect(url('p2p-chat') . "?user={$targetUserId}");
        }
    }
}

// Fetch all conversations
$myConversations = db()->fetchAll(
    "SELECT pc.*, 
            CASE WHEN pc.user_a = ? THEN pc.user_b ELSE pc.user_a END as other_user_id,
            (SELECT message FROM p2p_messages WHERE conversation_id = pc.id ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT created_at FROM p2p_messages WHERE conversation_id = pc.id ORDER BY created_at DESC LIMIT 1) as last_message_at,
            (SELECT COUNT(*) FROM p2p_messages WHERE conversation_id = pc.id AND sender_id != ? AND is_read = 0) as unread_count
     FROM p2p_conversations pc
     WHERE pc.user_a = ? OR pc.user_b = ?
     ORDER BY last_message_at DESC",
    [$userId, $userId, $userId, $userId]
);

// If no chat user specified in URL, pick the most recent conversation
if ($chatUserId === 0 && !empty($myConversations)) {
    $chatUserId = (int)$myConversations[0]['other_user_id'];
} elseif ($chatUserId === 0) {
    $chatUserId = 1;
}

$chatUser = db()->fetchOne("SELECT id, full_name, email, role FROM users WHERE id = ?", [$chatUserId]);

$conversation = null;
$messages = [];
if ($chatUserId > 0 && $chatUser) {
    $conversation = db()->fetchOne(
        "SELECT id FROM p2p_conversations WHERE (user_a = ? AND user_b = ?) OR (user_a = ? AND user_b = ?)",
        [$userId, $chatUserId, $chatUserId, $userId]
    );
    if ($conversation) {
        $messages = db()->fetchAll(
            "SELECT pm.*, u.full_name as sender_name FROM p2p_messages pm JOIN users u ON pm.sender_id = u.id WHERE pm.conversation_id = ? ORDER BY pm.created_at ASC",
            [(int)$conversation['id']]
        );
        db()->update('p2p_messages', ['is_read' => 1], 'conversation_id = ? AND sender_id = ?', [(int)$conversation['id'], $chatUserId]);
    }
}

$pageTitle = 'P2P Real-Time Messenger â€” ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/app_header.php';
?>
<!-- Chart.js 4 for Modern AI Agent Visualizations -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<style>
    .chat-layout {
        display: grid;
        grid-template-columns: 340px 1fr;
        grid-template-rows: 100%;
        height: calc(100vh - 135px);
        min-height: 480px;
        max-height: calc(100vh - 135px);
        background: #080c14;
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 16px;
        overflow: hidden;
    }
    @media (max-width: 900px) {
        .chat-layout {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 120px) !important;
            max-height: calc(100vh - 120px) !important;
            min-height: 420px !important;
            border-radius: 12px;
            overflow: hidden !important;
        }
        .chat-layout.has-active-chat .chat-conv-panel {
            display: none !important;
        }
        .chat-layout.has-active-chat .chat-main-panel {
            display: flex !important;
            height: 100% !important;
            flex: 1 1 0% !important;
            min-height: 0 !important;
            max-height: 100% !important;
            overflow: hidden !important;
        }
        .chat-layout:not(.has-active-chat) .chat-conv-panel {
            display: flex !important;
            height: 100% !important;
            flex: 1 1 0% !important;
            max-height: 100% !important;
            overflow: hidden !important;
        }
        .chat-layout:not(.has-active-chat) .chat-main-panel {
            display: none !important;
        }
        .chat-mobile-back-btn {
            display: inline-flex !important;
        }
    }
    .chat-mobile-back-btn {
        display: none;
    }
    .chat-conv-panel {
        background: #0d1524;
        border-right: 1px solid rgba(255,255,255,0.08);
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 0;
        max-height: 100%;
        overflow: hidden;
    }
    .chat-main-panel {
        background: #080c14;
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 0;
        max-height: 100%;
        overflow: hidden;
        position: relative;
    }
    .chat-header-bar {
        padding: 16px 24px;
        border-bottom: 1px solid rgba(255,255,255,0.08);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #0d1524;
        flex-shrink: 0;
    }
    .chat-messages-container {
        flex: 1 1 0%;
        min-height: 0;
        max-height: 100%;
        overflow-y: auto !important;
        overflow-x: hidden;
        padding: 20px 24px;
        display: flex;
        flex-direction: column;
    }
    .chat-footer-bar {
        padding: 16px 24px;
        border-top: 1px solid rgba(255,255,255,0.08);
        background: #0d1524;
        flex-shrink: 0;
    }
    .conv-item-box {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 18px;
        border-bottom: 1px solid rgba(255,255,255,0.04);
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .conv-item-box:hover {
        background: rgba(200,255,0,0.04);
    }
    .conv-item-box.active {
        background: rgba(200,255,0,0.08);
        border-left: 3px solid var(--accent);
    }
    .msg-bubble-sent {
        background: linear-gradient(135deg, #c8ff00 0%, #b2e600 100%);
        color: #080c14;
        font-weight: 500;
        border-radius: 16px 16px 2px 16px;
        padding: 12px 18px;
        box-shadow: 0 4px 14px rgba(200,255,0,0.18);
        display: inline-block;
        max-width: 88%;
        word-break: break-word;
        overflow-wrap: anywhere;
        white-space: normal;
        text-align: left;
    }
    .msg-bubble-received {
        background: #111a2e;
        color: #f8fafc;
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 16px 16px 16px 2px;
        padding: 14px 20px;
        box-shadow: 0 4px 14px rgba(0,0,0,0.3);
        display: inline-block;
        max-width: 92%;
        width: auto;
        word-break: break-word;
        overflow-wrap: anywhere;
        white-space: normal;
        text-align: left;
    }
    .quick-chip {
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.1);
        color: var(--fg2);
        padding: 4px 10px;
        border-radius: 100px;
        font-size: 11px;
        cursor: pointer;
        transition: all 0.15s;
        white-space: nowrap;
    }
    .quick-chip:hover {
        background: rgba(200,255,0,0.15);
        color: var(--accent);
        border-color: var(--accent);
    }
</style>

<?php $hasActiveChat = ($chatUser || $chatUserId === AI_BOT_ID); ?>
<div class="w-100">
    <div class="chat-layout <?= $hasActiveChat ? 'has-active-chat' : '' ?>">
        <!-- Left: Conversations List -->
        <div class="chat-conv-panel">
            <div style="padding: 18px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:space-between;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <i class="ph ph-chats-circle" style="font-size:22px; color:var(--accent);"></i>
                    <h5 style="color:#fff; font-size:16px; font-weight:700; margin:0;">Direct Messages</h5>
                </div>
                <span class="badge-status badge-active" style="font-size:11px;">Real-Time</span>
            </div>

            <!-- Search box -->
            <div style="padding: 12px 18px; border-bottom: 1px solid rgba(255,255,255,0.04);">
                <div style="position:relative;">
                    <i class="ph ph-magnifying-glass" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--fg3); font-size:14px;"></i>
                    <input type="text" id="convSearch" placeholder="Search conversations..." class="form-control" style="padding-left:36px; font-size:13px; background:#080c14; border:1px solid rgba(255,255,255,0.08); color:#fff; border-radius:100px;">
                </div>
            </div>

            <!-- List items -->
            <div style="overflow-y:auto; flex:1;" id="convContainer">
                <!-- Pinned AI Growth Consultant -->
                <a href="<?= url('p2p-chat') ?>?user=<?= AI_BOT_ID ?>" class="conv-item-box <?= $chatUserId === AI_BOT_ID ? 'active' : '' ?>" data-name="adsbooster ai consultant growth marketing bot" style="background: linear-gradient(90deg, rgba(200,255,0,0.08) 0%, rgba(56,189,248,0.06) 100%); border-bottom: 1px solid rgba(200,255,0,0.15);">
                    <div style="position:relative; flex-shrink:0;">
                        <div style="width:44px; height:44px; border-radius:12px; background:linear-gradient(135deg, #c8ff00 0%, #38bdf8 100%); display:flex; align-items:center; justify-content:center; color:#080c14; font-size:22px; box-shadow: 0 0 14px rgba(200,255,0,0.35);">
                            <i class="ph-fill ph-sparkle"></i>
                        </div>
                        <span style="position:absolute; bottom:-1px; right:-1px; width:10px; height:10px; border-radius:50%; background:var(--green); border:2px solid #0d1524;"></span>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:2px;">
                            <span style="color:#fff; font-size:14px; font-weight:700; display:flex; align-items:center; gap:6px;">
                                AI Consultant
                                <span style="background:var(--accent); color:#080c14; font-size:9px; font-weight:800; padding:1px 5px; border-radius:4px;">BOT</span>
                            </span>
                            <span style="font-size:10px; color:var(--accent); font-weight:600;">24/7 Live</span>
                        </div>
                        <p style="font-size:12px; color:var(--fg2); margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            Ad Strategy &amp; Copy Engine
                        </p>
                    </div>
                </a>

                <?php if (empty($myConversations)): ?>
                    <div style="padding: 32px 20px; text-align:center;">
                        <i class="ph ph-chats" style="font-size:36px; color:var(--fg3); margin-bottom:8px;"></i>
                        <p style="color:var(--fg2); font-size:13px; margin:0;">No active threads.</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($myConversations as $conv):
                    if ((int)$conv['other_user_id'] === AI_BOT_ID) continue; // Pinned above
                    $otherUser = db()->fetchOne("SELECT id, full_name, email FROM users WHERE id = ?", [(int)$conv['other_user_id']]);
                    $isActive = (int)$conv['other_user_id'] === $chatUserId;
                    $partnerName = $otherUser['full_name'] ?? 'User #' . $conv['other_user_id'];
                    $initial = strtoupper(substr($partnerName, 0, 1));
                ?>
                    <a href="<?= url('p2p-chat') ?>?user=<?= (int)$conv['other_user_id'] ?>" class="conv-item-box <?= $isActive ? 'active' : '' ?>" data-name="<?= strtolower(htmlspecialchars($partnerName)) ?>">
                        <div style="position:relative; flex-shrink:0;">
                            <div style="width:44px; height:44px; border-radius:12px; background:linear-gradient(135deg, rgba(200,255,0,0.2), rgba(16,185,129,0.2)); border:1px solid rgba(200,255,0,0.3); display:flex; align-items:center; justify-content:center; color:var(--accent); font-weight:700; font-size:16px;">
                                <?= $initial ?>
                            </div>
                            <span style="position:absolute; bottom:-1px; right:-1px; width:10px; height:10px; border-radius:50%; background:var(--green); border:2px solid #0d1524;"></span>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:2px;">
                                <span style="color:#fff; font-size:14px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:140px;">
                                    <?= htmlspecialchars($partnerName) ?>
                                </span>
                                <span style="font-size:11px; color:var(--fg3);">
                                    <?= $conv['last_message_at'] ? date('M d', strtotime($conv['last_message_at'])) : '' ?>
                                </span>
                            </div>
                            <div style="display:flex; align-items:center; justify-content:space-between;">
                                <p style="font-size:12px; color:var(--fg2); margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:160px;">
                                    <?= htmlspecialchars($conv['last_message'] ?? 'Start chatting...') ?>
                                </p>
                                <?php if ((int)$conv['unread_count'] > 0): ?>
                                    <span style="background:var(--accent); color:#080c14; font-weight:700; font-size:10px; border-radius:100px; padding:2px 7px;">
                                        <?= min((int)$conv['unread_count'], 99) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right: Active Chat Area -->
        <div class="chat-main-panel">
            <?php if ($chatUser || $chatUserId === AI_BOT_ID): 
                $isAIBot = ($chatUserId === AI_BOT_ID);
                $displayName = $isAIBot ? 'Adsbooster AI Consultant' : htmlspecialchars($chatUser['full_name']);
                $displayEmail = $isAIBot ? 'Multi-model Engine (Llama 3.3 &amp; Gemini)' : htmlspecialchars($chatUser['email']);
            ?>
                <!-- Header -->
                <div class="chat-header-bar">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <a href="<?= url('p2p-chat') ?>" class="btn btn-outline-dark btn-sm rounded-circle chat-mobile-back-btn" title="Back to conversations">
                            <i class="ph ph-arrow-left"></i>
                        </a>
                        <?php if ($isAIBot): ?>
                            <div style="width:42px; height:42px; border-radius:12px; background:linear-gradient(135deg, #c8ff00 0%, #38bdf8 100%); display:flex; align-items:center; justify-content:center; color:#080c14; font-size:22px; box-shadow: 0 0 12px rgba(200,255,0,0.3);">
                                <i class="ph-fill ph-sparkle"></i>
                            </div>
                        <?php else: ?>
                            <div style="width:42px; height:42px; border-radius:12px; background:linear-gradient(135deg, rgba(200,255,0,0.25), rgba(16,185,129,0.25)); border:1px solid rgba(200,255,0,0.35); display:flex; align-items:center; justify-content:center; color:var(--accent); font-weight:700; font-size:16px;">
                                <?= strtoupper(substr($chatUser['full_name'] ?? 'U', 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <h6 style="color:#fff; font-size:15px; font-weight:700; margin:0;"><?= $displayName ?></h6>
                                <span class="badge-status badge-active" style="font-size:10px; padding:2px 8px;"><?= $isAIBot ? 'AI Agent' : 'Active' ?></span>
                            </div>
                            <span style="font-size:12px; color:var(--fg3);"><?= $displayEmail ?></span>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <?php if (!$isAIBot): ?>
                            <a href="<?= url('portfolio') ?>?id=<?= $chatUserId ?>" target="_blank" class="btn btn-outline-light rounded-pill btn-sm font-size-12 px-3" title="View Public Portfolio & vCard">
                                <i class="ph ph-address-book me-1"></i> Portfolio &amp; vCard
                            </a>
                            <button type="button" class="btn btn-accent rounded-pill btn-sm font-size-12 fw-bold text-dark px-3" onclick="document.getElementById('dealModal').style.display='flex'">
                                <i class="ph-bold ph-handshake me-1"></i> Propose Contract
                            </button>
                        <?php endif; ?>
                        <button class="btn-outline-dark btn-sm rounded-circle" onclick="location.reload()" title="Refresh messages">
                            <i class="ph ph-arrows-clockwise"></i>
                        </button>
                    </div>
                </div>

                <!-- Messages Stream -->
                <div id="chatMessages" class="chat-messages-container" data-conv-id="<?= $conversation ? (int)$conversation['id'] : 0 ?>" data-user-id="<?= $userId ?>">
                    <?php if (empty($messages)): ?>
                        <div style="text-align:center; padding:60px 20px;">
                            <div style="width:56px; height:56px; border-radius:50%; background:rgba(200,255,0,0.1); border:1px solid rgba(200,255,0,0.2); display:flex; align-items:center; justify-content:center; margin:0 auto 16px; color:var(--accent);">
                                <i class="ph <?= $isAIBot ? 'ph-sparkle' : 'ph-paper-plane-tilt' ?>" style="font-size:26px;"></i>
                            </div>
                            <h5 style="color:#fff; font-size:16px; font-weight:700; margin-bottom:6px;"><?= $isAIBot ? 'AI Growth Consultant Online' : 'Start the Conversation' ?></h5>
                            <p style="color:var(--fg2); font-size:13px; max-width:420px; margin:0 auto 20px;">
                                <?= $isAIBot ? 'Ask questions about advertising formulas, copy, audience targeting, or campaign optimization.' : 'Say hello or send a quick proposal to ' . $displayName . '.' ?>
                            </p>
                            
                            <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
                                <?php if ($isAIBot): ?>
                                    <button type="button" class="quick-chip" onclick="quickSend('ðŸ“ˆ How can I optimize my Meta & Facebook ROAS?')">ðŸ“ˆ Meta ROAS Formula</button>
                                    <button type="button" class="quick-chip" onclick="quickSend('ðŸŽ¬ Draft a viral 30-second TikTok ad script for my product.')">ðŸŽ¬ TikTok Ad Script</button>
                                    <button type="button" class="quick-chip" onclick="quickSend('ðŸ” What is the best SEO title structure for ranking on Google?')">ðŸ” SEO Ranking Formula</button>
                                    <button type="button" class="quick-chip" onclick="quickSend('ðŸŽ¯ What audience interests should I target for e-commerce conversions?')">ðŸŽ¯ E-Commerce Targeting</button>
                                <?php else: ?>
                                    <button type="button" class="quick-chip" onclick="quickSend('ðŸ‘‹ Hi there! Let\'s collaborate on Adsbooster.')">ðŸ‘‹ Say Hello</button>
                                    <button type="button" class="quick-chip" onclick="quickSend('ðŸ’¼ I saw your marketplace listing and I\'m interested in discussing details.')">ðŸ’¼ Marketplace Inquiry</button>
                                    <button type="button" class="quick-chip" onclick="quickSend('â¤ï¸ Nice to meet you! Would love to connect and chat.')">â¤ï¸ Connect Match</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): 
                            $isMine = (int)$msg['sender_id'] === $userId;
                            $senderLabel = $isMine ? 'You' : ($msg['sender_id'] == AI_BOT_ID ? 'ðŸ¤– AI Consultant' : htmlspecialchars($msg['sender_name']));
                        ?>
                            <div class="mb-3 <?= $isMine ? 'text-end' : 'text-start' ?>" data-msg-id="<?= $msg['id'] ?>">
                                <div class="<?= $isMine ? 'msg-bubble-sent' : 'msg-bubble-received' ?>">
                                    <div style="font-size:11px; opacity:0.65; margin-bottom:4px; font-weight:600;"><?= $senderLabel ?></div>
                                    <div class="msg-content" style="line-height:1.55; font-size:13.5px;"><?= $isMine ? nl2br(htmlspecialchars($msg['message'])) : formatChatMessage($msg['message']) ?></div>
                                    <div style="font-size:10px; opacity:0.55; margin-top:5px; text-align:right;">
                                        <?= date('g:i A', strtotime($msg['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Input Area -->
                <?php if (!in_array($chatUserId, $allBlocked, true)): ?>
                    <div class="chat-footer-bar">
                        <!-- Quick starters -->
                        <div style="display:flex; gap:6px; margin-bottom:10px; overflow-x:auto; padding-bottom:4px;">
                            <?php if ($isAIBot): ?>
                                <span class="quick-chip" onclick="quickFill('How do I run a multi-platform ad campaign?')">ðŸ’¡ Multi-platform campaign</span>
                                <span class="quick-chip" onclick="quickFill('Give me 5 catchy ad headlines for my product.')">ðŸ”¥ Catchy headlines</span>
                                <span class="quick-chip" onclick="quickFill('How do I boost my business directory listing?')">â­ Boost directory ranking</span>
                            <?php else: ?>
                                <span class="quick-chip" onclick="quickFill('Sounds great, let\'s proceed!')">ðŸ¤ Let's proceed</span>
                                <span class="quick-chip" onclick="quickFill('Can you share more details?')">ðŸ“‹ More details?</span>
                                <span class="quick-chip" onclick="quickFill('Thanks for the update!')">ðŸ™ Thank you</span>
                                <span class="quick-chip" onclick="quickFill('What is your best price/rate?')">ðŸ’° Pricing info</span>
                            <?php endif; ?>
                        </div>

                        <form id="chatForm" method="POST" style="display:flex; align-items:center; gap:12px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="send_message">
                            <input type="hidden" name="recipient_id" value="<?= $chatUserId ?>">
                            <input type="hidden" name="ajax" value="1">

                            <input type="text" id="msgInput" name="message" class="form-control" placeholder="<?= $isAIBot ? 'Ask AI anything — or paste a product/page URL for live web analysis...' : 'Type your message here (Press Enter to send)...' ?>" autocomplete="off" required style="background:#080c14; border:1px solid rgba(255,255,255,0.12); color:#fff; border-radius:12px; padding:12px 18px; font-size:14px;">

                            <button type="submit" id="sendBtn" class="btn-accent" style="padding:12px 22px; border-radius:12px; font-weight:600; white-space:nowrap; display:flex; align-items:center; gap:8px;">
                                <span>Send</span>
                                <i class="ph ph-paper-plane-tilt"></i>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div style="padding:20px; text-align:center; background:#0d1524; border-top:1px solid rgba(255,255,255,0.08);">
                        <span style="color:var(--orange); font-size:13px;"><i class="ph ph-prohibit"></i> Messaging is disabled between your accounts.</span>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div style="display:flex; align-items:center; justify-content:center; flex:1; text-align:center;">
                    <div>
                        <i class="ph ph-chats-circle" style="font-size:48px; color:var(--fg3); margin-bottom:12px;"></i>
                        <h4 style="color:#fff; font-size:18px;">Select a Conversation</h4>
                        <p style="color:var(--fg2); font-size:13px;">Choose a contact from the left list to begin messaging.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function() {
    const chatBox = document.getElementById('chatMessages');
    const msgInput = document.getElementById('msgInput');
    const chatForm = document.getElementById('chatForm');
    const convSearch = document.getElementById('convSearch');

    function scrollToBottom() {
        if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
    }
    scrollToBottom();

    // Search filter for conversation list
    if (convSearch) {
        convSearch.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('#convContainer .conv-item-box').forEach(item => {
                const name = item.dataset.name || '';
                item.style.display = name.includes(q) ? 'flex' : 'none';
            });
        });
    }

    // Quick fill helper
    window.quickFill = function(text) {
        if (msgInput) {
            msgInput.value = text;
            msgInput.focus();
        }
    };

    window.quickSend = function(text) {
        if (msgInput) {
            msgInput.value = text;
            if (chatForm) chatForm.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    };

    // AJAX send message
    if (chatForm) {
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const text = msgInput.value.trim();
            if (!text) return;

            const formData = new FormData(chatForm);
            msgInput.value = '';

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    appendMessage(data.message, data.sender_name, data.time_formatted, true);
                    scrollToBottom();

                    if (data.ai_reply) {
                        const typingDiv = document.createElement('div');
                        typingDiv.className = 'mb-3 text-start';
                        typingDiv.id = 'aiTypingIndicator';
                        typingDiv.innerHTML = `
                            <div class="msg-bubble-received" style="padding:10px 16px;">
                                <div style="font-size:11px; opacity:0.65; margin-bottom:3px; font-weight:600;">ðŸ¤– AI Consultant</div>
                                <span style="font-style:italic; font-size:12px; color:var(--accent);"><i class="ph-bold ph-spinner" style="animation:spin 1s linear infinite;"></i> AI Consultant is analyzing...</span>
                            </div>
                        `;
                        chatBox.appendChild(typingDiv);
                        scrollToBottom();

                        setTimeout(function() {
                            const indicator = document.getElementById('aiTypingIndicator');
                            if (indicator) indicator.remove();
                            appendMessage(data.ai_reply.message, data.ai_reply.sender_name, data.ai_reply.time_formatted, false, data.ai_reply.formatted_message);
                            scrollToBottom();
                        }, 500);
                    }
                }
            })
            .catch(() => {
                location.reload();
            });
        });
    }

    window.aiCharts = {};
    window.initAiChart = function(canvasId, type, labels, values) {
        const el = document.getElementById(canvasId);
        if (!el || typeof Chart === 'undefined') return;
        if (window.aiCharts[canvasId]) {
            try { window.aiCharts[canvasId].destroy(); } catch(e){}
        }

        const isDoughnut = (type === 'doughnut' || type === 'pie');
        const colors = [
            '#1877f2', // Meta Blue
            '#00f2fe', // TikTok Cyan
            '#ea4335', // Google Red
            '#c8ff00', // Adsbooster Lime
            '#a855f7', // Purple
            '#f59e0b', // Amber
            '#10b981'  // Emerald
        ];

        const ctx = el.getContext('2d');
        window.aiCharts[canvasId] = new Chart(ctx, {
            type: type,
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: isDoughnut ? colors.slice(0, values.length) : 'rgba(200, 255, 0, 0.75)',
                    hoverBackgroundColor: isDoughnut ? colors.slice(0, values.length) : '#c8ff00',
                    borderColor: isDoughnut ? '#0a0f1d' : '#c8ff00',
                    borderWidth: isDoughnut ? 3 : 1,
                    borderRadius: isDoughnut ? 0 : 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: isDoughnut,
                        position: 'bottom',
                        labels: {
                            color: '#94a3b8',
                            font: { size: 11, family: 'Inter', weight: 600 },
                            padding: 10,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#fff',
                        bodyColor: '#c8ff00',
                        borderColor: 'rgba(255,255,255,0.1)',
                        borderWidth: 1,
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                return ' ' + context.label + ': ' + context.parsed + (isDoughnut ? '%' : '');
                            }
                        }
                    }
                },
                scales: isDoughnut ? {} : {
                    x: {
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: '#94a3b8', font: { size: 11 } }
                    },
                    y: {
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: '#94a3b8', font: { size: 11 } }
                    }
                }
            }
        });
    };

    function renderAllChartsInContainer(container) {
        if (!container) return;
        const canvases = container.querySelectorAll('canvas.js-ai-chart');
        canvases.forEach(cv => {
            try {
                const id = cv.id;
                const type = cv.dataset.type || 'doughnut';
                const labels = JSON.parse(cv.dataset.labels || '[]');
                const values = JSON.parse(cv.dataset.values || '[]');
                window.initAiChart(id, type, labels, values);
            } catch(e) {}
        });
    }

    function renderMarkdown(raw) {
        if (!raw) return '';
        let text = raw;

        // 1. Process custom AI tags
        // Metrics
        text = text.replace(/\[METRICS:([^\]]+)\]/gi, function(m, p1) {
            const pairs = p1.split('|');
            let cards = '';
            pairs.forEach(pair => {
                const parts = pair.split(':').map(s => s.trim());
                if (parts.length >= 2) {
                    const lbl = escapeHtml(parts[0]);
                    const val = escapeHtml(parts[1]);
                    const sub = parts[2] ? escapeHtml(parts[2]) : '';
                    cards += `<div style="flex:1 1 120px;min-width:110px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:10px 14px;">
                        <div style="font-size:10.5px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;margin-bottom:3px;">${lbl}</div>
                        <div style="font-size:18px;color:#c8ff00;font-weight:800;letter-spacing:-0.5px;line-height:1.2;">${val}</div>
                        ${sub ? `<div style="font-size:10px;color:#38bdf8;margin-top:2px;">${sub}</div>` : ''}
                    </div>`;
                }
            });
            return `<div class="d-flex flex-wrap gap-2 my-2">${cards}</div>`;
        });

        // Charts
        text = text.replace(/\[CHART:(doughnut|pie|bar|line):([^:]+):([^\]]+)\]/gi, function(m, p1, p2, p3) {
            const type = p1.toLowerCase();
            const title = escapeHtml(p2.trim());
            const items = p3.split(',');
            const labels = [];
            const values = [];
            items.forEach(it => {
                const parts = it.split(':').map(s => s.trim());
                if (parts.length === 2) {
                    labels.push(parts[0]);
                    values.push(parseFloat(parts[1]) || 0);
                }
            });
            const canvasId = 'aiChart_' + Math.floor(Math.random() * 1000000);
            const labelsJson = escapeHtml(JSON.stringify(labels));
            const valuesJson = escapeHtml(JSON.stringify(values));

            return `<div class="ai-chart-widget my-3" style="background:#0a0f1d;border:1px solid rgba(255,255,255,0.1);border-radius:14px;padding:16px 18px;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <span style="width:24px;height:24px;border-radius:6px;background:rgba(200,255,0,0.15);color:#c8ff00;display:flex;align-items:center;justify-content:center;font-size:13px;"><i class="ph-bold ph-chart-pie-slice"></i></span>
                        <span style="color:#fff;font-weight:700;font-size:13px;">${title}</span>
                    </div>
                    <span style="font-size:10px;color:#94a3b8;background:rgba(255,255,255,0.06);padding:2px 7px;border-radius:100px;">Data Visual</span>
                </div>
                <div style="position:relative;height:200px;width:100%;max-width:440px;margin:0 auto;">
                    <canvas id="${canvasId}" class="js-ai-chart" data-type="${type}" data-labels='${labelsJson}' data-values='${valuesJson}'></canvas>
                </div>
            </div>`;
        });

        // Image Graphic Widget: [IMAGE:url:caption]
        text = text.replace(/\[IMAGE:([^:]+):([^\]]+)\]/gi, function(m, p1, p2) {
            const url = escapeHtml(p1.trim());
            const caption = escapeHtml(p2.trim());
            return `<div class="ai-image-widget my-3" style="background:#0a0f1d;border:1px solid rgba(255,255,255,0.1);border-radius:14px;overflow:hidden;max-width:540px;">
                <img src="${url}" alt="${caption}" style="width:100%;height:auto;max-height:260px;object-fit:cover;display:block;" loading="lazy" />
                <div style="padding:10px 14px;font-size:11.5px;color:#94a3b8;border-top:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;gap:6px;">
                    <i class="ph-bold ph-image text-accent"></i> ${caption}
                </div>
            </div>`;
        });

        // Platforms
        text = text.replace(/\[PLATFORMS:([^\]]+)\]/gi, function(m, p1) {
            const platforms = p1.split('|');
            let html = `<div class="my-3 p-3" style="background:#0a0f1d;border:1px solid rgba(255,255,255,0.1);border-radius:14px;">
                <div style="font-size:12px;font-weight:700;color:#fff;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                    <i class="ph-fill ph-squares-four" style="color:#c8ff00;"></i> Channel Allocation Strategy
                </div>
                <div class="d-flex flex-column gap-2">`;
            platforms.forEach(p => {
                const parts = p.split(':').map(s => s.trim());
                const name = parts[0] || '';
                const pct = parts[1] || '';
                const desc = parts[2] || '';

                let icon = 'ph-share-network';
                let color = '#c8ff00';
                let bg = 'rgba(200,255,0,0.15)';
                const nLower = name.toLowerCase();
                if (nLower.includes('meta') || nLower.includes('facebook') || nLower.includes('instagram')) {
                    icon = 'ph-facebook-logo';
                    color = '#1877f2';
                    bg = 'rgba(24,119,242,0.18)';
                } else if (nLower.includes('tiktok')) {
                    icon = 'ph-tiktok-logo';
                    color = '#00f2fe';
                    bg = 'rgba(0,242,254,0.18)';
                } else if (nLower.includes('google') || nLower.includes('youtube')) {
                    icon = 'ph-google-logo';
                    color = '#ea4335';
                    bg = 'rgba(234,67,53,0.18)';
                }

                html += `<div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:34px;height:34px;border-radius:8px;background:${bg};color:${color};display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">
                            <i class="ph-bold ${icon}"></i>
                        </div>
                        <div>
                            <div style="font-size:13px;font-weight:700;color:#fff;">${escapeHtml(name)}</div>
                            <div style="font-size:11.5px;color:#94a3b8;line-height:1.3;">${escapeHtml(desc)}</div>
                        </div>
                    </div>
                    ${pct ? `<div style="text-align:right;flex-shrink:0;"><span style="font-size:14px;font-weight:800;color:${color};">${escapeHtml(pct)}</span><div style="font-size:9.5px;color:#64748b;">Allocation</div></div>` : ''}
                </div>`;
            });
            html += `</div></div>`;
            return html;
        });

        // Steps Roadmap
        text = text.replace(/\[STEPS:([^\]]+)\]/gi, function(m, p1) {
            const steps = p1.split('|');
            let html = `<div class="my-3 p-3" style="background:#0a0f1d;border:1px solid rgba(255,255,255,0.1);border-radius:14px;">
                <div style="font-size:12px;font-weight:700;color:#fff;margin-bottom:12px;display:flex;align-items:center;gap:6px;">
                    <i class="ph-bold ph-path" style="color:#c8ff00;"></i> Execution Roadmap
                </div>
                <div class="d-flex flex-column gap-2">`;
            steps.forEach(s => {
                const parts = s.split(':').map(str => str.trim());
                const num = parts[0] || 'â€¢';
                const title = parts[1] || '';
                const desc = parts[2] || '';
                const tool = parts[3] || '';

                html += `<div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:12px 14px;">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                        <div class="d-flex align-items-center gap-2">
                            <span style="width:22px;height:22px;border-radius:50%;background:rgba(200,255,0,0.18);color:#c8ff00;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;">${escapeHtml(num)}</span>
                            <span style="font-size:13px;font-weight:700;color:#fff;">${escapeHtml(title)}</span>
                        </div>
                        ${tool ? `<span style="font-size:10.5px;color:#38bdf8;background:rgba(56,189,248,0.12);padding:2px 8px;border-radius:6px;border:1px solid rgba(56,189,248,0.25);white-space:nowrap;">${escapeHtml(tool)}</span>` : ''}
                    </div>
                    ${desc ? `<div style="font-size:12px;color:#94a3b8;line-height:1.4;margin-left:30px;">${escapeHtml(desc)}</div>` : ''}
                </div>`;
            });
            html += `</div></div>`;
            return html;
        });

        // Action CTA
        text = text.replace(/\[ACTION:([^:]+):([^:]+):([^\]]+)\]/gi, function(m, p1, p2, p3) {
            const title = escapeHtml(p1.trim());
            const rawUrl = p2.trim();
            const icon = escapeHtml(p3.trim());
            const cleanUrl = rawUrl.replace(/\.php(\?|$)/i, '$1');
            const baseUrl = '<?= BASE_URL ?>';
            let finalUrl = cleanUrl;
            if (!cleanUrl.startsWith('http://') && !cleanUrl.startsWith('https://')) {
                finalUrl = baseUrl + '/' + cleanUrl.replace(/^\//, '');
            }
            return `<a href="${escapeHtml(finalUrl)}" class="btn btn-accent btn-sm rounded-pill fw-bold text-dark font-size-12 px-3 py-2 me-2 my-1 d-inline-flex align-items-center gap-2" style="box-shadow:0 4px 14px rgba(200,255,0,0.2);">
                <i class="${icon}"></i> ${title}
            </a>`;
        });

        // 2. Normalize and clean newlines
        text = text.replace(/<br\s*\/?>/gi, '\n').replace(/\r\n|\r/g, '\n');

        // 3. Auto-convert legacy pipe tables in JS
        if (text.includes('|')) {
            const lines = text.split('\n');
            const tableCards = [];
            const nonTable = [];
            let isTable = false;

            for (let i = 0; i < lines.length; i++) {
                const line = lines[i];
                const t = line.trim();
                if (t.startsWith('|') && t.endsWith('|')) {
                    if (/^\|[\s\-:]+(\|[\s\-:]+)+\|$/.test(t)) continue;
                    const cols = t.slice(1, -1).split('|').map(c => c.trim()).filter(c => c !== '');
                    if (cols.length > 0) {
                        const firstColLower = cols[0].toLowerCase();
                        if (firstColLower.includes('step') || firstColLower.includes('platform') || firstColLower.includes('phase') || firstColLower.includes('objective') || firstColLower.includes('feature') || firstColLower.includes('channel')) {
                            isTable = true;
                            continue;
                        }
                        isTable = true;
                        const num = cols[0] ? cols[0].replace(/[*#:]/g, '').trim() : 'â€¢';
                        const title = cols[1] ? cols[1].replace(/[*]/g, '').trim() : '';
                        const desc = cols[2] ? cols[2].replace(/[*]/g, '').trim() : '';
                        const tool = cols[3] ? cols[3].replace(/[*]/g, '').trim() : '';

                        tableCards.push(`
                            <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:12px 14px;margin-bottom:8px;">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <span style="width:22px;height:22px;border-radius:50%;background:rgba(200,255,0,0.18);color:#c8ff00;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;">${escapeHtml(num)}</span>
                                        <span style="font-size:13px;font-weight:700;color:#fff;">${escapeHtml(title)}</span>
                                    </div>
                                    ${tool ? `<span style="font-size:10.5px;color:#38bdf8;background:rgba(56,189,248,0.12);padding:2px 8px;border-radius:6px;border:1px solid rgba(56,189,248,0.25);">${escapeHtml(tool)}</span>` : ''}
                                </div>
                                ${desc ? `<div style="font-size:12px;color:#94a3b8;line-height:1.4;margin-left:30px;">${escapeHtml(desc)}</div>` : ''}
                            </div>
                        `);
                        continue;
                    }
                }

                if (isTable && tableCards.length > 0) {
                    nonTable.push(`<div class="my-3 p-3" style="background:#0a0f1d;border:1px solid rgba(255,255,255,0.1);border-radius:14px;"><div style="font-size:12px;font-weight:700;color:#fff;margin-bottom:12px;display:flex;align-items:center;gap:6px;"><i class="ph-bold ph-path" style="color:#c8ff00;"></i> Execution Roadmap</div>${tableCards.join('')}</div>`);
                    tableCards.length = 0;
                    isTable = false;
                }
                nonTable.push(line);
            }

            if (isTable && tableCards.length > 0) {
                nonTable.push(`<div class="my-3 p-3" style="background:#0a0f1d;border:1px solid rgba(255,255,255,0.1);border-radius:14px;"><div style="font-size:12px;font-weight:700;color:#fff;margin-bottom:12px;display:flex;align-items:center;gap:6px;"><i class="ph-bold ph-path" style="color:#c8ff00;"></i> Execution Roadmap</div>${tableCards.join('')}</div>`);
            }
            text = nonTable.join('\n');
        }

        // 4. Clean excessive ASCII lines and delimiter rows
        text = text.replace(/^\s*\|?[\s\-:]+(\|[\s\-:]+)+\|?\s*$/gm, '');
        text = text.replace(/^[-=_*]{3,}$/gm, '');

        // 5. Clean section headers: ## Header or ### Header
        text = text.replace(/^#{1,3}\s+(.+)$/gm, '<div class="fw-bold text-white font-size-14 mt-3 mb-2 pb-1 d-flex align-items-center gap-2" style="border-bottom:1px solid rgba(255,255,255,0.12);"><i class="ph-bold ph-caret-circle-right text-accent font-size-15"></i> $1</div>');

        // 6. Clean bullets and numbered lists
        text = text.replace(/^[â€¢\-*]\s+(.+)$/gm, '<div class="d-flex align-items-baseline gap-2 my-1" style="padding-left:4px;"><span style="color:#c8ff00;font-size:9px;">â—</span><span style="flex:1;font-size:13px;color:#e2e8f0;">$1</span></div>');
        text = text.replace(/^(\d+)\.\s+(.+)$/gm, '<div class="d-flex align-items-baseline gap-2 my-1" style="padding-left:4px;"><span style="color:#c8ff00;font-size:11px;font-weight:700;">$1.</span><span style="flex:1;font-size:13px;color:#e2e8f0;">$2</span></div>');

        // 7. Clean bold
        text = text.replace(/\*\*(.*?)\*\*/gs, '<strong class="text-white fw-bold">$1</strong>');

        // 8. Clean code
        text = text.replace(/`([^`]+)`/g, '<code style="background:rgba(255,255,255,0.1);color:#c8ff00;padding:2px 6px;border-radius:4px;font-size:12px;">$1</code>');

        // 9. Clean dangling double asterisks or pipes
        text = text.replace(/\*\*|\|\|/g, '');
        text = text.replace(/\n{3,}/g, '\n\n');

        // 10. Clean newlines and remove extraneous br tags around block containers
        text = text.replace(/\n/g, '<br>');
        text = text.replace(/(<\/?(?:div|canvas|a|p|ul|ol|li|img|span)[^>]*>)\s*<br\s*\/?>/gi, '$1');
        text = text.replace(/<br\s*\/?>\s*(<\/?(?:div|canvas|a|p|ul|ol|li|img)[^>]*>)/gi, '$1');

        return text;
    }

    function appendMessage(text, sender, time, isMine, preformattedHtml = null) {
        const div = document.createElement('div');
        div.className = 'mb-3 ' + (isMine ? 'text-end' : 'text-start');
        const formatted = isMine ? escapeHtml(text).replace(/\n/g, '<br>') : (preformattedHtml || renderMarkdown(text));
        div.innerHTML = `
            <div class="${isMine ? 'msg-bubble-sent' : 'msg-bubble-received'}">
                <div style="font-size:11px; opacity:0.65; margin-bottom:4px; font-weight:600;">${escapeHtml(sender)}</div>
                <div class="msg-content" style="line-height:1.55; font-size:13.5px;">${formatted}</div>
                <div style="font-size:10px; opacity:0.55; margin-top:5px; text-align:right;">${escapeHtml(time)}</div>
            </div>
        `;
        chatBox.appendChild(div);
        renderAllChartsInContainer(div);
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Initialize all existing charts in the chat stream
    renderAllChartsInContainer(chatBox);

    // Polling for incoming messages
    if (chatBox) {
        const convId = chatBox.dataset.convId;
        const currentUserId = chatBox.dataset.userId;

        if (convId && convId !== '0') {
            setInterval(function() {
                fetch('<?= url('chat-poll') ?>?conv_id=' + convId + '&user_id=' + currentUserId)
                    .then(r => r.json())
                    .then(data => {
                        if (data.messages && data.messages.length > 0) {
                            const wasBottom = chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 60;
                            data.messages.forEach(msg => {
                                if (!chatBox.querySelector('[data-msg-id="' + msg.id + '"]')) {
                                    const isMine = parseInt(msg.sender_id) === parseInt(currentUserId);
                                    const d = document.createElement('div');
                                    d.className = 'mb-3 ' + (isMine ? 'text-end' : 'text-start');
                                    d.setAttribute('data-msg-id', msg.id);
                                    const formatted = isMine ? escapeHtml(msg.message).replace(/\n/g, '<br>') : renderMarkdown(msg.message);
                                    d.innerHTML = `
                                        <div class="${isMine ? 'msg-bubble-sent' : 'msg-bubble-received'}">
                                             <div style="font-size:11px; opacity:0.65; margin-bottom:4px; font-weight:600;">${escapeHtml(msg.sender_name)}</div>
                                             <div class="msg-content" style="line-height:1.55; font-size:13.5px;">${formatted}</div>
                                             <div style="font-size:10px; opacity:0.55; margin-top:5px; text-align:right;">${new Date(msg.created_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}</div>
                                         </div>
                                     `;
                                     chatBox.appendChild(d);
                                     renderAllChartsInContainer(d);
                                 }
                             });
                             if (wasBottom) scrollToBottom();
                         }
                     })
                     .catch(() => {});
             }, 3000);
         }
     }
 })();
 </script>
 
 <!-- Propose Deal / Contract Modal -->
 <div id="dealModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
     <div class="card-dark" style="max-width:520px;width:90%;padding:28px;border-radius:16px;">
         <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary border-opacity-10">
             <h5 style="color:#fff;font-weight:700;margin:0;">
                 <i class="ph-bold ph-handshake text-accent me-1"></i> Propose Contract or Deal
             </h5>
             <button type="button" class="btn-close btn-close-white" onclick="document.getElementById('dealModal').style.display='none'"></button>
         </div>
 
         <form method="POST" action="<?= url('p2p-chat', ['user' => $chatUserId]) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="propose_deal">
            <input type="hidden" name="recipient_id" value="<?= $chatUserId ?>">

            <div class="mb-3">
                <label class="form-label" style="font-size:12px;color:var(--fg2);">Contract / Project Title *</label>
                <input type="text" name="deal_title" required class="form-control" placeholder="e.g. 30-Day TikTok Media Buying Sprint ($50k scale)" style="background:#080c14;color:#fff;border:1px solid var(--border);">
            </div>

            <div class="row g-2 mb-3">
                <div class="col-8">
                    <label class="form-label" style="font-size:12px;color:var(--fg2);">Total Agreed Amount ($ USD) *</label>
                    <input type="number" step="0.01" min="1" name="deal_amount" required class="form-control" placeholder="2500.00" style="background:#080c14;color:#fff;border:1px solid var(--border);">
                </div>
                <div class="col-4">
                    <label class="form-label" style="font-size:12px;color:var(--fg2);">Currency</label>
                    <input type="text" value="USD" readonly class="form-control text-center" style="background:#080c14;color:var(--accent);font-weight:700;border:1px solid var(--border);">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label" style="font-size:12px;color:var(--fg2);">Deliverables, Milestones &amp; Terms *</label>
                <textarea name="deal_terms" rows="4" required class="form-control" placeholder="Describe the expected creative deliverables, campaign milestones, reporting cadence, and payment release schedule..." style="background:#080c14;color:#fff;border:1px solid var(--border);font-size:13px;"></textarea>
            </div>

            <div style="display:flex;gap:12px;justify-content:flex-end;">
                <button type="button" class="btn-outline-dark btn-sm rounded-pill px-3" onclick="document.getElementById('dealModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-accent btn-sm rounded-pill px-4 fw-bold text-dark">
                    <i class="ph-bold ph-paper-plane-tilt me-1"></i> Send Deal Proposal
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
