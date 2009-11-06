<?php

// Constants
if(defined("YAML_PARSER") == false) {
  define("YAML_PARSER", dirname(__FILE__) . "/lib/spyc-0.2.5/spyc.php5");
}

// Include SWAT modules
require_once(dirname(__FILE__) . "/stanford.authorization.php"); // StanfordAuthorization class
require_once(dirname(__FILE__) . "/stanford.database.php");      // StanfordDatabase class
require_once(dirname(__FILE__) . "/stanford.person.php");        // StanfordPerson class
require_once(dirname(__FILE__) . "/stanford.util.php");          // StanfordUtil class


/**
 * Stanford Web Application Toolkit
 *
 * A framework designed to aid Stanford developers in creating robust applications by automating common tasks.
 * 
 * @date November 20, 2008
 * 
 * Copyright 2008,2009 Board of Trustees, Leland Stanford Jr. University
 * See LICENSE for licensing terms.
 *
 */


class StanfordApp {
  
  // Version
  const VERSION = '1.0.0';
  
  // Basic info
  public $name;               // Name of the app
  public $base_url;           // The base URL (e.g. http://www.stanford.edu/)
  public $base_dir;           // The base directory (e.g. /afs/ir/my_site/)
  public $admin_email;        // E-mail address of admin
  public $admin_name;         // Name of admin
  
  // Database
  public $db;                 // StanfordDatabase connection
  
  // Modules
  public $auth;               // StanfordAuthorization
  public $util;               // StanfordUtil tools
  public $logger;             // StanfordLog logger (assigned by set_logger)
  
  // Webauth
  public $visitor;            // The currently logged in Webauth user (StanfordPerson)
  
  
  /**
   * Given an optional YAML configuration file, creates a new StanfordApp
   * 
   * @param path config_file   Path to YAML configuration file
   *
   */

