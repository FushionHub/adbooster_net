<?php declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * Ad Policy & Brand-Safety Scanner Module for Adsbooster.net
 * 
 * Scans ad content for policy compliance, checks media URLs,
 * and logs scan results for audit trails.
 */
class PolicyScanner
{
    /**
     * Scan content for policy compliance
     *
     * @param string $text The ad text to scan
     * @param string|null $mediaUrl Optional media URL to validate
     * @param string $platform Target platform (facebook, instagram, google, tiktok)
     * @return array Compliance result with score (0-100) and issues array
     * @throws Exception On database or processing error
     */
    public static function scan(string $text, ?string $mediaUrl, string $platform): array
    {
        try {
            $db = db();
            $issues = [];
            $score = 100;

            // Get platform-specific rules
            $rules = self::getRules($platform);

            // Text pattern matching
            foreach ($rules['text_rules'] as $rule) {
                if (preg_match($rule['pattern'], $text)) {
                    $issues[] = [
                        'type' => 'text_violation',
                        'rule' => $rule['name'],
                        'severity' => $rule['severity'],
                        'message' => $rule['message'],
                        'suggestion' => $rule['suggestion'] ?? ''
                    ];
                    $score -= $rule['penalty'];
                }
            }

            // Content length checks
            $lengthRules = [
                'headline' => ['min' => 5, 'max' => 40],
                'body' => ['min' => 10, 'max' => 125],
                'description' => ['min' => 0, 'max' => 30]
            ];

            if (strlen($text) > 500) {
                $issues[] = [
                    'type' => 'content_length',
                    'rule' => 'excessive_length',
                    'severity' => 'warning',
                    'message' => 'Content exceeds recommended length',
                    'suggestion' => 'Keep content concise for better engagement'
                ];
                $score -= 5;
            }

            // URL reachability check
            if ($mediaUrl) {
                $urlCheck = self::checkUrlReachability($mediaUrl);
                if (!$urlCheck['reachable']) {
                    $issues[] = [
                        'type' => 'media_error',
                        'rule' => 'url_unreachable',
                        'severity' => 'error',
                        'message' => 'Media URL is not accessible: ' . ($urlCheck['error'] ?? 'Unknown error'),
                        'suggestion' => 'Verify the media URL is correct and publicly accessible'
                    ];
                    $score -= 20;
                }

                // Basic media validation
                $mediaCheck = self::validateMediaType($mediaUrl);
                if (!$mediaCheck['valid']) {
                    $issues[] = [
                        'type' => 'media_error',
                        'rule' => 'invalid_media_type',
                        'severity' => 'error',
                        'message' => $mediaCheck['message'],
                        'suggestion' => 'Use supported media formats (JPG, PNG, GIF, MP4)'
                    ];
                    $score -= 15;
                }
            }

            // Platform-specific additional checks
            $platformIssues = self::checkPlatformSpecific($text, $platform);
            $issues = array_merge($issues, $platformIssues);
            $score -= array_sum(array_column($platformIssues, 'penalty'));

            // Ensure score stays within bounds
            $score = max(0, min(100, $score));

            return [
                'score' => $score,
                'issues' => $issues,
                'platform' => $platform,
                'compliant' => $score >= 70,
                'scanned_at' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            error_log("PolicyScanner::scan Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get policy rules for a platform
     *
     * @param string|null $platform Platform name or null for all rules
     * @return array Policy rules array
     * @throws Exception On database error
     */
    public static function getRules(?string $platform): array
    {
        try {
            $db = db();

            if ($platform) {
                $stmt = $db->prepare(
                    "SELECT * FROM policy_rules WHERE platform = ? OR platform = 'all' ORDER BY category, name"
                );
                $stmt->execute([$platform]);
            } else {
                $stmt = $db->query(
                    "SELECT * FROM policy_rules ORDER BY platform, category, name"
                );
            }

            $dbRules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Merge with built-in rules for comprehensive coverage
            $builtinRules = self::getBuiltInRules();
            
            return [
                'database_rules' => $dbRules,
                'text_rules' => $builtinRules['text_rules'],
                'platform_rules' => $builtinRules['platform_rules'][$platform] ?? []
            ];
        } catch (Exception $e) {
            error_log("PolicyScanner::getRules Error: " . $e->getMessage());
            // Fall back to built-in rules
            return self::getBuiltInRules();
        }
    }

    /**
     * Log a scan result for audit purposes
     *
     * @param int $userId The user ID
     * @param int|null $campaignId Optional campaign ID
     * @param int|null $postId Optional post ID
     * @param int $score Compliance score (0-100)
     * @param array $issues Array of issues found
     * @return void
     * @throws Exception On database error
     */
    public static function logScan(int $userId, ?int $campaignId, ?int $postId, int $score, array $issues): void
    {
        try {
            $db = db();

            $stmt = $db->prepare(
                "INSERT INTO policy_scan_logs (user_id, campaign_id, post_id, score, issues, scanned_at) 
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $userId,
                $campaignId,
                $postId,
                $score,
                json_encode($issues)
            ]);
        } catch (Exception $e) {
            error_log("PolicyScanner::logScan Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if a URL is reachable
     *
     * @param string $url The URL to check
     * @return array Result with reachable flag and optional error message
     */
    private static function checkUrlReachability(string $url): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['reachable' => false, 'error' => 'Invalid URL format'];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            ca_curl_setopt($ch);
    
            curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['reachable' => false, 'error' => $error];
        }

        return ['reachable' => $httpCode >= 200 && $httpCode < 400];
    }

    /**
     * Validate media URL type by extension
     *
     * @param string $url The media URL
     * @return array Validation result
     */
    private static function validateMediaType(string $url): array
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi'];
        $path = parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions)) {
            return [
                'valid' => false,
                'message' => "Unsupported media type: .{$extension}"
            ];
        }

        return ['valid' => true];
    }

    /**
     * Check platform-specific content rules
     *
     * @param string $text Content text
     * @param string $platform Platform name
     * @return array Array of platform-specific issues
     */
    private static function checkPlatformSpecific(string $text, string $platform): array
    {
        $issues = [];

        $platformLimits = [
            'facebook' => ['headline' => 40, 'body' => 125],
            'instagram' => ['headline' => 40, 'body' => 2200],
            'google' => ['headline' => 30, 'body' => 90],
            'tiktok' => ['headline' => 80, 'body' => 2200]
        ];

        $limits = $platformLimits[$platform] ?? null;

        if ($limits) {
            if (strlen($text) > $limits['body']) {
                $issues[] = [
                    'type' => 'platform_limit',
                    'rule' => "{$platform}_body_length",
                    'severity' => 'warning',
                    'message' => "Content exceeds {$platform} body limit of {$limits['body']} characters",
                    'penalty' => 5
                ];
            }
        }

        // TikTok-specific: check for competitor mentions
        if ($platform === 'tiktok') {
            $competitors = ['instagram', 'youtube', 'snapchat', 'facebook'];
            foreach ($competitors as $competitor) {
                if (stripos($text, $competitor) !== false) {
                    $issues[] = [
                        'type' => 'platform_violation',
                        'rule' => 'tiktok_competitor_mention',
                        'severity' => 'error',
                        'message' => "TikTok ads should not mention competitor platforms",
                        'penalty' => 15
                    ];
                    break;
                }
            }
        }

        return $issues;
    }

    /**
     * Get built-in policy rules
     *
     * @return array Built-in rules for text and platforms
     */
    private static function getBuiltInRules(): array
    {
        return [
            'text_rules' => [
                [
                    'name' => 'prohibited_claims',
                    'pattern' => '/\b(cure|guarantee|miracle|instant|100%\s*effective)\b/i',
                    'severity' => 'error',
                    'penalty' => 25,
                    'message' => 'Contains prohibited health/miracle claims',
                    'suggestion' => 'Use factual, measurable claims instead'
                ],
                [
                    'name' => 'excessive_caps',
                    'pattern' => '/[A-Z]{5,}/',
                    'severity' => 'warning',
                    'penalty' => 5,
                    'message' => 'Excessive use of capital letters',
                    'suggestion' => 'Use proper capitalization for better readability'
                ],
                [
                    'name' => 'spammy_punctuation',
                    'pattern' => '/[!]{2,}|[?]{2,}|[.]{3,}/',
                    'severity' => 'warning',
                    'penalty' => 5,
                    'message' => 'Excessive punctuation detected',
                    'suggestion' => 'Use single punctuation marks for professional tone'
                ],
                [
                    'name' => 'misleading_urgency',
                    'pattern' => '/\b(last\s+chance|expires?\s+today|limited\s+time|act\s+now|don\'?t\s+miss)\b/i',
                    'severity' => 'warning',
                    'penalty' => 10,
                    'message' => 'Potential misleading urgency language',
                    'suggestion' => 'Provide specific deadlines if urgency is genuine'
                ],
                [
                    'name' => 'discriminatory_language',
                    'pattern' => '/\b(exclude|no\s+(?:gays?|women|men|disabled|black|white|asian|hispanic))\b/i',
                    'severity' => 'error',
                    'penalty' => 30,
                    'message' => 'Contains potentially discriminatory language',
                    'suggestion' => 'Review ad for inclusive targeting practices'
                ]
            ],
            'platform_rules' => [
                'facebook' => [
                    'no_personal_attributes' => 'Ads must not contain content asserting or implying personal attributes'
                ],
                'google' => [
                    'no_misleading_url' => 'Display URL must match the destination URL domain'
                ],
                'tiktok' => [
                    'no_static_watermark' => 'Videos should not contain watermarks from other platforms'
                ]
            ]
        ];
    }
}
