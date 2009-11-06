<?php
  
// Include StanfordEmail
require_once(dirname(__FILE__) . "/stanford.email.php");


/**
  * StanfordLog: E-mail settings
  *
  * E-mail configuration class for StanfordLog
  * 
  * Copyright 2008,2009 Board of Trustees, Leland Stanford Jr. University
  * See LICENSE for licensing terms.
  *
  */

class StanfordLogEmailSettings {
  
  // StanfordEmail object
  private $email;
  
  // Other settings
  private $delay;               // The delay between messages in seconds
  private $truncate_on_send;    // After sending an e-mail, truncate log (true) or preserve (false)
  private $probability;         // Probability of checking the log when the settings are loaded by StanfordLog
  
  
  /**
    * Creates a new StanfordLogEmailSettings object
    */
    
  function __construct() {
    
    // Initialize e-mail
    $this->email = new StanfordEmail();
    
    // Set defaults
    $this->set_sender("nobody@stanford.edu", "StanfordLog");
    $this->set_subject("StanfordLog Update");
    $this->set_delay(600);
    $this->set_truncate_on_send(false);
    $this->set_probability(.1);
    
  }
  
  
  /**
    * Gets the e-mail object
    *
    * @return StanfordEmail The e-mail
    */
    
  function get_email() {
    return $this->email;
  }
  
  
  /**
    * Gets the delay between messages
    *
    * @return int The delay between messages
    */
    
  function get_delay() {
    return $this->delay;
  }
  
  
  /**
    * Gets the probability of checking the log when the settings are loaded by StanfordLog
    *
    * @return float The probability
    */
    
  function get_probability() {
    return $this->probability;
  }
  
  
  /**
    * Gets the value of truncate on send
    *
    * @return boolean Truncate on send (true or false)
    */
    
  function get_truncate_on_send() {
    return $this->truncate_on_send;
  }
  
  
  /**
    * Sets the body of the message (the log itself)
    *
    * @param string body  The body
    */
    
  function set_body($body) {
    return $this->email->set_body($body, $is_html = false);
  }
  
  
  /**
    * Sets the delay between subsequent e-mails in seconds
    *
    * @param int delay  The delay, in seconds
    */
    
  function set_delay($delay) {
    if(is_int($delay) && $delay > 0) {
      $this->delay = $delay;
    }
    else {
      throw new Exception("Invalid delay in set_delay (must be greater than zero): $delay");
    }
  }
  
  
  /**
    * Sets probability of checking the log when the settings are loaded by StanfordLog
    *
    * @param float probability  The probability (between 0 and 1)
    */
    
  function set_probability($probability) {
    if($probability >= 0 && $probability <= 1 && (is_float($probability) || is_int($probability))) {
      $this->probability = $probability;
    }
    else {
      throw new Exception("Invalid parameter in set_probability (accepts a float between 0 and 1): $probability");
    }
  }
  
  
  /**
    * Adds a recipient to the message
    *
    * @param string address E-mail address
    * @param string name  Name
    *
    * @return boolean True on success, false on failure
    */
    
  function add_recipient($address, $name='') {
    return $this->email->add_recipient($address, $name);
  }
  
  
  /**
    * Adds a recipient to the message
    *
    * @param string address E-mail address
    * @param string name  Name
    *
    * @return boolean True on success, false on failure
    */
  
  function set_recipient($address, $name='') {
    return $this->email->add_recipient($address, $name);
  }
  
  
  /**
    * Sets the sender of the message
    *
    * @param string address E-mail address
    * @param string name  Name
    *
    * @return boolean True on success, false on failure
    */
  
  function set_sender($address, $name='StanfordLog') {
    return $this->email->set_sender($address, $name);
  }
  
  
  /**
    * Sets the subject of the message
    *
    * @param string subject The subject
    *
    * @return boolean True on success, false on failure
    */
  
  function set_subject($subject) {
    return $this->email->set_subject($subject);
  }
  
  
  /**
    * After the e-mail is sent, should the log be truncated (true) or preserved (false)?
    *
    * @param boolean value  True or false
    *
    * @return boolean True or false
    */
  
  function set_truncate_on_send($value) {
    return $this->truncate_on_send = $value;
  }
  
};

?>