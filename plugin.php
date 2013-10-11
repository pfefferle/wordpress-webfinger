<?php
/*
Plugin Name: WebFinger
Plugin URI: http://wordpress.org/extend/plugins/webfinger/
Description: WebFinger for WordPress
Version: 3.0.0-dev
Author: pfefferle
Author URI: http://notizblog.org/
*/

/**
 * webfinger
 *
 * @author Matthias Pfefferle
 * @author Will Norris
 */
class WebFingerPlugin {

  /**
   * adds some query vars
   *
   * @param array $vars
   * @return array
   */
  public function query_vars($vars) {
    $vars[] = 'well-known';
    $vars[] = 'resource';
    $vars[] = 'rel';

    return $vars;
  }

  /**
   * Add rewrite rules
   *
   * @param WP_Rewrite
   */
  function rewrite_rules( $wp_rewrite ) {
    $webfinger_rules = array(
      '.well-known/webfinger' => 'index.php?well-known=webfinger'
    );

    $wp_rewrite->rules = $webfinger_rules + $wp_rewrite->rules;
  }

  /**
   * Parse the webfinger request and render the document.
   *
   * @param WP $wp WordPress request context
   *
   * @uses apply_filters() Calls 'webfinger' on webfinger data array
   * @uses do_action() Calls 'webfinger_render' to render webfinger data
   */
  public function parse_request($wp) {
    // check if it is a webfinger request or not
    if (!array_key_exists('well-known', $wp->query_vars) ||
        $wp->query_vars['well-known'] != 'webfinger') {
      return;
    }

    // check if "resource" param exists
    if (!array_key_exists('resource', $wp->query_vars)) {
      status_header(400);
      header('Content-Type: text/plain; charset=' . get_bloginfo('charset'), true);
      echo 'missing "resource" parameter';
      exit;
    }

    // find matching user
    $user = self::get_user_by_uri($wp->query_vars['resource']);

    // check if "user" exists
    if (!$user) {
      status_header(404);
      header('Content-Type: text/plain; charset=' . get_bloginfo('charset'), true);
      echo 'no user found';
      exit;
    }

    // filter webfinger array
    $webfinger = apply_filters('webfinger', array(), $user, $wp->query_vars['resource'], $wp->query_vars);
    do_action('webfinger_render', $webfinger);
  }

  /**
   * Render the JRD representation of the webfinger resource.
   *
   * @param array $webfinger the webfinger data-array
   */
  public function render_jrd($webfinger) {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/jrd+json; charset=' . get_bloginfo('charset'), true);

    echo json_encode($webfinger);
    exit();
  }

  /**
   * generates the webfinger base array
   *
   * @param array $webfinger the webfinger data-array
   * @param stdClass $user the WordPress user
   * @param string $resource the resource param
   * @return array the enriched webfinger data-array
   */
  public function generate_default_content($webfinger, $user, $resource) {
    // generate "profile" url
    $url = get_author_posts_url($user->ID, $user->user_nicename);
    // generate default photo-url
    $photo = get_user_meta($user->ID, 'photo', true);
    if(!$photo) $photo = 'http://www.gravatar.com/avatar/'.md5($user->user_email);

    // generate default array
    $webfinger = array('subject' => $resource,
                       'aliases' => self::get_resources($user->ID),
                       'links' => array(
                         array('rel' => 'http://webfinger.net/rel/profile-page', 'type' => 'text/html', 'href' => $url),
                         array('rel' => 'http://webfinger.net/rel/avatar',  'href' => $photo)
                        ));

    // add user_url if set
    if (isset($user->user_url)) {
      $webfinger['links'][] = array('rel' => 'http://webfinger.net/rel/profile-page', 'type' => 'text/html', 'href' => $user->user_url);
    }

    return $webfinger;
  }

  /**
   * filters the webfinger array by request params like "rel"
   *
   * @link http://tools.ietf.org/html/rfc7033#section-4.3
   * @param array $array
   * @param stdClass $user
   * @param array $queries
   * @return array
   */
  public function filter_by_rel($webfinger, $user, $resource, $queries) {
    // check if "rel" is set
    if (!array_key_exists('rel', $queries)) {
      return $webfinger;
    }

    // filter webfinger-array
    $links = array();
    foreach ($webfinger['links'] as $link) {
      if ($link["rel"] == $queries["rel"]) {
        $links[] = $link;
      }
    }
    $webfinger['links'] = $links;

    // return only "links" with the matching
    return $webfinger;
  }

