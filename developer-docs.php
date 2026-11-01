<?php declare(strict_types=1);

$pageTitle = 'Developer API Documentation (A–Z) — Adsbooster.net';
$pageDescription = 'Comprehensive reference manual for integrating Adsbooster REST API v1, multi-network social posting, ad campaigns, AI UGC image generation, and webhooks.';
require_once __DIR__ . '/includes/app_header.php';
?>

<style>
.dev-doc-nav {
    position: sticky;
    top: 90px;
}
.dev-doc-nav a {
    color: #94a3b8;
    text-decoration: none;
    display: block;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s ease;
}
.dev-doc-nav a:hover, .dev-doc-nav a.active {
    color: #c4f000;
    background: rgba(196, 240, 0, 0.08);
}
.method-pill {
    font-size: 11px;
    font-weight: 800;
    padding: 4px 8px;
    border-radius: 6px;
    letter-spacing: 0.5px;
}
.method-get { background: rgba(56, 189, 248, 0.15); color: #38bdf8; }
.method-post { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
.method-delete { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.code-block {
    background: #060910 !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 10px;
    padding: 16px;
    font-family: 'Fira Code', 'Courier New', monospace;
    font-size: 12.5px;
    color: #e2e8f0;
    position: relative;
    overflow-x: auto;
}
.copy-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #94a3b8;
    border-radius: 6px;
    font-size: 11px;
    padding: 3px 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.copy-btn:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.15);
}
</style>

<div class="w-100">
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="page-title mb-1">
                <i class="ph-bold ph-code text-accent me-2"></i> Developer Documentation &amp; REST API (A–Z)
            </h1>
            <p class="page-subtitle mb-0">Build automated workflows, generate AI UGC ad imagery, publish posts live to connected social networks, manage ad budgets, and receive signed webhooks.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= url('api-settings') ?>" class="btn btn-accent btn-sm rounded-pill px-3">
                <i class="ph ph-key me-1"></i> Developer Console
            </a>
            <a href="<?= url('status') ?>" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="ph ph-activity me-1"></i> System Status (99.99%)
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Sidebar Navigation (Sticky) -->
        <div class="col-lg-3 d-none d-lg-block">
            <div class="card-dark p-3 rounded-4 dev-doc-nav" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="text-white-50 font-size-11 fw-bold text-uppercase px-2 mb-2">Getting Started</div>
                <a href="#quickstart">Overview &amp; Base URL</a>
                <a href="#authentication">Unified API Key Auth</a>
                <a href="#ratelimits">Rate Limits (60 req/min)</a>
                <a href="#webhooks">Webhooks &amp; Signatures</a>
                <a href="#gateway-connections">Gateway Connections</a>
                
                <div class="text-white-50 font-size-11 fw-bold text-uppercase px-2 mt-3 mb-2">Endpoint Reference</div>

                <a href="#ep-credits">1. Balances &amp; Wallet</a>
                <a href="#ep-campaigns">2. Ad Campaigns</a>
                <a href="#ep-posts">3. Multi-Network Social Posts</a>
                <a href="#ep-ugc">4. AI UGC Image Studio</a>
                <a href="#ep-directory">5. Verified Directory &amp; Leads</a>
                <a href="#ep-reviews">6. Platform Reviews &amp; Ratings</a>
                                <a href="#ep-webhooks">7. Webhooks Management</a><a href="#ep-public-analytics">8. Public Analytics Feed</a>

                <div class="text-white-50 font-size-11 fw-bold text-uppercase px-2 mt-3 mb-2">Client SDKs</div>
                <a href="#js-sdk">JavaScript SDK (Node & Browser)</a>
                <a href="#sdks">cURL, JavaScript, Python, PHP</a>
            </div>
        </div>

        <!-- Main Documentation Body -->
        <div class="col-lg-9">

            <!-- Section: Overview & Base URL -->
            <div id="quickstart" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <h3 class="text-white fw-bold h5 mb-2">Getting Started with Adsbooster API v1</h3>
                <p class="text-white-50 font-size-14 mb-3" style="color:#cbd5e1 !important;">
                    The Adsbooster API provides programmatic access to our autonomous ad scaling engine, multi-channel social posting suite, AI UGC image studio, and local verified business directory. All requests require HTTPS and return standard JSON payloads.
                </p>

                <div class="d-flex align-items-center justify-content-between p-3 rounded-3 flex-wrap gap-2 mb-2" style="background:#060910;border:1px solid rgba(255,255,255,0.08);">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-main-600 bg-opacity-20 text-main-600 border border-main-600 border-opacity-30 rounded-pill px-2 font-size-11">Production Base URL</span>
                        <code class="text-accent fw-bold font-size-14"><?= BASE_URL ?>/api/v1</code>
                    </div>
                    <span class="text-white-50 font-size-12">Format: JSON / UTF-8</span>
                </div>
            </div>

            <!-- Section: Authentication -->
            <div id="authentication" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <h3 class="text-white fw-bold h5 mb-2">
                    <i class="ph-bold ph-shield-check text-accent me-1"></i> Single Master API Key Protection
                </h3>
                <p class="text-white-50 font-size-14 mb-3" style="color:#cbd5e1 !important;">
                    Adsbooster uses a single unified Master API Key for all features across ad campaigns, social publishing, AI image generation, and directory APIs. You can pass your key using either of these three secure formats:
                </p>

                <div class="code-block mb-3">
                    <button class="copy-btn" onclick="navigator.clipboard.writeText('X-API-Key: adsb_live_your_api_key_here')">Copy</button>
# Option 1: Custom Header (Recommended)
X-API-Key: adsb_live_your_api_key_here

# Option 2: Standard Bearer Token
Authorization: Bearer adsb_live_your_api_key_here

# Option 3: Query Parameter
GET <?= BASE_URL ?>/api/v1/credits.php?api_key=adsb_live_your_api_key_here
                </div>
                <div class="p-3 rounded-3" style="background:rgba(200,255,0,0.05);border:1px solid rgba(200,255,0,0.2);">
                    <span class="text-accent fw-bold font-size-13">Security Note:</span>
                    <span class="text-white-50 font-size-13">Your API key is hashed with SHA-256 in our database. If an API key is compromised, you can revoke and regenerate it instantly from your <a href="<?= url('api-settings') ?>" class="text-accent text-decoration-none hover-underline">API Settings</a> console.</span>
                </div>
            </div>

            <!-- Section: Rate Limits -->
            <div id="ratelimits" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <h3 class="text-white fw-bold h5 mb-2">
                    <i class="ph-bold ph-gauge text-accent me-1"></i> Rate Limiting &amp; HTTP Headers
                </h3>
                <p class="text-white-50 font-size-14 mb-3" style="color:#cbd5e1 !important;">
                    To guarantee platform stability and prevent abuse, API requests are governed by a <strong>60 requests per minute</strong> sliding window. Standard rate-limit headers are returned on every single API response:
                </p>

                <div class="table-responsive mb-3">
                    <table class="table table-dark table-bordered border-secondary border-opacity-15 font-size-13 mb-0">
                        <thead>
                            <tr>
                                <th>Header</th>
                                <th>Description</th>
                                <th>Example</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>X-RateLimit-Limit</code></td>
                                <td>Maximum permitted requests per minute</td>
                                <td class="text-accent">60</td>
                            </tr>
                            <tr>
                                <td><code>X-RateLimit-Remaining</code></td>
                                <td>Remaining quota in the current 60-second window</td>
                                <td class="text-accent">58</td>
                            </tr>
                            <tr>
                                <td><code>X-RateLimit-Reset</code></td>
                                <td>Unix timestamp when the quota resets</td>
                                <td class="text-accent"><?= time() + 45 ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-white-50 font-size-13 mb-0" style="color:#cbd5e1 !important;">
                    When your quota is exceeded, the server returns <code>HTTP 429 Too Many Requests</code> with a <code>Retry-After</code> header indicating seconds to wait.
                </p>
            </div>

            <!-- Section: Webhooks -->
            <div id="webhooks" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <h3 class="text-white fw-bold h5 mb-2">
                    <i class="ph-bold ph-webhooks-logo text-accent me-1"></i> Webhooks &amp; HMAC Signature Verification
                </h3>
                <p class="text-white-50 font-size-14 mb-3" style="color:#cbd5e1 !important;">
                    Adsbooster dispatches real-time webhook events to your server when campaigns are updated, posts are published live, or ad spend milestones are reached. Every webhook contains an <code>X-Adsbooster-Signature</code> header generated using HMAC-SHA256.
                </p>

                <div class="code-block mb-3">
                    <button class="copy-btn" onclick="navigator.clipboard.writeText('$computed = hash_hmac(\'sha256\', file_get_contents(\'php://input\'), $webhookSecret);')">Copy</button>
// PHP Example: Verifying Webhook Signatures
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_ADSBOOSTER_SIGNATURE'] ?? '';
$secret = 'whsec_your_webhook_signing_secret';

$expectedSignature = hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(401);
    die('Invalid Webhook Signature');
}
// Proceed to handle payload safely
                </div>
            </div>

            <!-- Section: Gateway Account Connections & Platform Routing -->
            <div id="gateway-connections" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                    <h3 class="text-white fw-bold h5 mb-0">
                        <i class="ph-bold ph-link text-accent me-1"></i> Gateway Account Connections &amp; Platform Routing
                    </h3>
                    <span class="badge bg-main-600 bg-opacity-15 text-main-600 border border-main-600 border-opacity-30 rounded-pill px-2 py-0 font-size-11 fw-bold">Dual Gateway · Live</span>
                </div>
                <p class="text-white-50 font-size-14 mb-3" style="color:#cbd5e1 !important;">
                    Adsbooster routes <strong class="text-white">100% of external platform traffic through two unified gateways</strong>. No direct platform authorization codes are exchanged and <strong class="text-white">no direct platform access tokens are stored</strong>. Organic channels (Facebook, Instagram, TikTok, X, LinkedIn, Pinterest, YouTube, Google Calendar/Business) connect through the <code class="text-accent">Bundle.social</code> API; paid ad accounts (Meta Ads, TikTok Ads, Google Ads &amp; PMax, Snapchat) connect through the <code class="text-accent">Zernio</code> Ads API. Connection journeys start from <code class="text-accent"><?= BASE_URL ?>/social-connect.php</code> (or <code><?= BASE_URL ?>/oauth-callback.php?platform=X</code>, which transparently redirects to the correct gateway).
                </p>

                <!-- Gateway Callback Landing URIs -->
                <div class="d-flex align-items-center justify-content-between p-3 rounded-3 flex-wrap gap-2 mb-4" style="background:#060910;border:1px solid rgba(255,255,255,0.08);">
                    <div>
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase">Gateway Callback Landing URIs</div>
                        <code class="text-accent fw-bold font-size-14"><?= BASE_URL ?>/dashboard.php?action=bundle_connected</code>
                        <span class="text-white-50 font-size-11 mx-2">·</span>
                        <code class="text-accent fw-bold font-size-14"><?= BASE_URL ?>/dashboard.php?action=zernio_connected</code>
                    </div>
                    <button class="btn btn-outline-light btn-sm rounded-pill font-size-12 px-3" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>/dashboard.php?action=bundle_connected')">
                        <i class="ph ph-copy me-1"></i> Copy Bundle.social Callback
                    </button>
                </div>

                <!-- Platform Details Cards/Table -->
                <div class="table-responsive">
                    <table class="table table-dark align-middle mb-0" style="background:transparent; border-color: rgba(255,255,255,0.08);">
                        <thead>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.12); font-size:11px; text-transform:uppercase; color:#94a3b8;">
                                <th style="width:180px;">Platform</th>
                                <th>Gateway &amp; Connect Endpoint</th>
                                <th>Configuration</th>
                                <th style="width:170px;">Provisioned By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Bundle.social (Organic Engine): Meta / Facebook / Instagram -->
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:28px;height:28px;border-radius:6px;background:rgba(24,119,242,0.15);display:flex;align-items:center;justify-content:center;color:#1877F2;">
                                            <i class="ph-bold ph-facebook-logo font-size-16"></i>
                                        </div>
                                        <div>
                                            <div class="text-white fw-bold font-size-13">Meta (Facebook &amp; Instagram) — Organic</div>
                                            <div class="text-white-50 font-size-11">via Bundle.social Gateway</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <code class="text-accent font-size-12" style="background:#060910;padding:4px 8px;border-radius:6px;border:1px solid rgba(255,255,255,0.08);">ThirdPartyGateway::getBundleConnectUrl($userId, 'facebook')</code>
                                        <button class="btn btn-outline-light btn-sm p-1 px-2 font-size-11" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>/social-connect.php')" title="Copy URI"><i class="ph ph-copy"></i></button>
                                    </div>
                                    <div class="text-white-50 font-size-11 mt-1">Callback landing: <code><?= BASE_URL ?>/dashboard.php?action=bundle_connected</code></div>
                                    <div class="text-white-50 font-size-11 mt-1">Instagram key: <code>instagram</code> · Legacy URI <code><?= BASE_URL ?>/oauth-callback.php?platform=instagram</code> auto-redirects here.</div>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/social-connect.php" class="text-white font-size-12 text-decoration-none hover-underline d-inline-flex align-items-center gap-1">
                                        Channel Connections <i class="ph ph-arrow-square-out font-size-10"></i>
                                    </a>
                                    <div class="text-white-50 font-size-11">No per-platform developer app required — Bundle.social provisions profiles, tokens &amp; token refresh.</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary font-size-10" style="white-space:normal;line-height:1.4;">bundle_social · organic_profile — no direct platform tokens stored</span>
                                </td>
                            </tr>

                            <!-- TikTok: organic via Bundle.social (ads via Zernio) -->
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:28px;height:28px;border-radius:6px;background:rgba(0,242,254,0.15);display:flex;align-items:center;justify-content:center;color:#00f2fe;">
                                            <i class="ph-bold ph-tiktok-logo font-size-16"></i>
                                        </div>
                                        <div>
                                            <div class="text-white fw-bold font-size-13">TikTok for Business</div>
                                            <div class="text-white-50 font-size-11">Organic publishing · via Bundle.social</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <code class="text-accent font-size-12" style="background:#060910;padding:4px 8px;border-radius:6px;border:1px solid rgba(255,255,255,0.08);">ThirdPartyGateway::getBundleConnectUrl($userId, 'tiktok')</code>
                                        <button class="btn btn-outline-light btn-sm p-1 px-2 font-size-11" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>/social-connect.php')" title="Copy URI"><i class="ph ph-copy"></i></button>
                                    </div>
                                    <div class="text-white-50 font-size-11 mt-1">Callback landing: <code><?= BASE_URL ?>/dashboard.php?action=bundle_connected</code></div>
                                    <div class="text-white-50 font-size-11 mt-1">TikTok <em>Ads</em> accounts route through the Zernio gateway instead — see Zernio rows below.</div>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/social-connect.php" target="_blank" class="text-white font-size-12 text-decoration-none hover-underline d-inline-flex align-items-center gap-1">
                                        Channel Connections <i class="ph ph-arrow-square-out font-size-10"></i>
                                    </a>
                                    <div class="text-white-50 font-size-11">UGC video publishing &amp; scheduled organic posts dispatch via <code>createOrganicPost()</code>.</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary font-size-10" style="white-space:normal;line-height:1.4;">bundle_social · organic_profile</span>
                                </td>
                            </tr>

                            <!-- Twitter / X: organic via Bundle.social -->
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:28px;height:28px;border-radius:6px;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;color:#fff;">
                                            <i class="ph-bold ph-x-logo font-size-16"></i>
                                        </div>
                                        <div>
                                            <div class="text-white fw-bold font-size-13">Twitter / X</div>
                                            <div class="text-white-50 font-size-11">Organic publishing · via Bundle.social</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <code class="text-accent font-size-12" style="background:#060910;padding:4px 8px;border-radius:6px;border:1px solid rgba(255,255,255,0.08);">ThirdPartyGateway::getBundleConnectUrl($userId, 'twitter')</code>
                                        <button class="btn btn-outline-light btn-sm p-1 px-2 font-size-11" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>/social-connect.php')" title="Copy URI"><i class="ph ph-copy"></i></button>
                                    </div>
                                    <div class="text-white-50 font-size-11 mt-1">Callback landing: <code><?= BASE_URL ?>/dashboard.php?action=bundle_connected</code></div>
                                    <div class="text-white-50 font-size-11 mt-1">Legacy key <code>?platform=x</code> is accepted by the <code>oauth-callback.php</code> shim.</div>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/social-connect.php" target="_blank" class="text-white font-size-12 text-decoration-none hover-underline d-inline-flex align-items-center gap-1">
                                        Channel Connections <i class="ph ph-arrow-square-out font-size-10"></i>
                                    </a>
                                    <div class="text-white-50 font-size-11">Posts &amp; UGC broadcasts schedule through <code>SocialPostEngine</code> &rarr; Bundle.social.</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary font-size-10" style="white-space:normal;line-height:1.4;">bundle_social · organic_profile</span>
                                </td>
                            </tr>

                            <!-- LinkedIn: organic via Bundle.social -->
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:28px;height:28px;border-radius:6px;background:rgba(10,102,194,0.15);display:flex;align-items:center;justify-content:center;color:#0A66C2;">
                                            <i class="ph-bold ph-linkedin-logo font-size-16"></i>
                                        </div>
                                        <div>
                                            <div class="text-white fw-bold font-size-13">LinkedIn</div>
                                            <div class="text-white-50 font-size-11">Organic publishing · via Bundle.social</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <code class="text-accent font-size-12" style="background:#060910;padding:4px 8px;border-radius:6px;border:1px solid rgba(255,255,255,0.08);">ThirdPartyGateway::getBundleConnectUrl($userId, 'linkedin')</code>
                                        <button class="btn btn-outline-light btn-sm p-1 px-2 font-size-11" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>/social-connect.php')" title="Copy URI"><i class="ph ph-copy"></i></button>
                                    </div>
                                    <div class="text-white-50 font-size-11 mt-1">Callback landing: <code><?= BASE_URL ?>/dashboard.php?action=bundle_connected</code></div>
                                    <div class="text-white-50 font-size-11 mt-1">Pinterest &amp; YouTube follow the same pattern (<code>'pinterest'</code>, <code>'youtube'</code> platform keys).</div>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/social-connect.php" target="_blank" class="text-white font-size-12 text-decoration-none hover-underline d-inline-flex align-items-center gap-1">
                                        Channel Connections <i class="ph ph-arrow-square-out font-size-10"></i>
                                    </a>
                                    <div class="text-white-50 font-size-11">Unlimited connected profiles — managed entirely by the gateway.</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary font-size-10" style="white-space:normal;line-height:1.4;">bundle_social · organic_profile</span>
                                </td>
                            </tr>

                            <!-- Google: Calendar/Business via Bundle.social · Ads via Zernio · Sign-in identity direct -->
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:28px;height:28px;border-radius:6px;background:rgba(66,133,244,0.15);display:flex;align-items:center;justify-content:center;color:#4285F4;">
                                            <i class="ph-bold ph-google-logo font-size-16"></i>
                                        </div>
                                        <div>
                                            <div class="text-white fw-bold font-size-13">Google Cloud &amp; Ads</div>
                                            <div class="text-white-50 font-size-11">Calendar/Business · Bundle.social — Ads · Zernio</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <code class="text-accent font-size-12" style="background:#060910;padding:4px 8px;border-radius:6px;border:1px solid rgba(255,255,255,0.08);">getBundleConnectUrl($userId, 'google_calendar') / getZernioAdConnectUrl($userId, 'google')</code>
                                        <button class="btn btn-outline-light btn-sm p-1 px-2 font-size-11" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>/social-connect.php')" title="Copy URI"><i class="ph ph-copy"></i></button>
                                    </div>
                                    <div class="text-white-50 font-size-11 mt-1">Calendar events sync via <code>createCalendarEvent()</code> / <code>deleteCalendarEvent()</code> (Bundle.social).</div>
                                    <div class="text-white-50 font-size-11 mt-1">Google Ads campaign launch &amp; PMax config flow through <code>launchPaidCampaign()</code> (Zernio).</div>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/social-connect.php" target="_blank" class="text-white font-size-12 text-decoration-none hover-underline d-inline-flex align-items-center gap-1">
                                        Channel Connections <i class="ph ph-arrow-square-out font-size-10"></i>
                                    </a>
                                    <div class="text-white-50 font-size-11">Direct Google OAuth is retained only for "Sign in with Google" identity (<code>users.google_id</code>) via <code>modules/google_auth.php</code>.</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary font-size-10" style="white-space:normal;line-height:1.4;">bundle_social (calendar/business) · zernio (google_ads)</span>
                                </td>
                            </tr>

                            <!-- Zernio Ads Engine: all paid networks -->
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:28px;height:28px;border-radius:6px;background:rgba(103,232,102,0.15);display:flex;align-items:center;justify-content:center;color:#67e866;">
                                            <i class="ph-bold ph-megaphone font-size-16"></i>
                                        </div>
                                        <div>
                                            <div class="text-white fw-bold font-size-13">Zernio Ads Engine (Meta · TikTok · Google · LinkedIn · Pinterest · Reddit)</div>
                                            <div class="text-white-50 font-size-11">All paid advertising · single Zernio API (zernio.com/api/v1)</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <code class="text-accent font-size-12" style="background:#060910;padding:4px 8px;border-radius:6px;border:1px solid rgba(255,255,255,0.08);">ThirdPartyGateway::getZernioAdConnectUrl($userId, $network)</code>
                                        <button class="btn btn-outline-light btn-sm p-1 px-2 font-size-11" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>/social-connect.php')" title="Copy URI"><i class="ph ph-copy"></i></button>
                                    </div>
                                    <div class="text-white-50 font-size-11 mt-1">Callback landing: <code><?= BASE_URL ?>/dashboard.php?action=zernio_connected</code></div>
                                    <div class="text-white-50 font-size-11 mt-1">Campaign lifecycle: <code>launchPaidCampaign()</code> &rarr; <code>setAdCampaignStatus()</code> pause/resume &rarr; <code>getCampaignMetrics()</code> (ROAS feeds the metrics cron). Launch and pause/resume run <strong>asynchronously</strong>: API/UI producers enqueue a <code>zernio_launch_ad</code> / <code>zernio_toggle_status</code> job (<code>AsyncQueue::push()</code>) and reply instantly; <code>cron/queue_worker.php</code> executes them out-of-band with exponential-backoff retries. Server-side conversions ship through <code>sendConversionEvent()</code> (Meta CAPI &amp; TikTok Events via <code>POST /v1/ads/conversions</code>).</div>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/social-connect.php" target="_blank" class="text-white font-size-12 text-decoration-none hover-underline d-inline-flex align-items-center gap-1">
                                        Channel Connections <i class="ph ph-arrow-square-out font-size-10"></i>
                                    </a>
                                    <div class="text-white-50 font-size-11">Ad accounts persist as <code>gateway_provider='zernio'</code>, <code>account_type='ad_account'</code>; campaigns store <code>zernio_campaign_id</code>.</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary font-size-10" style="white-space:normal;line-height:1.4;">zernio · ad_account — no direct platform tokens stored</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-3 rounded-3 mt-3" style="background:rgba(56,189,248,0.05);border:1px solid rgba(56,189,248,0.2);">
                    <span class="text-info fw-bold font-size-13"><i class="ph-bold ph-info me-1"></i> Developer Tip:</span>
                    <span class="text-white-50 font-size-13">All account connections are initiated from <code><?= BASE_URL ?>/social-connect.php</code> (organic via Bundle.social, ad accounts via Zernio) — no developer-portal app registration is needed on any social platform. The legacy <code><?= BASE_URL ?>/oauth-callback.php?platform=X</code> URI still works as a compatibility shim: it transparently redirects to the correct gateway connect URL. Direct developer-app keys (Meta/TikTok/Snapchat/Twitter/LinkedIn) are deprecated in <code>config/apikeys.php</code>; only <code>BUNDLE_SOCIAL_API_KEY</code> and <code>ZERNIO_API_KEY</code> are consumed. Note: Snapchat is not offered by either gateway — organic channels come from Bundle.social's <code>/api/v1/social-account/connect</code> enum, ad networks from Zernio's <code>/v1/connect/{platform}/ads</code> (facebook, instagram, linkedin, tiktok, twitter, pinterest, googleads, redditads).</span>
                </div>
            </div>

            <!-- Endpoint 1: Balances & Wallet -->
            <div id="ep-credits" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">

                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="method-pill method-get">GET</span>
                    <code class="text-white fw-bold font-size-15">/api/v1/credits.php</code>
                </div>
                <p class="text-white-50 font-size-13 mb-3">Retrieve your real-time wallet balances, ad credits, AI credits, and active subscription plan.</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">cURL Request</div>
                        <div class="code-block">
