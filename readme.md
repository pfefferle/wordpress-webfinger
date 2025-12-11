# WebFinger

- Contributors: pfefferle, willnorris
- Donate link: https://notiz.blog/donate/
- Tags: discovery, webfinger, JRD, ostatus, activitypub
- Requires at least: 4.2
- Tested up to: 6.9
- Stable tag: 4.0.0
- License: MIT
- License URI: https://opensource.org/licenses/MIT

WebFinger for WordPress

## Description

WebFinger allows you to be discovered on the web using an identifier like `you@yourdomain.com` — similar to how email works, but for your online identity.

**Why is this useful?**

* **Fediverse & Mastodon:** WebFinger is essential for federation. It allows Mastodon and other ActivityPub-powered platforms to find and follow your WordPress site.
* **Decentralized Identity:** People can look you up using your WordPress domain, making your site the canonical source for your online identity.
* **Works with other plugins:** This plugin provides the foundation that other plugins (like the ActivityPub plugin) build upon.

**How it works:**

When someone searches for `@you@yourdomain.com` on Mastodon or another federated service, their server asks your WordPress site: "Who is this person?" WebFinger answers that question by providing information about you and links to your profiles.

**Technical details:**

WebFinger is an open standard ([RFC 7033](http://tools.ietf.org/html/rfc7033)) that enables discovery of information about people and resources on the internet. It works by responding to requests at `/.well-known/webfinger` on your domain.

## Frequently Asked Questions

### How do I customize my WebFinger identifier?

Go to **Users → Profile** in your WordPress admin and scroll down to the "WebFinger" section. There you can set a custom identifier (the part before the @) and see all your WebFinger aliases.

### How do I check if WebFinger is working?

Visit **Tools → Site Health** in your WordPress admin. The plugin adds checks that verify your WebFinger endpoint is accessible and properly configured. If there are any issues, you'll see guidance on how to fix them.

### Does this work with Mastodon?

Yes! WebFinger is the standard that Mastodon and other Fediverse platforms use to discover users. When someone searches for `@you@yourdomain.com`, WebFinger tells them where to find your profile.

### Do I need pretty permalinks?

Yes. WebFinger requires pretty permalinks to be enabled. Go to **Settings → Permalinks** and select any option other than "Plain".

### For developers: How do I add custom data to the WebFinger response?

Use the `webfinger_data` filter to add your own links or properties:

    add_filter( 'webfinger_data', function( $data ) {
      $data['links'][] = array(
        'rel'  => 'http://example.com/rel/profile',
        'href' => 'http://example.com/profile',
        'type' => 'text/html',
      );
      return $data;
    } );

### For developers: How do I add alternate output formats?

Use the `webfinger_render` action to output custom formats (like XRD):

    add_action( 'webfinger_render', function( $webfinger ) {
      // Set custom headers and output your format
      // ...
      exit;
    }, 5 );

See <https://github.com/pfefferle/wordpress-webfinger-legacy> for a complete example.

### Where can I learn more about WebFinger?

* WebFinger specification: [RFC 7033](http://tools.ietf.org/html/rfc7033)
* Community resources: <http://webfinger.net>

## Upgrade Notice

### 4.0.0

This is a major update with new features (Site Health checks, user profile settings) and requires PHP 7.2 or higher. After updating, visit **Tools → Site Health** to verify your WebFinger setup is working correctly.

### 3.0.0

This version drops classic WebFinger (XRD) support to keep the plugin lightweight. If you need legacy XRD format support, install the [WebFinger Legacy](https://github.com/pfefferle/wordpress-webfinger-legacy) plugin.

## Changelog

Project maintained on github at [pfefferle/wordpress-webfinger](https://github.com/pfefferle/wordpress-webfinger).

### 4.0.0

* Added: Site Health integration to check your WebFinger setup status directly in WordPress
* Added: User profile settings to customize your WebFinger identifier
* Added: Verification links to easily test your WebFinger aliases
* Improved: Security hardening for URI parsing and input validation
* Improved: Modernized codebase for PHP 7.2+ with namespace support
* Improved: Better organized code structure with separate classes
* Updated: Development infrastructure with GitHub Actions for automated testing

### 3.2.7

* Added: better output escaping
* Fixed: stricter queries

### 3.2.6

* remove E-Mail address

### 3.2.5

* fix typo

### 3.2.4

* update requirements

### 3.2.3

* fixed `acct` scheme for discovery

### 3.2.2

* fixed typo (thanks @ivucica)
* use `acct` as default scheme

### 3.2.1

* make `acct` protocol optional

### 3.2.0

* global refactoring

### 3.1.6

* added `user_nicename` as resource
* fixed WordPress coding standard issues

### 3.1.5

* fixed PHP warning

### 3.1.4

* updated requirements

### 3.1.3

* add support for the 'aim', 'ymsgr' and 'acct' protocol

### 3.1.2

* fixed the legacy code
* added feeds

### 3.1.1

* fixed 'get_user_by_various' function

### 3.1.0

* Added WebFinger legacy plugin, because the legacy version is still very popular and used by for example OStatus (Mastodon, Status.NET and GNU Social)
* Added Webfinger for posts support

### 3.0.3

* composer support
* compatibility updates

### 3.0.2

* `get_avatar_url` instead of custom code
* some small code improvements
* nicer PHP-docs

### 3.0.1

* updated version informations
* support the WordPress Coding Standard

### 3.0.0

* added correct error-responses
* remove legacy support for XRD and host-meta (props to Will Norris)

### 2.0.1

* small bugfix

### 2.0.0

* complete refactoring
* removed simple-web-discovery
* more filters and actions
* works without /.well-known/ plugin

### 1.4.0

* small fixes
* added "webfinger" as well-known uri

### 1.3.1

* added "rel"-filter (work in progress)
* added more aliases

### 1.3

* added host-meta resource feature (see latest spec)

### 1.2

* added 404 http error if user doesn't exist
* added jrd discovery for host-meta

### 1.1

* fixed an odd problem with lower WordPress versions
* added support for the http://wordpress.org/extend/plugins/extended-profile/ (thanks to Singpolyma)

### 1.0.1

* api improvements

### 1.0

* basic simple-seb-discovery
* json support
* some small improvements

### 0.9.1

* some changes to support http://unhosted.org

### 0.9

* OStatus improvements
* Better uri handling
* Identifier overview (more to come)
* Added filters
* Added functions to get a users webfingers

### 0.7

* Added do_action param (for future OStatus plugin)
* Author-Url as Webfinger-Identifier

### 0.5

* Initial release

## Installation

### From WordPress.org (recommended)

1. Go to **Plugins → Add New** in your WordPress admin
2. Search for "webfinger"
3. Click **Install Now**, then **Activate**
4. Make sure pretty permalinks are enabled (**Settings → Permalinks** — select any option except "Plain")
5. Visit **Tools → Site Health** to verify everything is working

### Manual Installation

1. Download the plugin from [WordPress.org](https://wordpress.org/plugins/webfinger/) or [GitHub](https://github.com/pfefferle/wordpress-webfinger/releases)
2. Upload the `webfinger` folder to `/wp-content/plugins/`
3. Activate the plugin in **Plugins → Installed Plugins**
4. Enable pretty permalinks if not already active
5. Check **Tools → Site Health** to confirm the setup
