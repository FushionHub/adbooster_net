<?php declare(strict_types=1);

/**
 * Google OAuth 2.0 Integration & Social Login
 * Adsbooster.net
 *
 * Handles redirect, callback, token exchange, user creation & session initialization.
 */

require_once __DIR__ . '/../config/site_setting_and_config.php';
require_once __DIR__ . '/../config/apikeys.php';

class GoogleAuth
{
    /**
     * Identity-only scopes. Adsbooster signs users in and reads name/email/avatar;
     * every calendar / mail / drive / platform capability is routed through the
     * Bundle.social + Zernio gateways, so no extra Google scopes are requested.
     */
    private const SCOPES = [
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
    ];

    public static function getClientId(): string
    {
        return api_key('GOOGLE_CLIENT_ID', 'google_client_id', '');
    }

    public static function getClientSecret(): string
    {
        return api_key('GOOGLE_CLIENT_SECRET', 'google_client_secret', '');
    }

    public static function getRedirectUri(): string
    {
        return BASE_URL . '/modules/google_auth.php?action=callback';
    }

    public static function getAuthorizationUrl(): string
    {
        $clientId = self::getClientId();
        if ($clientId === '') {
            // SECURITY: no mock/dry-run fallback. A fabricated callback used to
            // mint a fully-authenticated session for any visitor.
            throw new RuntimeException(
                'Google Sign-In is not configured on this site yet. '
                . 'Set GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET (env var, config/keys.local.php '
                . 'or Admin → Settings) to enable it.'
            );
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['google_oauth_state'] = $state;

        $params = http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => self::getRedirectUri(),
            'response_type' => 'code',
            'scope'         => implode(' ', self::SCOPES),
            'access_type'   => 'offline',
            'prompt'        => 'select_account',
            'state'         => $state,
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
    }

    /**
     * Validate the OAuth `state` parameter (CSRF protection).
     */
    public static function validateState(?string $state): bool
    {
        $expected = (string)($_SESSION['google_oauth_state'] ?? '');
        unset($_SESSION['google_oauth_state']);
        if ($expected === '' || $state === null || $state === '') {
            return false;
        }
        return hash_equals($expected, $state);
    }

    public static function exchangeCode(string $code): array
    {
        $clientId     = self::getClientId();
        $clientSecret = self::getClientSecret();

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Google OAuth credentials are not configured.');
        }
        if (str_starts_with($code, 'mock_')) {
            // Explicitly refuse legacy mock codes.
            throw new RuntimeException('Rejected a non-production authorization code.');
        }

        $payload = http_build_query([
            'code'          => $code,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => self::getRedirectUri(),
            'grant_type'    => 'authorization_code',
        ]);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = is_string($response) ? json_decode($response, true) : null;
        if (!is_array($decoded) || empty($decoded['access_token'])) {
            $reason = is_array($decoded)
                ? (string)($decoded['error_description'] ?? $decoded['error'] ?? 'unknown error')
                : 'no response from Google';
            throw new RuntimeException('Google token exchange failed: ' . $reason);
        }
        return $decoded;
    }

    public static function getUserInfo(string $accessToken): array
    {
        $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = is_string($response) ? json_decode($response, true) : null;
        if (!is_array($data) || empty($data['email'])) {
            throw new RuntimeException('Google did not return an email address.');
        }

        // SECURITY: only accounts whose email Google has verified may be used
        // for automatic login/registration. Unverified addresses (e.g. an
        // attacker pre-proving an arbitrary address on their own Google
        // account) must not be able to take over another user's email.
        $verified = !empty($data['verified_email']) || !empty($data['email_verified']);
        if (!$verified) {
            throw new RuntimeException('Sign-in refused: the Google account email is not verified.');
        }

        return [
            'id'      => (string)($data['id'] ?? 'g_' . bin2hex(random_bytes(6))),
            'email'   => (string)$data['email'],
            'name'    => (string)($data['name'] ?? 'Google User'),
            'picture' => (string)($data['picture'] ?? ''),
            'verified_email' => true,
        ];
    }
}

$action = strtolower(trim($_GET['action'] ?? ''));

