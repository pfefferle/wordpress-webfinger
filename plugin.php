<?php
/*
Plugin Name: Webfinger
Plugin URI: http://wordpress.org/extend/plugins/webfinger/
Description: Webfinger for WordPress
Version: 0.9
Author: Matthias Pfefferle
Author URI: http://notizblog.org/
*/

add_filter('query_vars', array('WebfingerPlugin', 'queryVars'));
add_action('parse_request', array('WebfingerPlugin', 'parseRequest'));
add_action('wp_head', array('WebfingerPlugin', 'addHtmlHeader'));
add_action('host_meta_xrd', array('WebfingerPlugin', 'addHostMeta'));
add_filter('plugin_action_links', array('WebfingerPlugin', 'addSettingsLink'), 10, 2);

// add profile parts
add_action('show_user_profile', array('WebfingerPlugin', 'addUserProfileInfos'));
add_action('edit_user_profile', array('WebfingerPlugin', 'addUserProfileInfos'));

/**
 * returns a users default webfinger
 * 
 * @param mixed $id_or_name_or_object
 * @param boolean $protocol
 * @return string
 */
function get_webfinger($id_or_name_or_object = null, $protocol = false) {
  $user = WebfingerPlugin::getUserByVarious($id_or_name_or_object);
  
  if ($user) {
    $webfinger = $user->user_login."@".WebfingerPlugin::getHost();
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
  $user = WebfingerPlugin::getUserByVarious($id_or_name_or_object);
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
  /**
   * adds some query vars
   *
   * @param array $vars
   * @return array
   */
  function queryVars($vars) {
    $vars[] = 'webfinger-uri';
    //$vars[] = 'webfinger';
    return $vars;
  }
  
  /**
   * parses the request
   */
  function parseRequest() {
    global $wp_query, $wp;
    
    $queryVars = $wp->query_vars;
    
    if( array_key_exists('webfinger-uri', $queryVars) ) {
      if (!$user = WebfingerPlugin::getUserByUri($queryVars['webfinger-uri'])) {
        return null;
      }
      
      $url = get_author_posts_url($user->ID, $user->user_nicename);
      
      header('Content-Type: application/xrd+xml; charset=' . get_option('blog_charset'), true);

      echo "<?xml version='1.0' encoding='".get_option('blog_charset')."'?>\n";
      echo "<XRD xmlns='http://docs.oasis-open.org/ns/xri/xrd-1.0'";
        do_action('webfinger_ns');
      echo ">\n";      
      echo "  <Subject>".htmlentities($queryVars['webfinger-uri'])."</Subject>\n";
      echo "  <Alias>".$url."</Alias>\n";
      echo "  <Link rel='http://webfinger.net/rel/profile-page' type='text/html' href='$url' />\n";
      echo "  <Link rel='http://webfinger.net/rel/avatar' href='http://www.gravatar.com/avatar/".md5( $user->user_email ).".jpg' />\n";
        do_action('webfinger_xrd', $user);
        // @deprecated please don't use
        do_action('webfinger_xrd_'.$user->user_login);
      echo "</XRD>";
      
      exit;
    } else {
      return null;
    }
  }
  
  /**
   * returns a Userobject 
   *
   * @param string $uri
   * @return stdClass
   */
  function getUserByUri($uri) {
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
      
      if ($host == WebfingerPlugin::getHost()) {
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
  function addHostMeta() {     
    echo "<Link rel='lrdd' template='".get_option('siteurl')."/?webfinger-uri={uri}' type='application/xrd+xml' />";
  }
  
  /**
   * adds lrdd-header-link to author profile pages
   */
  function addHtmlHeader() {
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
   */
  function addUserProfileInfos($user) {
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
   * returns the blogs host
   * 
   * @return string
   */
  function getHost() {
    $url = parse_url(get_bloginfo('url'));
    return $url['host'];
  }
  
  /**
   * add a settings link next to deactive / edit
   *
   * @param array $links
   * @param string $file
   * @return array
   */
  function addSettingsLink( $links, $file ) {
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
  function getUserByVarious($id_or_name_or_object = null) {
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
?>