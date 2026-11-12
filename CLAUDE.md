# Adsbooster Project Instructions

## Project Shape
- Framework-free PHP 8.3 monolith with server-rendered pages, MySQL, and vanilla JavaScript/CSS.
- Root `*.php` files are page controllers and views. Reusable domain logic is in `modules/`.
- `config/site_setting_and_config.php` is the application bootstrap: URL/session/error handling, DB, keys, security, settings, auth helpers, CSRF, currency, and maintenance mode.
- Public layout: `includes/header.php` + `includes/footer.php`.
- Authenticated layout: `includes/app_header.php` + `includes/app_footer.php`.
- Admin pages use `includes/admin_guard.php` and `admin/sidebar.php`.

## Runtime and Commands
- Laragon Apache currently targets PHP `8.3.36` through `C:\laragon\etc\apache2\mod_php.conf`.
- PHP is not on `PATH`; use `C:\laragon\bin\php\php-8.3.36-Win32-vs16-x64\php.exe`.
- Composer: `& 'C:\laragon\bin\php\php-8.3.36-Win32-vs16-x64\php.exe' 'C:\laragon\bin\composer\composer.phar' install`.
- Syntax check one file: `& 'C:\laragon\bin\php\php-8.3.36-Win32-vs16-x64\php.exe' -l path\to\file.php`.
- Development URL: `http://localhost/seopp`; clean URLs are provided by `.htaccess`.
- Async worker (the only synchronous Bundle.social/Zernio caller): `php cron/queue_worker.php --once` for Task Scheduler, bare for the daemon loop, `--retry-failed [--type=…]` to re-queue sandbox-simulated holds. Schedule it every minute (or run the daemon) or nothing external ever executes.
- Verification: `php database/smoke_gateways.php` (43 assertions incl. async queue + Zernio-routed conversion round-trips); schema migrations `php database/migrate_async.php` (idempotent, purges legacy platform-token columns) + `database/async_gateway_migration.sql` (canonical).
- There is no automated test suite, JS build pipeline, or configured linter.

## Conventions to Preserve
- Use `declare(strict_types=1);` in PHP files.
- Page filenames use kebab-case; PHP modules and DB fields use snake_case; classes use PascalCase.
- Include the central bootstrap or an auth/admin guard before accessing helpers or the database.
- Protect state-changing browser requests with `csrf_verify()` and render `csrf_field()`.
- Scope user-owned reads and writes by `user_id`; do not trust IDs from the request alone.
- Use prepared statements or the `Database` helper in `config/db.php`.
- Escape plain output with `e()` and rich HTML with `sanitize_html()`.
- Validate uploads with MIME and extension checks; prefer `validate_upload()` and `store_upload()` from `includes/security.php`.
- Use `set_flash()` + `redirect(url(...))` for normal form workflows and explicit JSON/status codes for AJAX/API handlers.
- Credentials resolve through constants/environment/local key file/`site_settings`; never commit live keys.
- Do not report simulated, fallback, or locally generated results as real third-party outcomes.
- ASYNC BOUNDARY: request handlers NEVER call ThirdPartyGateway directly — persist the local row (`pending`/`scheduled`/`queued`) and enqueue via `AsyncQueue::push()` with a `subject_key`; external ids (`zernio_campaign_id`, `bundle_post_id`) and status flips are written only by `cron/queue_worker.php` from confirmed (non-simulated) gateway answers. (Read-only gateway inspections — e.g. the admin comment feed — may call the gateway for GET data, but never mutate.)
- NO DIRECT PLATFORM APIs: Meta CAPI and TikTok Events traffic routes through the Zernio Ads Engine (`ThirdPartyGateway::sendConversionEvent`, job `zernio_send_conversion`); platform pixel IDs are routing labels only and no platform access token exists anywhere — do not reintroduce `graph.facebook.com` / `business-api.tiktok.com` calls or token columns (`meta_capi_access_token`/`tiktok_events_access_token` are purged).

## Core Domains
- Identity/security: users, sessions, remember-me, Google OAuth, TOTP/email 2FA, privacy exports.
- Advertising: campaigns, audience segments, A/B variants, metrics, policy scans, media-buyer rules.
- AI studios: Groq/NVIDIA NIM text, Gemini images, multi-provider video (free keyless Pollinations route default when no keys), browser storyboard renderer.
- Social: account connections, live publishing, scheduled queue, calendar, CAPI/TikTok events.
- Commerce: plans, ad/AI credits, Paystack/Flutterwave, transactions, invoices, referrals/payouts.
- Marketplace/community: listings, jobs, bounties/escrow, agencies/approvals, reviews, chat/deals, dating.
- Publishing/developer: blog/CMS/SEO, REST API v1, API keys, webhooks, JS SDK, system status.