curl -X GET "<?= BASE_URL ?>/api/v1/credits.php" \
  -H "X-API-Key: YOUR_API_KEY"
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Response (200 OK)</div>
                        <div class="code-block">
{
  "success": true,
  "user": {
    "id": 1,
    "full_name": "Admin User",
    "ad_credits": 250.00,
    "ai_credits": 480
  },
  "plan": { "name": "Growth Pro", "status": "active" }
}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Endpoint 2: Ad Campaigns -->
            <div id="ep-campaigns" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="method-pill method-post">POST</span>
                    <span class="method-pill method-get">GET</span>
                    <span class="method-pill method-post" style="background:rgba(234,179,8,0.15);color:#eab308;">PUT / PATCH</span>
                    <code class="text-white fw-bold font-size-15">/api/v1/campaigns.php</code>
                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 rounded-pill px-2 py-0 font-size-11 ms-auto">Zernio Ads Engine</span>
                </div>
                <p class="text-white-50 font-size-13 mb-3">Launch paid advertising campaigns across Meta, TikTok, Google Ads, LinkedIn, Pinterest, X and Reddit Ads or trigger status updates (active / paused) synced with Zernio. Launches and toggles are queued — background workers apply them out-of-band on gateway confirmation.</p>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Create Campaign Payload (POST)</div>
                        <div class="code-block">
{
  "campaign_name": "Summer D2C Scale",
  "campaign_type": "meta",
  "budget_amount": 100.00,
  "objective": "traffic",
  "headline": "Upgrade Your Summer Wardrobe",
  "primary_text": "Shop premium organic cotton essentials today with 20% off.",
  "destination_url": "https://example.com/shop",
  "creative_media_url": "https://example.com/creative.jpg"
}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Response (201 Created)</div>
                        <div class="code-block">
{
  "success": true,
  "message": "Campaign created and launched via the Zernio Ads Gateway.",
  "campaign_id": 42,
  "campaign": {
    "id": 42,
    "name": "Summer D2C Scale",
    "type": "meta",
    "budget_amount": 100.00,
    "status": "active",
    "zernio_campaign_id": "zern_cmp_04224aad65a8a8fb",
    "zernio_ad_account_id": "zern_act_1",
    "zernio_simulated": false
  }
}
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Pause / Resume Status (PUT /api/v1/campaigns.php?id=42)</div>
                        <div class="code-block">
{
  "status": "paused" // or "active"
}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Status Sync Response (200 OK)</div>
                        <div class="code-block">
{
  "success": true,
  "message": "Campaign status updated to 'paused' and synchronized with Zernio.",
  "campaign_id": 42,
  "status": "paused"
}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Endpoint 3: Social Posts -->
            <div id="ep-posts" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="method-pill method-post">POST</span>
                    <span class="method-pill method-get">GET</span>
                    <span class="method-pill method-delete">DELETE</span>
                    <code class="text-white fw-bold font-size-15">/api/v1/posts.php</code>
                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 rounded-pill px-2 py-0 font-size-11 ms-auto">Bundle.social Gateway</span>
                </div>
                <p class="text-white-50 font-size-13 mb-3">Publish content live across connected channels (Meta, TikTok, Twitter/X, LinkedIn) or queue into the scheduling pipeline via Bundle.social.</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Schedule Post Payload (POST)</div>
                        <div class="code-block">
{
  "platform": "instagram",
  "caption": "Scaling brand revenue with @Adsbooster AI! 🚀 #Growth",
  "media_url": "https://example.com/asset.png",
  "media_type": "image",
  "scheduled_time": "<?= date('Y-m-d H:i:s', strtotime('+2 hours')) ?>"
}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Response (201 Created)</div>
                        <div class="code-block">
{
  "success": true,
  "message": "Post scheduled successfully via Bundle.social.",
  "post_id": 85,
  "bundle_post_id": "bndl_post_b7a0e45b954644fc",
  "scheduled_time": "<?= date('Y-m-d H:i:s', strtotime('+2 hours')) ?>",
  "platform": "instagram"
}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Endpoint 4: AI UGC Image Studio -->
            <div id="ep-ugc" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="method-pill method-post">POST</span>
                    <span class="method-pill method-get">GET</span>
                    <code class="text-white fw-bold font-size-15">/api/v1/ugc.php</code>
                </div>
                <p class="text-white-50 font-size-13 mb-3">Synthesize photorealistic UGC ad imagery with customizable personas, aspect ratios, promotional trust badges, and variations.</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Generate UGC Image Payload</div>
                        <div class="code-block">
{
  "product_name": "Luxe Eye Cream",
  "ugc_style": "selfie_hold",
  "creator_persona": "gen_z",
  "aspect_ratio": "1:1",
  "target_audience": "Women 25-45",
  "badge_text": "⭐⭐⭐⭐⭐ Top Rated",
  "seed": 482910
}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Response (201 Created)</div>
                        <div class="code-block">
{
  "success": true,
  "creative": {
    "image_url": "/assets/uploads/ai/gemini/ugc_482910_1.png",
    "hook_headline": "Stop wasting budget on 10-step routines",
    "ad_copy": "Grab yours today with free express shipping!",
    "badge_text": "⭐⭐⭐⭐⭐ Top Rated",
    "seed": 482910
  },
  "credits_remaining": 478
}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Endpoint 5: Directory & Leads -->
            <div id="ep-directory" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="method-pill method-get">GET</span>
                    <span class="method-pill method-post">POST</span>
                    <code class="text-white fw-bold font-size-15">/api/v1/directory.php</code>
                </div>
                <p class="text-white-50 font-size-13 mb-3">Query verified local business listings, search by category or keyword, and publish new profiles.</p>

                <div class="code-block">
