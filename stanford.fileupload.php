<?php

/**
  * StanfordFileUpload is a simple wrapper class for saving and doing error checking on files
  * uploaded to the server through a form
  * 
  * @author ddonahue
  *
  * @date October 15, 2008
  * 
  * Copyright 2008,2009 Board of Trustees, Leland Stanford Jr. University
  * See LICENSE for licensing terms.
  *
  */
  
class StanfordFileUpload {
  
  // Version
  const VERSION = '1.0.0';
  
  // Defaults
  const DEFAULT_MAX_FILESIZE = 1024;   // 1 megabyte
  
  // Settings
  private $field_name;            // Name of the form field (e.g. 'picture')
  private $max_filesize;          // Max allowed file size in bytes (e.g. 500000)
  private $allowed_extensions;    // Allowed file extensions(e.g. array("gif", "jpg", "png"))
  private $save_directory;        // Directory in which to save the file (e.g. /home/my_files/)
  private $filename;              // The name of the file to be saved (e.g. error-2010-12-25.log)
  private $allow_cgi_bin;         // Allow uploads in cgi-bin directory?
  
  // Uploaded file
  private $uploaded_file;         // The file uploaded to the server (e.g. $_FILES['field_name'])
  
  // Error messages
  public $errors = array();
  public $error_heading = "The following errors prevented your file from being saved:";
  public $error_css_class = "stanford_fileupload_errors";
  
  
  /**
    * Creates and initializes a new StanfordFileUpload
    *
    * @param mixed file  Either the name of the field on the form (e.g. "my_file") or the array for the uploaded file (e.g. $_FILES['my_file'])
    */
    