  /**
   * returns a Userobject
   *
   * @param string $uri
   * @return stdClass
   */
  private function get_user_by_uri($uri) {
    global $wpdb;

    $uri = urldecode($uri);

    if (preg_match("~^https?://~i", $uri)) {
      // check if url matches with a
      // users profile url
      foreach (get_users_of_blog() as $user) {
        if (rtrim(str_replace("www.", "", get_author_posts_url($user->ID, $user->user_nicename)), "/") ==
            rtrim(str_replace("www.", "", $uri), "/")) {
          return $user;
        }
      }
    } elseif (preg_match('/^([a-zA-Z]+:)?([^@]+)@([a-zA-Z0-9._-]+)$/i', $uri, $array)) {
      $username = $array[2];
      $host = $array[3];
      $email = $username."@".$host;

      if ($host == parse_url(get_bloginfo('url'), PHP_URL_HOST)) {
        $sql =  "SELECT * FROM $wpdb->users u INNER JOIN $wpdb->usermeta um ON u.id=um.user_id WHERE u.user_email = '%s' OR
                                                  (um.meta_key = 'jabber' AND um.meta_value = '%s') OR
                                                  u.user_login = '%s' LIMIT 1;";
        $user = $wpdb->get_results($wpdb->prepare($sql, $email, $email, $username));
        if (!empty($user)) {
          return $user[0];
        }
      }
    }

    return false;
  }

  /**
   * returns a users default webfinger
   *
   * @param mixed $id_or_name_or_object
   * @param boolean $protocol
   * @return string
   */
  function get_resource($id_or_name_or_object, $protocol = false) {
    $user = self::get_user_by_various($id_or_name_or_object);

    if ($user) {
      $resource = $user->user_login."@".parse_url(home_url(), PHP_URL_HOST);
      if ($protocol) {
        $resource = "acct:".$resource;
      }
      return $resource;
    } else {
      return null;
    }
  }

  /**
   * returns all webfinger "resources"
   *
   * @param mixed $id_or_name_or_object
   * @return array
   */
  public function get_resources($id_or_name_or_object) {
    $user = self::get_user_by_various($id_or_name_or_object);

    if ($user) {
      $resources[] = self::get_resource($user, true);
      $resources[] = get_author_posts_url($user->ID, $user->user_nicename);
      if ($user->user_email && self::check_mail_domain($user->user_email)) {
        $resources[] = "mailto:".$user->user_email;
      }
      if (get_user_meta($user->ID, "jabber", true) && self::check_mail_domain(get_user_meta($user->ID, "jabber", true))) {
        $resources[] = "xmpp:".get_user_meta($user->ID, "jabber", true);
      }
      $resources = apply_filters('resources', $resources);

      return array_unique($resources);
    } else {
      return array();
    }
  }

  /**
   * Convenience method to get user data by ID, username, object or from current user.
   *
   * @param mixed $id_or_name_or_object the username, ID or object. If not provided, the current user will be used.
   * @return bool|object False on failure, User DB row object
   *
   * @author Will Norris
   * @see get_userdata_by_various() # DiSo OpenID-Plugin
   */
  public function get_user_by_various($id_or_name_or_object = null) {
    if ( $id_or_name_or_object === null ) {
      $user = wp_get_current_user();
      if ($user == null) return false;
      return $user->data;
    } else if ( is_object($id_or_name_or_object) ) {
      return $id_or_name_or_object;
    } else if ( is_numeric($id_or_name_or_object) ) {
      return get_userdata($id_or_name_or_object);
    } else {
      return get_userdatabylogin($id_or_name_or_object);
    }
  }

  /**
   * check if the email address has the same domain as the blog
   *
   * @param string $email
   * @return boolean
   */
  public function check_mail_domain($email) {
    if (preg_match('/^([a-zA-Z]+:)?([^@]+)@([a-zA-Z0-9._-]+)$/i', $email, $email_parts) &&
        ($email_parts[3] == parse_url(home_url(), PHP_URL_HOST))) {
      return true;
    }

    return false;
  }

}

add_action('query_vars', array('WebFingerPlugin', 'query_vars'));
add_action('parse_request', array('WebFingerPlugin', 'parse_request'));
add_action('generate_rewrite_rules', array('WebFingerPlugin', 'rewrite_rules'));

add_filter('webfinger', array('WebFingerPlugin', 'generate_default_content'), 0, 3);
add_filter('webfinger', array('WebFingerPlugin', 'filter_by_rel'), 99, 4);

add_action('webfinger_render', array('WebFingerPlugin', 'render_jrd'), 20, 1);

register_activation_hook(__FILE__, 'flush_rewrite_rules');
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');
