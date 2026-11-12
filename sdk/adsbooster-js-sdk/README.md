# adsbooster-js-sdk — published package pointer

The official Adsbooster JavaScript/TypeScript SDK now lives in its own repository
and is published to npm:

| | |
| :-- | :-- |
| npm package | <https://www.npmjs.com/package/adsbooster-js-sdk> |
| Source repository | <https://github.com/FushionHub/adsbooster-js-sdk> |
| Install | `npm install adsbooster-js-sdk` |
| CDN | `https://cdn.jsdelivr.net/npm/adsbooster-js-sdk/dist/adsbooster.js` |

```js
const { Adsbooster } = require('adsbooster-js-sdk');

const client = new Adsbooster({ apiKey: process.env.ADSBOOSTER_API_KEY });
const balance = await client.credits.balance();     // wallet snapshot
const campaigns = await client.campaigns.list();     // Zernio-backed
const feed = await client.publicAnalytics.feed();    // public aggregate, no auth
```

## Why this folder is now empty

This directory used to hold a placeholder `package.json` while the package was
still unpublished. The package is live on npm, so the canonical source lives in
`FushionHub/adsbooster-js-sdk` — clone that repository to contribute:

```bash
git clone https://github.com/FushionHub/adsbooster-js-sdk.git
```

The platform also serves the same client to browsers directly at
`/api/v1/sdk/adsbooster.js`, so self-hosted pages can use it without npm.

Full API reference: `developer-docs.php` on your Adsbooster deployment, or
[`llms-full.md`](../../llms-full.md) in the platform repository.

© 2026 Adsbooster.net — MIT License
