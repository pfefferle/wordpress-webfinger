=== Webfinger ===
Contributors: pfefferle
Donate link: http://14101978.de
Tags: OpenID, XRD, well-known, XML, Discovery, host-meta, Webfinger, diso, OStatus, OStatus Stack, simple web discovery, swd
Requires at least: 2.7
Tested up to: 3.3.1
Stable tag: 1.3.1

Webfinger (and simple-web-discovery) for WordPress!

== Description ==

Webfinger: http://tools.ietf.org/html/draft-ietf-appsawg-webfinger

simple-web-discovery: http://tools.ietf.org/html/draft-jones-simple-web-discovery

This plugin requires:

* the `/.well-known/`-plugin: http://wordpress.org/extend/plugins/well-known/
* the `host-meta`-plugin: http://wordpress.org/extend/plugins/host-meta/

more doku soon!

== Changelog ==
= 1.3.1 =
* added "rel"-filter (work in progress)
* added more aliases
= 1.3 =
* added host-meta resource feature (see latest spec)
= 1.2 =
* added 404 http error if user doesn't exist
* added jrd discovery for host-meta
= 1.1 =
* fixed an odd problem with lower WordPress versions
* added support for the http://wordpress.org/extend/plugins/extended-profile/ (thanks to Singpolyma)
= 1.0.1 =
* api improvements
= 1.0 =
* basic simple-seb-discovery
* json support
* some small improvements
= 0.9.1 =
* some changes to support http://unhosted.org
= 0.9 =
* OStatus improvements
* Better uri handling
* Identifier overview (more to come)
* Added filters
* Added functions to get a users webfingers
= 0.7 =
* Added do_action param (for future OStatus plugin)
* Author-Url as Webfinger-Identifier
= 0.5 =
* Initial release

== Installation ==

1. You have to download and install the `/.well-known/`-plugin first: http://wordpress.org/extend/plugins/well-known/
2. Then have to download and install the `host-meta`-plugin: http://wordpress.org/extend/plugins/host-meta/
3. Then you have to upload the `host-meta`-folder to the `/wp-content/plugins/` directory
4. Activate the plugin through the *Plugins* menu in WordPress
5. ...and that's it :)

== Frequently Asked Questions ==

soon...