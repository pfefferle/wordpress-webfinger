=== Webfinger ===
Contributors: pfefferle
Donate link: http://14101978.de
Tags: OpenID, XRD, well-known, XML, Discovery, host-meta, Webfinger, diso, OStatus, OStatus Stack, simple web discovery, swd
Requires at least: 2.7
Tested up to: 3.5.1
Stable tag: 2.0.0

Webfinger for WordPress!

== Description ==

Webfinger: http://tools.ietf.org/html/draft-ietf-appsawg-webfinger

more doku soon!

== Changelog ==
= 2.0.0 =
* complete refactoring
* removed simple-web-discovery
* more filters and actions
* works without /.well-known/ plugin
= 1.4.0 =
* small fixes
* added "webfinger" as well-known uri
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

1. Upload the `webfinger`-folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the *Plugins* menu in WordPress
3. ...and that's it :)

== Frequently Asked Questions ==
