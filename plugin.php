<?php
/*
Plugin Name: Webfinger
Plugin URI: http://wordpress.org/extend/plugins/webfinger/
Description: Webfinger for WordPress
Version: 2.1.0-dev
Author: pfefferle
Author URI: http://notizblog.org/
*/

/**
 * webfinger
 *
 * @author Matthias Pfefferle
 */
class WebfingerPlugin {
  
  /**
   * adds some query vars
   *
   * @param array $vars
   * @return array
   */
  public function query_vars($vars) {
    $vars[] = 'webfinger-uri';
    $vars[] = 'well-known';
    $vars[] = 'format';
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
   * renders the output-file
   *
   * @param array
   */
  public function parse_request($wp) {
    // check if "resource" param exists
    if (!array_key_exists('resource', $wp->query_vars)) {
      header('HTTP/1.0 400 Bad Request');
      header('Content-Type: text/plain; charset=' . get_bloginfo('charset'), true);
      echo 'missing "resource" parameter';
      exit;
    }

    // find matching user
    $user = self::get_user_by_uri($wp->query_vars['resource']);
      
    if (!$user) {
      header('HTTP/1.0 404 Not Found');
      header('Content-Type: text/plain; charset=' . get_bloginfo('charset'), true);
      echo 'no user found';
      exit;
    }
    
    // filter webfinger array
    $webfinger = apply_filters('webfinger', array(), $user, $wp->query_vars['resource'], $wp->query_vars);
    
    // interpret accept header
    if ($pos = stripos($_SERVER['HTTP_ACCEPT'], ';')) {
      $accept_header = substr($_SERVER['HTTP_ACCEPT'], 0, $pos);
    } else {
      $accept_header = $_SERVER['HTTP_ACCEPT'];
    }
    
    // accept header as an array
    $accept = explode(',', trim($accept_header));
    
    do_action("webfinger_render_mime", $accept, $webfinger, $user, $wp->query_vars);
    
    // old query-var filter used for example by the host-meta.json version
    // @link http://tools.ietf.org/html/draft-ietf-appsawg-webfinger-02
    $format = 'json';
    if (array_key_exists('format', $wp->query_vars)) {
      $format = $wp->query_vars['format'];
    }
    
    do_action("webfinger_render", $format, $webfinger, $user, $wp->query_vars);
    do_action("webfinger_render_{$format}", $webfinger, $user, $wp->query_vars);
  }
  
  /**
   * renders the webfinger file in json
   */
  public function render_by_mime($accept, $webfinger, $user) {
    // render jrd
    if (in_array(array("application/json", "application/jrd+json"), $accept)) {
      self::render_jrd($webfinger);
    }
    
    // render xrd
    if (in_array("application/xrd+xml", $accept)) {
      self::render_xrd($webfinger, $user);
    }
  }
  
  /**
   * renders the webfinger file in json
   */
  public function render_jrd($webfinger) {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/jrd+json; charset=' . get_bloginfo('charset'), true);

    echo json_encode($webfinger);
    exit();
  }
  
  /**
   * renders the webfinger file in xml
   */
  public function render_xrd($webfinger, $user) {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/xrd+xml; charset=' . get_bloginfo('charset'), true);
  
    echo "<?xml version='1.0' encoding='".get_bloginfo('charset')."'?>\n";
    echo "<XRD xmlns='http://docs.oasis-open.org/ns/xri/xrd-1.0'\n";
      // add xml-only namespaces
      do_action('webfinger_ns');
    echo ">\n";

    echo self::jrd_to_xrd($webfinger);
      // add xml-only content
      do_action('webfinger_xrd', $user);
    
    echo "\n</XRD>";
    exit;
  }
  
  /**
   * generates the webfinger base array
   */
  public function generate_default_content($webfinger, $user, $resource) {
    $url = get_author_posts_url($user->ID, $user->user_nicename);
    $photo = get_user_meta($user->ID, 'photo', true);
    if(!$photo) $photo = 'http://www.gravatar.com/avatar/'.md5($user->user_email);
    $webfinger = array('subject' => $resource,
                       'aliases' => self::get_resources($user->ID),
                       'links' => array(
                         array('rel' => 'http://webfinger.net/rel/profile-page', 'type' => 'text/html', 'href' => $url),
                         array('rel' => 'http://webfinger.net/rel/avatar',  'href' => $photo)
                        ));
    if ($user->user_url) {
      $webfinger['links'][] = array('rel' => 'http://webfinger.net/rel/profile-page', 'type' => 'text/html', 'href' => $user->user_url);
    }
    
    return $webfinger;
  }
  
  /**
   * filters the webfinger array by request params like "rel"
   *
   * @link tools.ietf.org/html/draft-jones-appsawg-webfinger#section-4.3
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

    remove_all_actions('webfinger_xrd');
  
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
   * add the host meta information
   */
  public function add_host_meta_links($array) {
    $array["links"][] = array("rel" => "lrdd", "template" => site_url("/?well-known=webfinger&resource={uri}&format=xrd"), "type" => "application/xrd+xml");

    return $array;
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
   * recursive helper to generade the xrd-xml from the jrd array
   *
   * @param string $host_meta
   * @return string
   */
  public function jrd_to_xrd($webfinger) {
    $xrd = null;

    foreach ($webfinger as $type => $content) {
      // print subject
      if ($type == "subject") {
        $xrd .= "<Subject>".htmlspecialchars($content)."</Subject>";
        continue;
      }
      
      // print aliases
      if ($type == "aliases") {
        foreach ($content as $uri) {
          $xrd .= "<Alias>".htmlspecialchars($uri)."</Alias>";
        }
        continue;
      }
      
      // print properties
      if ($type == "properties") {
        foreach ($content as $type => $uri) {
          $xrd .= "<Property type='".htmlspecialchars($type)."'>".htmlspecialchars($uri)."</Property>";
        }
        continue;
      }
      
      // print titles
      if ($type == "titles") {
        foreach ($content as $key => $value) {
          if ($key == "default") {
            $xrd .= "<Title>".htmlspecialchars($value)."</Title>";
          } else {
            $xrd .= "<Title xml:lang='".htmlspecialchars($key)."'>".htmlspecialchars($value)."</Title>";
          }
        }
        continue;
      }
      
      // print links
      if ($type == "links") {
        foreach ($content as $links) {
          $temp = array();
          $cascaded = false;
          $xrd .= "<Link ";

          foreach ($links as $key => $value) {
            if (is_array($value)) {
              $temp[$key] = $value;
              $cascaded = true;
            } else {
              $xrd .= htmlspecialchars($key)."='".htmlspecialchars($value)."' ";
            }
          }
          if ($cascaded) {
            $xrd .= ">";
            $xrd .= self::jrd_to_xrd($temp);
            $xrd .= "</Link>";
          } else {
            $xrd .= " />";
          }
        }
        
        continue;
      }
    }
    
    return $xrd;
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
   *
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
  
  public function host_meta_draft($format, $host_meta, $query_vars) {

    if (!array_key_exists('resource', $query_vars)) {
      return;
    }
    
    // find matching user
    $user = self::get_user_by_uri($query_vars['resource']);
      
    if (!$user) {
      return;
    }
      
    $webfinger = apply_filters('webfinger', array(), $user, $query_vars['resource'], $query_vars);
    
    do_action("webfinger_render", $format, $webfinger, $user, $query_vars);
    do_action("webfinger_render_{$format}", $webfinger, $user, $query_vars);
  }
}

add_action('query_vars', array('WebfingerPlugin', 'query_vars'));
add_action('parse_request', array('WebfingerPlugin', 'parse_request'));
add_action('generate_rewrite_rules', array('WebfingerPlugin', 'rewrite_rules'));

add_action('host_meta_render', array('WebfingerPlugin', 'host_meta_draft'), 1, 3);

add_action('webfinger_render_json', array('WebfingerPlugin', 'render_jrd'), 1, 1);
add_action('webfinger_render_jrd', array('WebfingerPlugin', 'render_jrd'), 1, 1);

add_action('webfinger_render_xml', array('WebfingerPlugin', 'render_xrd'), 1, 2);
add_action('webfinger_render_xrd', array('WebfingerPlugin', 'render_xrd'), 1, 2);

add_action('webfinger_render_mime', array('WebfingerPlugin', 'render_by_mime'), 1, 3);
 
add_filter('webfinger', array('WebfingerPlugin', 'generate_default_content'), 0, 3);
add_filter('webfinger', array('WebfingerPlugin', 'filter_by_rel'), 99, 4);
    
add_filter('host_meta', array('WebfingerPlugin', 'add_host_meta_links'));
    
register_activation_hook(__FILE__, 'flush_rewrite_rules');
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');
