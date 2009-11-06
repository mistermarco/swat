<?php

// Set location of PHPMailer
if(defined("PHP_MAILER") == false) {
  define("PHP_MAILER", dirname(__FILE__) . "/lib/phpMailer_v2.2/class.phpmailer.php");
}

// Include PHPMailer
require_once(PHP_MAILER);  
  
/**
  * A secure e-mail class based on PHPMailer, customized for Stanford
  * 
  * Copyright 2008,2009 Board of Trustees, Leland Stanford Jr. University
  * See LICENSE for licensing terms.
  *
  */

class StanfordEmail extends PHPMailer { 
  
  const VERSION = '1.0.0';
  
  const TO = 'to';
  const CC = 'cc';
  const BCC = 'bcc';
  const REPLY_TO = 'reply_to';
  
  // File attachments
  public $max_filesize;
  public $allowed_extensions;
  
  // Error messages
  public $errors;
  public $error_heading;
  public $error_css_class;
    
  /**
    * Creates a new StanfordEmail object
    */
    
  function __construct() {
    
    // Customize PHPMailer settings
    $this->From     = "nobody@stanford.edu";
    $this->FromName = "StanfordEmail";
    $this->Host     = "smtp.stanford.edu";
    $this->Mailer   = "sendmail";
    $this->Body     = "";
    
    // Initialize StanfordEmail settings
    $this->max_filesize = 5 * 1024 * 1024;  // 5 MB
    $this->allowed_extensions = array();
    
    $this->errors = array();
    $this->error_heading = "The following errors prevented your message from being sent:";
    $this->error_css_class = "stanford_email_errors";
    
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
    * Helper function for adding e-mail addresses to a message
    *
    * @param string email   The recipient's e-mail address
    * @param string name    The recipient's name (optional)
    * @param string type    Type of address
    *
    * @return boolean True on success, false on invalid e-mail address
    */
  
  private function add_address($email, $name='', $type='to') {
    if( $this->is_valid_address($email) == false ) {
      $this->add_error_message("Invalid '$type' e-mail address: " . $email);
      return false;
    }
    
    switch($type) {
      case StanfordEmail::TO:
        parent::AddAddress($email, $name);
        break;
        
      case StanfordEmail::CC:
        parent::AddCC($email, $name);
        break;
        
      case StanfordEmail::BCC:
        parent::AddBCC($email, $name);
        break;
        
      case StanfordEmail::REPLY_TO:
        parent::AddReplyTo($email, $name);
        break;
        
      default:
        return false;
    }
    
    return true;
  }
    
    
  /**
    * Adds a file attachment to the message
    *
    * @param path path    The path to the file (/path/to/file.txt)
    * @param string name  The filename to use (my_file.txt)
    *
    * @return boolean True on success, false on error
    */
    
  public function add_attachment($path, $name='') {
    
    // Check if file exists
    if($path == '' || file_exists($path) == false || is_dir($path) == true) {
      $this->add_error_message("Invalid file: " . $path);
      return false;
    }
    
    // Get file extension
    $ext = strtolower(substr(strrchr($path, '.'), 1));
    
    // Check extension
    if(sizeof($this->allowed_extensions) > 0 && in_array($ext, $this->allowed_extensions) == false) {
      $this->add_error_message("Attachment filetype is not allowed: " . $ext);
      return false;
    }
    
    // Check size of file
    if(filesize($path) > $this->max_filesize) {
      $this->add_error_message("Attachment filesize is too large (> " . ($this->max_filesize / 1024) . " KB)");
      return false;
    }
    
    // Add attachment
    if(parent::AddAttachment($path, $name) == true) {
      return true;
    }
    else {
      $this->add_error_message("Attachment could not be found or accessed");
      return false;
    }
  }
    
    
  /**
    * Adds a BCC to the message
    *
    * @param string email   The BCC's e-mail address
    * @param string name    The BCC's name (optional)
    *
    * @return boolean True on success, false on invalid e-mail address
    */
  
  public function add_bcc($email, $name='') {
    return $this->add_address($email, $name, StanfordEmail::BCC);
  }
    
    
  /**
    * Adds a CC to the message
    *
    * @param string email   The CC's e-mail address
    * @param string name    The CC's name (optional)
    *
    * @return boolean True on success, false on invalid e-mail address
    */
  
  public function add_cc($email, $name='') {
    return $this->add_address($email, $name, StanfordEmail::CC);
  }
    
    
  /**
    * Adds an error message to the list.
    *
    * @param string msg   The error message
    *
    * @return void
    */
    
  public function add_error_message($msg) {
    array_push($this->errors, $msg);
  }
  
  
  /**
    * Adds a recipient to the message
    *
    * @param string email   The recipient's e-mail address
    * @param string name    The recipient's name (optional)
    *
    * @return boolean True on success, false on invalid e-mail address
    */
  
  public function add_recipient($email, $name='') {
    return $this->add_address($email, $name, StanfordEmail::TO);
  }
    
    
  /**
    * Adds a Reply-To to the message
    *
    * @param string email   The Reply-To's e-mail address
    * @param string name    The Reply-To's name (optional)
    *
    * @return boolean True on success, false on invalid e-mail address
    */
  
  public function add_reply_to($email, $name='') {
   return $this->add_address($email, $name, StanfordEmail::REPLY_TO);
  } 
  
  
  /**
    * Displays a list of error messages that occured when trying to send a message
    */
    
  public function display_errors() {
    
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
    
  public function set_error_heading($error_heading) {
    $this->error_heading = $error_heading;
  }
  
  
  /**
    * Sets the CSS class for the div output by 'display_errors'
    *
    * @param string error_css_class The CSS class to use
    */
    
  public function set_error_css_class($error_css_class) {
    $this->error_css_class = $error_css_class;
  }
   
  
  /**
    * Returns a list of any error messages that occurred in setting parameters or trying to send the message
    *
    * @return array   List of error messages
    */
    
  public function get_errors() {
    return $this->errors;
  }
  
  
  /**
    * Gets the alt body (plaintext)
    *
    * @return string Alt body
    */
    
  public function get_alt_body() {
    return $this->AltBody;
  }
  
  
  /**
    * Gets the list of BCCs
    *
    * @return array BCCs
    */  
  
  public function get_bccs() {
    return $this->get_private_property("bcc");
  }
  
  
  /**
    * Gets the body of the message
    *
    * @return string The body of the message
    */  
  
  public function get_body() {
    return $this->Body;
  }
  
  
  /**
    * Gets the list of CCs
    *
    * @return array CCs
    */  
  
  public function get_ccs() {
    return $this->get_private_property("cc");
  }
  
  
  /**
    * Gets the list of recipients
    *
    * @return array Recipients
    */  
  
  public function get_recipients() {
    return $this->get_private_property("to");
  }
  
  
  /**
    * Gets the list of reply-tos
    *
    * @return array Reply-tos
    */
    
  public function get_reply_tos() {
    return $this->get_private_property("ReplyTo");
  }
  
  
  /**
    * Gets the e-mail address of the sender
    *
    * @return string Sender's e-mail address
    */  
  
  public function get_sender_email() {
    return $this->From;
  }
  
  
  /**
    * Gets the name of the sender
    *
    * @return string Sender's name
    */
    
  public function get_sender_name() {
    return $this->FromName;
  }
  
  
  /**
    * Gets the subject
    *
    * @return string Subject
    */  
  
  public function get_subject() {
    return $this->Subject;
  }
    
    
  /**
    * Determines if an e-mail address is valid
    *
    * @param string email   E-mail address
    *
    * @return boolean True on success, false on invalid e-mail address
    */
  
  public function is_valid_address($email) {
    
    // Validate e-mail (returns false on failure, string on success)
    $email = filter_var($email, FILTER_VALIDATE_EMAIL);
  
    return $email;
  }
    
    
  /**
    * Sets a list of allowed file extensions for attachments
    *
    * @param string extension   A file extension.  Function accepts a variable length list of arguments.
    *
    * @return void
    */
  
  public function set_allowed_extensions($extension) {
    $this->allowed_extensions = func_get_args();
  }
    
    
  /**
    * Sets the body of the message.  If the body contains HTML, sets the encoding and creates alternate text-only version.
    *
    * @param string body      The body content
    * @param boolean is_html  Set to true if the body content is HTML, false if plain text
    *
    * @return void
    */
  
  public function set_body($body, $is_html) {
    
    if($is_html == true) {
      parent::MsgHTML($body);
    }
    else {
      $this->Body = $body;
    }
    
  }
    
    
  /**
    * Sets the maximum filesize for attachments
    *
    * @param int size   The size in bytes
    *
    * @return void
    */
    
  public function set_max_filesize($size) {
    $this->max_filesize = $size;
  }
  
  
  /**
    * Sets the recipient of the message
    *
    * @param string email   The recipient's e-mail address
    * @param string name    The recipient's name (optional)
    *
    * @return boolean True on success, false on invalid e-mail address
    */
  
  public function set_recipient($email, $name='') {
    return $this->add_recipient($email, $name);
  }
  
  
  /**
    * Sets the sender of the message
    *
    * @param string email   The sender's e-mail address
    * @param string name    The sender's name (optional)
    *
    * @return boolean True on success, false on invalid e-mail address
    */
  
  public function set_sender($email, $name='') {
    if( $this->is_valid_address($email) == false) {
      $this->add_error_message("Invalid 'from' e-mail address: " . $email);
      return false;
    }
    
    $this->From = $email;
    $this->FromName = $name;
    
    return true;
  }
    
    
  /**
    * Sets the subject of the message
    *
    * @param string subject   The subject
    *
    * @return void
    */
  
  public function set_subject($subject) {
    $this->Subject = $subject;
    return true;
  }
    
    
  /**
    * Attempts to send the message
    *
    * @return boolean  True on success, false if any errors occurred prior to sending message (using set_*, add_*) functions or if the message could not be sent
    */
    
  public function send() {
    
    if(sizeof($this->errors) == 0) {
      if(parent::Send() == true) {
        return true;
      }
      else {
        $this->add_error_message("Unable to send message - Send() failed");
        return false;
      }
    }
    else {
      return false;
    }
  }
  
  
  /**
    * Accesses private properties of PHPMailer (to get recipients, ccs, bccs, reply-tos)
    *
    * @return mixed The value of the private property
    */
  
  private function get_private_property($prop) {
    
    // Convert this object into an array
    $cast = (array) $this;
    
    // Get the name of the parent class
    $class = get_parent_class($this);
    
    // Compute the string length
    $class_len = strlen($class);
    
    // Loop through all class members
    foreach($cast as $property => $value) {
      
      // Detect private properties
      if(substr($property, 1, $class_len) == $class) {
        
        // Check if the name of the property equals what we are looking for
        if(substr($property, $class_len + 2) == $prop) {
          
          // Return the value of the property
          return $value;
          
        }
      }
    } 
  }
}

?>