curl -X GET "<?= BASE_URL ?>/api/v1/directory.php?category=Retail&limit=5" \
  -H "X-API-Key: YOUR_API_KEY"
                </div>
            </div>

            <!-- Endpoint 6: Platform Reviews -->
            <div id="ep-reviews" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="method-pill method-get">GET</span>
                    <span class="method-pill method-post">POST</span>
                    <code class="text-white fw-bold font-size-15">/api/v1/reviews.php</code>
                </div>
                <p class="text-white-50 font-size-13 mb-3">Fetch verified reviews and ratings displayed across the platform homepage, or submit reviews (Pro members anytime; Free users after 5 days).</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Submit Review Payload</div>
                        <div class="code-block">
{
  "rating": 5,
  "title": "Unmatched ROAS Acceleration",
  "review_text": "The combination of the AI UGC Image Studio and one-click TikTok publishing through the Bundle.social gateway has eliminated creative fatigue for our agency.",
  "user_role": "Performance Agency Owner"
}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Response (201 Created)</div>
                        <div class="code-block">
{
  "success": true,
  "review_id": 14,
  "message": "Thank you! Your review has been published to the community."
}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Endpoint 7b: Public Aggregate Analytics (no auth) -->
            <div id="ep-public-analytics" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="method-pill method-get">GET</span>
                    <code class="text-white fw-bold font-size-15">/api/v1/public-analytics.php</code>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2 py-0 font-size-11">No authentication</span>
                </div>
                <p class="text-white-50 font-size-13 mb-3">Authentication-free, read-only JSON feed of the <strong class="text-white">same live aggregate analytics</strong> the homepage achievements modal renders. Serves totals, performance ratios, a 14-day traffic trend, the campaign status mix and the platform mix. <strong class="text-white">Zero user-level data</strong> leaves the server through this endpoint.</p>

                <div class="row g-3 mb-1">
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Limits &amp; CORS</div>
                        <div class="code-block">GET /api/v1/public-analytics.php?view=all

