<?php

// Include StanfordPerson
require_once(dirname(__FILE__) . "/stanford.person.php");

/**
  * A class used for interacting with the directory at Stanford
  *
  * @author ddonahue
  *
  * @date July 23, 2008
  * 
  * Copyright 2008,2009 Board of Trustees, Leland Stanford Jr. University
  * See LICENSE for licensing terms.
  *
  */

class StanfordDirectory {
  
  const VERSION = '1.0.0';
  
  const LDAP_SERVER = 'ldap.stanford.edu';

  public $ldap;     // LDAP connection handle
  public $error;    // Error message
  
  
  /**
    * Creates a new StanfordDirectory object
    */
   
  function __construct() {
    
    $this->ldap = 0;    // Connect only when necessary
    $this->error = '';

  }
  
  
  /**
    * Deconstructor calls disconnect
    */
   
  function __destruct() {
    
    $this->disconnect();

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
   * Connects and binds to the LDAP server
   *
   * @throws An Exception when ldap_connect, ldap_bind, or ldap_set_option fail
   *
   * @return void
   */
   
  function connect_and_bind() {
    
    // Check if already connected
    if($this->is_connected()) return;
    
    // Connect to the LDAP server
    $this->ldap = ldap_connect(StanfordDirectory::LDAP_SERVER);
    
    if ($this->ldap == false) {
      $this->error = 'Unable to connect to the directory';
      throw new Exception('Unable to connect to the directory');
      return;
    }
   
    // Bind
    if(ldap_bind($this->ldap) == false) {
      $this->error = 'Unable to bind to the directory';
      throw new Exception('Unable to bind to the directory');
      return;
    }
   
    // Set protocol version
    if(ldap_set_option($this->ldap,LDAP_OPT_PROTOCOL_VERSION,3) == false) {
      $this->error = 'Unable to set LDAP protocol version to 3';
      throw new Exception('Unable to set LDAP protocol version');
      return;
    }
   
    // Problematic...
    // SASL bind
    //if(ldap_sasl_bind($this->ldap,"","","GSSAPI") == false) {
    //  $error = 'Unable to perform SASL bind';
    //  throw new Exception('Unable to perform SASL bind');
    //  return;
    //}
  }
  
  
  /**
    * Closes the LDAP connection 
    *
    */
  
  function disconnect() {
    
    // Check ldap handle
    if($this->ldap) {
      
      // Close the connection
      ldap_close($this->ldap);
      
      // Reset the ldap variable
      $this->ldap = 0;
      
    }
  }
  
  
  /**
    * Queries the directory for a single attribute for a particular SUNetID 
    *
    * @param sunetid string SUNetID of the user to query
    * @param attr string name of attribute to return
    *
    * @return string value of the queried attribute
    */
    
  function get_attr($sunetid, $attr) {
    $result = $this->get_user_info($sunetid, array($attr));
        
    if(sizeof($result)) {
      return $result[$attr][0];
    }
    else {
      return false;
    }
  }
  
  
  /**
    * Gets information about a user and returns it as a StanfordPerson
    *
    * @param sunetid string SUNetID of the user to query
    *
    * @return StanfordPerson  A StanfordPerson
    */
    
  function get_person($sunetid) {
    
    // Connect if necessary
    if($this->is_connected() == false) $this->connect_and_bind();
    
    // Check SUNetID
    if($sunetid == '') return false;
    
    // Return result
    $person = new StanfordPerson($sunetid, $this);
    
    // Force a directory query (to ensure that it's a valid SUNetID)
    $person->fetch_info();
    
    // Check result
    if($person->get_search_status() == true) {
      return $person;
    }
    else {
      return false;
    }
  }
  
  
  /**
    * Returns all or limited information about a particular user given a SUNetID and an optional list of attributes 
    *
    * @param sunetid string SUNetID of the user to query
    * @param attributes array list of attributes to return
    *
    * @return array The dataset associated with the queried SUNetID
    */
    
  function get_user_info($sunetid, $attributes=0) {
    // Connect if necessary
    if($this->is_connected() == false) $this->connect_and_bind();
    
    $result = $this->search("uid=$sunetid", $attributes);
    
    if($result) {
      return $result[0];
    }
    else {
      return array();
    }
  }
  
  
  /**
   * Checks if there is a connection to the directory
   *
   * @return boolean true if connected to LDAP server, false otherwise 
   *
   */
  
  function is_connected() {
    if($this->ldap) {
      return true;
    }
    else {
      return false;
    }
  }
  
  
  /**
    * Searches the directory given a filter and optional list of fields 
    *
    * @param filter string LDAP search filter
    * @param attributes array list of attributes to return
    *
    * @throws An Exception when unable to connect and bind to LDAP server or if search filter is null
    *
    * @return array   The data returned from the search
    */
    
  function search($filter, $attributes=0) {
    // Connect if necessary
    $this->connect_and_bind();
   
    // Check arguments
    if(!$this->is_connected()) {
      throw new Exception("Unable to connect and bind to LDAP server");
    }
   
    // Check filter
    if(!$filter) {
      throw new Exception("Search filter is empty");
    }
   
    // Configure search parameters
    $dn = "cn=people,dc=stanford,dc=edu";
    if(!$attributes) $attributes = array();
   
    // Search
    $sr=@ldap_search($this->ldap, $dn, $filter, $attributes);
   
    // Check search result
    if($sr == FALSE) {
      throw new Exception("ldap_search returned false, bad search filter");
    }
   
    // Number of entries returned from the search
    $num_entries = ldap_count_entries($this->ldap, $sr);
   
    if($num_entries > 0) {
      return ldap_get_entries($this->ldap, $sr);
    }
    else {
      return array();
    }
  }
};
 
?>