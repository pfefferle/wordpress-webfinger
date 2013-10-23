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
   * add query vars
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
   * add rewrite rules
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
   * parse the webfinger request and render the document.
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
   * render the JRD representation of the webfinger resource.
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
    $webfinger = array('subject' => self::get_user_resource($user->ID),
                       'aliases' => self::get_user_resources($user->ID),
                       'links' => array(
                         array('rel' => 'http://webfinger.net/rel/profile-page', 'type' => 'text/html', 'href' => $url),
                         array('rel' => 'http://webfinger.net/rel/avatar',  'href' => $photo)
                        ));

    // add user_url if set
    if (isset($user->user_url) && !empty($user->user_url)) {
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
  public function filter_by_rel($webfinger) {
    // explode the query-string by hand because php does not
    // support multiple queries with the same name
    $query = explode("&", $_SERVER['QUERY_STRING']);
    $params = array();

    foreach($query as $param) {
      list($name, $value) = explode('=', $param);
      $params[urldecode($name)][] = urldecode($value);
    }

    // check if "rel" is set
    if (!array_key_exists('rel', $params)) {
      return $webfinger;
    }

    // filter webfinger-array
    $links = array();
    foreach ($webfinger['links'] as $link) {
      if (in_array($link["rel"], $params['rel'])) {
        $links[] = $link;
      }
    }
    $webfinger['links'] = $links;

    // return only "links" with the matching rel-values
    return $webfinger;
  }

  /**
   * returns a Userobject
   *
   * @param string $uri
   * @return WP_User
   * @uses apply_filters() uses 'webfinger_user' to filter the
   *       user and 'webfinger_user_query' to add custom query-params
   */
  private function get_user_by_uri($uri) {
    global $wpdb;

    $uri = urldecode($uri);

    if (!preg_match("/^([a-zA-Z^:]+):(.*)$/i", $uri, $match)) {
      // no valid scheme provided
      return null;
    }

    // extract the scheme
    $scheme = $match[1];
    // extract the "host"
    $host = $match[2];

    switch ($scheme) {
      // check urls
      case "http":
      case "https":
        if ($author_id = url_to_authorid($uri)) {
          $user = get_userdata($author_id);

          return apply_filters("webfinger_user", $user, $uri);
        }

        // search url in user_url
        $args = array(
          'search'         => $uri,
          'search_columns' => array('user_url'),
          'meta_compare'   => '=',
        );
        break;
      // check acct scheme
      case "acct":
        // get the identifier at the left of the '@'
        $parts = explode("@", $host);

        $args = array(
          'search'         => $parts[0],
          'search_columns' => array('user_name', 'display_name', 'user_login'),
          'meta_compare'   => '=',
        );
        break;
      // check mailto scheme
      case "mailto":
        $args = array(
          'search'         => $host,
          'search_columns' => array('user_email'),
          'meta_compare'   => '=',
        );
        break;
      // check instant messaging schemes
      case "xmpp":
      case "im":
        $args = array(
          'meta_query' => array(
            'relation' => 'OR',
            array(
              'key'     => 'jabber',
              'value'   => $host,
              'compare' => '='
            ),
            array(
              'key'     => 'yim',
              'value'   => $host,
              'compare' => '='
            ),
            array(
              'key'     => 'aim',
              'value'   => $host,
              'compare' => '='
            )
          )
        );
        break;
      default:
        $args = array();
        break;
    }

    $args = apply_filters("webfinger_user_query", $args, $uri, $scheme);

    // get user query
    $user_query = new WP_User_Query($args);

    // check result
    if (!empty($user_query->results)) {
      $user = $user_query->results[0];
    } else {
      $user = null;
    }

    return apply_filters("webfinger_user", $user, $uri);
  }

  /**
   * returns a users default webfinger
   *
   * @param mixed $id_or_name_or_object
   * @return string|null
   */
  function get_user_resource($id_or_name_or_object) {
    $user = self::get_user_by_various($id_or_name_or_object);

    if ($user) {
      $resource = $user->user_login."@".parse_url(home_url(), PHP_URL_HOST);

      return apply_filters('webfinger_user_resource', "acct:".$resource, $user);
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
  public function get_user_resources($id_or_name_or_object) {
    $user = self::get_user_by_various($id_or_name_or_object);

    if ($user) {
      $resources[] = self::get_user_resource($user, true);
      $resources[] = get_author_posts_url($user->ID, $user->user_nicename);
      if ($user->user_email && self::check_mail_domain($user->user_email)) {
        $resources[] = "mailto:".$user->user_email;
      }
      if (get_user_meta($user->ID, "jabber", true) && self::check_mail_domain(get_user_meta($user->ID, "jabber", true))) {
        $resources[] = "xmpp:".get_user_meta($user->ID, "jabber", true);
      }
      $resources = apply_filters('webfinger_user_resources', $resources, $user);

      return array_unique($resources);
    } else {
      return array();
    }
  }

  /**
   * convenience method to get user data by ID, username, object or from current user.
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

if (!function_exists('url_to_authorid')) {
  /**
   * Examine a url and try to determine the author ID it represents.
   *
   * Checks are supposedly from the hosted site blog.
   *
   * @param string $url Permalink to check.
   * @return int User ID, or 0 on failure.
   */
  function url_to_authorid($url) {
    global $wp_rewrite;

    // check if url hase the same host
    if (parse_url(site_url(), PHP_URL_HOST) != parse_url($url, PHP_URL_HOST)) {
      return 0;
    }

    // First, check to see if there is a 'author=N' to match against
    if ( preg_match('/[?&]author=(\d+)/i', $url, $values) ) {
      $id = absint($values[1]);
      if ( $id )
        return $id;
    }

    // Check to see if we are using rewrite rules
    $rewrite = $wp_rewrite->wp_rewrite_rules();

    // Not using rewrite rules, and 'p=N' and 'page_id=N' methods failed, so we're out of options
    if ( empty($rewrite) )
      return 0;

    // generate rewrite rule for the author url
    $author_rewrite = $wp_rewrite->get_author_permastruct();
    $author_regexp = str_replace("%author%", "", $author_rewrite);

    // match the rewrite rule with the passed url
    if (preg_match("/https?:\/\/(.+)".preg_quote($author_regexp, '/')."([^\/]+)/i", $url, $match)) {
      if ($user = get_user_by("slug", $match[2])) {
        return $user->ID;
      }
    }

    return 0;
  }
}

add_action('query_vars', array('WebFingerPlugin', 'query_vars'));
add_action('parse_request', array('WebFingerPlugin', 'parse_request'));
add_action('generate_rewrite_rules', array('WebFingerPlugin', 'rewrite_rules'));

add_filter('webfinger', array('WebFingerPlugin', 'generate_default_content'), 0, 3);
add_filter('webfinger', array('WebFingerPlugin', 'filter_by_rel'), 99, 1);

add_action('webfinger_render', array('WebFingerPlugin', 'render_jrd'), 20, 1);

register_activation_hook(__FILE__, 'flush_rewrite_rules');
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');