· GET / OPTIONS only (405 on other verbs)
· 30 requests / minute / IP  →  HTTP 429 + Retry-After
· X-RateLimit-Limit / X-RateLimit-Remaining headers
· Access-Control-Allow-Origin: *   (read-only, no credentials)
· Cache-Control: public, max-age=30</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Response (200 OK)</div>
                        <div class="code-block">{
  "success": true,
  "generated": "2026-11-01T16:05:13+00:00",
  "homepage": {
    "campaigns": "1.2K", "users": "2.4K",
    "platforms": 8, "success_rate": "94%",
    "monitoring": "24/7"
  },
  "analytics": {
    "totals": { "campaigns": 1200, "impressions": 8400000,
                "clicks": 210000, "conversions": 12400 },
    "performance": { "ctr_pct": 2.5, "cvr_pct": 5.9 },
    "trend_14d": [ { "date": "2026-11-01", "impressions": 61200 } ],
    "campaign_status_mix": { "active": 320, "paused": 88 },
    "platform_mix": [ { "platform_name": "facebook", "accounts": 24 } ],
    "note": "Aggregate analytics only. No user-level data is exposed."
  }
}</div>
                    </div>
                </div>
                <div class="text-white-50 font-size-11">The <code>view</code> query parameter (<code>all</code> | <code>campaigns</code> | <code>users</code> | <code>platforms</code> | <code>success</code>) selects which sections the homepage modal focuses on; the payload itself is always the full safe aggregate set.</div>
            </div>

            <!-- Endpoint 7: AI Video Studio -->
            <div id="ep-video-studio" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="method-pill method-post">POST</span>
                    <code class="text-white fw-bold font-size-15">/ugc-video-studio.php</code>
                    <span class="badge bg-warning bg-opacity-25 text-warning font-size-10">Session + CSRF</span>
                </div>
                <p class="text-white-50 font-size-13 mb-3">
                    Full video lifecycle with a <strong class="text-white">free default route</strong>: the keyless <code class="text-accent">Pollinations</code> engine (or the built-in Storyboard Studio) renders photoreal scene stills at zero cost and composites a real MP4/WebM in your browser — no API key required. When a <code>GEMINI_API_KEY</code> is configured, Veo renders video directly and the Nano Banana 2 image models paint every scene still. Replicate, fal.ai and Runway provide premium async cloud routes. Works in the app (same-origin) or from any client that holds a logged-in session cookie. Pass <code>provider=pollinations</code> to force the free route.
                </p>

                <div class="table-responsive mb-3">
                    <table class="table table-dark table-sm font-size-12 mb-0" style="border-color:rgba(255,255,255,.08);">
                        <thead><tr><th class="text-white-50">action</th><th class="text-white-50">Params</th><th class="text-white-50">What it does</th></tr></thead>
                        <tbody>
                            <tr><td><code>create_video</code></td><td><code>hook_text</code> or <code>script_body</code>, optional <code>provider, video_type, avatar_id, voice_id, style_id, caption_style, audio_track, broll_keyword, aspect_ratio, duration_seconds</code></td><td>Builds the scene plan, generates a photoreal still per scene (Pollinations free / Gemini when a key exists), returns <code>status=rendering_client</code> + <code>scenes[]</code> (browser composites the MP4/WebM), or <code>status=generating</code> when Veo accepted the job (poll it).</td></tr>
                            <tr><td><code>update_video</code></td><td><code>creative_id</code> + any editable field above</td><td><b>Edit in place:</b> same creative id, new params, fresh AI stills. The old file stays on disk until the new render is saved.</td></tr>
                            <tr><td><code>regenerate_video</code></td><td><code>creative_id</code></td><td><b>Remix:</b> same script, brand-new scene stills (new Gemini seed).</td></tr>
                            <tr><td><code>get_video_status</code></td><td><code>creative_id</code></td><td>Poll Veo/external jobs until <code>completed</code> or <code>failed</code>.</td></tr>
                            <tr><td><code>save_render</code></td><td><code>creative_id</code>, <code>video</code> (multipart), optional <code>thumbnail</code> (data URI)</td><td>Upload the finished browser render; deducts credits and marks <code>completed</code>.</td></tr>
                            <tr><td><code>delete_video</code></td><td><code>creative_id</code></td><td>Deletes the creative and its stored files.</td></tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-white-50 font-size-12 mb-2">Every request needs the <code>csrf_token</code> field (echoed by the page as <code>const CSRF</code>) and the session cookie.</p>

                <ul class="nav nav-pills mb-3" role="tablist">
                    <li class="nav-item"><button class="nav-link active rounded-pill px-3 py-1 font-size-12" data-bs-toggle="pill" data-bs-target="#vs-curl" type="button">cURL</button></li>
                    <li class="nav-item"><button class="nav-link rounded-pill px-3 py-1 font-size-12" data-bs-toggle="pill" data-bs-target="#vs-js" type="button">JavaScript</button></li>
                    <li class="nav-item"><button class="nav-link rounded-pill px-3 py-1 font-size-12" data-bs-toggle="pill" data-bs-target="#vs-py" type="button">Python</button></li>
                    <li class="nav-item"><button class="nav-link rounded-pill px-3 py-1 font-size-12" data-bs-toggle="pill" data-bs-target="#vs-php" type="button">PHP</button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="vs-curl">
                        <div class="code-block"># 1) Sign in and keep the session cookie
