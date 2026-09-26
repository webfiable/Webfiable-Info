=== Webfiable Análisis de Sitios ===
Contributors: webfiable
Tags: security, monitoring, hardening, inventory, endpoint
Requires at least: 5.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Connects your WordPress site to the panel where Webfiable shows its analyses of your site's security and configuration.

== Description ==

**Webfiable Análisis de Sitios** connects your WordPress site to Análisis de Sitios de Webfiable (https://siteaudit.webfiable.com), the Webfiable service that reviews the security and configuration of websites.

Without the plugin, Webfiable only sees what your site shows from the outside. With the plugin, and with your consent, it can read the full list of installed plugins and themes with their versions, and the exact WordPress and PHP versions. It uses that data to analyse your site, and the plugin connects your site to the panel where Webfiable shows its analyses.

= How it works =

1. You install and activate the plugin.
2. In **Settings → Webfiable** you enter your email, give your consent and leave the data connection on.
3. The plugin checks that the connection answers and registers your site with Webfiable.
4. You sign in to your panel at https://siteaudit.webfiable.com/acceso with that email: you receive a single-use sign-in link, with no password.

If the plugin was already set up before an update, your site registers again once after the update, in the background, without you having to do anything.

The plugin sends no email and no reports by email. The only messages you receive are the sign-in links you ask for on the panel's sign-in page.

= What data it shares =

Only if you give your consent and the data connection is on:

* the site address and an identifier the plugin creates when it is activated;
* the versions of WordPress, of PHP and of the plugin itself;
* the installed plugins and themes: name, identifier, version and short description;
* the email you enter in the settings and the date you gave your consent.

It does not read your content, your users, passwords or any other credential.

= How it protects that data =

* The list is served encrypted at your site's `/webfiable` address: AES-256-CBC for the data and RSA-2048 for the key, with a new initialisation vector for every response. Anyone can request that address, but only Webfiable holds the private key that reads the answer. The encryption keeps the content confidential; it does not sign it, and it does not limit who can ask.
* That address answers only after you give your consent and while the data connection is on, and it serves at most 25 requests per minute to the same IP address.
* If you withdraw your consent, the address stops serving the list at once and the data connection is switched off.
* The registration call itself (site identifier, site address and email) is sent as JSON over HTTPS.

= External service: Webfiable =

This plugin communicates with an external service, Webfiable (https://webfiable.com):

* **Registration.** When you save the settings with your consent given, and once after each plugin update if you had already given it, the plugin sends the site identifier, the site address and the email from the settings over HTTPS to `https://webfiable.com/wp-json/webfiable/v1/activations`.
* **Reading.** Afterwards, Webfiable requests your site's `/webfiable` address and reads, encrypted, the data listed above.

Privacy policy: https://webfiable.com/privacidad/ · Legal notice: https://webfiable.com/aviso-legal/

= Language =

The settings screen is written in Spanish and includes an English translation, which WordPress uses when your dashboard language is not Spanish.

== Installation ==

1. In your WordPress dashboard, go to **Plugins → Add New Plugin** and search for "Webfiable Análisis de Sitios".
2. Click **Install Now** and then **Activate**.
3. Go to **Settings → Webfiable**.
4. Enter your email, tick the consent box and leave the data connection on.
5. Click **Save Settings**. The plugin checks the connection and registers your site. If something fails, a notice tells you what to check and the data connection is switched off.

== Frequently Asked Questions ==

= How do I sign in to my panel? =

At https://siteaudit.webfiable.com/acceso, with the email you entered in the plugin settings. We send a single-use link to that address; there is no password.

= Why did the name change? =

The plugin used to be called "Webfiable Info". It now carries the name of the service it connects to. It is the same plugin: the folder, the site identifier and your settings do not change.

= Does the plugin send me reports by email? =

No. The plugin sends no email. Webfiable shows its analyses in your panel, which you enter with a sign-in link sent to your email when you ask for one.

= What happens after I update the plugin? =

If you had already given your consent, entered a valid email and left the data connection on, the plugin registers your site again once, in the background, through WordPress's scheduled tasks (WP-Cron). If that registration fails, nothing else changes: your settings stay as they were, the failure is written to the plugin's internal activity log, and you can register at any time by saving the settings.

= My site does not run WP-Cron. Will it register after an update? =

Not on its own. If `DISABLE_WP_CRON` is set and no system cron calls `wp-cron.php`, the registration after an update waits until the scheduled tasks run. You can register right away by saving the settings.

= What happens if I withdraw my consent? =

Your choice is saved at once and the data connection is switched off. You can give your consent again at any time from the settings.

= Why can registration fail? =

Before registering, the plugin checks that your site's `/webfiable` address answers. It fails if the server does not let the site call itself, if the permalinks need to be saved again, or if the PHP OpenSSL extension is missing. Fix the cause and save the settings again.

= What stays in my WordPress if I uninstall the plugin? =

The email, the consent and the data connection setting are deleted. The site identifier is kept, so that a reinstall is still the same site, and so is the plugin's activity log (its last 100 entries), which lives in your database and may contain the email and the site address from past saves and registrations.

== Changelog ==

= 2.2.0 =
* New name: Webfiable Análisis de Sitios (formerly Webfiable Info). The folder, the site identifier and your settings do not change.
* The settings screen and notices are written in Spanish; an English translation is included and used when the dashboard language is not Spanish.
* Once your site is registered, the settings screen tells you how to sign in to your Análisis de Sitios de Webfiable panel.
* After an update, a site that had already given its consent registers again once, in the background, so it appears in the panel without saving the settings.
* New Webfiable look in the plugin listing and on the settings screen.
* The privacy policy link points to the right page.
* Declares WordPress 5.3 as the minimum, which the code has needed since 2.1.1. Tested up to WordPress 7.1.

= 2.1.2 =
* Fixes the `.distignore`, which left the `assets/` folder, and with it the styles and images, out of the published packages.

= 2.1.1 =
* Full Spanish translation (es_ES), also used for the other Spanish locales.
* Fixes phpcs warnings in `endpoint.php` and the line endings of `notice.css`.

= 2.1.0 =
* Clearer messages and help texts on the settings screen.
* Refreshed settings screen.

= 2.0.6 =
* Confirmed compatibility with WordPress 6.9; no other changes.

= 2.0.5 =
* Longer timeouts: 30 s for the connection check and 60 s for the registration.

= 2.0.4 =
* The `/webfiable` address answers with and without a trailing slash.

= 2.0.3 =
* Resolves Plugin Check warnings.

= 2.0.2 =
* Published packages contain only production files.

= 2.0.1 =
* Published packages exclude development files.

= 2.0.0 =
* New settings screen.
* Optional `/webfiable` address, checked on save.
* Automatic registration after the check.
* Saving without consent switches the data connection off.

= 1.4 =
* First version, with hybrid AES-256/RSA-2048 encryption.

== Upgrade Notice ==

= 2.2.0 =
Webfiable Info is now called Webfiable Análisis de Sitios. If you had already given your consent, your site registers again on its own after the update; you will see it in your Análisis de Sitios de Webfiable panel when you sign in with your email.

= 2.1.2 =
Fixes the styles and images that were missing from the published packages.
