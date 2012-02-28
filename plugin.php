<?php
/*
Plugin Name: Webfinger
Plugin URI: http://wordpress.org/extend/plugins/webfinger/
Description: Webfinger for WordPress
Version: 1.2
Author: Matthias Pfefferle
Author URI: http://notizblog.org/
*/

/**
 * returns a users default webfinger
 * 
 * @param mixed $id_or_name_or_object
 * @param boolean $protocol
 * @return string
 */
function get_webfinger($id_or_name_or_object = null, $protocol = false) {
  $webfinger = new WebfingerPlugin();
  $user = $webfinger->get_user_by_various($id_or_name_or_object);
  
  if ($user) {
    $webfinger = $user->user_login."@".parse_url(get_bloginfo('url'), PHP_URL_HOST);
    if ($protocol) {
      $webfinger = "acct:".$webfinger;
    }
    return $webfinger;
  } else {
    return null;
  }
}

/**
 * returns all webfingers
 *
 * @param mixed $id_or_name_or_object
 * @param boolean $protocol
 * 
 * @return array
 */
function get_webfingers($id_or_name_or_object = null, $protocol = false) {
  $webfinger = new WebfingerPlugin();
  $user = $webfinger->get_user_by_various($id_or_name_or_object);
  $webfingers = array();
  
  if ($user) {
    $webfingers[] = ($protocol ? "acct:" : "").get_webfinger($user);
    $webfingers[] = get_author_posts_url($user->ID, $user->user_nicename);
    $webfingers = apply_filters('webfingers', $webfingers);

    return $webfingers;
  } else {
    return array();
  }
}

/**
 * webfinger
 *
 * @author Matthias Pfefferle
 */
class WebfingerPlugin {
  
