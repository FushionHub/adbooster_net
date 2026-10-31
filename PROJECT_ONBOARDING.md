# Adsbooster Codebase Map

## Overview

Adsbooster is a broad, framework-free PHP SaaS monolith for AI creative generation, paid advertising, organic social publishing, business/talent marketplaces, community collaboration, billing, and administration. The repository contains roughly 50,000 lines across about 190 PHP files, with most pages combining request handling, database access, HTML, CSS, and JavaScript in one script.

## Technology

| Layer | Current implementation |
|---|---|
| Backend | PHP 8.3, procedural page controllers plus static service classes |
| Database | MySQL/MariaDB through a custom PDO singleton |
| Frontend | Server-rendered HTML, Bootstrap 5, custom CSS/Sass, vanilla JS, some jQuery/GSAP/Swiper |
| AI | Groq/NVIDIA NIM text, Gemini image models, Veo/Replicate/fal/Runway video adapters + free keyless Pollinations route |
| Payments | Paystack and Flutterwave |
| Email | PHPMailer when installed, native `mail()` fallback |
| APIs | Direct REST-style PHP endpoints under `api/v1/`, HMAC webhooks, UMD JavaScript SDK |
| Scheduling | CLI-only PHP jobs under `cron/` |

## Architecture

```text
Browser / API client
        |
Apache + .htaccess clean URLs
        |
Root page or api/v1/*.php endpoint
        |
site_setting_and_config.php / auth guard / API bootstrap
        |
Page controller + modules/* static service
        |
Custom PDO Database helper
        |
MySQL + external AI/social/payment/email services
```

There is no framework router, dependency injection container, ORM, template engine, or frontend bundler. A root PHP page is normally both controller and view. Modules encapsulate reusable operations but still call the global `db()`, settings, URL, and security helpers directly.

## Asynchronous Dual-Gateway Architecture (v5.1)

All external platform traffic flows through two gateways — Bundle.social (organic engine) and Zernio (ads engine) — and every user-facing request is **asynchronous**:

