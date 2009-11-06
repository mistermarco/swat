<?php

/**
  * @author ddonahue
  * 
  * Copyright 2008,2009 Board of Trustees, Leland Stanford Jr. University
  * See LICENSE for licensing terms.
  *
  */
  
// Include StanfordLog
require_once(dirname(__FILE__) . "/stanford.log.php");

// Include StanfordDatabase
require_once(dirname(__FILE__) . "/stanford.database.php");


class StanfordLogDatabase extends StanfordLog {
  
  const VERSION = '1.0.0';      // Class version
  
  private $db;                   // MySQLi object
  private $table;                // Name of database table
  
  // Field names
  private $fields = array("id", "log_time", "remote_addr", "script", "query_string", "message");
  
  /**
    * Creates a new StanfordLogDatabase
    *
    * @param MySQLi db            A valid MySQLi/StanfordDatabase object
    * @param string table         The name of the database table
    * @param boolean create       Create the table if it does not exist
    */
    
  function __construct($db, $table, $create=false) {

    // Set the database connection handler
    if($db) {
      $this->set_database_connection($db);
      
      // Set the database table
      if($table) {
        $this->set_table($table, $create);
      }
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
    * Sets the database connection and connects if necessary
    *
    * @param MySQLi db  A valid, preconfigured MySQLi/StanfordDatabase object
    */
    
  function set_database_connection($db) {
    
    // Check DB
    if($db instanceof MySQLi == false) {
      throw new Exception("Must set the database connection to a valid MySQLi or StanfordDatabase object");
    }
    
    // Connect to DB
    if($db instanceof StanfordDatabase) {
      $db->connect();   // Will throw an exception on failure
    }
    
    // Valid database connection
    $this->db = $db;
    return true;
    
  }
  
  
  /**
    * Sets the name of the table to use
    *
    * @param string table   The name of the table
    * @param boolean create_if_nonexistent Auto-create table if it does not exist?  Defaults to false.  Optional.
    *
    * @return boolean Status
    */
    
  function set_table($table, $create_if_nonexistent = false) {
    
    // Check table name
    if($table == '') {
      throw new Exception("Table name is null");
    }
    
    // Create table
    if($create_if_nonexistent == true) {
      
      // Check database connection
      if($this->is_valid_db() == false) {
        throw new Exception("Not connected to database; cannot check/create table.");
      }
      
      // Check if table exists
      $sql = "DESCRIBE " . $this->db->escape_string($table);
      $result = $this->db->query($sql);
      
      // Table does not exist..
      if($result == 0 || mysqli_num_rows($result) == 0) {
        
        // Construct query to create new table
        $sql = "CREATE TABLE " . $this->db->escape_string($table) . " (";
        $sql .= "id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,";
        $sql .= "log_time TIMESTAMP DEFAULT NOW() NOT NULL,";
        $sql .= "remote_addr INT UNSIGNED NOT NULL,";
        $sql .= "script VARCHAR(500) NOT NULL,";
        $sql .= "query_string VARCHAR(1000) NOT NULL,";
        $sql .= "message TEXT";
        $sql .= ");";
        
        // Execute query
        $result = $this->db->query($sql);
        
        // Check result
        if($result == false) {
          throw new Exception("Unable to create table " . $table . " -- " . $this->db->error);
        }
      }
      else {
        // Log table already exists -- not a serious error, but enough to notify the developer
        $this->table = $this->db->escape_string($table);
        throw new Exception("Notice: Log table already exists.  You may now set the create_if_nonexistent parameter to false or simply omit it.");
      }
    }
    
    // No errors occurred, set table name and return success
    $this->table = $this->db->escape_string($table);
    return true;
    
  }  
  
  
  /**
    * Appends a message to the log table
    *
    * @param string message The message to log
    *
    * @return boolean Status
    */
    
  function append($message) {
    
    // Check DB
    if($this->is_valid_db() == false) {
      throw new Exception("Cannot append to log -- not connected to database");
    }
    
    // Check table
    if($this->table == '') {
      throw new Exception("Cannot append to log -- table not set");
    }

    // Get IP address
    $remote_addr = $this->db->escape_string($_SERVER['REMOTE_ADDR']);
    
    // Get requested script
    $script = $this->db->escape_string($_SERVER['PHP_SELF']);
    
    // Get query string
    $query_string = $this->db->escape_string(urldecode($_SERVER['QUERY_STRING']));
    
    // Escape the message and table name
    $message = $this->db->escape_string($message);
    $table = $this->db->escape_string($this->table);
    
    // Create query (all input has been escaped)
    $sql = "INSERT INTO $table (remote_addr, script, query_string, message) ";
    $sql .= "VALUES (INET_ATON('$remote_addr'), '$script', '$query_string', '$message')";
        
    // Execute query    
    $result = $this->db->query($sql);
    
    // Check result
    if($result == false) {
      throw new Exception("Unable to write to database -- " . $this->db->error);
    }
    
    // If e-mail settings have been defined
    if($this->email_settings instanceof StanfordLogEmailSettings) {
      
      // Send via e-mail if it is time to do so
      $this->send_if_delay_is_up();
      
    }
    
    // Return success
    return true;
        
  }
  
  
  /**
    * Determines if it is time to send the log through e-mail
    * If true, also sets the body of the e-mail to the most recent log messages
    *
    * @return boolean True or false
    */
  
  function is_time_to_send() {
    
    // Check e-mail settings before proceeding
    if($this->email_settings instanceof StanfordLogEmailSettings == false) {
      throw new Exception("E-mail settings not defined");
    }
    
    // Check database
    if($this->is_valid_db() == false) {
      throw new Exception("Not connected to database");
    }
    
    // Get marker
    $marker = $this->db->escape_string(self::MARKER);
    
    // Construct query to find the time that the last e-mail was sent
    $sql = "SELECT * FROM $this->table WHERE message = \"$marker\" ORDER BY id DESC LIMIT 1";
    
    // Get result
    $result = $this->db->query($sql);
    
    // Check result
    if($result) {
      
      // Get data
      $data = mysqli_fetch_assoc($result);
      
      // Get the timestamp
      $time_sent = strtotime($data['log_time']);
      
      // Check if it is time to send the message
      if(time() - $time_sent >= $this->email_settings->get_delay()) {
        
        // It is time to send the message if data is available
        $time_to_send = true;
        
        // Get the ID of the last sent message
        $id = $data['id'];
        
      }
            
    }
    
    else {
      
      // There was no sent marker
      // Send everything currently in the database table
      
      $time_to_send = true;
      
    }
    
    // Time to send
    if($time_to_send == true) {
      
      // Get all log entries
      $sql = "SELECT log_time, INET_NTOA(remote_addr) AS ip, script, query_string, message FROM $this->table";
      
      // If ID is set, get all log entries after it (ID is of the last sent marker)
      if($id > 0) {
        $sql .= " WHERE id > " . $this->db->escape_string($id);
      }
      
      // Order by ID
      $sql .= " ORDER BY id";
      
      // Execute query
      $result = $this->db->query($sql);
      
      // Check result
      if($result) {
        
        // Initialize e-mail body
        $email_body = '';
        
        // Get the data and create the body of the e-mail
        while($row = mysqli_fetch_assoc($result)) {
          
          // Add log entry to message
          $email_body .= $this->array_to_csv_string($row);
          
        }
        
        // Set the body of the message
        $this->email_settings->set_body($email_body);
        
        // Return true - time to send the e-mail
        return true;
        
      }
      
    }
    
    // Not yet time to send (there is no data to send or the delay is not up)
    return false;
  }
  
  
  /**
    * Checks if it is time to send the log through e-mail
    *
    * @return boolean True when the e-mail was sent, false if not
    */
    
  function send_if_delay_is_up() {
    
    if($this->is_time_to_send() == true) {
      return $this->send_email();
    }
    else {
      return false;
    }
    
  }
  
  
  /**
    * Truncates the log to zero length
    *
    * @return boolean The result, true or false
    */
  
  function truncate() {
    
    // Check database
    if($this->is_valid_db() == false) {
      throw new Exception("Not connected to database");
    }
    
    // Check table name
    if($this->table == '') {
      throw new Exception("Unable to truncate -- table name is null");
    }
    
    // Escape table name
    $table = $this->db->escape_string($this->table);
    
    // Construct truncate statement
    $sql = "TRUNCATE TABLE $table";
    
    // Execute statement
    $result = $this->db->query($sql);
    
    // Check result
    if($result == true) {
      return true;
    }
    else {
      throw new Exception("Unable to truncate -- " . $this->db->error);
    }    
        
  }
  
  
  /**
    * Checks if the database connection is valid
    *
    * @return boolean True or false
    */
    
  function is_valid_db() {
    
    $is_mysqli = ($this->db instanceof MySQLi == true && $this->db instanceof StanfordDatabase == false);
    $is_stanforddb = ($this->db instanceof StanfordDatabase && $this->db->is_connected());
    
    return ($is_mysqli || $is_stanforddb);
    
  }
  
}

?>