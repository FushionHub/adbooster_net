<?php declare(strict_types=1);

/**
 * Status Monitor — outage detection, incident documentation and owner alerts.
 *
 * The status page probes every service on each request and writes the result
 * to `service_check_log`. This module turns that history into:
 *   • incident records (what broke, when, and WHY),
 *   • an email to the platform owner the moment a service goes down and the
 *     moment it recovers,
 *   • a recovery notification when the check passes again.
 *
 * Alerts fire on state transitions only, so a permanently broken check cannot
 * flood the owner's inbox.
 */

require_once __DIR__ . '/../config/db.php';

class StatusMonitor
{
    /**
     * Turn a raw probe error into a human explanation of WHY the check failed.
     */
    public static function causeFor(string $key, string $detail): string
    {
        $detail = trim((string)$detail);

        $cause = match ($key) {
            'database'   => 'The database did not answer the health query — either the connection dropped, the server is overloaded, or the schema is mid-migration.',
            'runtime'    => 'The PHP runtime reported a version or fatal error — the web server may have restarted with a broken configuration.',
            'storage'    => 'The uploads directory is missing, not writable by the web user, or the disk is below the 200 MB safety margin.',
            'cache'      => 'The cache / rate-limit directory could not be created or written, which also breaks API rate limiting.',
            'image_cdn'  => 'The Google image endpoint was unreachable or rejected the configured Gemini key (401/403) — AI creative generation is degraded until the key is valid again.',
            'ai_script'  => 'The Groq script-writing endpoint was unreachable or rejected the configured key — copy generation falls back to the other providers.',
            'rest_api'   => 'The REST API bootstrap could not read its tables, so API clients would receive errors.',
            'webhooks'   => 'The webhook registry or dispatcher could not be reached, so outbound event delivery is paused.',
            default      => 'The health probe did not meet its success criteria.',
        };

        if ($detail !== '') {
            $cause .= ' Reported detail: ' . $detail;
        }
        return $cause;
    }

    /**
     * Detect an outage/recovery transition for a check and act on it.
     *
     * @param array $check { key, label, ok, note }
     * @return string|null 'incident' | 'recovery' | null
     */
    public static function handleTransition(array $check): ?string
    {
        $key = (string)($check['key'] ?? '');
        if ($key === '') {
            return null;
        }

        try {
            $recent = db()->fetchAll(
                "SELECT ok FROM service_check_log WHERE check_key = ? ORDER BY id DESC LIMIT 2",
                [$key]
            );
            if (count($recent) < 2) {
                return null;
            }

            $nowOk  = ((int)$recent[0]['ok'] === 1);
            $prevOk = ((int)$recent[1]['ok'] === 1);

            if (!$nowOk && $prevOk) {
                self::openIncident($check);
                return 'incident';
            }
            if ($nowOk && !$prevOk) {
                self::closeIncident($check);
                return 'recovery';
            }
        } catch (\Throwable $e) {
            error_log('StatusMonitor::handleTransition failed: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Record an outage and alert the platform owner.
     */
    public static function openIncident(array $check): ?array
    {
        try {
            $existing = db()->fetchOne(
                "SELECT id FROM service_incidents WHERE check_key = ? AND status = 'open' ORDER BY id DESC LIMIT 1",
                [(string)$check['key']]
            );
            if ($existing) {
                return null; // already open — not a new transition
            }

            $cause = self::causeFor((string)$check['key'], (string)($check['note'] ?? ''));
            $incidentId = db()->insert('service_incidents', [
                'check_key'   => (string)$check['key'],
                'check_label' => (string)($check['label'] ?? $check['key']),
                'severity'    => 'major',
                'title'       => (string)($check['label'] ?? $check['key']) . ' outage',
                'cause'       => $cause,
                'detail'      => mb_substr((string)($check['note'] ?? ''), 0, 1000),
                'status'      => 'open',
                'started_at'  => date('Y-m-d H:i:s'),
                'notified'    => 0,
            ]);

            $notified = false;
            try {
                require_once __DIR__ . '/notifier.php';
                $notified = Notifier::developer('downtime_alert', [
                    'check_key'   => (string)$check['key'],
                    'check_label' => (string)($check['label'] ?? $check['key']),
                    'error'       => mb_substr((string)($check['note'] ?? ''), 0, 500),
                    'cause'       => $cause,
                    'status'      => 'DOWN',
                    'reference'   => 'incident #' . (int)$incidentId,
                    'status_url'  => (defined('BASE_URL') ? BASE_URL : '') . '/status',
                    'time'        => date('c'),
                ]);
            } catch (\Throwable $e) {
                error_log('Downtime alert email failed: ' . $e->getMessage());
            }

            if ($notified) {
                db()->update('service_incidents', ['notified' => 1], 'id = ?', [$incidentId]);
            }

            return db()->fetchOne("SELECT * FROM service_incidents WHERE id = ?", [$incidentId]);
        } catch (\Throwable $e) {
            error_log('StatusMonitor::openIncident failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Close an incident when the check recovers and notify the owner.
     */
    public static function closeIncident(array $check): ?array
    {
        try {
            $open = db()->fetchOne(
                "SELECT * FROM service_incidents WHERE check_key = ? AND status = 'open' ORDER BY id DESC LIMIT 1",
                [(string)$check['key']]
            );
            if (!$open) {
                return null;
            }

            db()->update('service_incidents', [
                'status'      => 'resolved',
                'resolved_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int)$open['id']]);

            try {
                require_once __DIR__ . '/notifier.php';
                Notifier::developer('downtime_recovery', [
                    'check_key'   => (string)$check['key'],
                    'check_label' => (string)($check['label'] ?? $check['key']),
                    'error'       => mb_substr((string)($open['detail'] ?? ''), 0, 500),
                    'reference'   => 'incident #' . (int)$open['id'],
                    'status'      => 'RECOVERED',
                    'status_url'  => (defined('BASE_URL') ? BASE_URL : '') . '/status',
                    'time'        => date('c'),
                ]);
            } catch (\Throwable $e) {
                error_log('Downtime recovery email failed: ' . $e->getMessage());
            }

            return db()->fetchOne("SELECT * FROM service_incidents WHERE id = ?", [(int)$open['id']]);
        } catch (\Throwable $e) {
            error_log('StatusMonitor::closeIncident failed: ' . $e->getMessage());
            return null;
        }
    }
}
