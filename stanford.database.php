<?php

/**
  * StanfordDatabase is an extension of MySQLi intended to assist developers in creating secure database-enabled applications
  * 
  * Copyright 2008,2009 Board of Trustees, Leland Stanford Jr. University
  * See LICENSE for licensing terms.
  *
  */
  
class StanfordDatabase extends MySQLi {
  
  const VERSION = "1.0.0";
  
  const HOST_STANDARD  = "mysql-user.stanford.edu";
  const HOST_ENCRYPTED = "127.0.0.1";
    
  private $username;        // Username
  private $password;        // Password
  private $database;        // Name of database
  private $host;            // Host
  
  private $connected;       // Connected to database?
  
  private $mysql_sessions;  // MySQL-based sessions enabled?
  
  
  /**
    * Create a new StanfordDatabase object and initialize mysqli
    *
    * @param string username         The username used to connect to the database
    * @param string password         The password used to connect to the database
    * @param string database         The name of the database
    * @param boolean use_encryption  Use encryption?  True or false.  Default is false.
    */
    
  function __construct($username='', $password='', $database='', $use_encryption=false) {
       
    $this->username = $username;
    $this->password = $password;
    $this->database = $database;
    
    // Connect using a different host address depending on the need for full encryption
    if($use_encryption == true) {
      $this->host = StanfordDatabase::HOST_ENCRYPTED;
    }
    else {
      $this->host = StanfordDatabase::HOST_STANDARD;
    }
    
    // Important - initialize the mysqli object
    $link = parent::init();
  }
  
  
  /**
    * Destructor closes the database connection
    */
    
  function __destruct() {
    $this->close();
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
    * Sets the name of the database
    *
    * @param string database  The name of the database
    */
 
  function set_database($database) {
    $this->database = $database;
  }
  
  
  /**
    * Sets the username used to connect to the database
    *
    * @param string username  The username
    */
 
  function set_username($username) {
    $this->username = $username;
  }
  
  
  /**
    * Sets the password used to connect to the database
    *
    * @param string password  The password
    */
 
  function set_password($password) {
    $this->password = $password;
  }
  
  
  /**
    * Sets the host to connect to depending on whether or not to use encryption
    *
    * @param boolean value  Should be set to true if using encryption, false to disable encryption.
    */
 
  function use_encryption($value=true) {
    
    // Must call this function before connecting
    if($this->is_connected() == true) {
      throw new Exception("StanfordDatabase error -- call use_encryption() before connect()");
    }
    
    if($value == true) {
      $this->host = StanfordDatabase::HOST_ENCRYPTED;
    }
    else {
      $this->host = StanfordDatabase::HOST_STANDARD;
    }
  }
  
  
  /**
    * Connects to the database using the given credentials.
    */
 
  function connect() {
    
    // Check if already connected
    if($this->is_connected() == true) return true;
    
    // Check for necessary information
    if($this->host == '' || $this->database == '' || $this->username == '') {
      throw new Exception("Cannot connect to the database -- missing required information");
    }
    
    // Try to connect
    $result = @parent::real_connect($this->host, $this->username, $this->password, $this->database);
    
    // Check the result
    if($result == true) {
      
      // Connected successfully
      $this->connected = true;
      return true;
      
    }
    else {
      // Error
      throw new Exception("Cannot connect to the database -- " . mysqli_connect_error());
    }
  }
  
  
  /**
    * Closes the connection to the database
    */
 
  function close() {
    
    // Check if connected
    if($this->is_connected()) {
      
      // Check if disconnected successfully
      if(parent::close() == true) {
        
        // Set connected to false and return true
        $this->connected = false;        
        return true;
      }
    }
    
    // Unable to disconnect
    return false;
  }
  
  
  /**
    * Checks if MySQL is connected
    *
    * @return boolean  True if connected, false otherwise
    */
 
  function is_connected() {
    return $this->connected;
  }
  
  
  /**
    * Checks if the connection is encrypted
    *
    * @return boolean  True if encrypted, false otherwise
    */
    
  function connection_is_encrypted() {
    if($this->host == self::HOST_ENCRYPTED) {
      return true;
    }
    else {
      return false;
    }
  }
      
  
  /**
    * Sets up MySQL-based sessions.  Database settings must be configured before calling this function.
    *
    * @date December 3, 2008
    *
    * @throws An Exception when database cannot be connected to or when creating a new php_sessions table fails
    * 
    * @return boolean  True on success, exception on failure
    */
  
  function setup_mysql_sessions() {
        
    // Connect to database
    $this->connect();
    
    // Check connection
    if($this->is_connected() == false) {
      throw new Exception("Unable to set up MySQL-based sessions -- cannot connect to database.");
    }
    
    // Check if table exists
    $sql = "SHOW TABLES LIKE 'php_sessions'";
    $result = $this->query($sql);
    
    // If not..
    if(mysqli_num_rows($result) == 0) {
      
      // Create table
      $create_table = "CREATE TABLE php_sessions (
        sessionid varchar(40) BINARY NOT NULL DEFAULT '',
        expiry int(10) UNSIGNED NOT NULL DEFAULT '0',
        value text NOT NULL,
        PRIMARY KEY  (sessionid)
      ) TYPE=MyISAM COMMENT='Sessions';";
      
      $result = $this->query($create_table);
      
      if($result == false) {
        throw new Exception("Unable to set up MySQL-based sessions -- cannot create new table 'php_sessions.'");
      }
    }
    
    // The variables below are used by an external script that enables MySQL-based sessions
    // They are required so that the other script can connect to the database
    
    global $STANFORD_DB, $STANFORD_DB_USER, $STANFORD_DB_PASS, $STANFORD_DB_HOST;
    
    $STANFORD_DB = $this->database;
    $STANFORD_DB_USER = $this->username;
    $STANFORD_DB_PASS = $this->password;
    $STANFORD_DB_HOST = $this->host;
     
    // Include custom session handler functions stored on the server
    require_once '/etc/php5/init/sessions.php';
        
    // Set MySQL sessions flag to true
    $this->mysql_sessions = true;
    
    return true;
  }
  
  
  /**
    * Checks if the script is using MySQL-based sessions (established using setup_mysql_sessions)
    *
    * @return boolean True or false
    */
    
  function using_mysql_sessions() {
    
    // Return true if the mysql_sessions flag is set to true and the session.save_handler configuration directive is set to user
    return ($this->mysql_sessions == true) && (ini_get('session.save_handler') == 'user');
    
  }
    
}

?>