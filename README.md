# Adsbooster.net — Next-Gen AI Ad Optimization, UGC Image Studio & Verified Growth Directory

[![PHP Version](https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3%20%7C%208.4-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Database](https://img.shields.io/badge/MySQL-8.0%2B%20%7C%20MariaDB-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![AI Engines](https://img.shields.io/badge/AI-Google%20Gemini%20(Nano%20Banana%202%20%2B%20Veo)%20%7C%20Groq%20LPU%20%7C%20Firecrawl-f55036?style=flat-square)](https://ai.google.dev)
[![License](https://img.shields.io/badge/License-Proprietary-blue?style=flat-square)](#)

**Adsbooster.net** is an enterprise-grade digital advertising optimization platform, AI UGC image creative studio, real-time multi-network social publisher, verified business marketplace, and thought leadership platform built with vanilla PHP 8.x, dark luxury aesthetics, and multi-model AI intelligence.

---

## 🌟 Key Platform Capabilities

### 1. 🎨 AI UGC Image Generator & Creative Studio (`ugc-generator.php`)
- **Photorealistic AI UGC Generation**: Instant creative generation via the **Google Gemini image stack** — Nano Banana 2 (`gemini-3-pro-image-preview`) first, Nano Banana (`gemini-2.5-flash-image`) as fallback — with curated first-party stills when no key is configured. Groq LPU synthesizes the ad copy.
- **Canva-Style Image Editor**: Filters, badge stamps, headline typography, and canvas export served same-origin through `media-proxy.php` so downloads never fail.
- **Creator Personas & Shoot Styles**:
  - *Personas*: Gen Z Creator, SaaS Founder, Fitness Athlete, Everyday Homemaker, Tech Reviewer, Luxury Fashion Model.
  - *Styles*: Handheld Selfie Product Hold, Unboxing Moment, Lifestyle Kitchen Setting, Influencer Desk Setup, Dramatic Before & After, Minimalist Aesthetic Flatlay.
  - *Aspect Ratios*: 1:1 Square (Feed), 9:16 Vertical (Stories/Reels/TikTok), 16:9 Landscape (YouTube/Banner), 4:5 Portrait (Instagram Grid).
- **Interactive HTML5 Canvas Studio**:
  - Real-time photo filters: Brightness, Contrast, Saturation, Sepia Warmth, and High-Contrast Grayscale.
  - Badge stamps: `VERIFIED`, `50% OFF SALE`, `⭐ TOP RATED`, `BEST SELLER`, `NEW ARRIVAL`, `LIMITED STOCK`.
  - Headline typography and subtext overlays with real-time text styling and font size sliders.
- **1-Click Variation Regeneration**: Synthesizes alternative creative variations using randomized seed generation while preserving the core product concept.
- **Direct Deployment**: 1-click download in high resolution or instant push into live multi-network social post creator.

### 1b. 🎬 AI Video Studio (`ugc-video-studio.php`, `modules/ai_video_engine.php`, `modules/gemini_engine.php`)
- **FREE default route — Pollinations (`pollinations`)**: keyless, no API key, no account. Renders photoreal scene stills via the free `image.pollinations.ai` Flux endpoint and composites them into a real MP4/WebM in your browser with the built-in Storyboard compositor. Force it with `provider=pollinations`.
- **Full Google AI Video Pipeline**: Text-to-video via **Google Veo** (`veo-3.1-generate-preview` → `veo-3.0` fallback chain) whenever a `GEMINI_API_KEY` is present; **Nano Banana 2** paints a photoreal still for every storyboard scene, stored same-origin so the canvas compositor never taints.
- **Premium async cloud routes**: Replicate (Kling / Wan / Minimax), fal.ai (Kling 2.5 / Veo), Runway Gen-3 Alpha Turbo. (Luma has been removed — the free Pollinations route replaces it.)
- **Storyboard Studio (built-in, always works)**: Browser composites scenes into a real MP4/WebM via `canvas.captureStream()` + MediaRecorder — Ken Burns zoom, TikTok-style bouncing captions, synthesized beat, and Web Speech voice-over. Cloud render rejections automatically fall back to this path, so a render never dead-ends.
- **Library Actions per video**:
  - **Edit** — loads the script, hook, CTA, avatar, style, and scenes back into the composer; *Update & re-render* rebuilds in place (same creative id).
  - **Regenerate** — same script, brand-new AI scene stills (fresh Gemini seed).
  - **Download** — one-click MP4/WebM export.
  - **Run ad** — sends the video straight into the ad launcher (`create-ad.php?video_id=…`).
  - **Post** — sends it to the Post Creator for multi-network publishing.
- **UGC talking-head and cinematic commercial modes** with creator avatars, voices, caption styles, aspect ratios (9:16 / 1:1 / 16:9), and durations 10-120s.

### 2. 🚀 Dual Third-Party Unified Gateways (`modules/ThirdPartyGateway.php`, `social-connect.php`, `create-ad.php`, `post-creator.php`)
100% of external platform traffic is routed exclusively through two enterprise-grade third-party gateways. Direct social network API credentials (direct Meta Graph, TikTok Marketing, Google Ads API, Snapchat Marketing API) have been eliminated:

```
                              +---------------------------------------+
                              |            ADSBOOSTER.NET             |
                              |       (Vanilla PHP 8.2+ Core)         |
                              +-------------------+-------------------+
                                                  |
              +-----------------------------------+-----------------------------------+
              |                                                                       |
              v                                                                       v
+-------------------------------+                                       +-------------------------------+
|   BUNDLE.SOCIAL GATEWAY       |                                       |        ZERNIO GATEWAY         |
|     (Organic Engine)          |                                       |         (Ads Engine)          |
+-------------------------------+                                       +-------------------------------+
| - Organic Post Scheduling     |                                       | - Meta Ads (FB & Instagram)   |
| - UGC & Reel Publishing       |                                       | - TikTok Video Ads            |
| - Multi-Account Connections   |                                       | - Google Ads & Search/PMax    |
| - Comment Moderation & Reply  |                                       | - LinkedIn & Pinterest Ads    |
| - Per-Post Organic Analytics  |                                       | - Reddit Ads                  |
| - Multi-Account Content Queue |                                       | - Real-Time Pause / Resume    |
| - Stored as organic_profile   |                                       | - Google Calendar Events Sync |
+---------------+---------------+                                       +---------------+---------------+
|                                                                       |
v                                                                       v
[Social Platforms: FB, IG, TT, X, LinkedIn]             [Ad Networks: Meta, TikTok, Google, LinkedIn, Pinterest]
```

- **Bundle.social Gateway (`https://api.bundle.social`, `/api/v1/...`, `x-api-key` auth) — Organic Engine**:
  - Powers all multi-account organic publishing across Facebook, Instagram, TikTok, X (Twitter), LinkedIn, Pinterest, YouTube and more (real enum: `POST /api/v1/social-account/connect`).
  - Generates seamless OAuth connection URLs via `ThirdPartyGateway::getBundleConnectUrl($userId, $platform)` (server route: `dashboard.php?action=connect_bundle&platform=…` → `POST /api/v1/social-account/connect {type, teamId, redirectUrl}` → `{url}`).
  - Callback landing URI: `https://yourdomain.com/dashboard.php?action=bundle_connected` (Bundle.social redirects back to `redirectUrl` as-is, or with `?error=` appended).
  - Dispatches immediate and queued posts via `ThirdPartyGateway::createOrganicPost()` → `POST /api/v1/post/` body `{teamId, title, postDate, status: SCHEDULED, socialAccountTypes, data:{PLATFORM:{text, uploadIds}}}` (media pre-uploaded via `POST /api/v1/upload/from-url`); tracks `bundle_post_id` across `scheduled_posts`.
  - Comment surface: list/reply/moderate via `GET|POST /api/v1/comment/`, `PATCH|DELETE /api/v1/comment/{id}`; per-post analytics via `GET /api/v1/analytics/post?postId=…`.
  - Direct social access tokens are never stored locally; connections persist in `connected_accounts` with `gateway_provider = 'bundle_social'` and `account_type = 'organic_profile'`.

- **Zernio Gateway (`https://zernio.com/api/v1`, Bearer auth) — Paid Advertising Engine + Calendar**:
  - Handles campaign launches across Meta Ads (Facebook & Instagram), TikTok Video Ads, Google Ads (Search & Performance Max), LinkedIn, Pinterest, X and Reddit Ads via the unified `POST /v1/ads/create` endpoint (legacy single-creative shape, `Idempotency-Key` header sent on every launch).
  - Ad-account connect: `dashboard.php?action=connect_zernio&platform=…` → `ThirdPartyGateway::getZernioAdConnectUrl($userId, $network)` → profile ensure (`POST /v1/profiles {externalId}`) then `GET /v1/connect/{platform}/ads?profileId=…&redirect_url=…` → `{authUrl, state}` or `{alreadyConnected: true}`. Zernio has **no completion step**: it completes OAuth on its own callback and redirects the browser to `redirect_url` as-is.
  - Callback landing URI: `https://yourdomain.com/dashboard.php?action=zernio_connected`.
  - Dispatches campaigns via `ThirdPartyGateway::launchPaidCampaign()`, persisting `zernio_campaign_id` and `zernio_ad_account_id` in `ad_campaigns`.
  - Real-time 1-click status synchronization: `ThirdPartyGateway::setAdCampaignStatus($zernioCampaignId, 'active'|'paused', $platform)` → `PUT /v1/ads/campaigns/{id}/status` body `{status, platform}` (both required).
  - Budget scaling: `ThirdPartyGateway::setCampaignBudget($id, $amount, 'daily'|'lifetime')` → `PUT /v1/ads/campaigns/{id}` body `{budget:{amount,type}}`.
  - Automated background metrics ingestion: `cron/pull_campaign_metrics.php` queries `ThirdPartyGateway::getCampaignMetrics($zernioCampaignId)` → `GET /v1/ads/campaigns/{id}/analytics` to populate `campaign_metrics_daily`.
  - Server-side conversion delivery: `ThirdPartyGateway::sendConversionEvent()` → `POST /v1/ads/conversions` (Meta CAPI & TikTok Events via Zernio; `GET /v1/ads/accounts` lists connected ad accounts).

- **Unified Developer & Sandbox Resilience**:
  - When API keys remain at placeholder defaults (`YOUR_BUNDLE_SOCIAL_API_KEY`, `YOUR_ZERNIO_API_KEY`), `ThirdPartyGateway` automatically activates sandbox simulation, preventing staging crashes and enabling offline testing.

### 3. 🌐 Developer Platform, REST API v1 & SDKs (`api/v1/*`, `developer-docs.php`, `api-settings.php`)

- **One Unified Master API Key**: Single bearer token granting comprehensive access across all platform domains (`read`, `write`, `ads`, `ai`, `social`, `directory`, `wallet`, `ugc`, `reviews`).
- **Official Multi-Language SDKs**:
  - **JavaScript / TypeScript SDK** (`sdk/adsbooster-js-sdk/` & `/api/v1/sdk/adsbooster.js`): Zero-dependency Node ≥18 + browser client with full TypeScript typings, automatic 429/5xx retries, rate-limit telemetry, typed errors, and Node webhook signature verification. Includes 1-click campaign controls (`campaigns.pause()`, `campaigns.resume()`), `posts.create()` returning `bundle_post_id`, and `campaigns.create()` returning `zernio_campaign_id`.
  - **Python SDK / Client**: Modern `requests`-based client for automated ad launching, status toggling, and social scheduling.
  - **PHP Client**: Native cURL client for rapid server-to-server integration.
- **Sliding-Window Rate Limiting**: Enforces a strict quota of 60 requests per minute tracked in the `api_rate_limits` table. Exceeded quotas return HTTP 429 Too Many Requests with standard `X-RateLimit-Limit`, `X-RateLimit-Remaining`, and `X-RateLimit-Reset` headers.
- **Public Aggregate Analytics Endpoint** (`/api/v1/public-analytics.php`): Authentication-free, GET-only JSON feed of the SAME aggregate analytics the homepage achievements modal renders — totals, performance ratios, 14-day trend and platform mix, zero user-level data. IP-keyed limit of 30 requests/minute (429 + `Retry-After`), CORS `*`, safe for public widgets and blog embeds.
- **Real-Time HMAC-SHA256 Webhooks**: Outbound event notifications (`campaign.status_changed`, `post.published`, `payment.completed`, `webhook.test_ping`) signed with cryptographic `X-Adsbooster-Signature` headers.
- **Interactive Webhook Tester**: Integrated "Ping Test" in the Developer Console (`api-settings.php`) verifying target endpoints in real time with millisecond latency telemetry.
- **Comprehensive A–Z Documentation**: Full reference manual (`developer-docs.php`) complete with interactive copyable code snippets in cURL, JavaScript (Fetch / SDK), Python, and PHP.
- **Live System Status Page**: Real-time service uptime, latency tracking, and operational health dashboard (`status.php`).

### 4. ⭐ Community Reviews & Rating System (`reviews.php`, `index.php`, `dashboard.php`, `admin/reviews.php`)
- **Tiered Eligibility Enforcement**:
  - **Pro Plan Subscribers**: May submit and update verified platform reviews at any time.
  - **Free Plan Users**: Unlock review submission after 5 days of registered platform usage. Free members receive an unobtrusive dashboard banner with "Leave Review" or "Skip for now" (dismisses for 7 days via cookie and session).
- **Dynamic Homepage Showcase**: Top-rated verified reviews dynamically featured on the homepage (`index.php`) displaying user avatars, ratings, review comments, and membership plan badges.
- **Administrative Review Moderation**: Dedicated admin suite (`admin/reviews.php`) to approve, feature, edit, or reject submitted community feedback.

### 5. 💼 Dedicated Public Portfolios & AI Resume Import (`portfolio.php`, `portfolio-builder.php`)
- **Dedicated Public Showcase**: Every registered member receives an SEO-indexed, shareable public portfolio link (`/portfolio.php?u=username`) accessible without login.
- **4 Luxury Design Themes**:
  - *Dark Luxury Noir* (Obsidian `#080c14` with lime `#c8ff00` accents)
  - *Creative Cyber Neon* (Vibrant purple & cyan gradient glow)
  - *Executive Titanium* (Corporate slate and titanium elegance)
  - *Minimalist Aurora Glass* (Emerald `#10b981` with frosted blur)
- **Portfolio Studio (`portfolio-builder.php`)**: Full visual editor with dynamic repeaters for Case Studies & Projects (title, highlight metric, description, link), Service Packages (title, price, scope), Core Skills tag manager, social networks, WhatsApp button, and a live card preview.
- **AI Resume Import (Groq-powered)**: Drag-and-drop `.pdf`, `.docx` (native `ZipArchive`) and `.txt` support alongside raw text pasting. Extracts bio, skills, services and quantified case studies, computes an ATS score, and 1-click syncs into `user_portfolios` and `users`.
- **Public QR Code**: Each portfolio renders a scannable QR code (fullscreen modal) that opens the public portfolio from any phone camera.

### 6. 💼 Advertising & Creative Talent Marketplace (`jobs.php`, `job-details.php`, `create-job.php`)
- **Opportunities & Bounties**: Browse and post contracts, retainers, and bounties for media buyers, UGC creators, copywriters, and performance marketers.
- **Multi-Filter Discovery**: Search by keyword, contract type (Contract, Freelance, Bounty, Full-Time), workplace mode (100% Remote, Hybrid), and budget range.
- **Direct Employer Connect**: Inquire and apply instantly through P2P Chat (`p2p-chat.php?user=...`) or WhatsApp.

### 7. 🤝 Enhanced P2P Chat with Contract & Deal Proposals (`p2p-chat.php`)
- **Real-Time Collaboration**: Connect with clients, freelancers, sellers, and platform administrators.
- **Deal & Contract Proposal Tool**: Propose structured project contracts with agreed amounts ($ USD), milestones, and deliverables stored in `p2p_deals`.
- **Public Profile Links**: View any chat partner's public portfolio directly from the conversation header.
- **Safety Tools**: Integrated user reporting, block controls, and 24/7 AI Growth Consultant assistant.

### 8. 🏢 Verified Business & Product Directory (`marketplace.php`)
- **Dynamic Homepage Showcase**: Embedded directly onto `index.php` with live seller badges and contact channels.
- **Instant Buyer-Seller Direct Connect**: One-click WhatsApp direct chat (`wa.me`) and instant click-to-call (`tel:`).
- **Tiered Seller Verification**: Verified merchant badges, category filtering (SaaS, Agency, Ecommerce, Real Estate), and geolocation.

### 9. 🔐 Robust Two-Factor Authentication (2FA) & Admin Gate (`security-2fa.php`, `admin/totp-verify.php`, `admin-login.php`)
- **RFC 6238 TOTP Standard**: Compatible with Google Authenticator, Authy, Microsoft Authenticator, and 1Password.
- **Admin Verification Gate**: All administrative sessions require 2FA TOTP verification challenge (`admin/totp-verify.php`) before dashboard access is granted.
- **Email OTP Verification**: Fallback verification codes dispatched to verified account emails.
- **Emergency Recovery Codes**: 8 single-use cryptographically hashed backup codes for account retrieval.

### 10. 🛡️ Administrative Control Suite (`/admin/`)
- 25 administrative modules across 7 sidebar sections (Overview, Users & Access, Content & Marketplace, Revenue, AI & Automation, Communication, Safety & Config), all reachable from the sidebar:
  - `index.php` — Platform overview, telemetry, financial summary, and audit logs.
  - `system-health.php` — Worker queues, cron telemetry, and service diagnostics.
  - `users.php` — **Full user management**: KPI cards, filters (status/role/search), pagination, profile editing, ad **and** AI credit adjustments, plan changes with expiry, suspend/activate, seller verification, one-time password reset, 2FA reset, guarded account deletion (email confirmation), and **impersonation** ("view as user" with a yellow exit banner).
  - `settings.php` — Site identity, AI keys (Gemini/Groq/NVIDIA NIM/Firecrawl), **default video provider** (Auto/Veo/Pollinations free/Storyboard), **maintenance mode** (public sees a 503 page; admins stay in), **open/closed registration** (invite links still work), and the new-user AI credit grant.
  - `pricing.php` — Dynamic subscription tiers, credit quotas, and feature flags.
  - `listings.php` — Marketplace directory moderation and badge assignment.
  - `jobs.php` — Talent opportunities and bounty moderation.
  - `reviews.php` — Community review moderation and homepage featuring.
  - `campaigns.php` — Ad campaign inspection, manual budget adjustment, and status overrides.
  - `ugc-studio.php` / `ai-media-buyer.php` / `social-media.php` / `server-tracking.php` — AI creative oversight, autonomous buying rules, publishing hub, and CAPI bridge.
  - `agency-approvals.php` — Whitelabel portals and client content approvals.
  - `payouts.php` & `transactions.php` — Financial ledger inspection and withdrawal approvals.
  - `cms.php` & `blogs.php` — Article review and static content manager.
  - `analytics.php` — Platform-wide conversion, latency, and revenue metrics.
  - `tickets.php` — Customer support ticket desk and resolution pipeline.
  - `p2p-chat.php` — Escalated dispute review and chat safety monitoring.
  - `blogs.php` moderation covers public portfolio SEO fields.
  - `seo.php` — Meta titles, descriptions, OpenGraph tags, and sitemap settings.
  - `policy-rules.php` — Platform terms, safety guidelines, and advertising policies.
  - `referrals.php` — Affiliate tracking, referral tiers, and payout thresholds.
  - `fraud-watch.php` — Click-fraud detection and IP anomaly telemetry.
  - `translations.php` — Multilingual dictionary and localization strings.

---

## 🛠️ Technology Stack

- **Backend**: Pure PHP 8.1 - 8.4 (Zero framework overhead, extreme performance)
- **Database**: MySQL 8.0+ / MariaDB 10.3+ (InnoDB, utf8mb4 / utf8mb4_general_ci, indexed relations)
- **Frontend**: Vanilla CSS Design System (Dark Luxury Palette `#080c14`, `#0d1524`, `#c8ff00`), Phosphor Icons
- **Rich Text Editor**: CKEditor 5 Classic via official CDN
- **Mail Engine**: PHPMailer 6.x with custom dark luxury HTML templates
- **AI Providers**: Google Gemini (Nano Banana 2 image models, Veo 3.x video, Gemini 2.5 Flash), Groq LPUs (`openai/gpt-oss-120b`, `qwen/qwen3.8-27b`), NVIDIA NIM (`meta/llama-3.1-70b-instruct` via integrate.api.nvidia.com), Firecrawl (live web scraping), **Pollinations (FREE keyless video stills)**
- **Third-Party Gateways (100% Unified Routing)**:
  - **Bundle.social API** (`https://api.bundle.social/api/v1`, `x-api-key`): Organic Social Media Management (FB, IG, TikTok, X, LinkedIn, Pinterest, YouTube and more; comment moderation; post analytics)
  - **Zernio API** (`https://zernio.com/api/v1`, Bearer): Paid Advertising Management (Meta Ads, TikTok Ads, Google Ads & PMax, LinkedIn Ads, Pinterest Ads, X Ads, Reddit Ads) + Google Calendar events + server-side conversion delivery (Meta CAPI / TikTok Events)
- **Billing Gateways**: Paystack & Flutterwave
- **Client SDKs**: Official JavaScript/TypeScript SDK (`adsbooster-js-sdk` & `/api/v1/sdk/adsbooster.js`), Python, and PHP client integrations

---

## 🚀 Quickstart Guide

### 1. Local Development (Laragon / XAMPP)
1. Clone the project into your web directory:
   ```bash
   git clone https://github.com/your-org/adsbooster.git C:\laragon\www\seopp
   ```
2. Start Apache and MySQL from Laragon.
3. Create an empty database `adsbooster` — the app **auto-installs the full schema on first page load** (guarded installer in `database/install.php`). Run the gateway migration runner to configure third-party gateways:
   ```bash
   php database/migrate_gateways.php
   # Or import manually: mysql -u root adsbooster < database/adsbooster_master.sql
   ```
4. Optional DB overrides — create `config/db.local.php` (never committed):
   ```php
   <?php return ['host' => '127.0.0.1', 'name' => 'adsbooster', 'user' => 'root', 'pass' => ''];
   ```
5. Add your `BUNDLE_SOCIAL_API_KEY` and `ZERNIO_API_KEY` in Admin → Settings (or `config/apikeys.php`). Add `GEMINI_API_KEY` to enable Nano Banana 2 images + Veo video. Groq powers text generation; Firecrawl powers live store scraping.
6. Access the application in your browser:
   ```
   http://localhost/seopp
   ```
   Clean URLs (`/login`, `/dashboard`, `/ugc-video-studio`, `/social-connect`) work via the root `.htaccess`.

### 2. Live Linux Server Deployment
Refer to the comprehensive, step-by-step [DEPLOYMENT.md](DEPLOYMENT.md) for Apache VirtualHost, Nginx reverse proxy, SSL configuration, and cron automation.

---

## 📂 Project Directory Structure

```
seopp/
├── admin/                     # Administrative management dashboard & moderation
│   ├── index.php              # Admin overview & system metrics
│   ├── totp-verify.php        # Admin 2FA TOTP verification challenge
│   ├── pricing.php            # Subscription & pricing plan manager
│   ├── listings.php           # Marketplace directory moderation
│   ├── jobs.php               # Jobs & bounties moderation
│   ├── reviews.php            # Community reviews moderation
│   ├── blogs.php              # Blog post moderation
│   ├── settings.php           # Platform & Dual Third-Party Gateway credentials
│   ├── campaigns.php          # Ad campaign inspection & Zernio synchronization
│   ├── social-media.php       # Social publishing hub & Bundle.social force-publish
│   └── users.php              # User role & permission controls
├── api/v1/                    # Public REST API v1
│   ├── bootstrap.php          # Auth, Master API Key verification, Rate limiter
│   ├── campaigns.php          # Ad campaign endpoints (Zernio Ads Engine backed)
│   ├── capi-bridge.php        # Meta CAPI / TikTok Events server-side bridge
│   ├── posts.php              # Social post creation & scheduling (Bundle.social backed)
│   ├── ugc.php                # AI UGC image generation endpoints
│   ├── directory.php          # Business directory endpoints
│   ├── reviews.php            # Community review submission & querying
│   ├── credits.php            # Balance check & credit transaction endpoints
│   ├── sdk/adsbooster.js      # Self-hosted JavaScript SDK (UMD) for developers
│   └── webhooks.php           # Webhook sender & test ping helper
├── sdk/adsbooster-js-sdk/     # Official JavaScript SDK package (src, dist, types, examples)
├── assets/                    # Static assets (CSS, JS, Fonts, Images)
├── config/                    # System configurations
│   ├── db.php                 # Database PDO singleton connection & DB::getConnection() wrapper
│   ├── site_setting_and_config.php # Constants & session handlers
│   └── apikeys.php            # Groq, Gemini, Bundle.social, Zernio, SMTP, & Payment API keys
├── cron/                      # Automated scheduled background scripts
│   ├── publish_scheduled_posts.php # Dispatches scheduled queue via Bundle.social
│   ├── pull_campaign_metrics.php   # Ingests real ROAS/CTR metrics from Zernio
│   ├── auto_buyer_daemon.php       # Evaluates anti-burn rules & syncs pauses to Zernio
│   ├── ab_test_winner.php          # Declares A/B test winners & reallocates budget
│   └── campaign_doctor.php         # Analyzes health & generates auto-pilot recommendations
├── database/                  # Master migrations and seeders
│   ├── adsbooster_master.sql  # Dual Third-Party Gateway schema definitions
│   ├── migrate_gateways.php   # Automated idempotent gateway migration runner
│   ├── adsbooster_complete.sql# Complete master database dump
│   └── export_master_sql.php  # Automated database snapshot generator
├── includes/                  # Reusable layouts, headers, and navigation
├── modules/                   # Core business logic classes
│   ├── ThirdPartyGateway.php  # Central unified API gateway (Bundle.social + Zernio)
│   ├── social_post_engine.php # Real-time multi-network publishing engine
│   ├── auto_buyer_engine.php  # Autonomous anti-burn kill switch engine
│   ├── audience_builder.php   # Custom audience segmentation & lookalike models
│   ├── ab_test.php            # Creative variant testing engine
│   ├── ai_engine.php          # Groq/Gemini text, UGC images (Nano Banana 2), resume parser
│   ├── ai_video_engine.php    # Veo + Storyboard Studio video lifecycle (create/edit/regenerate)
│   ├── gemini_engine.php      # Google Gemini image stack (Nano Banana 2 chain) + Veo model list
│   ├── firecrawl_scraper.php  # Live web/site scraping for store imports & AI chat context
│   ├── ecommerce_sync.php     # Shopify/WooCommerce import + campaign auto-packs
│   ├── email_service.php      # PHPMailer & branded HTML email system
│   └── two_factor.php         # RFC 6238 TOTP authentication library
├── api-settings.php           # Developer console, Master API key & webhook tester
├── developer-docs.php         # Complete A-Z developer documentation manual
├── status.php                 # Real-time service uptime & health telemetry
├── ugc-generator.php          # AI UGC Image Studio & Canva-style canvas editor
├── ugc-video-studio.php       # AI Video Studio — Veo/Nano Banana 2, edit, regenerate, run ads
├── post-creator.php           # Real-time multi-channel post composer & publisher (Bundle.social)
├── create-ad.php              # Multi-network paid ad campaign launcher (Zernio)
├── edit-campaign.php          # Campaign manager with 1-click Zernio pause/resume sync
├── media-proxy.php            # Same-origin image proxy (canvas-safe editor downloads)
├── ecommerce-sync.php         # Shopify & WooCommerce 1-Click sync
├── ad-spy.php                 # Competitor ad spy & reverse-engineering engine
├── ai-media-buyer.php         # Autonomous media buyer & anti-burn kill-switch
├── social-connect.php         # Dual gateway account manager (Bundle.social + Zernio)
├── calendar.php               # Interactive content calendar with Google Calendar sync
├── audience-builder.php       # Demographic cohorts & AI lookalike segment builder
├── ab-test.php                # Split testing studio for ad variants
├── portfolio.php              # Dedicated public showcase with QR sharing
├── portfolio-builder.php      # Showcase Studio, dynamic repeaters & theme designer
├── reviews.php                # Community review submission portal
├── marketplace.php            # Verified business & product directory
├── jobs.php / job-details.php # Advertising talent marketplace
└── security-2fa.php           # User Two-Factor Authentication manager
```

---

*© 2026 Adsbooster.net Engineering Team. All rights reserved.*
