# Webfiable Análisis de Sitios

<p>
  <img src="assets/img/webfiable-lockup-light.svg" alt="Webfiable" width="177" height="28">
</p>

Connects your WordPress site to the panel where Webfiable shows its analyses of your site's security and configuration.

[Análisis de Sitios](https://siteaudit.webfiable.com) · [Plugin on WordPress.org](https://wordpress.org/plugins/webfiable-info/) · [Webfiable](https://webfiable.com)

![Version](https://img.shields.io/wordpress/plugin/v/webfiable-info?label=version) ![Tested up to](https://img.shields.io/wordpress/plugin/tested/webfiable-info?label=tested%20up%20to)

## What it is

**Webfiable Análisis de Sitios** connects your WordPress site to [Análisis de Sitios de Webfiable](https://siteaudit.webfiable.com), the Webfiable service that reviews the security and configuration of websites.

Without the plugin, Webfiable only sees what your site shows from the outside. With the plugin, and with your consent, it can read the full list of installed plugins and themes with their versions, and the exact WordPress and PHP versions. It uses that data to analyse your site, and the plugin connects your site to the panel where Webfiable shows its analyses.

You sign in to your panel at <https://siteaudit.webfiable.com/acceso> with the email you entered in the plugin settings: you receive a single-use sign-in link, with no password. If the plugin was already set up before an update, your site registers again once after the update, in the background.

The plugin sends no email and no reports by email.

## What data it shares

Only if you give your consent and the data connection is on:

- the site address and an identifier the plugin creates when it is activated;
- the versions of WordPress, of PHP and of the plugin itself;
- the installed plugins and themes: name, identifier, version and short description;
- the email you enter in the settings and the date you gave your consent.

It does not read your content, your users, passwords or any other credential.

## How it protects that data

- The list is served encrypted at your site's `/webfiable` address: AES-256-CBC for the data and RSA-2048 for the key, with a new initialisation vector for every response. Anyone can request that address, but only Webfiable holds the private key that reads the answer. The encryption keeps the content confidential; it does not sign it, and it does not limit who can ask.
- That address answers only after you give your consent and while the data connection is on, and it serves at most 25 requests per minute to the same IP address.
- If you withdraw your consent, the address stops serving the list at once and the data connection is switched off.
- The registration call itself (site identifier, site address and email) is sent as JSON over HTTPS to `https://webfiable.com/wp-json/webfiable/v1/activations`.

Privacy policy: <https://webfiable.com/privacidad/> · Legal notice: <https://webfiable.com/aviso-legal/>

## Installation and setup

1. In your WordPress dashboard, go to **Plugins → Add New Plugin** and search for "Webfiable Análisis de Sitios".
2. Click **Install Now** and then **Activate**.
3. Go to **Settings → Webfiable**.
4. Enter your email, tick the consent box and leave the data connection on.
5. Click **Save Settings**. The plugin checks the connection and registers your site. If something fails, a notice tells you what to check and the data connection is switched off.

The settings screen is written in Spanish and includes an English translation, which WordPress uses when your dashboard language is not Spanish.

## Frequently asked questions

The questions and answers are the same as in the plugin's listing on WordPress.org (`readme.txt`, «Frequently Asked Questions»): how to sign in to the panel, why the name changed, what happens after an update and on sites without WP-Cron, what happens when consent is withdrawn, why registration can fail, and what stays after uninstalling.

## Development

- Every pull request runs `.github/workflows/ci.yml`: version coherence across the plugin header, `includes/constants.php` and `readme.txt`; a self-test of the release checks; the English translation against the Spanish source strings; the PHP unit tests on PHP 8.3 and 7.4; phpcs; a PHP 7.4 parse of every shipped file; and the package a release would publish, built in dry-run mode, inspected file by file and run through Plugin Check.
- A release is a `v*.*.*` tag. `.github/workflows/release.yml` refuses a tag whose version differs from the code, repeats the checks on the package and only then deploys it to WordPress.org.

## License

GPL v3 or later. See the [full license](https://www.gnu.org/licenses/gpl-3.0.html).
