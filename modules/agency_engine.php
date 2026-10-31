<?php
declare(strict_types=1);

/**
 * Full Agency Whitelabel & Client Portals Engine
 * Adsbooster.net
 */

class AgencyEngine
{
    /**
     * Get or Create Agency Whitelabel Settings
     */
    public static function getAgencyPortal(int $agencyUserId): ?array
    {
        $portal = db()->fetch("SELECT * FROM agency_portals WHERE agency_user_id = ?", [$agencyUserId]);
        if (!$portal) {
            $user = db()->fetch("SELECT * FROM users WHERE id = ?", [$agencyUserId]);
            $subdomain = 'agency-' . $agencyUserId;
            $portalId = db()->insert('agency_portals', [
                'agency_user_id' => $agencyUserId,
                'custom_subdomain' => $subdomain,
                'custom_domain' => null,
                'portal_name' => ($user['full_name'] ?? 'Growth') . ' Marketing Agency',
                'portal_logo_url' => 'assets/images/logo/logo.png',
                'brand_primary_color' => '#c8ff00',
                'brand_secondary_color' => '#080c14',
                'welcome_headline' => 'Welcome to Your Client Growth & ROI Dashboard',
                'is_whitelabel_active' => 1
            ]);
            $portal = db()->fetch("SELECT * FROM agency_portals WHERE id = ?", [$portalId]);
        }
        return $portal;
    }

    /**
     * Add Client Account with Magic Token
     */
    public static function addClientAccount(int $agencyUserId, array $params): array
    {
        $token = 'client_' . bin2hex(random_bytes(16));
        $name = trim($params['client_name'] ?? 'New Client');
        $email = trim($params['client_email'] ?? 'client@example.com');
        $company = trim($params['client_company'] ?? 'Acme Corp');
        $retainer = (float)($params['monthly_retainer'] ?? 1500.00);

        $clientId = db()->insert('agency_client_accounts', [
            'agency_user_id' => $agencyUserId,
            'client_name' => $name,
            'client_email' => $email,
            'client_company' => $company,
            'monthly_retainer' => $retainer,
            'currency' => 'USD',
            'access_token' => $token,
            'status' => 'active'
        ]);

        return [
            'id' => $clientId,
            'access_token' => $token,
            'magic_link' => BASE_URL . '/client-approval.php?token=' . $token
        ];
    }

    /**
     * Create Approval Item for Client
     */
    public static function createApprovalItem(int $agencyUserId, int $clientAccountId, array $params): int
    {
        return db()->insert('agency_client_approvals', [
            'agency_user_id' => $agencyUserId,
            'client_account_id' => $clientAccountId,
            'item_type' => $params['item_type'] ?? 'ad_campaign',
            'item_id' => $params['item_id'] ?? null,
            'title' => $params['title'] ?? 'Q4 Multi-Network Growth Campaign',
            'preview_data' => json_encode($params['preview_data'] ?? []),
            'approval_status' => 'pending'
        ]);
    }
}
