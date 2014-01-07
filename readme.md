# WebFinger #
**Contributors:** pfefferle, willnorris  
**Donate link:** http://14101978.de  
**Tags:** well-known, discovery, webfinger, JRD  
**Requires at least:** 2.7  
**Tested up to:** 3.6.1  
**Stable tag:** 2.0.1  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

WebFinger for WordPress!

## Description ##

Enables WebFinger ([RFC 7033](http://tools.ietf.org/html/rfc7033)) support for WordPress.

About WebFinger:

WebFinger is used to discover information about people or other
entities on the Internet that are identified by a URI using
standard Hypertext Transfer Protocol (HTTP) methods over a secure
transport.  A WebFinger resource returns a JavaScript Object
Notation (JSON) object describing the entity that is queried.
The JSON object is referred to as the JSON Resource Descriptor (JRD).

(quote from the [RFC](http://tools.ietf.org/html/rfc7033))

## Frequently Asked Questions ##

### How can I extend WebFinger ###

You can add your own links or properties like that:

```function oexchange_target_link($array) {
  $array["links"][] = array("rel" => "http://oexchange.org/spec/0.8/rel/resident-target",
    "href" => "http://example.com",
    "type" => "application/xrd+xml");
  return $array;
}
add_filter('webfinger', 'oexchange_target_link');```

### Where can I find the Spec? ###

WebFinger was specified as [RFC 7033](http://tools.ietf.org/html/rfc7033)

### Where can I find out more about WebFinger? ###

Please visit <http://webfinger.net>

## Changelog ##

Project maintined on github at
[pfefferle/wordpress-webfinger](https://github.com/pfefferle/wordpress-webfinger).

### 3.0.0 ###

* added correct error-responses
* remove legacy support for XRD and host-meta (props to Will Norris)

### 2.0.1 ###

* small bugfix

### 2.0.0 ###

* complete refactoring
* removed simple-web-discovery
* more filters and actions
* works without /.well-known/ plugin

### 1.4.0 ###

* small fixes
* added "webfinger" as well-known uri

### 1.3.1 ###

* added "rel"-filter (work in progress)
* added more aliases

### 1.3 ###

* added host-meta resource feature (see latest spec)

### 1.2 ###

* added 404 http error if user doesn't exist
* added jrd discovery for host-meta

### 1.1 ###

* fixed an odd problem with lower WordPress versions
* added support for the http://wordpress.org/extend/plugins/extended-profile/ (thanks to Singpolyma)

### 1.0.1 ###

* api improvements

### 1.0 ###

* basic simple-seb-discovery
* json support
* some small improvements

### 0.9.1 ###

* some changes to support http://unhosted.org

### 0.9 ###

* OStatus improvements
* Better uri handling
* Identifier overview (more to come)
* Added filters
* Added functions to get a users webfingers

### 0.7 ###

* Added do_action param (for future OStatus plugin)
* Author-Url as Webfinger-Identifier

### 0.5 ###

* Initial release

## Installation ##

1. Upload the `webfinger`-folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the *Plugins* menu in WordPress
3. ...and that's it :)

## Upgrade Notice ##

### 3.0.0 ###

This versions drops classic WebFinger support to keep the plugin short and simple