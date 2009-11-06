<?php

// Include StanfordLog
require_once(dirname(__FILE__) . "/stanford.log.php");


/**
  * StanfordLogFile is a simple class for logging messages to text files
  * 
  * @author ddonahue
  *
  * @date November 20, 2008
  * 
  * Copyright 2008,2009 Board of Trustees, Leland Stanford Jr. University
  * See LICENSE for licensing terms.
  *
  */

class StanfordLogFile extends StanfordLog {
  
  const VERSION = '1.0.0';
  
  public $path;           // The path to the log file
  public $log_file;       // File handle
  public $persistent;     // Do not close the file while persistent mode is on
  
  
  /**
    * Creates a new StanfordLogFile
    * 
    * @param string path  The path to the log file
    */
    
  function __construct($path) {
    $this->set_path($path);
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
    * Appends a message to the log file
    *
    * @param string message The message to log
    *
    * @return boolean True on success, false on failure
    */
    
  function append($message) {
    
    // Get time of request
    if( ($time = $_SERVER['REQUEST_TIME']) == '') {
      $time = time();
    }
   
    // Get IP address
    $remote_addr = $_SERVER['REMOTE_ADDR'];
    
    // Get requested script
    $script = $_SERVER['PHP_SELF'];
    
    // Get query string
    $query_string = urldecode($_SERVER['QUERY_STRING']);
   
    // Format the date and time
    $date = date("Y-m-d H:i:s", $time);
   
    // Open the log file
    $this->open();
    
    // Write to file
    $result = fputcsv($this->log_file, array($date, $remote_addr, $script, $query_string, $message));
    
    // Close
    $this->close();
    
    // If e-mail settings have been defined
    if($this->email_settings instanceof StanfordLogEmailSettings) {
      
      // Send via e-mail if it is time to do so
      $this->send_if_delay_is_up();
      
    }
    
    // Check result
    if($result) {
      return true;
    }
    else {
      return false;
    }    
    
  }
  
  
  /**
    * Sets the path of the log file
    *
    * @param string The path of the log file
    */
    
  function set_path($path) {
    
    // Check if path is null
    if($path == null) {
      throw new Exception("Path cannot be null");
    }
    
    // Check if path is a directory
    if(is_dir($path)) {
      throw new Exception("Path cannot be a directory");
    }
    
    // Set path
    $this->path = $path;
    
    // Return true
    return true;
    
  }
  
  
  /**
    * Opens the log file
    *
    * @string mode  The mode used to open the file (second parameter to fopen)
    *
    * @return boolean True on success or false on failure
    */
    
  function open($mode='a') {
    
    // Check if file is already open
    if($this->is_open() == true) {
      
      // When in persistent mode, just ignore this open() call
      if($this->persistent == true) {
        return true;
      }
      else {
        throw new Exception("File already open: $this->path");
      }
    }
    
    // Try to open file
    $this->log_file = @fopen($this->path, $mode);
    
    // Check result
    if($this->is_open() == false) {
      throw new Exception("Unable to open '$this->path' (check permissions)");
    }
    else {
      return true;
    }
    
  }
  
  
  /**
    * Checks if the log file is open
    *
    * @return boolean True or false
    */
    
  function is_open() {
    if($this->log_file) {
      return true;
    }
    else {
      return false;
    }
  }
  
  
  /**
    * Closes the file
    */
    
  function close() {
    
    if($this->is_open() && $this->persistent == false) {
      fclose($this->log_file);
      $this->log_file = 0;
    }
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
    
    // Open a file for reading backwards so that we can 
    // go from the bottom up and search for a sent marker
    $elif = popen("tac $this->path",'r');
    
    // Loop through the file starting from the bottom
    while($line = fgetcsv($elif, 4096, ',')) {
      
      // Get log time and message
      $time = $line[0];
      $message = $line[4];
      
      // Check if the current message is our sent marker
      // so that we know when the last e-mail was sent
      if($message == self::MARKER) {
        
        // Convert the time in the file to a timestamp
        $time_sent = strtotime($time);
        
        // Check if the delay is up
        if(time() - $time_sent >= $this->email_settings->get_delay()) {
          
          // Check if there is anything to send
          if($email_body != '') {
            
            // Set the body of the message
            $this->email_settings->set_body($email_body);
            
            // Close the file
            fclose($elif);
            
            // Time to send the e-mail
            return true;
            
          }
          else {
            
            // Nothing to send
            fclose($elif);
            return false;
          }
          
        }
        else {
          
          // Delay is not yet up
          fclose($elif);
          return false;
          
        }
        
      }
      else {
        
        // Add the current line to our buffer
        $email_body = $this->array_to_csv_string($line) . $email_body;
        
      }
      
    }
    
    // Close the file
    fclose($elif);
    
    // If we got here, there was no sent marker
    // Check if there's anything to send, and if so, return true
    
    if($email_body) {
      
      // Send the whole log file up to this point
      $this->email_settings->set_body($email_body);
      return true;
      
    }
    else {
      
      // Nothing to send
      return false;
      
    }
  }
  
  
  /**
    * Checks if it is time to send the log through e-mail
    *
    * @return boolean True when the e-mail was sent, false if not
    */
    
  function send_if_delay_is_up() {
    
    // Open the log file for append and read access
    $this->open("a+");
    
    // Lock the file (since we are reading and writing based on what we read from the file)
    $this->lock_file(true);
    
    // Check if it is time to send
    if($this->is_time_to_send() == true) {
    
      // Send the e-mail
      $result = $this->send_email();
      
    }
    else {
      
      // Do not send the e-mail
      $result = false;
      
    }
    
    // Unlock the file
    $this->lock_file(false);  
    
    // Close the file
    $this->close();
    
    // Return result
    return $result;
      
  }
  
  
  /**
    * Truncates the log file to zero length
    *
    * @return boolean The result, true or false
    */
    
  function truncate() {
    
    // If the file is open, call ftruncate
    if($this->is_open()) {
      return ftruncate($this->log_file, 0);
    }
    
    // Otherwise open the file for writing and then close it to truncate
    else {
      
      try {
        
        // Try to open and close the file in write/truncate mode
        $this->open('w');
        $this->close();
        
        return true;
        
      }
      catch(Exception $e) {
        
        // If open/close failed, return false
        return false;
        
      }
      
    }
    
  }
  
  
  /**
    * When parameter is true, locks an open file so that other processes cannot access it
    *  and sets persistent mode to true (meaning this class cannot close the file)
    * When parameter is false, turns persistent mode off
    *
    * @param boolean value  True to lock, false to turn persistent mode off so that the file can be closed
    */
    
  function lock_file($value) {
    
    // On (lock the file if it is open)
    if($value == true) {
      if($this->is_open()) {
        flock($this->log_file, LOCK_EX);
      }
    }
    
    // Set value
    $this->persistent = $value;
    
  }
  
};

?>