curl -c jar.txt -d "csrf_token=PAGE_CSRF&email=you%40mail.com&password=secret" <?= BASE_URL ?>/login

# 2) Regenerate an existing video (same script, new Nano Banana 2 stills)
curl -b jar.txt -X POST <?= BASE_URL ?>/ugc-video-studio.php \
  -d "action=regenerate_video" \
  -d "csrf_token=STUDIO_CSRF" \
  -d "creative_id=17"</div>
                    </div>
                    <div class="tab-pane fade" id="vs-js">
                        <div class="code-block">// In-app: the studio page already exposes CSRF + LIB state
const fd = new FormData();
fd.append('action', 'regenerate_video');
fd.append('csrf_token', CSRF);
fd.append('creative_id', '17');
const res = await fetch(window.location.pathname, { method:'POST', body:fd, credentials:'same-origin' });
const { success, video } = await res.json();
if (success && video.status === 'rendering_client') {
  // composite video.scenes[] with canvas + MediaRecorder, then save_render
}</div>
                    </div>
                    <div class="tab-pane fade" id="vs-py">
                        <div class="code-block">import requests

s = requests.Session()
page = s.get("<?= BASE_URL ?>/ugc-video-studio.php")
import re
csrf = re.search(r'const CSRF\s*=\s*"([^"]+)"', page.text).group(1)

