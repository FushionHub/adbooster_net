# ADVANCED PROMPT — Adsbooster.net Competitive Differentiators
### (Addendum to `adsbooster-master-prompt.md` — read that file first, this extends it, nothing here overrides it)

Everything below must obey the same hard constraints already set: vanilla PHP (no framework), PDO + prepared statements, MySQL/InnoDB/latin1, one feature per file under `modules/` or its own top-level/admin page, dark-theme/green-accent UI reusing the existing template components, email-first behavior, dynamic `BASE_URL`, and the existing credit/wallet/plan system (`ad_credits`, `ai_credits`, `pricing_plans`, `transactions`). Every new feature must plug into the existing `users`, `ad_campaigns`, `scheduled_posts`, `marketplace_items`, `team_invitations`, and `site_settings` tables rather than duplicating them.

Treat this as a second QA pass: these are the features that make Adsbooster.net feel like a category leader instead of a template clone — competitive against Vista Social, AdEspresso, Hootsuite, and VowAds combined — while staying inside the exact same vanilla-PHP/MySQL architecture.

---

## 1. Unified Cross-Network Analytics Command Center (`analytics.php`)

The single biggest differentiator: one dashboard that blends **ad performance** (`ad_campaigns`) and **organic post performance** (`scheduled_posts`) into one view per user, instead of forcing them to check each network separately.

