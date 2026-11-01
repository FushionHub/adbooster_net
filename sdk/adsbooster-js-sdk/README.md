# adsbooster-js-sdk — Official Adsbooster.net JavaScript/TypeScript SDK (Package Stub)

Official client for the **Adsbooster.net REST API v1** — the Zernio Ads Engine
and the Bundle.social Organic Gateway behind one unified master API key.

> **Source of truth:** [`/api/v1/sdk/adsbooster.js`](https://github.com/FushionHub/adbooster_net/blob/main/api/v1/sdk/adsbooster.js)
> on the Adsbooster deployment. This directory only holds the npm package stub
> (`package.json`); the full zero-dependency client lives at `/api/v1/sdk/adsbooster.js`
> and is served by every deployment at `{BASE_URL}/api/v1/sdk/adsbooster.js`.
> API reference: `{BASE_URL}/developer-docs.php`.

## Install / Quick start (Node ≥ 18, zero dependencies)

```js
const { Adsbooster } = require('adsbooster-js-sdk');
// or from your deployment:
// const { Adsbooster } = require('https://yourdomain.com/api/v1/sdk/adsbooster.js');

const client = new Adsbooster({
  apiKey: 'adsb_live_…',        // Developer Console → api-settings.php
  baseUrl: 'https://adsbooster.net',
});

const balance = await client.credits.balance();     // wallet snapshot
const campaigns = await client.campaigns.list();     // Zernio-backed
const created   = await client.campaigns.create({    // launch → zernio_campaign_id
  campaign_name: 'Summer D2C Scale',
  campaign_type: 'meta',
  budget_amount: 100,
  objective: 'traffic',
  headline: 'Upgrade Your Summer Wardrobe',
  primary_text: 'Shop premium organic cotton essentials today.',
  destination_url: 'https://example.com/shop',
  creative_media_url: 'https://example.com/creative.jpg',
});

await client.campaigns.pause(created.campaign_id);   // queued pause/resume
const post = await client.posts.create({ caption: 'New drop!', platforms: ['facebook', 'instagram'] });
```

### Public aggregate analytics (no authentication server-side)

The homepage achievements modal is powered by a public, aggregate-only feed —
available through the SDK without any user context:

```js
const feed = await client.publicAnalytics.feed('all');   // 'all' | 'campaigns' | 'users' | 'platforms' | 'success'
feed.homepage;   // { campaigns, users, platforms, success_rate, monitoring }
feed.analytics;  // { totals, performance, trend_14d, campaign_status_mix, platform_mix, note }
```

Zero user-level data is exposed. Limits: **30 requests/minute/IP** (HTTP 429 +
`Retry-After`); CORS `*`, so it is safe for public widgets and blog embeds.

## Resource map

| Resource | Methods | Backed by |
| :-- | :-- | :-- |
| `client.campaigns` | `list`, `get`, `create`, `update`, `pause`, `resume` | Zernio Ads Engine (`/api/v1/campaigns.php`) |
| `client.posts` | `list`, `get`, `create`, `remove` | Bundle.social Gateway (`/api/v1/posts.php`) |
| `client.ugc` | `listRecent`, `generateImage` | AI UGC Studio (`/api/v1/ugc.php`) |
| `client.credits` | `balance` | Wallet (`/api/v1/credits.php`) |
| `client.directory` | `list`, `createListing` | Public directory (`/api/v1/directory.php`) |
| `client.reviews` | `list`, `submit` | Community reviews (`/api/v1/reviews.php`) |
| `client.capi` | `scriptUrl`, `track` | Server-side conversions (`/api/v1/capi-bridge.php`) |
| `client.webhooks` | `testPingUrl` (+ `webhooks.verify` / `webhooks.sign` HMAC-SHA256 helpers) | Webhooks (`/api/v1/webhooks.php`) |
| `client.publicAnalytics` | `feed(view)`, `totals()` | `/api/v1/public-analytics.php` (no auth) |

## Conventions

- **Errors**: every non-2xx raises `AdsboosterError` (`status`, `code`,
  `details`, `rateLimit`, `isRateLimit`).
- **Retries**: automatic on 429/5xx (`maxRetries`, default 2, honours `Retry-After`).
- **Quota telemetry**: `client.lastRateLimit` mirrors `X-RateLimit-*` headers.
- **Gateway reality**: campaigns dispatch **asynchronously** through the Zernio
  queue (`campaigns.create()` returns instantly; `campaign_id` arrives in the
  response payload and the external `zernio_campaign_id` lands once the worker
  confirms). Same for posts (`bundle_post_id`) and conversions.

© 2026 Adsbooster.net — MIT License