  function __construct($file) {
    
    // Method 1:  Send the name of the field as parameter to constructor
    if(is_string($file)) {
      
      // Set the name of the field on the form
      $this->set_field_name($file);
      
      // Get the file from the $_FILES array
      $this->uploaded_file = $_FILES[$this->field_name];
    
    }
    
    // Method 2:  Send the uploaded file from the $_FILES array to the constructor
    else if(is_array($file)) {
      
      // Set the file
      $this->set_uploaded_file($file);
      
    }
    
    // Configure and initialize
    
    // Set the max filesize
    $this->set_max_filesize(self::DEFAULT_MAX_FILESIZE);
    
    // Initialize allowed extensions array
    $this->allowed_extensions = array();
    
    // Disallow saving to cgi-bin
    $this->allow_cgi_bin = false;
        
    // Check if file has been uploaded
    if($this->has_been_uploaded() == true) {
      
      // Get the file information
      $this->load_file_info();
      
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
    * Gets the list of allowed file extensions
    *
    * @return array List of allowed file extensions
    */
    
  function get_allowed_extensions() {
    return $this->allowed_extensions;
  }
  
  
  /**
    * Get the PHP-generated error code from the uploaded file
    *
    * @return int The error code
    */
    
  function get_error_code() {
    return $this->uploaded_file['error'];
  }
  
  
  /**
    * Get the list of error messages that prevented a file from being uploaded
    *
    * @return array List of error messages
    */
    
  function get_errors() {
    return $this->errors;
  }
  
  
  /**
    * Get the extension of the uploaded file
    *
    * @return string File extension
    */
    
  function get_extension() {
    
    // No file extension
    if(strpos($this->get_original_filename(), ".") === false) {
      return '';
    }
    
    // File extension = everything after the last '.'
    else {
      return end(explode(".", $this->get_original_filename()));
    }
    
  }
   
  
  /**
    * Get the name of the file to be saved on the server
    *
    * @return string The name of the file
    */
    
  function get_filename() {
    return $this->filename;
  }
  
  
  /**
    * Get maximum allowed file size for this uploaded file (in kilobytes)
    *
    * @return int The maximum file size in kilobytes
    */
    
  function get_max_filesize() {
    return ($this->max_filesize / 1024);
  }
  
  
  /**
    * Get maximum allowed file size for this uploaded file (in bytes)
    *
    * @return int The maximum file size in bytes
    */
    
  function get_max_filesize_in_bytes() {
    return $this->max_filesize;
  }
  
  /**
    * Get the browser-reported MIME type of the uploaded file
    *
    * @return string MIME type
    */
    
  function get_mime_type() {
    return $this->uploaded_file['type'];
  }
  
  
  /**
    * Get the original name of the file uploaded to the server
    *
    * @return string The name of the file
    */
    
  function get_original_filename() {
    return $this->uploaded_file['name'];
  }
  
  
  /**
    * Get the path of the file to be saved on the server
    *
    * @return string The path of the file
    */
    
  function get_save_location() {
    return $this->save_directory . $this->filename;
  }
  
  
  /**
    * Get the size of the uploaded file
    *
    * @return int The size of the file in bytes
    */
    
  function get_size() {
    return $this->uploaded_file['size'];
  }
   
  
  /**
    * Get the path of the temporary uploaded file saved on the server
    *
    * @return string The temporary path of the file
    */
    
  function get_temp_location() {
    return $this->uploaded_file['tmp_name'];
  }
  
  
  /**
    * Adds a message to the list of errors
    *
    * @param string msg   The error message
    */
    
  function add_error_message($msg) {
    $this->errors[] = $msg;
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
    * Allow or disallow saving uploaded files within the cgi-bin directory (disabled by default and strongly discouraged)
    *
    * @param boolean val  True or false
    */
    
  function set_allow_cgi_bin($val) {
    $this->allow_cgi_bin = $val;
  }
  
  
  /**
    * Sets the list of allowed file extensions (accepts a variable number of arguments)
    *
    * @param string ext   File extension (e.g. 'gif')
    */
    
  function set_allowed_extensions($ext) {
    
    if(func_num_args() <= 0) {
      throw new Exception("set_allowed_extensions requires one or more arguments.");
    }
    else {
      $this->allowed_extensions = func_get_args();
    }
    
  }
  
  
  /**
    * Sets the name of the form field
    *
    * @param string name  The name of the form field
    */
    
  function set_field_name($name) {
    $this->field_name = $name;
  }
  
  
  /**
    * Sets the name of the file to be saved on the server
    *
    * @param string filename  The name of the file
    */
    
  function set_filename($filename) {
    if($filename) {
      $this->filename = $filename;
    }
    else {
      throw new Exception("Filename cannot be blank");
    }
  }
  
  
  /**
    * Sets the maximum allowed size of an uploaded file in kilobytes
    *
    * @param int size   The file size in kilobytes
    */
    
  function set_max_filesize($size) {
    
    if($size < 0) {
      throw new Exception("Maximum file size must be greater than or equal to zero.");
    }
    else {
      $this->max_filesize = $size * 1024;
    }
    
  }
  
  
  /**
    * Sets the number of fields to display on the form (using display_form)
    *
    * @param int num  The number of fields to display
    */
  
  function set_num_fields($num) {
    $this->num_fields = $num;
  }
  
  
  /**
    * Sets the directory in which to save the uploaded file
    *
    * @param string dir   The directory
    */
    
  function set_save_directory($dir) {
    
    // Normalize (remove trailing slash if present)
    $save_dir = realpath($dir);
    
    // Check if valid
    if(is_dir($save_dir)) {
      
      // Set the save directory with a trailing slash
      $this->save_directory = $save_dir . DIRECTORY_SEPARATOR;
      
    }
    else {
      
      // Error
      throw new Exception("Directory does not exist: {$dir}");
    }
  }
  
  
  /**
    * Sets the uploaded file to process
    *
    * @param array file   The uploaded file
    */
    
  function set_uploaded_file($file) {
    if(is_array($file)) {
      $this->uploaded_file = $file;
    }
    else {
      throw new Exception("Invalid file type (expected array): " . gettype($file));
    }
  }
  
  
  /**
    * Displays a simple form for uploading a single file
    */
    
  function display_form() {
    
    echo "<form action='{$_SERVER['PHP_SELF']}' method='post' enctype='multipart/form-data'>\n";
    
    echo "<p><strong><label for='{$this->field_name}'>Please select a file:</strong><br/>\n";
    
    echo "<input type='hidden' name='MAX_FILE_SIZE' value='{$this->max_filesize}' />\n";
    
    echo "<input type='file' name='{$this->field_name}' id='{$this->field_name}' />\n";
    
    echo "</label></p>\n";
    
    echo "<p><input type='submit' name='submit' value='Upload File' /></p>\n";
    
    echo "</form>\n";
    
  }
  
  
  /**
    * Detects if a file associated with the given field name has been uploaded
    *
    * @return boolean   True when file upload has been detected, false otherwise
    */
    
  function has_been_uploaded() {
    return ($this->uploaded_file != null && file_exists($this->uploaded_file['tmp_name']));      
  }
  
  
  /**
    * Loads the uploaded file
    *
    * @return boolean  True on success, false on failure
    */
    
  function load_file_info() {
    
    // Check if the file is valid
    if($this->has_been_uploaded() == false) {
      return false;
    }
    
    // Set the filename
    $this->filename = $this->get_original_filename();
    
    return true;
  }
  
  
  /**
    * Checks the uploaded file against a variety of validations
    *
    * @return boolean   True if validations passed, false on failure
    */
    
  function validate() {
    
    // Check if there are any custom errors in the list
    if(sizeof($this->get_errors()) > 0) {
      return false;
    }
    
    // If the file has not been uploaded..
    if($this->has_been_uploaded() == false) {
      
      // Cannot save a nonexistent file
      $this->add_error_message("No file has been uploaded, unable to save.");
      
      return false;
    }
    
    // Check if the save directory has been configured
    if($this->save_directory == '') {
      
      // Must specify a save directory
      $this->add_error_message("Save directory has not been configured.");
      
      return false;
      
    }
    
    // Check PHP-generated error code
    if($this->get_error_code() > 0) {
      
      $upload_errors = array(
        UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        UPLOAD_ERR_FORM_SIZE  => 'The uploaded file is too large (> ' . ($this->max_filesize / 1024) . ' KB)',
        UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION  => 'File upload stopped by extension'
      );
   
      if(isset($upload_errors[$this->get_error_code()])) {
        
        $this->add_error_message($upload_errors[$this->get_error_code()] . ': ' . $this->get_original_filename());
        
        return false;
      }
      else {
        $this->add_error_message("PHP returned an unknown error code [{$this->get_error_code()}]");
        
        return false;
      }
    }
    
    // Check if uploaded file
    if(is_uploaded_file($this->get_temp_location()) == false) {
      
      $this->add_error_message("Cannot modify a file that was not uploaded through a form.");
      
      return false;
    }
    
    // Check filesize
    if($this->get_size() > $this->max_filesize) {
      $this->add_error_message("The uploaded file is too large (> " . ($this->max_filesize / 1024) . " KB): " . $this->get_original_filename());
      
      return false;
    }
    
    // Check if path already exists
    if(file_exists($this->get_save_location())) {
      
      $this->errors[] = "File already exists: {$this->get_filename()}";
      
      return false;
    }
    
    // Check extension
    if(sizeof($this->allowed_extensions) > 0) {
      
      $found = false;
      
      foreach($this->allowed_extensions as $ext) {
        if(strtolower($this->get_extension()) == strtolower($ext)) {
          $found = true;
          break;
        }
      }
     
      if($found == false) {
        
        $this->add_error_message("Invalid file extension: " . $this->get_extension());
        
        return false;
        
      }
    }
    
    // Get explicitly specified save directory (using set_save_directory), resolved to full path
    $save_dir = realpath($this->save_directory);
    
    // Get final save directory (explicitly specified save directory plus the filename), resolved to full path
    $final_save_dir = realpath(dirname($this->get_save_location()));
    
    // Ensure that the path to the new file is in the upload directory, not above it
    // Criteria: final_save_dir must begin with save_dir
    if(strpos($final_save_dir, $save_dir) !== 0) {
      
      $this->add_error_message("Unable to save a file outside of the upload directory");
      
      return false;
      
    }
    
    // Ensure that file is not being saved in cgi-bin
    // Criteria: the string 'cgi-bin' must not occur within final_save_dir
    if($this->allow_cgi_bin == false && stripos($final_save_dir, "cgi-bin") == true) {
      
      $this->add_error_message("Cannot save uploaded files in cgi-bin directory");
      
      return false;
      
    }
    
    return true;
  }
  
  
  /**
    * Saves an uploaded file in a permanent location on the server once it has passed all validations
    *
    * @return boolean   True on success, false on failure
    */
    
  function save() {
    
    // Check the file for errors
    if($this->validate() == true) {
      
      // Move the uploaded file from its temporary location to its destination
      if(move_uploaded_file($this->get_temp_location(), $this->get_save_location()) == true) {
        
        // File uploaded and moved successfully
        return true;
        
      }
      
      // Error in move_uploaded_file()
      else {
        
        $this->add_error_message("Unable to move the uploaded file to its new location (check permissions).");
        return false;  
        
      }
      
    }
    
    // Validation failed
    else {
      return false;
    }
  }
  
  
  /**
    * When using an array as a field name on the form, use this function to get the list of StanfordFileUploads
    *
    * @param string array_name  The name of the array on the form
    *
    * @return array   A list of StanfordFileUploads
    */
    
  function get_file_uploads($array_name) {
    
    // Get the array of uploaded files
    $files = $_FILES[$array_name];
    
    // Create a new array
    $new_array = array();
    
    // Go through each attribute
    foreach($files as $key => $array) {
            
      // Go through each file
      for($i=0; $i < sizeof($array); $i++) {
        
        // Create new array
        if(isset($new_array[$i]) == false) {
          $new_array[$i] = array();
        }
                
        // Set the value
        $new_array[$i][$key] = $array[$i];
      }
      
    }
    
    // Now we have an array of individual files
    // Convert each to a StanfordFileUpload
    
    $stanford_file_uploads = array();
    
    foreach($new_array as $file) {
      
      // If this is a real file
      if($file['name'] != null) {
        
        // Add to array
        $stanford_file_uploads[] = new StanfordFileUpload($file);
      }
    }
    
    return $stanford_file_uploads;
  }
  
};