  private $user;
  private $webfinger_uri;
  
  
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
    $vars[] = 'principal';
    $vars[] = 'service';
    return $vars;
  }
  
  /**
   * parses the request
   *
   * @return mixed
   */
  public function parse_request() {
    global $wp_query, $wp;
    
    $query_vars = $wp->query_vars;

    if( array_key_exists('webfinger-uri', $query_vars) ) {
      if (!$this->user = $this->get_user_by_uri($query_vars['webfinger-uri'])) {
        header("HTTP/1.0 404 Not Found");
        echo "Not Found";
        exit;
      }
      
      $this->webfinger_uri = $query_vars['webfinger-uri'];
      $accept = explode(',', $_SERVER['HTTP_ACCEPT']);
      
      if (in_array('application/json', $accept) || (array_key_exists('format', $query_vars) && $query_vars['format'] == "json")) {
        $this->render_jrd();
      } else {
        $this->render_xrd();
      }
      
      exit;
    }
  }
  
  public function render_simple_web_discovery($request) {
    $this->user = $this->get_user_by_uri($request['principal']);
    $this->webfinger_uri = $request['principal'];
    
    if ($this->user) {
      $content = $this->generate_content();
      
      $swd = array();
      foreach ($content['links'] as $link) {
        if ($link['rel'] == $request['service']) {
          $swd['locations'][] = $link['href'];
        } 
      }
    
      $swd = apply_filters('simple_web_discovery', $swd, $this->user, $request['service']);
      
      if ($swd) {
        header("Access-Control-Allow-Origin: *");
        header('Content-Type: application/json; charset=' . get_option('blog_charset'), true);
        echo json_encode($swd);
        exit;
      }
    }
    
    header("HTTP/1.0 404 Not Found");
    echo "Not Found";
    exit;
  }
  
  /**
   * renders the webfinger file in xml
   */
  public function render_xrd() {
    header("Access-Control-Allow-Origin: *");
    header('Content-Type: application/xrd+xml; charset=' . get_option('blog_charset'), true);
    $content_array = $this->generate_content();
    
    echo "<?xml version='1.0' encoding='".get_option('blog_charset')."'?>\n";
    echo "<XRD xmlns='http://docs.oasis-open.org/ns/xri/xrd-1.0'\n";
      // add xml-only namespaces
      do_action('webfinger_ns');
    echo ">\n";

    echo $this->jrd_to_xrd($content_array);
      // add xml-only content
      do_action('webfinger_xrd', $this->user);
    
    echo "\n</XRD>";
  }
  
  /**
   * renders the webfinger file in json
   */
  public function render_jrd() {
    header("Access-Control-Allow-Origin: *");
    header('Content-Type: application/json; charset=' . get_option('blog_charset'), true);
    $webfinger = $this->generate_content();

    echo json_encode($webfinger);
  }
  
  /**
   * generates the webfinger base array (and activate filter)
   *
   * @return array
   */
  public function generate_content() {
    $url = get_author_posts_url($this->user->ID, $this->user->user_nicename);
    $photo = get_user_meta($this->user->ID, 'photo', true);
    if(!$photo) $photo = 'http://www.gravatar.com/avatar/'.md5($this->user->user_email);
    
    $webfinger = array('subject' => $this->webfinger_uri,
                       'aliases' => array($url),
                       'links' => array(
                         array('rel' => 'http://webfinger.net/rel/profile-page', 'type' => 'text/html', 'href' => $url),
                         array('rel' => 'http://webfinger.net/rel/avatar',  'href' => $photo)
                       ));
    if ($this->user->user_url) {
      $webfinger['links'][] = array('rel' => 'http://webfinger.net/rel/profile-page', 'type' => 'text/html', 'href' => $this->user->user_url);
    }
    $webfinger = apply_filters('webfinger', $webfinger, $this->user);
    
    return $webfinger;
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
        $sql =  "SELECT * FROM $wpdb->users u INNER JOIN $wpdb->usermeta um ON u.id=um.user_id WHERE u.user_email = '$email' OR 
                                                  (um.meta_key = 'jabber' AND um.meta_value = '$email') OR
                                                  u.user_login = '$username' LIMIT 1;";
        $user = $wpdb->get_results($wpdb->prepare($sql));
        if (!empty($user)) {
          return $user[0];
        }
      }
    }
    
    return false;
  }
  
  /**
   * add the host meta information
   */
  public function add_host_meta_link() {     
    echo "<Link rel='lrdd' template='".get_option('siteurl')."/?webfinger-uri={uri}' type='application/xrd+xml' />";
  }

  /**
   * add the host meta information
   */
  public function add_host_meta_info($array) {
    $array["links"][] = array("rel" => "lrdd", "template" => get_option('siteurl')."/?webfinger-uri={uri}", "type" => "application/xrd+xml");
    $array["links"][] = array("rel" => "lrdd", "template" => get_option('siteurl')."/?webfinger-uri={uri}?format=json", "type" => "application/json");

    return $array;
  }
  
  /**
   * adds lrdd-header-link to author profile pages
   */
  public function add_html_link() {
    global $wp_query;
    
    if (is_author() && !is_feed()) {
      $author = $wp_query->get_queried_object();
      $url = get_author_posts_url($author->ID, $author->user_nicename);
      
      echo "\n";
      echo "<link rel='lrdd' href='".get_option('siteurl')."/?webfinger-uri=".urlencode($url)."' type='application/xrd+xml' />";
      echo "\n";
    }
  }
  
  /**
   * prints webfinger part on the user-site
   *
   * @param stdClass $user
   */
  public function user_profile_infos($user) {
    $webfingers = get_webfingers($user, true);
?>
    <h3 id="webfinger">Webfinger</h3>
    
    <table class="form-table">
      <tr>
        <th scope="row"><?php _e('Your Identifier')?></th>
        <td>
          <ul>
            <?php foreach ($webfingers as $webfinger) {  ?>
              <li><?php echo $webfinger ?></li>
            <?php } ?>
          </ul>
          
          <a href="http://webfinger.org/lookup/<?php echo get_webfinger($user); ?>" target="_blank">Check your webfinger!</a>
        </td>
      </tr>
    </table>
<?php
  }
  
  /**
   * add a settings link next to deactive / edit
   *
   * @param array $links
   * @param string $file
   * @return array
   */
  public function settings_link( $links, $file ) {
    if( preg_match("/webfinger/i", $file) && function_exists( "admin_url" ) ) {
      $settings_link = '<a href="' . admin_url( 'profile.php#webfinger' ) . '">' . __('Identifiers') . '</a>';
      array_unshift( $links, $settings_link );
    }
    return $links;
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
}

function webfinger_init() {
  $webfinger = new WebfingerPlugin();

  add_action('well_known_simple-web-discovery', array(&$webfinger, 'render_simple_web_discovery'), 2);
  add_filter('query_vars', array(&$webfinger, 'query_vars'));
  add_action('parse_request', array(&$webfinger, 'parse_request'));
  add_action('wp_head', array(&$webfinger, 'add_html_link'));
  add_filter('host_meta', array(&$webfinger, 'add_host_meta_info'));
  add_filter('plugin_action_links', array(&$webfinger, 'settings_link'), 10, 2);

  // add profile parts
  add_action('show_user_profile', array(&$webfinger, 'user_profile_infos'));
  add_action('edit_user_profile', array(&$webfinger, 'user_profile_infos'));
}

webfinger_init();
?>