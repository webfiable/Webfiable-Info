=== Webfiable Info ===
Contributors: webfiable
Tags: security, monitoring, hardening, inventory, endpoint
Requires at least: 4.7
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.6
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Connect your site to Webfiable (webfiable.com) to track config health and get security recommendations. Public early access (white march). Free.

== Description ==

**Improve your site's security posture and configuration health with monitoring and recommendations.**

Webfiable Info is the on-site companion for the Webfiable security service (https://webfiable.com). It securely gathers information about your site's WordPress version, plugins, themes, and basic site metadata and registers your site with Webfiable so you can receive ongoing reports via email. You stay in control: consent is explicit, and the public endpoint is opt-in and verified on save.

During the white march period, there is no separate signup or billing - the plugin registers your site automatically from the settings screen and you can use the service for free. A subscription may be required in the future; we will notify administrators well in advance.

== Features ==

* **One-click registration**: Enter a report recipient email, grant consent, and enable the endpoint; Webfiable Info verifies the endpoint and registers the site automatically.
* **Opt-in endpoint**: The public `/webfiable` endpoint is disabled by default and verified when enabled. If verification or registration fails, the plugin safely disables it.
* **Consent-aware behavior**: Turning off consent simply saves your choice and disables the endpoint; you can re-enable later.
* **Lightweight by design**: No heavy background jobs; the endpoint serves inventory on demand and runs in milliseconds.
* **Secure by default**: Uses hybrid encryption (AES-256 + RSA-2048) to transport data.
* **Part of the Webfiable service**: Currently in white march (early access) and free to use; a subscription may be required in the future. Learn more at https://webfiable.com.

== Security Features ==

Webfiable Info is built with security at its core, ensuring that your website's data is protected at every stage:

* **Hybrid Encryption**: Combines AES and RSA. The inventory is encrypted with AES-256-CBC; the AES key is encrypted with RSA-2048.
* **Fresh IV per response**: Each response uses a new IV so ciphertext is always unique.
* **Public endpoint, private content**: The `/webfiable` endpoint can be accessed by anyone, but the payload is encrypted for Webfiable only.
* **Rate limiting**: Basic per-IP rate limiting reduces abuse.

== Why It Is Secure ==

1. **Strong transport**: AES-256 for data, RSA-2048 for the key - only Webfiable can decrypt.
2. **Unique IVs**: Each response is unique even for identical content.
3. **Minimal inventory**: Only software inventory and basic metadata needed for analysis; no credentials or content are collected.

== Installation ==

1. Download the `webfiable-info.zip` file to your computer.
2. Log in to your WordPress admin dashboard.
3. Go to `Plugins > Add New`.
4. Click the `Upload Plugin` button at the top of the page.
5. Click `Choose File` and select the `webfiable-info.zip` file you downloaded.
6. Click `Install Now`.
7. Once the installation is complete, click `Activate Plugin`.

After activation:

1. Go to `Settings -> Webfiable Info`.
2. Enter the report recipient email and check the consent box.
3. Enable the `/webfiable` endpoint and click `Save settings`.
4. The plugin verifies the endpoint and completes registration. If verification fails, the endpoint will be disabled and a notice explains what to fix.

== Frequently Asked Questions ==

= Do I need a Webfiable subscription? =

Not during the white march (early access). The plugin registers your site automatically from the settings screen and you can use the service for free. A subscription may be required in the future. We will provide clear notice and a smooth upgrade path. See https://webfiable.com for updates.

= How is my data secured? =

Data is encrypted on your site before transport using AES-256-CBC. The AES key is encrypted with RSA-2048 so only Webfiable can decrypt the payload.

= What information is collected? =

Minimal inventory only: site URL, WordPress version, installed plugins and themes (name, slug, version, short description), a site identifier, consent timestamp, and the email you provide for reports. No user content or credentials.

= What happens if I disable consent? =

Your preference is saved immediately, and the `/webfiable` endpoint is turned off. You can re-enable consent and the endpoint at any time from Settings.

= Why did registration fail? =

The plugin enables and verifies the endpoint before registering. If your server blocks loopback requests, permalinks are misconfigured, or the OpenSSL PHP extension is missing, verification may fail. Fix the issue and click `Save settings` again - the plugin will retry.

== Changelog ==

= 2.0.6 =
* Confirmed WordPress 6.9 compatibility after passing all tests; no other changes to the plugin.

= 2.0.5 =
* Increase self-test timeout to 30s and activation call timeout to 60s to reduce registration failures on slower sites.

= 2.0.4 =
* Allow the `/webfiable` rewrite rule to match with or without a trailing slash so endpoint verification no longer fails on sites that enforce trailing slashes.

= 2.0.3 =
* Resolve Plugin Check (PCP) warnings by trimming short description and updating translation loader.

= 2.0.2 =
* Finalize release packaging so WordPress.org distributions only include production files.

= 2.0.1 =
* Ensure WordPress.org releases exclude development-only files.

= 2.0.0 =
* New settings page under Settings -> Webfiable Info.
* Opt-in `/webfiable` endpoint with on-save verification.
* Automatic customer registration after successful verification.
* Consent gating that saves your choice and disables the endpoint when consent is off.
* Improved notices and lightweight, reliable design.

= 1.4 =
* Initial release with AES-256/RSA-2048 hybrid encryption.

== Upgrade Notice ==

= 2.0.6 =
Confirmed WordPress 6.9 compatibility after passing all tests; no other changes.

== License ==

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 3 of the License, or (at your option) any later version.









