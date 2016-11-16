<?php

/**
  * The class used for user- and group-based authorization
  * 
  * @author ddonahue
  *
  * @date September 12, 2008
  * 
  * Copyright 2008,2009 Board of Trustees, Leland Stanford Jr. University
  * See LICENSE for licensing terms.
  *
  */

class StanfordAuthorization {
  
  const VERSION = '1.0.1';
  
  const USER = 'user';
  const GROUP = 'group';
  
  const PERMIT = true;
  const DENY = false;
  
  public $acl;                // The ACL (list containing the names of users and/or groups and whether to permit/deny access)
  public $current_user;       // Currently logged-in user
  public $requires_webauth;   // True if WebAuth must be enabled, false otherwise
  
  /**
    * Creates a new StanfordAuth object
    */
    
  function __construct() {
    
    // Initialize ACL
    $this->acl = array();
    
    // Get current user
    $this->current_user = $_ENV['REMOTE_USER'];
    
    // Set requires_webauth
    $this->requires_webauth = false;
    
  }
  
  
  /**
    * Gets the version number of the class
    *
    * @return string  The version number
    */
    
  function get_version() {
    return self::VERSION;
  }
  
  
  /**
    * Helper function to edit the ACL.
    *
    * @param array list     List of user or group names
    * @param string type    Type of list - 'user' or 'group'
    * @param boolean allow  True if permit, false if deny
    */
  
  function add_to_acl($list, $type, $allow) {
    foreach($list as $name) {
      array_push($this->acl, array('name' => $name, 'type' => $type, 'allow' => $allow) );
    }
  }
  
  
  /**
    * Adds one or more SUNetIDs to the list of authorized users
    *
    * @param mixed users  A single SUNetID (string) or list of SUNetIDs (array of strings)
    */
    
  function require_user($users) {
    if(is_string($users)) $users = array($users);
    
    $this->add_to_acl($users, self::USER, self::PERMIT);
  }
  
  
  /**
    * Pseudonym for require_user
    */
    
  function require_users($users) {
    return $this->require_user($users);
  }
  
  
  /**
    * Adds one or more group names to the list of authorized groups
    *
    * @param mixed groups  A single group name (string) or list of groups (array of strings)
    */
    
  function require_group($groups) {
    if(is_string($groups)) $groups = array($groups);
    
    $this->add_to_acl($groups, self::GROUP, self::PERMIT);
  }
  
  
  /**
    * Pseudonym for require_group
    */
    
  function require_groups($groups) {
    return $this->require_group($groups);
  }
  
  
  /**
    * Adds one or more SUNetIDs to the list of restricted users
    *
    * @param mixed users  A single SUNetID (string) or list of SUNetIDs (array of strings)
    */

  function deny_user($users) {
    if(is_string($users)) $users = array($users);
    
    $this->add_to_acl($users, self::USER, self::DENY);
  }
  
  
  /**
    * Pseudonym for deny_user
    */
    
  function deny_users($users) {
    return $this->deny_user($users);
  }
  
  
  /**
    * Adds one or more group names to the list of restricted groups
    *
    * @param mixed groups  A single group name (string) or list of groups (array of strings)
    */
    
  function deny_group($groups) {
    if(is_string($groups)) $groups = array($groups);
    
    $this->add_to_acl($groups, self::GROUP, self::DENY);
  }
  
  
  /**
    * Pseudonym for deny_group
    */
    
  function deny_groups($groups) {
    return $this->deny_group($groups);
  }
  
  
  /**
    * Does this ACL permit the currently logged in user?
    *
    * @return boolean True if permit, false if deny
    */
    
  function permit_current_user() {
    return $this->user_is_authorized($this->current_user);
  }
  
  
  /**
    * Determines if a user should be granted access to the resource
    *
    * @param string sunetid   A user's SUNetID
    *
    * @return boolean  True if authorized, false if denied
    */
  
  function user_is_authorized($sunetid) {
    // No sunetid => not authed
    if( $sunetid == '' ) return false;
    
    // No ACL => authed
    if(!$this->acl || sizeof($this->acl) == 0) return true;
    
    // Defaults
    $action = false;    // The final action to take -- permit (true) or deny (false)
    $matched = false;   // Set to true if the ACL contains an explicit match on the given SUNetID
    $allowed = false;   // Set to true if the ACL contains one or more 'allow' entries
    $denied = false;    // Set to true if the ACL contains one or more 'deny' entries
    
    // Go through ACL entries
    foreach($this->acl as $entry) {
      
      // Permit
      if($entry['allow'] == true) {
        $allowed = true;          // ACL contains one or more 'allows'
        $current_action = true;   // If there is a match, allow the user
      }
      
      // Deny
      else {
        $denied = true;           // ACL contains one or more 'denies'
        $current_action = false;  // If there is a match, deny the user
      }
      
      // Check for match
      if($this->user_matches_acl_entry($sunetid, $entry)) {
        $matched = true;
        $action = $current_action;
      }
    }
    
    // If there was no explicit match in the whole ACL, determine default action to take
    if($matched == false) {
      
      // If there were only 'allow' entries and none for 'deny' then deny
      if($allowed && !$denied) {
        $action = false;
      }
      
      // If there were only 'deny' entries and none for 'allow' then allow
      else if($denied && !$allowed) {
        $action = true;
      }
      
      // If there were both allow and deny entries then deny
      else {
        $action = false;
      }
    }
    
    return $action;
  }
  
  
  /**
    * Private method for determining a match in the ACL
    *
    * @param string sunetid   The current user's SUNetID
    * @param array entry      The ACL entry
    *
    * @return true on match, false otherwise
    */
    
  private function user_matches_acl_entry($sunetid, $entry) {
    if($entry['type'] == 'user') {
      if($entry['name'] == $sunetid) {
        return true;
      }
    }
    else if($entry['type'] == 'group') {
      // TODO
    }
    
    return false;
  }
  
  
  /**
    * Gets the value of the flag 'requires_webauth'
    *
    * @return boolean   True if Webauth is required, false if otherwise
    */
    
  function requires_webauth() {
    return $this->requires_webauth;
  }
  
  
  /**
    * Throws an exception if the script is not protected by Webauth
    */
  
  function require_webauth() {
    
    $this->requires_webauth = true;
    
    if($this->is_behind_webauth() == false) {
      throw new Exception("Not behind Webauth");
    }
    
  }
  
  
  /**
    * Checks if the app is behind Webauth
    *
    * @return boolean  True if behind Webauth, false otherwise
    */
    
  function is_behind_webauth() {
    
    // If the WEBAUTH_USER environment variable is set, the script is behind Webauth
    // This variable is automatically set by the WebauthLDAP Apache module
    // It contains the SUNetID of the currently logged-in user
    
    if(isset($_ENV['WEBAUTH_USER']) == true) {
      return true;
    }
    else {
      return false;
    }
  }
  
  
  /**
    * Logs a user out of Webauth on the current subdomain ([subdomain].stanford.edu) - must be called before page output
    */
    
  function force_webauth_logout() {
    
    // Set webauth_at cookie to null, expiration one hour ago
    setcookie ("webauth_at", "", time() - 3600, "/");
    
    // Forward to logout page
    header("Location: https://weblogin.stanford.edu/logout");
    
  }
}
?>
