<?php

/**
  * StanfordForm provides spam protection and other form-related functions
  * 
  * @author ddonahue
  *
  * @date July 23, 2008
  * 
  * Copyright 2008,2009 Board of Trustees, Leland Stanford Jr. University
  * See LICENSE for licensing terms.
  *
  */

class StanfordForm {
  
  // Version
  const VERSION = '1.0.0';
  
  // Form status
  const IS_VALID = 1;
  const IS_SPAM = 2;
  const IS_EXPIRED = 3;
  const SUBMITTED_TOO_QUICKLY = 4;
  const NOT_YET_SUBMITTED = 5;
        
  // Detection modes
  const NO_TIMEOUTS = 0;
  const TOLERANT = 1;
  const MODERATE = 2;
  const STRICT = 3;
  
  // Member variables
  public $id;
  public $code;
  public $timeout;
  public $min_submission_time;
  public $errors = array();
  public $status;
  public $honeypots = array();
    
  // Error handling
  public $error_heading = "The following errors occurred:";
  public $error_css_class = "stanford_form_errors";
  
  
  /**
    * Creates a new StanfordForm
    */
    
  function __construct() {
    
    // Check session
    if(isset($_SESSION) == false) {
      session_start();
    }
    
    // Set the detection mode
    $this->set_detection_mode(self::MODERATE);
    
    // Generate a random ID
    $this->id = md5(time() + rand());
    
    // Generate honeypots
    $_SESSION[$this->id]['honeypots'] = array();
   
    for($i=0; $i < rand(2,4); $i++) {
      $honeypot = array();
   
      // Create a random string
      $random = md5(uniqid(rand(), true));
   
      // Create name of honeypot from random string
      $honeypot['name'] = substr($random, 0, rand(5,8));
      $honeypot['default_value'] = '';
   
      // Store in session
      array_push($_SESSION[$this->id]['honeypots'], $honeypot);
   
    }
    
    // Set timestamp
    $_SESSION[$this->id]['timestamp'] = time();
        
    // Check the submission (if any)
    $this->spam_check();
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
    * Returns HTML code that protects and uniquely identifies a form for later processing.
    *
    * @author ddonahue
    *
    * @date June 9, 2008
    * 
    * @return string  HTML code to place between <form> tags
    */
   
  function get_antispam_code() {
   
    // Output hidden field containing unique form ID
    $this->code .= '<input type="hidden" name="form_id" value="' . $this->id . '" />' . "\n";
   
    
    foreach($this->get_honeypots() as $honeypot) {
      
      // Output to form
      $this->code .= self::generate_honeypot($honeypot['name'], $honeypot['default_value']);
      
    }
   
    return $this->code;
  }
  
  
  /**
    * Gets the unique ID of the form
    *
    * @return int The form ID
    */
    
  function get_form_id() {
    return $this->id;
  }
  
  
  /**
    * Gets the list of honeypots for this form
    *
    * @return array List of honeypots
    */
    
  function get_honeypots() {
    return $_SESSION[$this->id]['honeypots'];
  }
  
  
  /**
    * Gets the minimum time allowed for a submission
    *
    * @return int Minimum submission time
    */
    
  function get_min_submission_time() {
    return $this->min_submission_time;
  }
  
  
  /**
    * Gets the status of the spam check
    *
    * @return int Status code
    */
    
  function get_status() {
    return $this->status;
  }
  
  
  /**
    * Gets the timeout
    *
    * @return int Timeout
    */
    
  function get_timeout() {
    return $this->timeout;
  }
    
  
  /**
    * Gets the timestamp for this form
    *
    * @return int The UNIX timestamp
    */
    
  function get_timestamp() {
    return $_SESSION[$this->id]['timestamp'];
  }
  
  
  /**
    * Checks a form protected by get_antispam_code().  Must be called after form submission.
    *
    * @param boolean recheck  Force a re-check if true
    * 
    * @return int   The status of the spam check - either IS_VALID, IS_SPAM, IS_EXPIRED, SUBMITTED_TOO_QUICKLY, or NOT_YET_SUBMITTED
    */
   
  function spam_check($recheck=false) {
    
    // Check if spam_check has already been run
    if($this->status > 0) {
      
      // Do a recheck
      if($recheck == true) {
        
        // Reset variables set in previous check
        $this->status = 0;
        $this->errors = array();
        
      }
      
      // Return the result from the previous check
      else {
        return $this->status;
      }
    }
   
    // Clean up expired forms
    foreach($_SESSION as $key => $form) {
      if(strlen($key) == 32 && isset($form['expiration']) && $form['expiration'] < time()) {
        unset($_SESSION[$key]);
      }
    }
   
    // Check form ID
    $id = $_REQUEST['form_id'];
    if($id == '') {
      
      // No form ID => Not yet submitted
      return $this->set_status(self::NOT_YET_SUBMITTED);
      
    }
   
    // Check if ID matches a valid session
    if(isset($_SESSION[$id]) == false) {
      
      // No session data => Form expired
      return $this->set_status(self::IS_EXPIRED);
      
    }
   
    // At this point, the form ID is valid and mapped to the session
   
    // Check minimum time to submit form
    if(isset($_SESSION[$id]['mintime']) && $_SESSION[$id]['mintime'] > time()) {
      
      // Time < min time => Submitted too quickly
      return $this->set_status(self::SUBMITTED_TOO_QUICKLY);
      
    }
    
    // Check maximum time to submit form
    if(isset($_SESSION[$id]['expiration']) && $_SESSION[$id]['expiration'] < time()) {
      
      // Time > max time => Form expired
      return $this->set_status(self::IS_EXPIRED);
      
    }
   
    // Check honeypot fields
    foreach($_SESSION[$id][honeypots] as $honeypot) {
      $name = $honeypot[name];
      $default_value = $honeypot[default_value];
   
      if(isset($_REQUEST[$name]) == false || $_REQUEST[$name] != $default_value) {
        
        // Honeypot modified or missing => Spambot
        return $this->set_status(self::IS_SPAM);
        
      }
    }
   
    // No errors => Valid
    return $this->set_status(self::IS_VALID);
  }
  
  
  /**
    * Generates a randomized hidden text field
    *
    * @return string  HTML code to output within form
    */
   
  function generate_honeypot($name, $default_value) {
    // Types of elements and styles
    $element_types = array("p", "div", "span", "fieldset");
   
    $hide_styles   = array("display: none;",
                           "position: absolute; top: -300px;",
                           "position: absolute; left: -1000px;");
   
    $labels        = array("Do not change this field",
                           "SPAM protection (do not modify)",
                           "Leave this field untouched");
    // Choose randomly
    $element = $element_types[rand(0,sizeof($element_types)-1)];
    $style = $hide_styles[rand(0,sizeof($hide_styles)-1)];
    $label = $labels[rand(0,sizeof($labels)-1)];
   
    // Open container
    $code .= "<$element style='$style'>$label:<br/>\n";
   
    // Choose type of input randomly
    $textbox = rand(0,1);
   
    // Escape output
    $default_value = htmlspecialchars($default_value);
   
    // Input box
    if($textbox) {
      $code .= "\t<input type='text' name='$name' id='$name' value='$default_value' />\n";
    }
   
    // Textarea
    else {
      $code .= "\t<textarea name='$name' id='$name'>$default_value</textarea>\n";
    }
   
    // Close container
    $code .= "</$element>\n";
    
    return $code;
  }
  
  
  /**
    * Adds an error message to the list
    *
    * @param string msg   The error message
    */
    
  function add_error_message($msg) {
    if(is_string($msg)) {
      $this->errors[] = $msg;
    }
    else if(is_array($msg)) {
      foreach($msg as $error_message) {
        $this->errors[] = $error_message;
      }
    }
    else {
      throw new Exception("Invalid argument type (expected string or array): " . gettype($msg));
    }
  }
  
  
  /**
    * Displays a list of error messages that occurred after the form submission
    */
    
  function display_errors() {
    
    // Only display errors when there are errors to display
    if(sizeof($this->errors) > 0) {
      
      // Start div
      if($this->error_css_class) {
        echo "<div class='{$this->error_css_class}'>\n";
      }
      else {
        echo "<div>";
      }
      
      // Display descriptive heading
      if($this->error_heading) {
        echo "\t<p>{$this->error_heading}</p>\n";
      }
      
      // Start list output
      echo "\t<ul>\n";
      
      // Display each error
      foreach($this->get_errors() as $error) {
        echo "\t\t<li>$error</li>\n";
      }
      
      // End list
      echo "\t</ul>\n";
      
      // End div
      echo "</div>\n";
    }
  }
  
  
  /**
    * Sets the heading output by 'display_errors'
    *
    * @param string error_heading  The heading to display
    */
    
  function set_error_heading($error_heading) {
    $this->error_heading = $error_heading;
  }
  
  
  /**
    * Sets the CSS class for the div output by 'display_errors'
    *
    * @param string error_css_class The CSS class to use
    */
    
  function set_error_css_class($error_css_class) {
    $this->error_css_class = $error_css_class;
  }
  
  
  /**
    * Returns the list of error messages
    *
    * @return array   List of error messages
    */
    
  function get_errors() {
    return $this->errors;
  }
  
  
  /**
    * Sets a predefined detection mode (STRICT, MODERATE, TOLERANT, or NO_TIMEOUTS)
    * 
    * @param int mode  The detection mode
    *
    * @return boolean  True on success, false on failure
    */
    
  function set_detection_mode($mode) {
    
    // Mode must be an integer
    if(is_int($mode) == false) {
      return false;
    }
    
    switch($mode) {
      
      case self::STRICT:
        $this->set_min_submission_time(5);  // 5 seconds
        $this->set_timeout(60*60);          // 1 hour
        
        break;
        
      case self::MODERATE:
        $this->set_min_submission_time(3);  // 3 seconds
        $this->set_timeout(60*60*24);       // 24 hours
        
        break;
        
      case self::TOLERANT:
        $this->set_min_submission_time(2);  // 2 seconds
        $this->set_timeout(60*60*24*3);     // 3 days
        
        break;
        
      case self::NO_TIMEOUTS:
        $this->set_min_submission_time(0);  // Disabled
        $this->set_timeout(0);              // Disabled  
        
        break;
      
      default:
        return false;
    }
    
    return true;
  }
  
  
  /**
    * Sets the minimum time allowed for form submission.  The form will be marked invalid if submitted in less than the specified number of seconds.  Set to 0 to disable this protection.
    *
    * @param int time  Time in seconds
    */
  
  function set_min_submission_time($time) {
    if(is_int($time) == false || $time < 0) {
      throw new Exception("The timeout must be a positive integer.");
    }
    else {
      $this->min_submission_time = $time;
      $_SESSION[$this->id]['mintime'] = $this->get_timestamp() + $time;
    }
  }
  
  
  /**
    * Sets the status of the form
    *
    * @param int status   The status code
    *
    * @return int Status code on success, 0 on failure
    */
    
  function set_status($status) {
        
    switch($status) {
      case self::IS_VALID:
        break;
        
      case self::IS_SPAM:
        break;
        
      case self::IS_EXPIRED:
        $this->add_error_message("The form expired.  Please try again.");
        
        break;
        
      case self::SUBMITTED_TOO_QUICKLY:
        $this->add_error_message("Form submitted too quickly.  Please try again.");
        
        break;
        
      case self::NOT_YET_SUBMITTED:
        break;
        
      default:
        return 0;
    }
    
    $this->status = $status;
    
    return $status;
  }
  
  
  /**
    * Sets the maximum time allowed for form submission.  The form will be marked invalid if submitted in more than the specified number of seconds.  Set to 0 to disable this protection.
    *
    * @param int time  Time in seconds
    */
  
  function set_timeout($time) {
    if(is_int($time) == false || $time < 0) {
      throw new Exception("The timeout must be a positive integer.");
    }
    else {
      $this->timeout = $time;
      $_SESSION[$this->id]['expiration'] = $this->get_timestamp() + $time;
    }
  }
  
  
  /**
    * Check if the form has been submitted
    *
    * @return boolean True if submitted, false otherwise
    */
    
  function has_been_submitted() {
    $status = $this->spam_check();
    
    return ($status != self::NOT_YET_SUBMITTED);
  }
  
  
  /**
    * Check if there are error messages
    *
    * @return boolean True if there are errors, false if there are none
    */
    
  function has_errors() {
    return (sizeof($this->errors) > 0);
  }
  
  
  /**
    * Checks if a form was submitted by a spambot
    */
    
  function is_spam() {
    $status = $this->spam_check();
    
    return ($status == self::IS_SPAM);
  }
  
  
  /**
    * Checks if form submission is valid
    */
    
  function is_valid() {
    $status = $this->spam_check();
    
    return ($status == self::IS_VALID);
  }
  
  
  /**
    * Checks if the form submission should be allowed
    *
    * @return boolean True if yes, false if no
    */
    
  function should_allow_submission() {
    
    // Allow submission when the form is valid (meaning: not spam, timed out, or submitted too quickly)
    return $this->is_valid();
    
  }
  
  
  /**
    * Checks if error messages should be displayed
    *
    * @return boolean True if yes, false if no
    */
    
  function should_display_errors() {
    
    // Display errors if there are errors to display
    return ($this->has_been_submitted() == true && sizeof($this->errors) > 0);
    
  }
  
  
  /**
    * Checks if the form should be displayed
    *
    * @return boolean True if yes, false if no
    */
    
  function should_display_form() {
    
    // Display the form when there is an error message to display or the form has not been submitted
    return ($this->should_display_errors() == true || $this->has_been_submitted() == false);
    
  }
  
  
  /**
    * Checks if success should be displayed
    *
    * @return boolean True if yes, false if no
    */
    
  function should_display_success() {
    
    // Display success when either the form is spam or there are no errors
    return ($this->has_been_submitted() == true && ($this->is_spam() == true || $this->should_display_errors() == false));
    
  }
  
};

?>