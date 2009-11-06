<?php

/**
  * StanfordUtil is a collection of basic functions for the Stanford Web Application Toolkit
  *
  * @author ddonahue
  *
  * @date October 23, 2008
  * 
  * Copyright 2008,2009 Board of Trustees, Leland Stanford Jr. University
  * See LICENSE for licensing terms.
  *
  */

class StanfordUtil {
  
  const VERSION = '1.0.0';
  
  /* Error reporting settings */
  const DEBUGGING   = 'debugging';
  const DEVELOPMENT = 'development';
  const PRODUCTION  = 'production';
  
  
  /**
    * Gets the version number of the class
    *
    * @return string  The version number
    */
    
  function get_version() {
    return self::VERSION;
  }
  
  
  /**
    * Strips slashes automatically added to form input by magic_quotes
    *
    * @date June 11, 2008
    * @credit http://talks.php.net/show/php-best-practices/26
    * 
    */
  
  function undo_magic_quotes() {
    if (get_magic_quotes_gpc()) {
     
      $in = array(&$_GET, &$_POST, &$_REQUEST, &$_COOKIE);
     
      while (list($k,$v) = each($in)) {
        foreach ($v as $key => $val) {
          if (!is_array($val)) {
            $in[$k][$key] = stripslashes($val);
            continue;
          }
          $in[] =& $in[$k][$key];
        }
      }
     
      unset($in);
    }
  }
  
  
  /**
    * Sets the error reporting settings for production, development, or debugging
    *
    * @author ddonahue
    * @author thanosb
    *
    * @date July 23, 2008
    * 
    * @param string mode     The error reporting mode - either production, development, or debugging
    *
    * @return mixed  String representing the error mode set on success, false on failure.
    */
  
  function set_error_reporting($mode) {
    
    $mode = strtolower($mode);
    
    if($mode == self::DEBUGGING) {
      // Report all PHP errors except for notices
      ini_set('error_reporting', E_ALL);
       
      // Set the display_errors directive to On
      ini_set('display_errors', 1);
       
      // Do not log errors to the web server's error log
      ini_set('log_errors', 0);
    }
    
    else if($mode == self::DEVELOPMENT) {
      // Report all PHP errors and notices
      ini_set('error_reporting', E_ALL ^ E_NOTICE);
       
      // Set the display_errors directive to On
      ini_set('display_errors', 1);
       
      // Do not log errors to the web server's error log
      ini_set('log_errors', 0);
    }
    
    else if($mode == self::PRODUCTION) {
      // Report simple running errors
      ini_set('error_reporting', E_ALL ^ E_NOTICE);
       
      // Set the display_errors directive to Off
      ini_set('display_errors', 0);
       
      // Log errors to the web server's error log
      ini_set('log_errors', 1);
    }
    
    else {
      return false;
    }
    
    return $mode;
  }
  
}
?>