## Important Compatibility Boundary
- The dual gateway conversion is COMPLETE: 100% of external platform traffic flows through `modules/ThirdPartyGateway.php` — Bundle.social for organic (FB, IG, TikTok, X, LinkedIn, Pinterest, YouTube, Google Calendar/Business), Zernio for paid ads (Meta, TikTok Ads, Google Ads/PMax, Snapchat, Combo Pack).
- Separate authentication journeys: organic connects and ad-account connects are distinct, provider-bound flows. `ThirdPartyGateway::issueGatewayState()` issues an HMAC-signed, single-use, 15-minute state per journey (secret in `$_SESSION['gateway_state_secret']`, registry in `$_SESSION['gateway_state_pending']`); `validateGatewayState($state, $provider, $userId)` consumes it. All four callback handlers (dashboard.php + social-connect.php, `bundle_connected` and `zernio_connected`) REQUIRE a valid state — a Bundle.social state cannot be replayed as Zernio and vice versa. The platform/account identity stored at callback time is taken from the signed state, never from unsigned GET params.
- The two journeys still share the `connected_accounts` table (`gateway_provider` distinguishes `bundle_social`/`organic_profile` from `zernio`/`ad_account`) — that sync behavior is intentional, do not split the table.
- Organic engine guards: only Bundle.social `SUPPORTED_PLATFORMS` (meta/facebook/instagram/tiktok/twitter/linkedin/pinterest/youtube) may be posted; `SocialPostEngine::assertOrganicPlatform()` rejects paid channels (snapchat, google_ads) at publish/schedule/runtime, `api/v1/posts.php` rejects them with an explanatory 400, and UI selectors (post-creator, calendar, edit-post) offer organic channels only.
- Ads status truth: pause/resume is executed via `ThirdPartyGateway::setAdCampaignStatus()` FIRST; the local `ad_campaigns.status` is persisted only on gateway confirmation (dashboard toggle, edit-campaign, admin/campaigns, api/v1/campaigns PUT).
- `connected_accounts` now has ONLY gateway-shaped columns (`gateway_provider`, `account_type`, `platform_name`, `external_account_id`, `display_name`, `status`). The legacy direct-OAuth columns (`platform`, `access_token`, `account_id`, `platform_user_id`, `metadata_json`, `token_expiry`) no longer exist — any consumer reading them will fatal.
- Direct platform OAuth/token exchange is eliminated: `oauth-callback.php` is a legacy shim that redirects `?platform=X` requests to the gateway connect URLs; `config/apikeys.php`'s vendor constants (META/TIKTOK/SNAPCHAT/TWITTER/LINKEDIN) are deprecated with zero consumers; `oauth_auth_url()` was removed. Only `BUNDLE_SOCIAL_API_KEY` and `ZERNIO_API_KEY` are consumed for platform access (plus Google sign-in identity only: `users.google_id`).
- `modules/google_calendar.php` was deleted (orphaned); calendar sync goes through `ThirdPartyGateway::createCalendarEvent()`/`deleteCalendarEvent()` (Bundle.social).
- Gateway simulation mode: placeholder API keys return payloads flagged `simulated: true`. Never persist simulated payloads as real data — `cron/pull_campaign_metrics.php` refuses to write them.
- `database/migrate_gateways.php` drops and rebuilds `connected_accounts` and can be auto-invoked from the bootstrap. Back up before running on a production database.

## Known Baseline Constraints
- `database/adsbooster_complete.sql` and root SQL dumps are absent, although installers and docs require them.
- `sdk/adsbooster-js-sdk/` is now only a pointer README — the SDK source lives in `FushionHub/adsbooster-js-sdk` and is published to npm as `adsbooster-js-sdk`. The UMD build served by the app remains at `api/v1/sdk/adsbooster.js`.
- `vendor/` is absent; Composer currently reports PHPMailer as not installed.
- PHP 8.3 has the main required extensions but not `zip`; DOCX parsing therefore cannot use `ZipArchive` locally.
- A sandboxed MySQL 8.4.3 instance (data dir `C:\laragon\data\mysql-8.4.3-winx64`, port 3306, root no password) was initialized for runtime verification. The `adsbooster` database contains the REAL gateway migration output (`third_party_gateways`, restructured `connected_accounts`, `zernio_*`/`bundle_post_id` columns) plus MINIMAL STUB tables (users, ad_campaigns, scheduled_posts, campaign_metrics_daily, cron_run_logs, site_settings) and one smoke-test user. It is not a full production dataset — the complete dump is still missing. `database/smoke_gateways.php` (CLI-only) re-runs the verification suite; Apache is not required for CLI checks.
- Preserve existing user changes. Re-read recently modified files before editing because the gateway integration is actively changing.

## Before Shipping Any Change
1. Trace the affected page, module, DB tables, API endpoint, admin surface, and cron worker.
2. Add tests or a reproducible smoke script for the changed behavior; this project currently lacks regression coverage.
3. Lint every changed PHP file with both available PHP 8.3 and 8.4 binaries.
4. Verify authorization, CSRF, ownership checks, upload handling, outbound HTTP/TLS, and fallback truthfulness.
5. Smoke-test through Laragon once Apache/MySQL are running.
