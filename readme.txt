=== Webfiable Info ===
Contributors: webfiable
Tags: security, monitoring, WordPress security
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.4.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Webfiable is a monitoring plugin that provides insights into your site's health and security posture. Requires a free Webfiable subscription.

== Description ==

**Ensure your website's security posture and configuration health with monitoring and recommendations. Requires an active Webfiable subscription (currently free).**

The Webfiable Info plugin is a component of the Webfiable security service, designed to help you maintain a robust security posture for your WordPress website. By securely gathering information about your site's plugins, themes, and WordPress version, the plugin enables the Webfiable service to perform in-depth analysis and provide weekly recommendations tailored to your specific configuration.

== Features ==

* **Simple and Reliable Design**: Built with simplicity in mind, this plugin minimizes the risk of issues arising on your website and reduces the need for frequent updates, contributing to a stable and secure environment.
* **Lightweight and Efficient**: The plugin is designed to be very lightweight, executing its tasks within seconds, and running no more than once per day, ensuring no impact on your website's performance.
* **Secure Data Transmission**: Utilizes advanced hybrid encryption (AES + RSA) to securely transmit data to the Webfiable service.
* **Proactive Security Monitoring**: Enables continuous monitoring of your site’s security posture and configuration health.
* **Part of the Webfiable Service**: Requires an active Webfiable subscription (currently free).

== Security Features ==

Webfiable Info is built with security at its core, ensuring that your website’s data is protected at every stage:

* **Hybrid Encryption**: Combines AES and RSA encryption to safeguard your data. The plugin uses AES-256 to encrypt the collected data, and then securely transmits the AES key by encrypting it with RSA-2048.
* **Initialization Vector (IV)**: Each data transmission uses a unique Initialization Vector (IV) to ensure that even identical data produces different ciphertexts, enhancing security.
* **RSA Key Management**: The RSA encryption ensures that only the Webfiable service can decrypt the transmitted data, using a private key that remains secure on the Webfiable infrastructure.

== Why It Is Secure ==

1. **Advanced Encryption Techniques**: Webfiable Info employs AES-256 for data encryption, a standard widely recognized for its strength and security. The AES key is then encrypted with RSA-2048, ensuring that even if the data is intercepted, it cannot be decrypted without the corresponding private RSA key, which is securely stored by Webfiable.

2. **Data Integrity**: The use of a unique IV for each transmission guarantees that your data remains confidential and secure, preventing any potential attackers from predicting or replicating encrypted data streams.

3. **Confidentiality by Design**: The plugin is designed to collect only the necessary information for security analysis, ensuring that your website's sensitive data is handled with the utmost care and never exposed.

== Installation ==

1. Download the `webfiable-info.zip` file to your computer.
2. Log in to your WordPress admin dashboard.
3. Go to `Plugins > Add New`.
4. Click the `Upload Plugin` button at the top of the page.
5. Click `Choose File` and select the `webfiable-info.zip` file you downloaded.
6. Click `Install Now`.
7. Once the installation is complete, click `Activate Plugin`.

== Frequently Asked Questions ==

= Do I need a Webfiable subscription to use this plugin? =

Yes, an active Webfiable subscription is required for the plugin to function. The plugin sends encrypted data to the Webfiable service, where it is analyzed as part of your subscription.

= How does the plugin ensure my data is secure? =

The plugin uses a hybrid encryption method, combining AES-256 and RSA-2048, to securely encrypt and transmit your website's data. This ensures that only the Webfiable service can decrypt and analyze the information.

= What information does this plugin collect? =

The plugin collects information about your installed plugins, themes, and the WordPress version. This data is used by the Webfiable service to assess your website's security posture and provide recommendations.

== Changelog ==

= 1.4 =
* Initial release with enhanced security features, including AES-256 encryption and RSA-2048 for key transmission.

== Upgrade Notice ==

= 1.4 =
Initial release.

== License ==

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 3 of the License, or (at your option) any later version.
