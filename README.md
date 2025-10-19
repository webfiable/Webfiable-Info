# Webfiable Info

> A lightweight, privacy-respecting companion plugin that connects your WordPress site to the [Webfiable](https://webfiable.com) security service for configuration monitoring and actionable recommendations.

- Status: Publicly available in white-march (early access)
- License: GPLv3 or later
- WordPress: 5.0+
- PHP: 7.4+

## Overview

Webfiable Info securely gathers a minimal software inventory (WordPress version, installed plugins and themes, and basic site metadata) and registers your site with [Webfiable](https://webfiable.com). You receive the first full report and ongoing summaries via email.

During the white-march period there is no separate sign-up or billing—the plugin registers your site from the settings screen and the service is free to use. A subscription may be required after general availability; administrators will be notified well in advance.

## Features

- One-click registration: enter a report email, grant consent, and enable the endpoint. The plugin verifies the endpoint and completes registration automatically.
- Opt-in endpoint: the public `/webfiable` endpoint is disabled by default and verified when enabled. If verification or registration fails, the plugin safely disables it.
- Consent-aware behavior: turning off consent simply saves your choice and disables the endpoint; you can re-enable later.
- Lightweight by design: no heavy background jobs; the endpoint serves inventory on demand and runs in milliseconds.
- Secure by default: hybrid encryption (AES-256-CBC + RSA-2048) protects the transport payload.
- Part of the Webfiable service: learn more at [webfiable.com](https://webfiable.com).

## Security

- Hybrid Encryption: inventory is encrypted with AES-256-CBC; the AES key is encrypted with RSA-2048.
- Fresh IV per response: each response uses a new IV so ciphertext is always unique.
- Public endpoint, private content: the `/webfiable` endpoint may be accessed publicly, but the payload can only be decrypted by Webfiable.
- Rate limiting: basic per-IP limiting reduces abuse.

## Installation & Setup

1. Install the plugin (zip upload or from source).
2. Activate it in WordPress.
3. Go to Settings -> Webfiable Info.
4. Enter the report recipient email and check the consent box.
5. Enable the `/webfiable` endpoint and click “Save settings”.
6. The plugin verifies the endpoint and completes registration. If verification fails, a notice explains what to fix and the endpoint is safely disabled.

## FAQ

### Do I need a Webfiable subscription?
Not during white-march (early access). The plugin registers your site automatically and the service is free to use. When the service launches publicly, a subscription may be required. We will provide clear notice and a smooth path to upgrade. See updates at [webfiable.com](https://webfiable.com).

### How is my data secured?
Data is encrypted on your site before transport using AES-256-CBC. The AES key is encrypted with RSA-2048 so only Webfiable can decrypt the payload.

### What information is collected?
Minimal inventory only: site URL, WordPress version, installed plugins and themes (name, slug, version, short description), a site identifier, consent timestamp, and the email you provide for reports. No user content or credentials.

### What happens if I disable consent?
Your preference is saved immediately, and the `/webfiable` endpoint is turned off. You can re-enable consent and the endpoint at any time from Settings.

### Why might registration fail?
The plugin verifies the endpoint before registering. If your server blocks loopback requests, permalinks are misconfigured, or the PHP OpenSSL extension is missing, verification may fail. Fix the issue and click "Save settings" again — the plugin will retry.

## Contributing
Issues and PRs are welcome. Please keep changes focused and consistent with the existing code style.

## License
GPLv3 or later. See the [LICENSE](https://www.gnu.org/licenses/gpl-3.0.html).








