<?php
/*
Plugin Name: webfinger
Plugin URI: http://notizblog.org/
Description: Webfinger for WordPress
Version: 0.1
Author: Matthias Pfefferle
Author URI: http://notizblog.org/
*/

add_filter('query_vars', array('WebfingerProvider', 'queryVars'));
add_action('parse_request', array('WebfingerProvider', 'parseRequest'));
add_action('host_meta_xrd', array('WebfingerProvider', 'addHostMeta'));

/**
 * webfinger
 *
 * @author Matthias Pfefferle
 */
class WebfingerProvider {
  /**
   * adds some query vars
   *
   * @param array $vars
   * @return array
   */
  function queryVars($vars) {
    $vars[] = 'webfinger-uri';

    return $vars;
  }
  
  /**
   * 
   */
  function parseRequest() {
    global $wp_query, $wp;
    
    $queryVars = $wp->query_vars;
    
    if( array_key_exists('webfinger-uri', $queryVars) ) {
      if (!$user = WebfingerProvider::getUser($queryVars['webfinger-uri'])) {
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
        do_action('webfinger_xrd');
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
  function getUser($uri) {
  	global $wpdb;
	
	// get user by acct
  	if (substr($uri, 0, 5) == "acct:") {
		$identifier = str_replace("acct:", "", $uri);
  		$dbfield = "user_email";
  	// get user by xmpp
  	} elseif (substr($uri, 0, 5) == "xmpp:") {
  		$identifier = str_replace("xmpp://", "", $uri);
  		$identifier = str_replace("xmpp:", "", $uri);
  		$sql = "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'jabber' AND meta_value = '$identifier'";
		$identifier = $wpdb->get_var($wpdb->prepare($sql));
		$dbfield = "ID";
  	} else {
  		return false;	
  	}
  	
  	$identifier = $wpdb->escape($identifier);
    $sql =  "SELECT * FROM $wpdb->users WHERE $dbfield = '$identifier';";

    $user = $wpdb->get_results($wpdb->prepare($sql));
    
    return $user[0];
  }
  
  /**
   * add the host meta information
   */
  function addHostMeta() {     
    echo "<Link rel='lrdd'
              template='".get_option('siteurl')."?webfinger-uri={uri}' />";
  }
}
?>