  function __construct($config_file='') {

    // If behind webauth, get currently logged-in user's information from WebauthLDAP module
    if( isset($_ENV['WEBAUTH_USER']) ) {
      $this->visitor = new StanfordPerson();
      $this->visitor->set_sunetid($_ENV['WEBAUTH_USER']);
      $this->visitor->set_full_name($_ENV['WEBAUTH_LDAP_DISPLAYNAME']);
      $this->visitor->set_email($_ENV['WEBAUTH_LDAP_MAIL']);
    }
    
    // Initialize Stanford objects
    $this->auth = new StanfordAuthorization();
    $this->util = new StanfordUtil();
    
    // Load options from config file if available
    if($config_file != '') {
      $this->load_config($config_file);
    }
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
    * Parses and loads settings from config file
    *
    * @param string path  The path to the file
    */
    
  function load_config($path) {
    
    // Check path
    if(file_exists($path) == false) {
      throw new Exception("Unable to load configuration -- file not found: $path");
    }
    
    // Check file
    if(is_file($path) == false) {
      throw new Exception("Unable to load configuration -- not a file: $path");
    }
    
    // Check permissions
    if(is_readable($path) == false) {
      throw new Exception("Unable to load configuration -- access denied: $path");
    }
    
    // Check size    
    if(filesize($path) == 0) {
      throw new Exception("Unable to load configuration -- file is empty: $path");
    }
    
    // Load parser
    require_once(YAML_PARSER);
    
    // Load config
    $config = Spyc::YAMLLoad($path);
    
    // Check result - if empty, throw an exception
    if(sizeof($config) == 0) {
      throw new Exception("Unable to load configuration -- parse error: $path");
    }
    
    // General application options
    if(isset($config['info']['name']))           { $this->set_name($config['info']['name']); }
    if(isset($config['info']['admin email']))    { $this->set_admin_email($config['info']['admin email']); }
    if(isset($config['info']['admin name']))     { $this->set_admin_name($config['info']['admin name']); }
    if(isset($config['info']['base url']))       { $this->set_base_url($config['info']['base url']); }
    if(isset($config['info']['base directory'])) { $this->set_base_dir($config['info']['base directory']); }
    
    // Database
    if(isset($config['database']['name'])) { 
      
      // Initialize db object
      $this->db     = new StanfordDatabase();
      
      // Credentials
      if(isset($config['database']['name']))     { $this->db->set_database($config['database']['name']); }
      if(isset($config['database']['username'])) { $this->db->set_username($config['database']['username']); }
      if(isset($config['database']['password'])) { $this->db->set_password($config['database']['password']); }
      
      // Encryption
      if(isset($config['database']['use encryption'])) { $this->db->use_encryption($config['database']['use encryption']); }
      else { $this->db->use_encryption(false); }

    }
    
    // Use mysql sessions set to true
    if(isset($config['database']['use mysql sessions']) && $config['database']['use mysql sessions'] == true ) {
      
      // Check DB
      if($this->db instanceof StanfordDatabase) {
        
        // Enable MySQL sessions
        $this->db->setup_mysql_sessions();
        
      }
      else {
        
        // DB not configured
        throw new Exception("Cannot enable MySQL-based sessions -- database credentials not provided");  
        
      }
    }
          
    // Logging
    if(isset($config['settings']['logging mode'])) {
      $this->util->set_error_reporting($config['settings']['logging mode']);
    }
    
    // Undo magic quotes
    if(isset($config['settings']['undo magic quotes']) && $config['settings']['undo magic quotes'] == true) {
      $this->util->undo_magic_quotes();
    }
  }
  
  
  /**
    * Logs a message
    *
    * @param string message The message to log
    *
    * @return boolean True on success, false on failure
    */
    
  function log($message) {
    
    // Check the logger
    if($this->logger instanceof StanfordLog) {
      
      // Log the message
      return $this->logger->append($message);
      
    }
    else {
      
      // Throw an exception
      throw new Exception("Cannot call log() without previously invoking set_logger()");
    }
  }
  
  
  /**
    * Wrapper function for StanfordAuthorization
    */
  
  function is_behind_webauth() {
    return $this->auth->is_behind_webauth();
  }
  
  
  /**
    * Wrapper function for StanfordAuthorization
    */
  
  function require_webauth() {
    return $this->auth->require_webauth();
  }
  
  
  /**
    * Wrapper function for StanfordAuthorization
    */
    
  function requires_webauth() {
    return $this->auth->requires_webauth();
  }
  
  
  /**
    * Gets the name of the administrator
    *
    * @return string  The administrator's name
    */
    
  function get_admin_name() {
    return $this->admin_name;
  }
  
  
  /**
    * Gets the e-mail address of the administrator
    *
    * @return string  The administrator's e-mail address
    */
    
  function get_admin_email() {
    return $this->admin_email;
  }
  
  
  /**
    * Gets the base directory of the application
    *
    * @return string  The application's base directory
    */
    
  function get_base_dir() {
    return $this->base_dir;
  }
    
  
  /**
    * Gets the base URL of the application
    *
    * @return string  The application's base URL
    */
    
  function get_base_url() {
    return $this->base_url;
  }
  
  
  /**
    * Gets the logger for this application (set by set_logger)
    *
    * @return StanfordLog The logger
    */
    
  function get_logger() {
    return $this->logger;
  }
  
  
  /**
    * Gets the application's name
    *
    * @return string  The name of the application
    */
    
  function get_name() {
    return $this->name;
  }
  
  
  /**
    * Gets a StanfordPerson representing the currently logged-in user  
    *
    * @return StanfordPerson  The currently logged-in StanfordPerson if behind Webauth, null otherwise
    */
    
  function get_visitor() {
    
    // Return currently logged in user
    return $this->visitor;
    
  }
  
  
  /**
    * Given an optional path to a file, returns a path
    *
    * @param string path  A relative path to a directory or file.  Optional.  (e.g. if base_dir is /afs/ir/my_site/, calling $app->dir("file.txt") yields /afs/ir/my_site/file.txt )
    *
    * @return string  The concatenation of the application's base directory and the specified path
    */
      
  function path($path='') {
    return $this->base_dir . $path;
  }
  
  
  /**
    * Given an optional relative path to a webpage, returns an absolute URL
    *
    * @param string page  Relative path of the webpage to link to.  Optional.  (e.g. if base_url is www.stanford.edu, calling $app->url('page.php') yields www.stanford.edu/page.php )
    *
    * @return url  The concatenation of the application's base URL and the specified page (absolute URL)
    */
      
  function url($page='') {
    return $this->base_url . $page;
  }
    
  
  /**
    * Sets the administrator's e-mail address
    *
    * @param string email  The administrator's e-mail address
    */
  
  function set_admin_email($email) {
    $this->admin_email = $email;
  }
  
  
  /**
    * Sets the administrator's name
    *
    * @param string name  The administrator's name
    */
  
  function set_admin_name($name) {
    $this->admin_name = $name;
  }
  
  
  /**
    * Sets the base directory of the application
    *
    * @param string dir  The base directory (with or without trailing slash - e.g. /afs/ir/path_to_my_app/ )
    */
  
  function set_base_dir($dir) {
    if($dir[strlen($dir)-1] != '/') {
      $dir .= '/'; 
    }
    
    $this->base_dir = $dir;
  }
  
  
  /**
    * Sets the base URL of the application
    *
    * @param string url  The base URL (with or without trailing slash - e.g. http://www.stanford.edu/group/my_group/)
    */
    
  function set_base_url($url) {
    if($url[strlen($url)-1] != '/') {
      $url .= '/'; 
    }
    
    $this->base_url = $url;
  }
  
  
  /**
    * Sets the database connection
    *
    * @param StanfordDatabase db  The database connection
    */
    
  function set_db($db) {
    $this->db = $db;
  }
  
  
  /**
    * Sets the logger to use
    *
    * @param StanfordLog logger  A logging module that inherits from StanfordLog
    *
    * @return boolean True on success, false on failure
    */
    
  function set_logger($logger) {
    
    // Logger must inherit from StanfordLog
    if($logger instanceof StanfordLog) {
      
      // Set the logger
      $this->logger = $logger;
      return true;
    }
    else {
      throw new Exception("The parameter for set_logger must derive from StanfordLog");
    }
  }
    
  
  /**
    * Sets the name of the application
    *
    * @param string name  The name of the application
    */
    
  function set_name($name) {  
    $this->name = $name;
  }
  
};

?>
