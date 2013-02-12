<?php
/*
Plugin Name: Webfinger
Plugin URI: http://wordpress.org/extend/plugins/webfinger/
Description: Webfinger for WordPress
Version: 2.0.0-dev
Author: Matthias Pfefferle
Author URI: http://notizblog.org/
*/

/**
 * webfinger
 *
 * @author Matthias Pfefferle
 */
class WebfingerPlugin {
  
  public function __construct() {
    add_action('init', array( $this, 'init' ));
    
    register_activation_hook(__FILE__, 'flush_rewrite_rules');
    register_deactivation_hook(__FILE__, 'flush_rewrite_rules');
  }
  
  public function init() {
    load_plugin_textdomain('webfinger', null, basename(dirname( __FILE__ )));

    add_action('query_vars', array($this, 'query_vars'));
    add_action('parse_request', array($this, 'parse_request'));
    
    // testing "webfinger" well-known uri
    add_action('well_known_webfinger', array($this, 'render_output'), 1, 1);
    // host-meta resource
    add_action('well_known_host-meta', array($this, 'render_output'), -1, 1);
    add_action('well_known_host-meta.json', array($this, 'render_output'), -1, 1);

    add_action('webfinger_render_json', array($this, 'render_jrd'), 1, 1);
    add_action('webfinger_render_jrd', array($this, 'render_jrd'), 1, 1);
    
    add_action('webfinger_render_xrd', array($this, 'render_xrd'), 1, 2);
    
    add_filter('webfinger', array($this, 'generate_default_content'), 0, 3);
    add_filter('webfinger', array($this, 'filter_by_rel'), 99, 4);
    
    add_filter('host_meta', array($this, 'add_host_meta_links'));
  }
  
  /**
   * adds some query vars
   *
   * @param array $vars
   * @return array
   */
  public function query_vars($vars) {
    $vars[] = 'webfinger-uri';
    $vars[] = 'webfinger';
    $vars[] = 'format';
    $vars[] = 'resource';
    $vars[] = 'rel';

    return $vars;
  }
  
  /**
   * parses the request
   *
   * @return mixed
   */
  public function parse_request($wp) {
    if (array_key_exists('webfinger', $wp->query_vars)) {
      $this->render_output($wp->query_vars);
    }
  }
  
  /**
   * renders the output-file
   *
   * @param array
   */
  public function render_output($query_vars) {
    // check if "resource" param exists
    if (!array_key_exists('resource', $query_vars)) {
      return;
    }
    
    // find matching user
    $user = $this->get_user_by_uri($query_vars['resource']);
      
    if (!$user) {
      return;
    }

    $format = 'json';
    if (array_key_exists('well-known', $query_vars) &&
        $query_vars['well-known'] == "host-meta") {
      $format = 'xrd';
    }
    if (array_key_exists('format', $query_vars)) {
      $format = $query_vars['format'];
    }
      
    $webfinger = apply_filters('webfinger', array(), $user, $query_vars['resource'], $query_vars);
    
    do_action("webfinger_render_{$format}", $webfinger, $user, $query_vars);
    do_action("webfinger_render", $format, $webfinger, $user, $query_vars);
  }
  
  /**
   * renders the webfinger file in json
   */
  public function render_jrd($webfinger) {
    header("Access-Control-Allow-Origin: *");
    header('Content-Type: application/json; charset=' . get_option('blog_charset'), true);

    echo json_encode($webfinger);
    exit();
  }
  
  /**
   * renders the webfinger file in xml
   */
  public function render_xrd($webfinger, $user) {
    header("Access-Control-Allow-Origin: *");
    header('Content-Type: application/xrd+xml; charset=' . get_option('blog_charset'), true);
  
    echo "<?xml version='1.0' encoding='".get_option('blog_charset')."'?>\n";
    echo "<XRD xmlns='http://docs.oasis-open.org/ns/xri/xrd-1.0'\n";
      // add xml-only namespaces
      do_action('webfinger_ns');
    echo ">\n";

    echo $this->jrd_to_xrd($webfinger);
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
                       //'aliases' => get_webfingers($user->ID),
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
    $array["links"][] = array("rel" => "lrdd", "template" => home_url("/?webfinger=true&resource={uri}&format=xrd"), "type" => "application/xrd+xml");
    $array["links"][] = array("rel" => "lrdd", "template" => home_url("/?webfinger=true&resource={uri}"), "type" => "application/json");

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
        $xrd .= "<Subject>$content</Subject>";
        continue;
      }
      
      // print aliases
      if ($type == "aliases") {
        foreach ($content as $uri) {
          $xrd .= "<Alias>".htmlentities($uri)."</Alias>";
        }
        continue;
      }
      
      // print properties
      if ($type == "properties") {
        foreach ($content as $type => $uri) {
          $xrd .= "<Property type='".htmlentities($type)."'>".htmlentities($uri)."</Property>";
        }
        continue;
      }
      
      // print titles
      if ($type == "titles") {
        foreach ($content as $key => $value) {
          if ($key == "default") {
            $xrd .= "<Title>".htmlentities($value)."</Title>";
          } else {
            $xrd .= "<Title xml:lang='".htmlentities($key)."'>".htmlentities($value)."</Title>";
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
              $xrd .= htmlentities($key)."='".htmlentities($value)."' ";
            }
          }
          if ($cascaded) {
            $xrd .= ">";
            $xrd .= $this->jrd_to_xrd($temp);
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
}

new WebfingerPlugin();