**Feature split (comprehensive gateway surface):**
- **Bundle.social (organic):** post scheduling, multi-account connections, UGC/reel publishing, automated postings, **comment moderation** (`getPostComments`/`replyToComment`/`moderateComment`), connected-account listing, analytics, Google Calendar sync.
- **Zernio (paid ads):** campaign launching, tracking, ROAS monitoring, budget viewing & scaling (`setCampaignBudget`), pause/resume, ad-account listing, campaign detail, and **server-side conversion-event delivery** (`sendConversionEvent`) for Meta CAPI + TikTok Events. NO direct platform API remnant exists: `graph.facebook.com` / `business-api.tiktok.com` used to be contacted by `modules/server_tracking.php` — that module now routes everything through Zernio, holds no platform tokens (pixel IDs are routing labels only), and the platform-token columns are dropped by `database/migrate_async.php`. `pixel_token` remains (Adsbooster's own bridge credential for `api/v1/capi-bridge.php`).

1. **Producers** (`create-ad.php`, `post-creator.php`, `dashboard.php`, `edit-campaign.php`, `admin/campaigns.php`, `admin/social-media.php` — including comment replies/moderation, `server-tracking.php` test events, `api/v1/capi-bridge.php`, `api/v1/campaigns.php`, `api/v1/posts.php`, `modules/social_post_engine.php`, `modules/server_tracking.php`, `modules/auto_buyer_engine.php`, `cron/publish_scheduled_posts.php`) never make external HTTP calls. They persist a local row (campaign `pending`, post `scheduled`, event `queued`) and call `AsyncQueue::push()` with a dedupe `subject_key` (`post:{id}` / `campaign:{id}` / `event:{eventId}` / `comment-*:{commentId}`), then answer instantly.
2. **Queue** — the `async_job_queue` MySQL table (pending → processing → completed/failed/cancelled) with exponential backoff (30s, 60s, 120s… capped 1h), stale-lock reclaim (`last_attempt_at`, 15 min), terminal `failed` jobs carrying an honest error, and `retryFailed()` re-queueing.
3. **Worker** — `cron/queue_worker.php` is the ONLY synchronous gateway caller: `--once` (Task Scheduler batch), daemon loop, or `--retry-failed [--type=…]`. Job types: `bundle_publish_post`, `bundle_cancel_post`, `bundle_reply_comment`, `bundle_moderate_comment`, `zernio_launch_ad`, `zernio_toggle_status`, `zernio_send_conversion`. (Exception: the admin Comment-Moderation tab's comment *list* is a read-only gateway inspection — mutations still go through the queue.)
4. **Persistence honesty** — simulated payloads (`simulated: true`, placeholder API keys) never update rows as real outcomes: no fabricated `bundle_post_id` / `zernio_campaign_id`, statuses flip only on gateway confirmation, conversion events read explicit states (`queued` → `gateway_dispatched (HTTP…)` / `simulated` / `skipped` / `error: …`). Simulated holds terminal-fail with `SIMULATED: …` and re-run via `--retry-failed` once a live key is configured.

Schema: `database/async_gateway_migration.sql` (canonical artifact); `database/migrate_async.php` (idempotent guarded migrator incl. legacy-token purge, also invoked by the bootstrap). Verification: `database/smoke_gateways.php` (43 assertions, including the queue round-trip and Zernio-routed conversion delivery).

## Key Entry Points

- `config/site_setting_and_config.php`: central bootstrap and global helpers.
- `config/db.php`: `Database` PDO wrapper and `db()`/`pdo()` accessors.
- `config/apikeys.php`: credential resolution + gateway-centric integration status.
- `includes/security.php`: escaping, HTML sanitization, upload checks, SSRF/TLS helpers.
- `includes/app_header.php`: authenticated app guard, shared shell, complete user navigation.
- `includes/admin_guard.php`: role and admin-2FA gate.
- `.htaccess`: clean routes, parameterized routes, internal-directory blocking, headers, caching.
- `api/v1/bootstrap.php`: API-key authentication and 60-request/minute rate limiting.
- `modules/ai_engine.php`, `modules/ai_video_engine.php`, `modules/social_post_engine.php`, `modules/payments.php`: central product engines.

## Main Product Flows

### Authentication
`register.php` creates a user, assigns starter AI credits, optionally records a referral/team link, logs in, and redirects to onboarding. `login.php` verifies password/status, rate-limits attempts, branches through `two-factor-verify.php` when enabled, issues optional remember-me tokens, and sends a login alert. Admin access mirrors the user session and is additionally gated by `admin/totp-verify.php`.

### AI image and video
`ugc-generator.php` charges AI credits, builds a prompt, calls `GeminiEngine`, stores a same-origin image, asks Groq for copy, and logs the creative. Its editor uses `media-proxy.php`, `assets/js/image-editor.js`, and a browser canvas export model. `ugc-video-studio.php` generates scripts/storyboards, selects a cloud renderer or built-in storyboard mode, creates scene stills, renders video client-side with Canvas/MediaRecorder, uploads the result, and only then debits credits.

### Ads and social publishing
`create-ad.php` validates campaign/targeting/budget/media, debits ad credits, stores a campaign and ledger entry, then dispatches through the social publishing engine. `post-creator.php` generates captions/illustrations, publishes immediately to multiple connected networks, or writes a scheduled row. `cron/publish_scheduled_posts.php` claims due rows and publishes them without duplicating records.

### Metrics and automation
`cron/pull_campaign_metrics.php` currently imports real Meta insights only and intentionally does not invent unavailable metrics. Campaign Doctor, A/B winner selection, and auto-buyer rules consume `campaign_metrics_daily`; automatic rules can pause or resize local campaign budgets.

### Billing
Billing/checkout creates pending transactions through `PaymentProcessor`; `payment-verify.php` verifies the provider response and `completeTransaction()` updates the appropriate wallet or plan, applies referral rewards, emits notifications, and generates an invoice.

### Marketplace and collaboration
The app includes business/product/service listings, jobs, creator bounties, agency client approvals, ecommerce imports/auto-packs, public portfolios/vCards, resume-to-portfolio sync, P2P chat with an AI consultant and deal proposals, support tickets, referrals/payouts, team workspaces, and a dating/connection directory.

### Admin and developer platform
The admin area manages users, credits/plans, listings/jobs/reviews/blog/CMS, campaigns, AI/social/tracking, payouts/transactions, tickets/chat, SEO/policies/translations, settings, and operational logs. The developer surface exposes campaign, post, credit, UGC, directory, review, and CAPI endpoints plus API keys, rate limits, webhooks, and a browser/Node UMD SDK.

## Data Domains

- Accounts: `users`, `connected_accounts`, `team_members`, `team_invitations`, `notifications`.
- Ads: `ad_campaigns`, `ad_campaign_variants`, `campaign_metrics_daily`, `campaign_recommendations`, `campaign_run_logs`, `audience_segments`.
- AI: `ai_creatives`, `ai_video_creatives`, `ai_media_buyer_rules`, `ai_media_buyer_logs`, `competitor_ad_analyses`.
- Social/chat: `scheduled_posts`, `p2p_conversations`, `p2p_messages`, `p2p_deals`, social inbox tables.
- Commerce: `pricing_plans`, `transactions`, `invoices`, `referrals`, `referral_rewards`, `payout_requests`.
- Marketplace: `marketplace_items`, `jobs`, creator bounty tables, agency tables, ecommerce tables.
- Public profiles: `user_portfolios`, `user_vcards`, dating profile/pair/block/report tables.
- CMS/ops: `site_settings`, `dynamic_content`, `blog_posts`, `site_reviews`, translations, tickets/messages, fraud/visitor/cookie/cron/email/service logs.
- Developer/tracking: `api_keys`, `api_rate_limits`, `webhooks`, server tracking tables.

## Current Readiness Caveats

1. The gateway conversion is complete: `connected_accounts` is gateway-shaped (`gateway_provider`, `account_type`, `platform_name`, `external_account_id`) and all platform traffic routes through `modules/ThirdPartyGateway.php`. Connect auth is journey-separated: HMAC-signed, single-use `issueGatewayState()`/`validateGatewayState()` states bind each callback to its gateway (Bundle.social organic vs Zernio ads). The migration drops and rebuilds the table and can be auto-invoked from the main bootstrap — back up before running on existing data.
2. The master SQL dump is missing, so documented first-load installation and the web installer cannot initialize a clean environment. However, `database/build_cpanel_sql.php` and `database/export_comprehensive_sql.php` regenerate a complete dump from whichever DB the configured `config/db.php` points at (`SHOW TABLES` → `SHOW CREATE TABLE`). Run either on a fully-populated host to rebuild `adsbooster_complete.sql`.
3. There are inconsistent duplicate concepts: `marketplace_items` vs `marketplace_listings`, `support_tickets` vs the `tickets` view, and `team_members` vs `team_invitations`.
4. Several older modules call transaction methods on the custom `Database` wrapper that it does not implement (`beginTransaction`, `commit`, `rollBack`, `inTransaction`).
5. Resolved: the orphaned `modules/google_calendar.php` (which called an undefined `GoogleAuth::refreshAccessToken()`) was deleted; calendar sync now delegates to the Bundle.social gateway (`ThirdPartyGateway::createCalendarEvent()` / `deleteCalendarEvent()`).
6. No automated tests, CI, static analysis, or coverage configuration exists. `database/migrate_features.php` has no automated caller — run it manually against any schema missing the AI-video, ecommerce, server-tracking-pixel, social-inbox, agency, or creator-bounty tables.
7. Composer dependencies are not installed, the official SDK package directory is empty, and the active PHP build lacks `zip`.
8. Static syntax checks pass under PHP 8.3 and 8.4, including the current gateway files. Runtime verification has since been executed against a sandboxed MySQL instance (`database/smoke_gateways.php`: 43/43 PASS — gateway config resolution, all organic/ads simulation payloads flagged `simulated`, single-use provider-bound connect-state auth round-trips, replay/forgery/cross-provider rejection, the full async queue round-trip, comprehensive gateway methods (comment moderation + Zernio budgets/events), and Zernio-routed conversion delivery holding honestly in sandbox). Apache/HTTP flows still await a running server for end-to-end browser checks.

## Where to Change Things

| Goal | Primary locations |
|---|---|
| Add an authenticated page | Root `*.php`, `includes/app_header.php`, `includes/app_footer.php` |
| Add reusable business logic | `modules/` static class following existing patterns |
| Add an API operation | `api/v1/bootstrap.php`, direct `api/v1/<resource>.php`, SDK, developer docs |
| Change auth/security | `config/site_setting_and_config.php`, guards, `includes/security.php` |
| Change schema | A reviewed idempotent migration plus regenerated master SQL dump |
| Add background behavior | CLI-only script under `cron/`, log to `cron_run_logs` |
| Change public UI | `includes/header.php`, `includes/footer.php`, page, `assets/` |
| Change admin behavior | `admin/<feature>.php`, `admin/sidebar.php`, admin guard |

## Verification Baseline

- PHP syntax: all current PHP files pass `php -l` under PHP 8.3.36 and 8.4.12.
- Tests: none found; `database/smoke_gateways.php` provides a CLI gateway + async-queue verification suite (43 assertions).
- Runtime: gateway migration and gateway/cron behavior verified against a sandboxed MySQL 8.4.3 instance; Apache browser flows still unexercised.
- Database schema: inferred from code plus `database/migrate_gateways.php`; the historical full dump `adsbooster_complete.sql` remains absent (the auto-installer in the bootstrap requires it for first-load installs).
