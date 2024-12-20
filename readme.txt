=== WebFinger ===
Contributors: pfefferle, willnorris
Donate link: https://notiz.blog/donate/
Tags: discovery, webfinger, JRD, ostatus, activitypub
Requires at least: 4.2
Tested up to: 6.6
Stable tag: 3.2.7
License: MIT
License URI: https://opensource.org/licenses/MIT

WebFinger for WordPress

== Description ==

Enables WebFinger ([RFC 7033](http://tools.ietf.org/html/rfc7033)) support for WordPress.

About WebFinger:

> WebFinger is used to discover information about people or other entities on the Internet that are identified by a URI using standard Hypertext Transfer Protocol (HTTP) methods over a secure transport.  A WebFinger resource returns a JavaScript Object Notation (JSON) object describing the entity that is queried. The JSON object is referred to as the JSON Resource Descriptor (JRD).

(quote from the [RFC](http://tools.ietf.org/html/rfc7033))

== Frequently Asked Questions ==

= How to extend the JRD file =

You can add your own links or properties like that:

    function oexchange_target_link( $array ) {
      $array["links"][] = array( 'rel' => 'http://oexchange.org/spec/0.8/rel/resident-target',
        'href' => 'http://example.com',
        'type' => 'application/xrd+xml' );
      return $array;
    }
    add_filter( 'webfinger_data', 'oexchange_target_link' );

= Add alternate file/output formats =

You can add your own links or properties like that:

    function render_xrd($webfinger) {
      // set custom header();

      // JRD to XRD code

      exit;
    }
    add_action( 'webfinger_render', 'render_xrd', 5 );

You can find a detailed example here <https://github.com/pfefferle/wordpress-webfinger-legacy>

= The spec =

WebFinger is specified as [RFC 7033](http://tools.ietf.org/html/rfc7033)

= The WebFinger community page =

Please visit <http://webfinger.net>

== Upgrade Notice ==

= 3.0.0 =

This versions drops classic WebFinger support to keep the plugin short and simple. All legacy stuff is bundled in this new plugin <https://github.com/pfefferle/wordpress-webfinger-legacy>

== Changelog ==

Project maintained on github at [pfefferle/wordpress-webfinger](https://github.com/pfefferle/wordpress-webfinger).

= 3.2.7 =

* Added: better output escaping
* Fixed: stricter queries

= 3.2.6 =

* remove E-Mail address

= 3.2.5 =

* fix typo

= 3.2.4 =

* update requirements

= 3.2.3 =

* fixed `acct` scheme for discovery

= 3.2.2 =

* fixed typo (thanks @ivucica)
* use `acct` as default scheme

= 3.2.1 =

* make `acct` protocol optional

= 3.2.0 =

* global refactoring

= 3.1.6 =

* added `user_nicename` as resource
* fixed WordPress coding standard issues

= 3.1.5 =

* fixed PHP warning

= 3.1.4 =

* updated requirements

= 3.1.3 =

* add support for the 'aim', 'ymsgr' and 'acct' protocol

= 3.1.2 =

* fixed the legacy code
* added feeds

= 3.1.1 =

* fixed 'get_user_by_various' function

= 3.1.0 =

* Added WebFinger legacy plugin, because the legacy version is still very popular and used by for example OStatus (Mastodon, Status.NET and GNU Social)
* Added Webfinger for posts support

= 3.0.3 =

* composer support
* compatibility updates

= 3.0.2 =

* `get_avatar_url` instead of custom code
* some small code improvements
* nicer PHP-docs

= 3.0.1 =

* updated version informations
* support the WordPress Coding Standard

= 3.0.0 =

* added correct error-responses
* remove legacy support for XRD and host-meta (props to Will Norris)

= 2.0.1 =

* small bugfix

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

Follow the normal instructions for [installing WordPress plugins](https://codex.wordpress.org/Managing_Plugins#Installing_Plugins).

= Automatic Plugin Installation =

To add a WordPress Plugin using the [built-in plugin installer](https://codex.wordpress.org/Administration_Screens#Add_New_Plugins):

1. Go to [Plugins](https://codex.wordpress.org/Administration_Screens#Plugins) > [Add New](https://codex.wordpress.org/Plugins_Add_New_Screen).
1. Type "`webfinger`" into the **Search Plugins** box.
1. Find the WordPress Plugin you wish to install.
    1. Click **Details** for more information about the Plugin and instructions you may wish to print or save to help setup the Plugin.
    1. Click **Install Now** to install the WordPress Plugin.
1. The resulting installation screen will list the installation as successful or note any problems during the install.
1. If successful, click **Activate Plugin** to activate it, or **Return to Plugin Installer** for further actions.

= Manual Plugin Installation =

There are a few cases when manually installing a WordPress Plugin is appropriate.

* If you wish to control the placement and the process of installing a WordPress Plugin.
* If your server does not permit automatic installation of a WordPress Plugin.
* If you want to try the [latest development version](https://github.com/pfefferle/wordpress-webfinger).

Installation of a WordPress Plugin manually requires FTP familiarity and the awareness that you may put your site at risk if you install a WordPress Plugin incompatible with the current version or from an unreliable source.

Backup your site completely before proceeding.

To install a WordPress Plugin manually:

* Download your WordPress Plugin to your desktop.
    * Download from [the WordPress directory](https://wordpress.org/plugins/webfinger/)
    * Download from [GitHub](https://github.com/pfefferle/wordpress-webfinger/releases)
* If downloaded as a zip archive, extract the Plugin folder to your desktop.
* With your FTP program, upload the Plugin folder to the `wp-content/plugins` folder in your WordPress directory online.
* Go to [Plugins screen](https://codex.wordpress.org/Administration_Screens#Plugins) and find the newly uploaded Plugin in the list.
* Click **Activate** to activate it.