r = s.post("<?= BASE_URL ?>/ugc-video-studio.php",
           data={"action": "regenerate_video", "csrf_token": csrf, "creative_id": "17"})
video = r.json()["video"]
print(video["status"], len(video["scenes"]), "scenes")</div>
                    </div>
                    <div class="tab-pane fade" id="vs-php">
                        <div class="code-block">$ch = curl_init('<?= BASE_URL ?>/ugc-video-studio.php');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => 'jar.txt',      // session from the login step
    CURLOPT_POSTFIELDS => http_build_query([
        'action'      => 'regenerate_video',
        'csrf_token'  => 'STUDIO_CSRF',
        'creative_id' => '17',
    ]),
]);
$video = json_decode(curl_exec($ch), true)['video'];
echo $video['status']; // rendering_client → composite in browser, or poll get_video_status</div>
                    </div>
                </div>
            </div>

            <!-- Endpoint 8: Post Creator & Social Publishing -->
            <div id="ep-post-creator" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="method-pill method-post">POST</span>
                    <code class="text-white fw-bold font-size-15">/post-creator.php</code>
                    <span class="badge bg-warning bg-opacity-25 text-warning font-size-10">Session + CSRF</span>
                </div>
                <p class="text-white-50 font-size-13 mb-3">
                    AI content pipeline: Groq writes the caption, Gemini Nano Banana 2 paints the illustration, and <code>publish_now</code> dispatches both live to every connected organic account (Facebook, Instagram, TikTok, Twitter/X, LinkedIn, Pinterest, YouTube) in a single request — all via the Bundle.social gateway. Snapchat and Google Ads are paid-only channels routed through the Zernio Ads engine.
                </p>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Generate an AI post image (JSON, 5 credits)</div>
                        <div class="code-block">action=generate_illustration
