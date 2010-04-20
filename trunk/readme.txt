=== host-meta ===
Contributors: Matthias Pfefferle
Donate link:
Tags: OpenID, XRD, well-known, XML, Discovery, host-meta, Webfinger
Requires at least: 2.7
Tested up to: 2.9.9
Stable tag: 0.2

This plugin provides a host-meta - file for WordPress (RFC Draft: http://tools.ietf.org/html/draft-hammer-hostmeta-05).

The plugin requires the `/.well-known/`-plugin: http://wordpress.org/extend/plugins/well-known/!

From the RFC:

   Web-based protocols often require the discovery of host policy or
   metadata, where host is not a single resource but the entity
   controlling the collection of resources identified by URIs with a
   common host as defined.  While these protocols have a
   wide range of metadata needs, they often define metadata that is
   concise, has simple syntax requirements, and can benefit from storing
   its metadata in a common location used by other related protocols.

   Because there is no URI or a resource available to describe a host,
   many of the methods used for associating per-resource metadata (such
   as HTTP headers) are not available.  This often leads to the
   overloading of the root HTTP resource (e.g. 'http://example.com/')
   with host metadata that is not specific to the root resource (e.g. a
   home page or web application), and which often has nothing to do it.

   This memo registers the "well-known" URI suffix 'host-meta' in the
   Well-Known URI Registry established by,
   and specifies a simple, general-purpose metadata document for hosts,
   to be used by multiple Web-based protocols.

== Changelog ==

= 0.2 =
* Initial release

== Installation ==

1. You have to download and install the `/.well-known/`-plugin first: http://wordpress.org/extend/plugins/well-known/
2. Then you have to upload the `host-meta`-folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the *Plugins* menu in WordPress
4. ...and that's it :)

== Frequently Asked Questions ==

soon...