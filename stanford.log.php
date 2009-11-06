<?php

// Include StanfordLogEmailSettings
require_once(dirname(__FILE__) . "/stanford.log.emailsettings.php");

// Include other StanfordLog classes
require_once(dirname(__FILE__) . "/stanford.log.file.php");
require_once(dirname(__FILE__) . "/stanford.log.database.php");

/**
  * Logging suite includes methods for logging to a flat file or
  * a database (with e-mail capabilities)
  *
  * @date November 6, 2008
  * 
  * Copyright 2008,2009 Board of Trustees, Leland Stanford Jr. University
  * See LICENSE for licensing terms.
  *
  */

class StanfordLog {
  
  const VERSION = '1.0.0';                        // Version number of the class
  const MARKER = 'StanfordLog e-mail sent';       // Reserved message used to track messages sent via e-mail
  
  // E-mail settings
  public $email_settings;
  
  
  /**
    * Gets the version number of the class
    *
    * @return string  The version number
    */
    
  function get_version() {
    return self::VERSION;
  }
  
  
  /**
    * Loads the e-mail settings into the object
    * Used for sending the log out via e-mail at a specified interval
    *
    * @param StanfordLogEmailSettings email_settings  E-mail settings
    *
    * @return boolean Status, true on success or false on failure
    */
    
  function load_email_settings($email_settings) {
    
    // Check email_settings
    if($email_settings instanceof StanfordLogEmailSettings) {
      
      // Check recipients
      if(sizeof($email_settings->get_email()->get_recipients()) == 0) {
        throw new Exception("No recipients defined in StanfordLogEmailSettings");
      }
      
      // Check delay
      if($email_settings->get_delay() <= 0) {
        throw new Exception("Invalid delay defined in StanfordLogEmailSettings: " . $email_settings->get_delay());
      }
      
      // Save e-mail settings      
      $this->email_settings = $email_settings;
      
      // Check to see if the log should be read (compute random value and compare to defined probability)
      if(rand(1, 100) <= ($email_settings->get_probability() * 100) ) {
        
        // Send the e-mail if the delay is up
        $this->send_if_delay_is_up();
        
      }
      
      return true;
    }
    
    // Error
    else {
      throw new Exception("StanfordLog::load_email_settings accepts one parameter which is a StanfordLogEmailSettings object");
    }
  }
  
  
  /**
    * Sends the e-mail to the log recipient
    * Truncates the log if applicable
    * Appends a message to the log indicating that an e-mail was sent
    *
    * @return boolean The result, true if successful and false otherwise
    */
    
  function send_email() {
    
    // Get the StanfordEmail
    $email = $this->email_settings->get_email();
    
    // Send the message
    if( ($result = $email->send()) == true) {
    
      // Truncate log
      if($this->email_settings->get_truncate_on_send() == true) {
        $this->truncate();
      }
      
      // Log the sent marker
      $this->append(self::MARKER);
      
    }
    
    // Return result
    return $result;
    
  }
  
  
  /**
    * Given an array, returns the output of the fputcsv function
    *
    * @param array array  The array to convert to a CSV-formatted string
    *
    * @return string  A CSV-formatted string
    */
    
  function array_to_csv_string($array) {
    
    // Create a virtual file
    // Up to 1MB is kept in memory, if it becomes bigger it will automatically be written to a temporary file
    $csv = fopen('php://temp/maxmemory:'. (1024*1024), 'r+');
    
    // Call fputcsv on the array and direct it to our stream
    fputcsv($csv, $array);
    
    // Go to the beginning of the stream
    rewind($csv);
    
    // Get the written string
    $output = stream_get_contents($csv);
    
    // Close the stream
    fclose($csv);
    
    // Return the result
    return $output;
  }
  
};

?>