csrf_token=…
illustration_prompt=Flat lay of our skincare set
                          on marble, morning light

→ { "success": true,
    "url": "/assets/uploads/ai/gemini/post_1_…​.png",
    "model": "gemini-3-pro-image-preview" }</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Publish live to every connected channel</div>
                        <div class="code-block">action=publish_now
csrf_token=…
caption=New drop 🚀 link in bio #ad
platforms[]=meta&platforms[]=tiktok
existing_media_url=/assets/uploads/ai/gemini/post_1_…​.png
media_type=image

→ "Live Post Published! Channels: Meta, TikTok"</div>
                    </div>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-dark table-sm font-size-12 mb-0" style="border-color:rgba(255,255,255,.08);">
                        <thead><tr><th class="text-white-50">action</th><th class="text-white-50">Params</th><th class="text-white-50">What it does</th></tr></thead>
                        <tbody>
                            <tr><td><code>generate_ai</code></td><td><code>ai_prompt</code>, <code>ai_platform</code></td><td>Groq writes a platform-tuned caption (1 credit).</td></tr>
                            <tr><td><code>generate_illustration</code></td><td><code>illustration_prompt</code></td><td>Gemini Nano Banana 2 renders the post image (5 credits) and stores it same-origin.</td></tr>
                            <tr><td><code>publish_now</code></td><td><code>caption</code>, <code>platforms[]</code> (organic channels only), optional <code>existing_media_url</code> + <code>media_type</code> or a <code>media_upload</code> file</td><td><strong>Asynchronous</strong> multi-network dispatch through the Bundle.social Gateway: the request creates scheduled rows + <code>bundle_publish_post</code> queue jobs (<code>AsyncQueue::push()</code>) and returns instantly; <code>cron/queue_worker.php</code> performs the actual dispatch (<code>ThirdPartyGateway::createOrganicPost</code>) with retries. Paid channels (all ad networks) are rejected here — they belong to the Zernio Ads API.</td></tr>
                            <tr><td><code>schedule</code></td><td><code>scheduled_at</code> + same media fields</td><td>Queues into <code>scheduled_posts</code> for the automation worker.</td></tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-white-50 font-size-12 mb-2">Connect accounts first under <a href="<?= url('social-connect') ?>" class="text-accent">Social Connect</a> — publishing uses real OAuth tokens, never passwords.</p>
            </div>

            <!-- JavaScript SDK -->
            <div id="js-sdk" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="badge bg-accent bg-opacity-25 text-accent font-size-10 fw-bold">OFFICIAL SDK</span>
                    <code class="text-white fw-bold font-size-15">adsbooster-js-sdk</code>
                    <a href="<?= BASE_URL ?>/api/v1/sdk/adsbooster.js" download class="btn btn-outline-light btn-sm rounded-pill px-3 font-size-11 ms-auto">
                        <i class="ph ph-download-simple me-1"></i> Download (self-hosted)
                    </a>
                </div>
                <p class="text-white-50 font-size-13 mb-3">
                    Zero-dependency JavaScript / TypeScript client for the full REST API — AI UGC creatives, campaigns, multi-network publishing, server-side CAPI tracking, credits and signed webhooks. Works in Node ≥18 and modern browsers, with automatic retries on rate limits and full typings.
                    <a href="https://github.com/adsbooster-sdks/adsbooster-js-sdk" target="_blank" class="text-accent">SDK repository <i class="ph ph-arrow-square-out"></i></a>
                </p>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Install (npm · ESM · UMD · typings included)</div>
                        <div class="code-block">npm install adsbooster-js-sdk

# or self-hosted, straight from this deployment:
# <script src="<?= BASE_URL ?>/api/v1/sdk/adsbooster.js"></script></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Quick start</div>
                        <div class="code-block">import { Adsbooster } from 'adsbooster-js-sdk';

const client = new Adsbooster({ apiKey: 'adsb_live_…' });

const bal   = await client.credits.balance();
const img   = await client.ugc.generateImage({ product_name: 'Cloud-Knit Shoes' });
const camp  = await client.campaigns.create({ name: 'Q4 Drop', platform: 'meta', objective: 'conversions', budget_amount: 25 });
await client.posts.create({ platform: 'tiktok', caption: 'New drop 🚀 #ad', media_url: img.creative.image_url });</div>
                    </div>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-dark table-sm font-size-12 mb-0" style="border-color:rgba(255,255,255,.08);">
                        <thead><tr><th class="text-white-50">Namespace</th><th class="text-white-50">Methods</th></tr></thead>
                        <tbody>
                            <tr><td><code>client.campaigns</code></td><td><code>list({page,limit,status})</code> · <code>get(id)</code> · <code>create(payload)</code> · <code>update(id,payload)</code> · <code>pause(id)</code> · <code>resume(id)</code></td></tr>
                            <tr><td><code>client.posts</code></td><td><code>list()</code> · <code>get(id)</code> · <code>create({platform,caption,media_url,media_type})</code> · <code>remove(id)</code></td></tr>
                            <tr><td><code>client.ugc</code></td><td><code>generateImage({product_name,ugc_style,aspect_ratio,…})</code> · <code>listRecent({limit})</code></td></tr>
                            <tr><td><code>client.credits</code></td><td><code>balance()</code></td></tr>
                            <tr><td><code>client.directory</code></td><td><code>list({category,q,limit})</code> · <code>createListing(payload)</code></td></tr>
                            <tr><td><code>client.reviews</code></td><td><code>list()</code> · <code>submit({rating,review_text})</code></td></tr>
                            <tr><td><code>client.capi</code></td><td><code>scriptUrl()</code> · <code>track({pixel_token,event_name,custom_data})</code></td></tr>
                            <tr><td><code>client.webhooks</code></td><td><code>testPingUrl(url)</code> · <code>Adsbooster.webhooks.verify(rawBody, signature, secret)</code> (Node)</td></tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-white-50 font-size-12 mb-0">
                    <i class="ph ph-shield-check text-success me-1"></i>
                    The SDK auto-retries <code>429</code>/<code>5xx</code>, honors <code>Retry-After</code>, exposes the live quota on <code>client.lastRateLimit</code>, and throws typed <code>AdsboosterError</code> objects (<code>err.status</code>, <code>err.code</code>). Verify inbound webhooks with <code>Adsbooster.webhooks.verify()</code> using the RAW request body.
                </p>
            </div>

            <!-- Client SDKs -->
            <div id="sdks" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <h3 class="text-white fw-bold h5 mb-3">
                    <i class="ph-bold ph-terminal-window text-accent me-1"></i> Multi-Language Code Snippets
                </h3>

                <!-- Tabs for SDKs -->
                <ul class="nav nav-pills mb-3" id="sdkTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-pill px-3 py-1 font-size-12" id="js-tab" data-bs-toggle="pill" data-bs-target="#sdk-js" type="button">JavaScript (Fetch)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1 font-size-12" id="py-tab" data-bs-toggle="pill" data-bs-target="#sdk-py" type="button">Python (Requests)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1 font-size-12" id="php-tab" data-bs-toggle="pill" data-bs-target="#sdk-php" type="button">PHP (cURL)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1 font-size-12" id="curl-tab" data-bs-toggle="pill" data-bs-target="#sdk-curl" type="button">cURL</button>
                    </li>
                </ul>

                <div class="tab-content" id="sdkTabContent">
                    <div class="tab-pane fade show active" id="sdk-js">
                        <div class="code-block">
