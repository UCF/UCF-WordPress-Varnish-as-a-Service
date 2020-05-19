=== Plugin Name ===
Contributors: jartes, JavierCasares
Tags: varnish, cache, purge, ban
Requires at least: 4.4.0
Tested up to: 5.3.0
Stable tag: 1.2.3

Clear your Varnish cache when new, edited or deleted content happens.

== Description ==

WordPress Varnish as a Service is a plugin for <a href="https://www.varnish-cache.org/">Varnish Cache</a> that purges/bans a cache server.

Supports Varnish 2 (purge) and Varnish 3 (ban) versions, Secret Key (for Admin Port) and HTTP Purge.

Based on <a href="http://wordpress.org/extend/plugins/wordpress-varnish/">WordPress Varnish</a> and <a href="http://wordpress.org/extend/plugins/varnish-purger/">Plugin Varnish Purges</a>.

== Installation ==

1. Upload folder and contents `wordpress-varnish-as-a-service` to the `wp-content/plugins` directory
2. Activate the plugin through the `Plugins` menu in WordPress
3. Configure your Varnish IP server and port. Also your Secret Key and Varnish version if you use Admin Port.


== Screenshots ==

1. Configuration.

== Changelog ==

= 1.3.1 =
 * Fix ban on second and third varnish cache servers.
 * Add ban on menu edit
 * Fix Undefined variable notice.
 * Add ban on permalink update.
 * Fix duplicate class / instantiate object critcal error.

= 1.3.0 =
 * Update plugin to support multisite.
 * Save configurations to network site options.
 * Stop plugin from being loaded on client side requests.
 * Requires plugin to be uninstalled + reinstalled, or deactivated + activated and settings reconfigured at network level.

= 1.2.11 =
 * Fix PHP7+ deprecated warnings on constructor name.

= 1.2.10 =
 * Remove deprecated has_cap warnings with debug on.

= 1.2.7 =
 * Added Github URI for Github Updater plugin usage.

= 1.2.6 =
 * Added support for attachment bans on create/update/delete.

= 1.2.3 =
 * Minor update

= 1.2.2 =
 * WordPress 4.7.2 compatibility
 * Fix minor bug
 * PHP7 Compatible

= 1.2.1 =
 * WordPress 4 compatibility
 * Added plugin icon

= 1.2.0 =
 * Fixed first server configuration
 * Fix some problems with some PHP distributions

= 1.1.1 =
 * Up to 3 Varnish servers

= 1.1.0 =
 * Delete configuration when uninstalled
 * Performance improvements
 * Controlled login to Varnish with or without Secret Key
 * Tested up to WordPress 2.5
 * Connection test to Varnish Server

= 1.0.1 =
 * First stable version