- New table `campaign_metrics_daily` (campaign_id, date, impressions, clicks, spend, conversions, ctr, cpc — one row per campaign per day, populated by a cron puller `cron/pull_campaign_metrics.php` that calls each network's reporting API and falls back to simulated data until live keys are supplied).
- New table `post_metrics_daily` (post_id, date, reach, likes, comments, shares, saves).
- `analytics.php`: date-range picker, network filter, a "Best performing creative" panel, a "Underperforming — needs attention" panel, and a simple day-over-day trend chart (plain Chart.js or a hand-rolled SVG sparkline — no heavy JS framework).
- Exportable as CSV/PDF using the same pattern as `admin/transactions.php`.
- Admin gets a platform-wide version (`admin/analytics.php`) aggregating across all users for business intelligence.

---

## 2. AI Campaign Doctor & Auto-Budget Optimizer (`modules/campaign_doctor.php`)

Turns the existing `ai_engine.php` from a copywriting tool into a strategic advisor.

- Scheduled job (`cron/campaign_doctor.php`) scans each active campaign's `campaign_metrics_daily` trend and flags: rising CPC, falling CTR, budget exhaustion ETA, or a stalled combo-pack network.
- Writes recommendations into a new `campaign_recommendations` table (campaign_id, recommendation_type, message, suggested_action, status: pending/applied/dismissed).
- Dashboard widget surfaces "3 campaigns need attention" with one-click **Apply Suggestion** (e.g., shift 20% of budget from the weakest network to the strongest within a combo pack) or **Dismiss**.
- Weekly digest email (via `email_service.php`) summarizing wins, losses, and recommended actions — reinforces the email-first requirement while adding real retention value.
- Optional: opt-in **Auto-Pilot mode** per campaign (`ad_campaigns.autopilot_enabled` boolean) where the doctor applies safe recommendations automatically without user confirmation, logged to `campaign_recommendations.status = 'auto_applied'`.

---

## 3. Ad Policy & Brand-Safety Pre-Flight Scanner (`modules/policy_scanner.php`)

Directly strengthens the existing "policy compliance checkbox" into something real instead of a trust-me checkbox.

- Before a campaign or post can be submitted, run copy + creative metadata through a lightweight rules engine: banned-word list per network (stored in `policy_rules` table: platform, rule_type text/image/url, pattern, severity), destination URL reachability check, and image/video basic content checks (aspect ratio, min resolution, file size limits per network).
- Returns a **Compliance Score** (0–100) and a list of flagged issues shown inline on `create-ad.php` / `post-creator.php` before the final submit button unlocks for anything scoring below a configurable threshold (admin-tunable in `site_settings`).
- Admin can manage the rule list from `admin/policy-rules.php` — this is what makes it "meets and match content/ads policy of the socialmedias" a verifiable feature, not just marketing copy.
- Every scan is logged to `policy_scan_logs` for audit/dispute purposes if a network later rejects an ad.

---

## 4. Creative A/B Split Testing (`ab-test.php`)

- Users can launch a campaign with 2–4 creative/headline variants (`ad_campaign_variants` table: campaign_id, variant_label, headline, primary_text, creative_media_url, traffic_split_pct).
- `campaign_metrics_daily` extended with a `variant_id` nullable column.
- Auto-declare-winner logic (cron-based) that, once a statistically-reasonable sample is hit (simple threshold, not full stats significance testing — keep it lightweight), reallocates the remaining budget toward the winning variant and notifies the user by email.
- Results view reuses the analytics chart component from §1.

---

## 5. Smart Audience Builder & Lookalike Suggestions (`audience-builder.php`)

- New table `audience_segments` (user_id, name, criteria_json — age range, geo, interests, device, and optionally "based on past converting campaigns").
- Reusable from `create-ad.php`'s targeting step as a saved-segment dropdown instead of retyping targeting every time.
- "Suggest a lookalike" button: a simple heuristic (not real ML) that looks at the user's best-performing past campaign's `targeting_spec` and proposes a nearby variant (broadened interests, adjacent age bracket) — clearly labeled as a heuristic suggestion, not a guarantee.

---

## 6. Content Calendar View (`calendar.php`)

- A visual month/week grid layered on top of the existing `scheduled_posts` + `ad_campaigns` (campaign start/end dates) data — drag-to-reschedule updates `scheduled_time` via a small AJAX endpoint (`modules/calendar_reschedule.php`).
- Color-coded by platform badge (reuses the same badge classes already used in `post-creator.php`'s timeline).
- This is the natural home for the Google Calendar sync toggle already specified in the master prompt (§6.5) — surfaced visually instead of buried in a form.

---

## 7. Boosted Marketplace Listings (self-serve, powered by the existing ad engine)

Ties the Marketplace (§6.7 of the master prompt) directly into the ad-credit economy instead of leaving it as a passive directory.

- Add `is_boosted`, `boost_expires_at` columns to `marketplace_items`.
- "Boost this listing" button on the user's own marketplace items → spends `ad_credits` (reuses `PaymentProcessor`/wallet logic, no new payment path needed) → sets `is_boosted = 1` for N days → boosted items always win the "every 3rd slot" placement in `marketplace.php` ahead of the random `ad_campaigns` pull.
- Gives users a second reason to hold ad credits beyond `create-ad.php`, increasing platform stickiness.

---

## 8. Marketplace Trust Layer: Reviews, Ratings & Verified Badge

- `marketplace_reviews` table (item_id, reviewer_user_id, rating 1–5, comment, created_at).
- `users.is_verified_seller` boolean, set by admin after manual review (`admin/users.php` gets a verify toggle) — surfaces a badge next to the seller name on listings and dating profiles alike.
- Average rating displayed on listing cards; feeds into a simple "Top Rated" filter alongside the existing product/service/company filter.

---

## 9. Referral & Affiliate Growth Engine (`referrals.php`)

- `users.referral_code` (unique, auto-generated at registration) and `referrals` table (referrer_id, referred_id, status pending/rewarded, reward_credits).
- Shareable link `?ref=CODE` on `register.php`; on the referred user's first successful payment, both parties are credited `ad_credits` or `ai_credits` (configurable amount in `site_settings`).
- Dashboard widget shows referral count, pending rewards, and a copy-link button.
- Admin view (`admin/referrals.php`) to audit payouts and adjust reward amounts platform-wide.

---

## 10. Agency / White-Label Sub-Account Mode

Builds on the `users.parent_user_id` hierarchy that already exists for team members — extends it into a real agency offering.

- `users.account_type` ENUM('individual','agency_owner','agency_client') — an agency owner can create/manage multiple client sub-accounts (separate from `team_member`, which shares one wallet; a `agency_client` gets its **own** wallet/credits but is billed through the agency owner).
- `admin/agency` isn't needed — this is user-facing: `agency-clients.php` lets an agency owner create a client workspace, switch between client dashboards (a workspace switcher dropdown in the header, reading from `dynamic_content`/session context), and see roll-up billing across all clients.
- Optional custom branding per client workspace: `agency_client_branding` table (logo_url, accent_color hex) — the header/footer include checks for this override before falling back to the platform default logo/colors, keeping the "unified header/footer config" pattern intact.

---

## 11. In-App Notification Center + Real-Time-Feeling Updates (no WebSocket dependency)

- `notifications` table (user_id, type, title, body, link, is_read, created_at) — populated by every existing event that already triggers an email (payment success, ticket reply, campaign status change, team invite accepted, AI generation complete, boosted listing expiring soon).
- Bell icon in the unified header (`includes/header.php`) with an unread-count badge, polling a lightweight JSON endpoint (`modules/notifications_poll.php`) every 20–30 seconds — deliberately avoids requiring a WebSocket server, staying consistent with the "no framework, minimal moving parts" constraint.
- This turns email-first into **email + in-app first**, without adding infrastructure complexity.

---

## 12. Two-Factor Authentication (2FA) for Users and Mandatory for Admin

- `users.totp_secret`, `users.totp_enabled` columns; TOTP implemented with a small vanilla-PHP RFC 6238 function (no Composer dependency) — `modules/two_factor.php`.
- Optional for regular users (toggle in account settings), **mandatory** for any `role = 'admin'` account before `admin-login.php` grants a session — meaningfully raises the security bar on the panel that controls payments, credits, and CMS content.
- Backup codes generated and stored hashed, downloadable once at setup time.

---

## 13. Self-Service GDPR Data Tools (`privacy-center.php`)

Directly extends the cookie-consent/GDPR work already required in the master prompt (§9) into full data-subject rights, which is what actually makes a GDPR claim credible.

- "Download my data" — streams a JSON/CSV export of the user's own rows across `users`, `ad_campaigns`, `scheduled_posts`, `transactions`, `ai_creatives`.
- "Delete my account" — soft-deletes (`users.status = 'suspended'`, scrubs PII fields, keeps financial records for legal retention per `site_settings`-configurable retention days) rather than a hard cascade delete, to stay compliant with both GDPR erasure and financial record-keeping obligations.
- Consent history view — pulls the user's own rows from `cookie_consents` by matching stored `consent_uuid` cookie, so they can see what they agreed to and when.

---

## 14. Multi-Language & Multi-Currency Support

- `translations` table (locale, translation_key, value) — admin-managed via `admin/translations.php`; `get_content()`/`get_setting()` helpers extended to accept an optional `$locale` param, defaulting to a `site_settings` platform default or a `?lang=` query param stored in session.
- `pricing_plans` gets a companion `pricing_plan_currency_overrides` table (plan_id, currency_code, price) so the same plan can display in USD/EUR/NGN/etc.; the visitor's likely currency is inferred from the same IP telemetry already being logged in `visitor_logs` (no new tracking surface introduced — reuses existing data).
- Paystack/Flutterwave calls already accept a currency parameter — wire the resolved currency through `PaymentProcessor::initiatePayment()`.

---

## 15. Auto-Refill Wallet (Never-Interrupted Campaigns)

- `users.auto_refill_enabled`, `auto_refill_threshold`, `auto_refill_amount`, `auto_refill_gateway` columns.
- Cron (`cron/auto_refill_check.php`) checks balances after each campaign spend deduction; if below threshold and auto-refill is on, silently triggers `PaymentProcessor::initiatePayment()` against a previously tokenized/saved payment method (Paystack and Flutterwave both support charge-authorization/tokenization flows) and emails a receipt.
- Solves the real-world pain point of a combo-pack campaign pausing mid-flight because credits ran dry — a genuine "stands out" feature for an ads platform.

---

## 16. Fraud & Anomaly Watch for Admin

- Lightweight rule-based flags (no ML dependency) written to `fraud_flags` table: multiple failed payment attempts in a short window, a sudden spike in ad spend inconsistent with account history, many accounts sharing one IP from `visitor_logs`, or a support ticket flood from one user.
- `admin/fraud-watch.php` surfaces flagged accounts/transactions for manual review with one-click suspend, matching the existing `users.status` enum — no new enforcement mechanism needed, just smarter surfacing of what's already there.

---

## 17. Public REST API + Webhooks for Power Users (`api/v1/*`)

- Read-only + scoped-write REST endpoints (`api/v1/campaigns.php`, `api/v1/posts.php`, `api/v1/credits.php`) authenticated via a per-user API key (`api_keys` table: user_id, key_hash, scopes, created_at, last_used_at) generated from a new `api-settings.php` page.
- Outbound webhooks (`webhooks` table: user_id, event_type, target_url, secret) fired on campaign status change / post published / payment completed, signed with an HMAC header — lets power users and agencies integrate Adsbooster into their own tooling without needing framework-level routing (plain PHP files under `api/v1/` mapped 1:1 to endpoints keeps this consistent with the no-framework rule).
- This is the feature that turns Adsbooster from "a website" into "a platform" in the eyes of larger agency customers.

---

## 18. Gamified Engagement: Badges & Milestones

- `achievements` table (key, title, description, icon) and `user_achievements` (user_id, achievement_id, earned_at) — awarded automatically on milestones already tracked elsewhere: first campaign launched, first combo-pack, 10 posts scheduled, first referral reward, verified seller status, 100% policy-scan-clean streak.
- Small badge shelf on the dashboard/profile — cheap to build (just table reads + existing icon set from Phosphor Icons already bundled in the template) but meaningfully increases retention and gives the platform personality beyond a raw utility tool.

---

## 19. Dating/Connection Safety Layer

Strengthens the existing Dating & Connection Search (master prompt §6.8) with the trust features that kind of feature actually needs to not be a liability.

- `dating_profiles.is_photo_verified` boolean set after admin review of a selfie-match upload (`dating_verification_uploads` table, admin-only visibility, auto-deleted after review per a retention setting).
- "Report profile" action writes to the existing `support_tickets` table with `ticket_type = 'support'` and a `report_target_user_id` column addition — reuses the entire ticket admin workflow instead of building a parallel moderation system.
- Block/unmatch capability: `dating_blocks` table (blocker_id, blocked_id) checked in both `dating-search.php` results and `p2p-chat.php` message delivery.

---

## 20. SEO & Discoverability Toolkit

- `admin/seo.php`: per-page meta title/description/OG-image overrides stored in `dynamic_content` under a reserved `section_key = 'seo'`, so it reuses the existing content-management pattern rather than inventing a new table.
- Auto-generated `sitemap.php` (served as `sitemap.xml` via a simple content-type header) that includes all public pages, published blog posts, and active marketplace listings.
- `robots.php` served as `/robots.txt` referencing the sitemap, toggleable "allow indexing" switch in `site_settings` for staging environments.

---

## Integration Notes (keep this consistent with the master prompt)

- Every new table above follows the same `InnoDB`/`latin1_swedish_ci`/FK-constrained pattern already established.
- Every new page/module gets its **own file**, same as the master prompt's "one feature per file" rule — nothing here should be bolted onto an existing file as an afterthought.
- Every credit-spending feature (boosted listings, auto-refill, referral rewards) must run through the **existing** `PaymentProcessor`/wallet columns on `users` — do not create a second parallel currency system.
- Every user-facing new page must be wrapped with the same `includes/header.php` / `includes/footer.php` so the dark-green theme, cursor, preloader, and cookie consent stay unified across old and new pages.
- Every new automated/cron process (`campaign_doctor`, `pull_campaign_metrics`, `auto_refill_check`, `ab-test` winner selection) should log its run to a shared `cron_run_logs` table (job_name, started_at, finished_at, status, notes) so admin has one place to verify automation is healthy — extends the existing "admin should be able to audit the website" requirement from the master prompt.

---

## Updated "Don't Drop This" Additions

- [ ] Unified analytics dashboard (ads + organic, user-facing and admin-facing)
- [ ] AI Campaign Doctor with recommendations + optional Auto-Pilot
- [ ] Real policy/brand-safety pre-flight scanner with admin-managed rules
- [ ] A/B creative testing with auto-winner budget reallocation
- [ ] Smart audience builder + lookalike suggestions
- [ ] Visual drag-to-reschedule content calendar
- [ ] Boosted marketplace listings paid for with ad credits
- [ ] Marketplace reviews/ratings + verified seller badge
- [ ] Referral/affiliate program with credit rewards
- [ ] Agency white-label sub-account mode with workspace switcher
- [ ] In-app notification center (polling-based, no WebSocket dependency)
- [ ] 2FA optional for users, mandatory for admin
- [ ] Self-service GDPR data export/delete + consent history
- [ ] Multi-language + multi-currency support
- [ ] Auto-refill wallet for uninterrupted campaigns
- [ ] Admin fraud/anomaly watch
- [ ] Public REST API + signed webhooks
- [ ] Gamified badges/achievements
- [ ] Dating safety layer: photo verification, report, block
- [ ] SEO toolkit: meta overrides, sitemap.xml, robots.txt
- [ ] `cron_run_logs` table so every automated job is auditable from admin
