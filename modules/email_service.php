<?php declare(strict_types=1);

/**
 * Branded HTML Email Service with PHPMailer Integration
 * Adsbooster.net
 *
 * Implements an email-first architecture with responsive HTML templates,
 * PHPMailer SMTP transport, and audit logging.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/apikeys.php';

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

class EmailService
{
    private const BRAND_PRIMARY = '#c8ff00';
    private const BRAND_DARK    = '#080c14';
    private const BRAND_SURFACE = '#0d1524';
    private const BRAND_BORDER  = '#1e293b';
    private const BRAND_TEXT    = '#f8fafc';
    private const BRAND_MUTED   = '#94a3b8';
    private const COMPANY_NAME  = 'Adsbooster.net';
    private const COMPANY_URL   = 'https://adsbooster.net';
    private const SUPPORT_EMAIL = 'support@adsbooster.net';

    /**
     * Send a branded HTML email using the specified template style.
     */
    public static function sendBrandedMail(
        string $to,
        string $subject,
        string $body,
        string $templateStyle = 'modern'
    ): array {
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid recipient email address.'];
        }

        if (empty(trim($subject))) {
            return ['success' => false, 'message' => 'Subject cannot be empty.'];
        }

        $htmlBody = self::wrapInTemplate($body, $templateStyle);

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . self::COMPANY_NAME . ' <' . self::SUPPORT_EMAIL . '>',
            'Reply-To: ' . self::SUPPORT_EMAIL,
            'X-Mailer: AdsboosterPHPMailer/2.0',
        ];

        $result = self::sendMail($to, $subject, $htmlBody, $headers);

        self::logEmail($to, $subject, 'branded', $result['success'] ? 'sent' : 'failed', null, $htmlBody);

        return $result;
    }

    /**
     * Send direct email composed by a user.
     */
    public static function sendDirectEmail(
        string $toEmail,
        string $subject,
        string $htmlContent,
        ?int $senderUserId = null,
        string $replyTo = ''
    ): array {
        if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid recipient email address.'];
        }

        $senderName = self::COMPANY_NAME;
        if ($senderUserId) {
            $sender = db()->fetchOne("SELECT full_name, email FROM users WHERE id = ?", [$senderUserId]);
            if ($sender) {
                $senderName = $sender['full_name'] . ' via ' . self::COMPANY_NAME;
                if (empty($replyTo)) {
                    $replyTo = $sender['email'];
                }
            }
        }

        $wrapped = self::wrapInTemplate($htmlContent, 'modern');

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $senderName . ' <' . self::SUPPORT_EMAIL . '>',
            'Reply-To: ' . (!empty($replyTo) ? $replyTo : self::SUPPORT_EMAIL),
            'X-Mailer: AdsboosterPHPMailer/2.0',
        ];

        $result = self::sendMail($toEmail, $subject, $wrapped, $headers, $replyTo);

        self::logEmail($toEmail, $subject, 'user_outreach', $result['success'] ? 'sent' : 'failed', $senderUserId, $htmlContent);

        return $result;
    }

    /**
     * Send a transactional event email based on type.
     */
    public static function sendTransactional(string $to, string $type, array $data): array
    {
        $templates = self::getTemplates();

        if (!isset($templates[$type])) {
            return ['success' => false, 'message' => "Unknown transactional template: {$type}"];
        }

        $template = $templates[$type];
        $subject  = $template['subject'];
        $body     = $template['body'];

        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $subject = str_replace('{{' . $key . '}}', (string)$value, $subject);
                $body    = str_replace('{{' . $key . '}}', (string)$value, $body);
            }
        }

        $wrapped = self::wrapInTemplate($body, 'modern');

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . self::COMPANY_NAME . ' <' . self::SUPPORT_EMAIL . '>',
            'Reply-To: ' . self::SUPPORT_EMAIL,
            'X-Mailer: AdsboosterPHPMailer/2.0',
        ];

        $result = self::sendMail($to, $subject, $wrapped, $headers);

        self::logEmail($to, $subject, $type, $result['success'] ? 'sent' : 'failed', null, $wrapped);

        return $result;
    }

    /**
     * Send email using PHPMailer when available, falling back to PHP mail().
     */
    private static function sendMail(
        string $to,
        string $subject,
        string $body,
        array $headers,
        string $customReplyTo = ''
    ): array {
        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->CharSet = 'UTF-8';

                $smtpHost  = api_key('SMTP_HOST', 'smtp_host', '');
                $smtpUser  = api_key('SMTP_USER', 'smtp_username', '');
                $smtpPass  = api_key('SMTP_PASS', 'smtp_password', '');
                $smtpPort  = (int)api_key('SMTP_PORT', 'smtp_port', '587');
                $fromEmail = api_key('FROM_EMAIL', 'from_email', self::SUPPORT_EMAIL);
                $fromName  = api_key('FROM_NAME', 'from_name', self::COMPANY_NAME);

                if (!empty($smtpHost) && !empty($smtpUser)) {
                    $mail->isSMTP();
                    $mail->Host       = $smtpHost;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $smtpUser;
                    $mail->Password   = $smtpPass;
                    $mail->SMTPSecure = ($smtpPort === 465)
                        ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                        : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = $smtpPort;
                    $mail->Timeout    = 6;
                } else {
                    $mail->isMail();
                }

                $mail->setFrom($fromEmail, $fromName);
                $mail->addAddress($to);

                if (!empty($customReplyTo)) {
                    $mail->addReplyTo($customReplyTo);
                } else {
                    $mail->addReplyTo(self::SUPPORT_EMAIL, self::COMPANY_NAME);
                }

                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $body;
                $mail->AltBody = strip_tags($body);

                $mail->send();
                return ['success' => true, 'message' => 'Email sent successfully via PHPMailer.'];
            } catch (\Throwable $e) {
                error_log("PHPMailer transport error: " . $e->getMessage() . " — Falling back to native mail()");
            }
        }

        $headerString = implode("\r\n", $headers);
        try {
            $sent = @mail($to, $subject, $body, $headerString);
            if ($sent) {
                return ['success' => true, 'message' => 'Email sent successfully via native mail().'];
            }
            return ['success' => false, 'message' => 'Email transport could not deliver message.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Wrap content in Adsbooster dark luxury HTML template.
     */
    private static function wrapInTemplate(string $content, string $style): string
    {
        $siteName = self::COMPANY_NAME;
        $accent   = self::BRAND_PRIMARY;
        $darkBg   = self::BRAND_DARK;
        $surface  = self::BRAND_SURFACE;
        $text     = self::BRAND_TEXT;
        $muted    = self::BRAND_MUTED;
        $border   = self::BRAND_BORDER;
        $year     = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$siteName}</title>
<style>
  body { margin: 0; padding: 0; background-color: {$darkBg}; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: {$text}; }
  .email-container { max-width: 600px; margin: 40px auto; background-color: {$surface}; border: 1px solid {$border}; border-radius: 16px; overflow: hidden; }
  .email-header { padding: 32px 36px 24px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.06); }
  .email-brand { font-size: 24px; font-weight: 800; color: #ffffff; text-decoration: none; letter-spacing: -0.5px; }
  .email-brand span { color: {$accent}; }
  .email-body { padding: 36px; line-height: 1.6; font-size: 15px; color: {$text}; }
  .email-body h1, .email-body h2, .email-body h3 { color: #ffffff; margin-top: 0; }
  .email-body a { color: {$accent}; }
  .btn-action { display: inline-block; background-color: {$accent}; color: #080c14 !important; font-weight: 700; font-size: 14px; padding: 13px 30px; text-decoration: none; border-radius: 10px; margin: 20px 0; }
  .code-box { background: #080c14; border: 1px solid {$accent}; border-radius: 12px; padding: 20px; text-align: center; font-size: 32px; font-family: monospace; font-weight: 800; letter-spacing: 8px; color: {$accent}; margin: 24px 0; }
  .email-footer { padding: 24px 36px; background-color: #060910; border-top: 1px solid {$border}; text-align: center; font-size: 12px; color: {$muted}; }
</style>
</head>
<body>
<div class="email-container">
  <div class="email-header">
    <a href="https://adsbooster.net" class="email-brand">Adsbooster<span>.net</span></a>
  </div>
  <div class="email-body">
    {$content}
  </div>
  <div class="email-footer">
    <p style="margin:0 0 8px;">&copy; {$year} {$siteName}. Multi-network ad automation, social scheduler &amp; directory.</p>
    <p style="margin:0;">Need assistance? Contact our team at <a href="mailto:support@adsbooster.net" style="color:{$muted};">support@adsbooster.net</a></p>
  </div>
</div>
</body>
</html>
HTML;
    }

    /**
     * Get transaction templates.
     */
    private static function getTemplates(): array
    {
        return [
            'welcome' => [
                'subject' => 'Welcome to Adsbooster.net — Scale Your Business',
                'body' => '
                    <h2>Welcome to the Network, {{name}}! 👋</h2>
                    <p>Your Adsbooster account is active and ready. You now have access to multi-network ad publishing across Meta, Instagram, TikTok, and Google Ads, automated scheduling, the verified business directory, and our connection network.</p>
                    <div style="text-align:center;">
                        <a href="https://adsbooster.net/dashboard" class="btn-action">Go to My Dashboard &rarr;</a>
                    </div>
                    <p style="color:#94a3b8;font-size:13px;">If you have any questions, our team and AI Consultant are ready to assist you 24/7.</p>',
            ],
            'two_factor_code' => [
                'subject' => 'Your Adsbooster Security Verification Code: {{code}}',
                'body' => '
                    <h2>Two-Factor Security Verification 🔒</h2>
                    <p>Hi {{name}},</p>
                    <p>Use the following 6-digit one-time code to complete your login or security confirmation:</p>
                    <div class="code-box">{{code}}</div>
                    <p style="color:#94a3b8;font-size:13px;text-align:center;">This code expires in 10 minutes. If you did not attempt to sign in, please update your account password immediately.</p>',
            ],
            'login_alert' => [
                'subject' => 'Security Notice: New Login to Your Account',
                'body' => '
                    <h2>New Login Detected 🛡️</h2>
                    <p>Hi {{name}},</p>
                    <p>A new login was detected on your Adsbooster account:</p>
                    <div style="background:#080c14;border:1px solid #1e293b;border-radius:10px;padding:16px;margin:20px 0;">
                        <p style="margin:0 0 6px;"><b>IP Address:</b> {{ip}}</p>
                        <p style="margin:0 0 6px;"><b>Time:</b> {{time}}</p>
                        <p style="margin:0;"><b>Location:</b> {{location}}</p>
                    </div>
                    <p style="color:#94a3b8;font-size:13px;">If this was you, no action is needed. If you did not authorize this login, reset your password immediately from the privacy center.</p>',
            ],
            'p2p_message' => [
                'subject' => 'New Message from {{sender_name}} on Adsbooster',
                'body' => '
                    <h2>You Have a New Message 💬</h2>
                    <p>Hi {{name}},</p>
                    <p><b>{{sender_name}}</b> sent you a direct message on Adsbooster:</p>
                    <div style="background:#080c14;border-left:3px solid #c8ff00;border-radius:6px;padding:16px 20px;margin:20px 0;font-style:italic;">
                        "{{preview}}"
                    </div>
                    <div style="text-align:center;">
                        <a href="https://adsbooster.net/p2p-chat?user={{sender_id}}" class="btn-action">Reply to Message &rarr;</a>
                    </div>',
            ],
            'dating_match' => [
                'subject' => '❤️ Mutual Match on Adsbooster Connections!',
                'body' => '
                    <h2>You and {{match_name}} Paired! 🎉</h2>
                    <p>Hi {{name}},</p>
                    <p>Exciting news! You and <b>{{match_name}}</b> have mutually paired on the Adsbooster Connection Network.</p>
                    <div style="text-align:center;margin:30px 0;">
                        <a href="https://adsbooster.net/p2p-chat?user={{match_id}}" class="btn-action" style="background:#ec4899;color:#fff !important;">Start Chatting Now &rarr;</a>
                    </div>',
            ],
            'campaign_status' => [
                'subject' => 'Campaign Update: {{campaign_name}} is {{status}}',
                'body' => '
                    <h2>Campaign Status Update 🚀</h2>
                    <p>Hi {{name}},</p>
                    <p>Your ad campaign <b>{{campaign_name}}</b> on {{platform}} is now <b>{{status}}</b>.</p>
                    <p style="color:#94a3b8;font-size:13px;">{{details}}</p>
                    <div style="text-align:center;">
                        <a href="https://adsbooster.net/campaigns" class="btn-action">View Campaign Analytics &rarr;</a>
                    </div>',
            ],
            'team_invite' => [
                'subject' => 'Team Collaboration Invitation on Adsbooster.net',
                'body' => '
                    <h2>You\'ve Been Invited to Join a Team 👥</h2>
                    <p>Hi,</p>
                    <p><b>{{inviter_name}}</b> has invited you to collaborate on their Adsbooster workspace as a <b>{{role}}</b>.</p>
                    <div style="text-align:center;">
                        <a href="{{invite_link}}" class="btn-action">Accept Invitation &rarr;</a>
                    </div>
                    <p style="color:#94a3b8;font-size:13px;">This invitation link will remain active for 7 days.</p>',
            ],
            'payout_status' => [
                'subject' => 'Referral Payout Request: {{status}}',
                'body' => '
                    <h2>Payout Request Update 💰</h2>
                    <p>Hi {{name}},</p>
                    <p>Your referral commission payout request of <b>\${{amount}}</b> via {{method}} has been marked as <b>{{status}}</b>.</p>
                    <p style="color:#94a3b8;font-size:13px;">{{notes}}</p>',
            ],
            'password_reset' => [
                'subject' => 'Password Reset Verification Code - Adsbooster',
                'body' => '
                    <h2>Password Reset Request 🔐</h2>
                    <p>Hi {{name}},</p>
                    <p>We received a request to reset your Adsbooster account password. Use the single-use 6-digit security code below to proceed with setting your new password:</p>
                    <div class="code-box">{{code}}</div>
                    <p style="color:#94a3b8;font-size:13px;">This security code will expire in 15 minutes. If you did not make this request, you can safely ignore this email; your current password remains secure.</p>',
            ],
        ];
    }

    /**
     * Audit log of sent emails.
     */
    private static function logEmail(
        string $to,
        string $subject,
        string $type,
        string $status,
        ?int $senderId = null,
        string $body = ''
    ): void {
        try {
            db()->insert('email_logs', [
                'sender_id' => $senderId,
                'recipient' => $to,
                'subject'   => $subject,
                'body'      => $body,
                'type'      => $type,
                'status'    => $status,
                'sent_at'   => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log("Failed to log email: " . $e->getMessage());
        }
    }
}