switch ($action) {
    case 'login':
    case 'register':
    case 'redirect':
        try {
            $url = GoogleAuth::getAuthorizationUrl();
        } catch (\Throwable $e) {
            set_flash('error', $e->getMessage());
            redirect(BASE_URL . '/login.php');
        }
        header('Location: ' . $url);
        exit;

    case 'callback':
        $code  = (string)($_GET['code'] ?? '');
        $state = (string)($_GET['state'] ?? '');

        if ($code === '') {
            set_flash('error', 'Google authentication was cancelled or the authorization code is missing.');
            redirect(BASE_URL . '/login.php');
        }

        // CSRF: the state we issued must come back unchanged.
        if (!GoogleAuth::validateState($state)) {
            set_flash('error', 'Google sign-in could not be verified (invalid state). Please try again.');
            redirect(BASE_URL . '/login.php');
        }

        try {
            $tokens   = GoogleAuth::exchangeCode($code);
            $userInfo = GoogleAuth::getUserInfo((string)$tokens['access_token']);
        } catch (\Throwable $e) {
            error_log('google_auth callback: ' . $e->getMessage());
            set_flash('error', 'Google sign-in failed: ' . $e->getMessage());
            redirect(BASE_URL . '/login.php');
        }

        $_SESSION['google_access_token'] = $tokens['access_token'];
        if (!empty($tokens['refresh_token'])) {
            $_SESSION['google_refresh_token'] = $tokens['refresh_token'];
        }
        $_SESSION['google_user_info'] = $userInfo;

        // Check if user already exists in DB
        $userEmail = strtolower(trim($userInfo['email']));
        $user = db()->fetch("SELECT * FROM users WHERE google_id = ? OR email = ?", [$userInfo['id'], $userEmail]);

        if ($user) {
            // SECURITY: an account cannot sign in (or link Google to itself)
            // while suspended/banned. This runs before any session issuance.
            if (($user['status'] ?? 'active') !== 'active') {
                error_log('google_auth: login refused for non-active user id=' . (int)$user['id']);
                set_flash('error', 'This account has been suspended. Please contact support.');
                redirect(BASE_URL . '/login.php');
            }

            $linkUpdate = [];
            if (empty($user['google_id'])) {
                $linkUpdate['google_id'] = $userInfo['id'];
            }
            if (!empty($userInfo['picture']) && ($user['avatar'] ?? '') !== $userInfo['picture']) {
                $linkUpdate['avatar'] = $userInfo['picture'];
            }
            if ($linkUpdate) {
                db()->update('users', $linkUpdate, 'id = ?', [(int)$user['id']]);
                $user = array_merge($user, $linkUpdate);
            }
            auth_login($user);
            set_flash('success', 'Welcome back, ' . htmlspecialchars((string)$user['full_name']) . '! Logged in via Google.');
            redirect(BASE_URL . '/dashboard.php');
        } else {
            // Auto-register new Google user
            $referralCode = strtoupper(substr(md5($userInfo['id'] . time()), 0, 8));
            $refCode = trim($_SESSION['ref_code'] ?? ($_COOKIE['ref_code'] ?? ''));
            $referrerId = null;

            if ($refCode !== '') {
                $refUser = db()->fetch("SELECT id FROM users WHERE referral_code = ?", [$refCode]);
                if ($refUser) {
                    $referrerId = (int)$refUser['id'];
                }
            }

            $newUserId = db()->insert('users', [
                'full_name'            => $userInfo['name'] ?: 'Google User',
                'email'                => $userEmail,
                'password'             => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                'google_id'            => $userInfo['id'],
                'avatar'               => !empty($userInfo['picture']) ? $userInfo['picture'] : null,
                'role'                 => 'user',
                'account_type'         => 'individual',
                'referral_code'        => $referralCode,
                'referred_by'          => $referrerId,
                'ad_credits'           => 5.00,
                'ai_credits'           => 25,
                'onboarding_completed' => 1,
                'status'               => 'active',
            ]);

            if ($referrerId) {
                try {
                    db()->insert('referrals', [
                        'referrer_id'    => $referrerId,
                        'referred_id'    => $newUserId,
                        'status'         => 'pending',
                        'reward_credits' => 10.00,
                    ]);
                } catch (\Throwable $e) {}
            }

            $newUser = db()->fetch("SELECT * FROM users WHERE id = ?", [$newUserId]);
            auth_login($newUser);

            // Register the Google Calendar surface via the Zernio gateway on
            // signup (calendar lives on Zernio — Bundle.social's API has no
            // calendar surface; gateway-shaped row, no direct tokens stored).
            try {
                db()->query(
                    "INSERT INTO connected_accounts
                        (user_id, gateway_provider, account_type, platform_name, external_account_id, display_name, status)
                     VALUES (?, 'zernio', 'organic_profile', 'google_calendar', ?, ?, 'connected')
                     ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), status = 'connected'",
                    [
                        (int)$newUserId,
                        'zrn_google_calendar_' . (int)$newUserId,
                        trim((string)($userInfo['name'] ?? 'Google User')) . ' (Google Calendar via Zernio)',
                    ]
                );
            } catch (\Throwable $e) {}

            set_flash('success', 'Account created successfully with Google! 5 Ad Credits and 25 AI Credits added.');
            redirect(BASE_URL . '/dashboard.php');
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid Google Auth action.']);
        exit;
}