// 1. Using the official JavaScript SDK (Node.js & Modern Browsers)
import { Adsbooster } from 'adsbooster-js-sdk';

const client = new Adsbooster({ apiKey: 'adsb_live_your_api_key' });

// Launch a paid ad campaign (routed via Zernio Ads Gateway)
const campaign = await client.campaigns.create({
  campaign_name: 'Summer Scale',
  campaign_type: 'meta',
  budget_amount: 50.00,
  objective: 'traffic',
  headline: '20% Off Sustainable Fashion',
  primary_text: 'Handcrafted luxury apparel delivered to your door.',
  destination_url: 'https://example.com/shop'
});
console.log('Zernio Campaign Ref:', campaign.campaign.zernio_campaign_id);

// 1-Click Pause / Resume synced with Zernio
await client.campaigns.pause(campaign.campaign_id);

// Schedule organic post across Instagram & TikTok (routed via Bundle.social)
const post = await client.posts.create({
  platform: 'instagram',
  caption: 'Catch our latest collection live on the feed! #SustainableFashion',
  media_url: 'https://example.com/asset.png',
  media_type: 'image'
});
console.log('Bundle.social Post Ref:', post.bundle_post_id);
                        </div>
                    </div>
                    <div class="tab-pane fade" id="sdk-py">
                        <div class="code-block">
import requests

class AdsboosterClient:
    def __init__(self, api_key: str, base_url: str = "<?= BASE_URL ?>/api/v1"):
        self.base_url = base_url.rstrip("/")
        self.headers = {
            "X-API-Key": api_key,
            "Content-Type": "application/json"
        }

    def create_campaign(self, payload: dict) -> dict:
        """Launch paid ad campaign via Zernio Gateway."""
        r = requests.post(f"{self.base_url}/campaigns.php", json=payload, headers=self.headers)
        return r.json()

    def set_campaign_status(self, campaign_id: int, status: str) -> dict:
        """Pause or resume campaign with instant Zernio status sync."""
        r = requests.put(f"{self.base_url}/campaigns.php?id={campaign_id}", json={"status": status}, headers=self.headers)
        return r.json()

    def schedule_post(self, payload: dict) -> dict:
        """Schedule or publish organic post via Bundle.social."""
        r = requests.post(f"{self.base_url}/posts.php", json=payload, headers=self.headers)
        return r.json()

# Example usage
client = AdsboosterClient("adsb_live_your_api_key")
camp = client.create_campaign({
    "campaign_name": "Autumn Boost",
    "campaign_type": "meta",
    "budget_amount": 75.00,
    "headline": "Fall In Love With Our New Range"
})
print("Campaign Created:", camp.get("campaign", {}).get("zernio_campaign_id"))
client.set_campaign_status(camp["campaign_id"], "paused")
                        </div>
                    </div>
                    <div class="tab-pane fade" id="sdk-php">
                        <div class="code-block">
&lt;?php
declare(strict_types=1);

class AdsboosterSDK {
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey, string $baseUrl = '<?= BASE_URL ?>/api/v1') {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    private function request(string $endpoint, string $method = 'GET', array $data = []): array {
        $ch = curl_init($this->baseUrl . '/' . ltrim($endpoint, '/'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        $resp = curl_exec($ch);
        curl_close($ch);
        return json_decode((string)$resp, true) ?: [];
    }

    public function launchCampaign(array $payload): array {
        return $this->request('campaigns.php', 'POST', $payload);
    }

    public function setCampaignStatus(int $id, string $status): array {
        return $this->request("campaigns.php?id={$id}", 'PUT', ['status' => $status]);
    }

    public function schedulePost(array $payload): array {
        return $this->request('posts.php', 'POST', $payload);
    }
}

// Example usage
$client = new AdsboosterSDK('adsb_live_your_api_key');
$post = $client->schedulePost([
    'platform' => 'tiktok',
    'caption'  => 'Viral creator hook #fyp',
    'media_url'=> 'https://example.com/reel.mp4'
]);
echo "Bundle Post ID: " . ($post['bundle_post_id'] ?? 'N/A');
                        </div>
                    </div>
                    <div class="tab-pane fade" id="sdk-curl">
                        <div class="code-block">
# 1. Launch a Paid Campaign via Zernio Ad Engine
curl -X POST "<?= BASE_URL ?>/api/v1/campaigns.php" \
  -H "X-API-Key: adsb_live_your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "campaign_name": "Global Scale Q4",
    "campaign_type": "meta",
    "budget_amount": 100.00,
    "headline": "Special Offer Inside",
    "destination_url": "https://example.com"
  }'

# 2. 1-Click Pause Campaign Status (Synced to Zernio)
curl -X PUT "<?= BASE_URL ?>/api/v1/campaigns.php?id=42" \
  -H "X-API-Key: adsb_live_your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"status": "paused"}'

# 3. Schedule Organic Post via Bundle.social
curl -X POST "<?= BASE_URL ?>/api/v1/posts.php" \
  -H "X-API-Key: adsb_live_your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "platform": "instagram",
    "caption": "Brand announcement! #Growth",
    "media_url": "https://example.com/asset.png"
  }'
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
