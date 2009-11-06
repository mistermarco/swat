<?php

// Include StanfordDirectory
include_once(dirname(__FILE__) . "/stanford.directory.php");    // StanfordDirectory class

/**
  * Straightforward class for getting information about Stanford users
  * 
  * @author ddonahue
  *
  * @date November 7, 2008
  * 
  * Copyright 2008,2009 Board of Trustees, Leland Stanford Jr. University
  * See LICENSE for licensing terms.
  *
  */

class StanfordPerson {
  
  const VERSION = "1.0.0";
  
  const STUDENT   = "student";
  const STAFF     = "staff";
  const FACULTY   = "faculty";
  const AFFILIATE = "affiliate";
  
  private $directory;      // StanfordDirectory
  
  private $sunetid;        // SUNetID
  private $first_name;     // First name
  private $last_name;      // Last name
  private $middle_name;    // Middle name/initial
  private $full_name;      // Full name
  private $email;          // E-mail address
  
  private $home_phone;     // Home phone #
  private $mobile_phone;   // Mobile phone #
  private $pager_email;    // Pager e-mail address
  private $pager_number;   // Pager #
  private $work_phone;     // Work phone #
  
  private $home_postal_address;      // Home address
  private $work_postal_address;      // Work address
  private $permanent_postal_address; // Permanent address
  
  private $job_title;      // Job title
  
  private $primary_affiliation; // Primary affiliation
  private $is_student;     // Is a student
  private $is_staff;       // Is staff
  private $is_faculty;     // Is faculty
  private $is_affiliate;   // Is affiliate
  
  private $fetched_info_from_directory;  // Set to true once the directory has been queried
  private $search_status;                // Set to true on successful query, false on no results
  private $close_ldap_after_search;      // Set to true if the connection to the directory should
                                         //  be closed after the search
    
  
  /**
    * Creates a new StanfordPerson given optional SUNetID and StanfordDirectory.
    *
    * @param string sunetid               The user's SUNetID
    * @param StanfordDirectory directory  StanfordDirectory object (for persistent connections)
    */
    
  function __construct($sunetid=null, $directory=null) {
    
    // Set parameters
    $this->sunetid = $sunetid;
    $this->directory = $directory;
    $this->fetched_info_from_directory = false;
    
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
    * Helper function which is called automatically when any unknown information about a person is requested.  Queries the directory only once for all available information, all other calls to the function are ignored.
    *
    * @return boolean  True on successful search, false on no results
    */
  
  function fetch_info() {
    
    // Check if a fetch is necessary
    if($this->fetched_info_from_directory == true) return $this->search_status;    
    
    // Perform error checking on directory and sunetid
    $this->check_before_query();
    
    // Get user info
    $user_info = $this->directory->get_user_info($this->sunetid);
    
    // Check the result
    if(sizeof($user_info) == 0) {
      // Set search status and return false
      $this->search_status = false;
      return false;
    }
  
    // Store user information
    if(is_array($user_info)) {  
      
      // Set search status
      $this->search_status = true;
      
      // Parse the resultset
      $this->sunetid     = $user_info[uid][0];
      $this->first_name  = $user_info[sudisplaynamefirst][0];
      $this->last_name   = $user_info[sudisplaynamelast][0];
      $this->middle_name = $user_info[sudisplaynamemiddle][0];
      $this->full_name   = $user_info[displayname][0];
      $this->email       = $user_info[mail][0];
      
      $this->home_phone           = $user_info[homephone][0];
      $this->mobile_phone         = $user_info[mobile][0];
      $this->pager_number         = $user_info[pager][0];
      $this->work_phone           = $user_info[telephonenumber][0];
      $this->pager_email          = $user_info[suemailpager][0];
      
      $this->home_postal_address  = $user_info[homepostaladdress][0];
      $this->work_postal_address  = $user_info[postaladdress][0];
      $this->permanent_postal_address = $user_info[supermanentaddress][0];
      
      $this->job_title            = $user_info[title][0];
      
      // Get affiliations
      if(isset($user_info[suaffiliation])) {
        $this->affiliations = $user_info[suaffiliation];
        unset($this->affiliations['count']);
        
        // Primary affiliation
        $primary_affiliation = $user_info[sugwaffilcode1][0];
        
        foreach($this->affiliations as $affiliation) {
          if(strpos($affiliation, "nonactive") !== false) continue;
          
          if(strpos($affiliation, "stanford:staff") !== false) {
            $this->is_staff = true;
            if($affiliation == $primary_affiliation) {$this->primary_affiliation = StanfordPerson::STAFF; }
          }
          
          if(strpos($affiliation, "stanford:student") !== false) {
            $this->is_student = true;
            if($affiliation == $primary_affiliation) {$this->primary_affiliation = StanfordPerson::STUDENT; }
          }
          
          if(strpos($affiliation, "stanford:faculty") !== false) {
            $this->is_faculty = true;
            if($affiliation == $primary_affiliation) {$this->primary_affiliation = StanfordPerson::FACULTY; }
          }
          
          if(strpos($affiliation, "stanford:affiliate") !== false) {
            $this->is_affiliate = true;
            if($affiliation == $primary_affiliation) {$this->primary_affiliation = StanfordPerson::AFFILIATE; }
          }
        }
      }
    }
    
    // Set flag to not search the directory again
    $this->fetched_info_from_directory = true;
    
    // Close the connection if necessary
    if($this->close_ldap_after_search == true) {
      $this->directory->disconnect();
    }
    
    return $this->search_status;
  }
  
  
  /**
    * Sets the e-mail address
    *
    * @param string email   The person's e-mail address
    */
    
  function set_email($email) {
    $this->email = $email;
  }
  
  
  /**
    * Sets the first name
    *
    * @param string first_name  The person's first name
    */
    
  function set_first_name($first_name) {
    $this->first_name = $first_name;
  }
  
  
  /**
    * Sets the full name
    *
    * @param string full_name   The person's full name
    */
    
  function set_full_name($full_name) {
    $this->full_name = $full_name;
  }
  
  
  /**
    * Sets the last name
    *
    * @param string last_name  The person's last name
    */
    
  function set_last_name($last_name) {
    $this->last_name = $last_name;
  }
  
  
  /**
    * Sets the middle name or initial
    *
    * @param string middle_name   The person's middle name or initial
    */
    
  function set_middle_name($middle_name) {
    $this->middle_name = $middle_name;
  }
  
  
  /**
    * Sets the SUNetID
    *
    * @param string sunetid   The person's SUNetID
    */
  
  function set_sunetid($sunetid) {
    $this->sunetid = $sunetid;
  }
    
  
  /**
    * Static method that gets the currently logged-in Webauth user
    *
    * @return StanfordPerson The currently logged-in user or null if not behind Webauth
    */
    
  function get_current_user() {
    if($_ENV['WEBAUTH_USER']) {
      return new StanfordPerson($_ENV['WEBAUTH_USER']);
    }
    else {
      return null;
    }
  }
  
  
    
    
    
  /**
    * Basic getter functions for all StanfordPerson attributes
    * If necessary, the information is fetched from the directory
    * Otherwise, it is returned from the local cached result
    */
    
  function get_email() {
    if($this->email == null) { $this->fetch_info(); }
    
    return $this->email;
  }
    
  function get_first_name() {
    if($this->first_name == null) { $this->fetch_info(); }
    
    return $this->first_name;
  }
    
  function get_full_name() {
    if($this->full_name == null) { $this->fetch_info(); }
    
    return $this->full_name;
  }
      
  function get_last_name() {
    if($this->last_name == null) { $this->fetch_info(); }
    
    return $this->last_name;
  }
    
  function get_middle_name() {
    if($this->middle_name == null) { $this->fetch_info(); }
    
    return $this->middle_name;
  }
  
  function get_home_phone() {
    if($this->home_phone == null) { $this->fetch_info(); }
    
    return $this->home_phone;
  }
  
  function get_mobile_phone() {
    if($this->mobile_phone == null) { $this->fetch_info(); }
    
    return $this->mobile_phone;
  }
  
  function get_pager_number() {
    if($this->pager_number == null) { $this->fetch_info(); }
    
    return $this->pager_number;
  }
  
  function get_work_phone() {
    if($this->work_phone == null) { $this->fetch_info(); }
    
    return $this->work_phone;
  }
  
  function get_pager_email() {
    if($this->pager_email == null) { $this->fetch_info(); }
    
    return $this->pager_email;
  }
  
  function get_home_postal_address() {
    if($this->home_postal_address == null) { $this->fetch_info(); }
    
    return $this->home_postal_address;
  }
  
  function get_work_postal_address() {
    if($this->work_postal_address == null) { $this->fetch_info(); }
    
    return $this->work_postal_address;
  }
  
  function get_permanent_postal_address() {
    if($this->permanent_postal_address == null) { $this->fetch_info(); }
    
    return $this->permanent_postal_address;
  }
  
  function get_job_title() {
    if($this->job_title == null) { $this->fetch_info(); }
    
    return $this->job_title;
  }
  
  
  /**
    * Gets the status of the LDAP search
    *
    * @return boolean  True on success, false on failure (or not yet attempted)
    */
    
  function get_search_status() {
    return $this->search_status;
  }
  
  
  /**
    * Gets the SUNetID
    *
    * @return string  The person's SUNetID
    */
    
  function get_sunetid() {
    return $this->sunetid;
  }
  
  
  /**
    * Debugging function - prints a sentence describing a user's affiliation
    */
    
  function print_affiliation() {
    
    $this->fetch_info();
    
    echo '<p>' . $this->first_name . ' ' . $this->last_name . ' is ';
    
    switch($this->primary_affiliation) {
      case StanfordPerson::STUDENT:
        echo 'a student';
        break;
      case StanfordPerson::FACULTY:
        echo 'a faculty member';
        break;
      case StanfordPerson::STAFF:
        echo 'a staff member';
        break;
      case StanfordPerson::AFFILIATE:
        echo 'an affiliate';
        break;
      default:
        echo 'an unknown type';
    }
    
    echo '</p>';
  }
  
  
  /**
    * Checks if the person is a student
    *
    * @return boolean True or false
    */
    
  function is_a_student() {
    $this->fetch_info();
    
    return $this->is_student;
  }
  
  
  /**
    * Checks if the person is a faculty member
    *
    * @return boolean True or false
    */
    
  function is_faculty() {
    $this->fetch_info();
    
    return $this->is_faculty;
  }
  
  
  /**
    * Checks if the person is a staff member
    *
    * @return boolean True or false
    */
    
  function is_staff() {
    $this->fetch_info();
    
    return $this->is_staff;
  }
  
  
  /**
    * Checks if the person is an affiliate
    *
    * @return boolean True or false
    */
    
  function is_affiliate() {
    $this->fetch_info();
    
    return $this->is_affiliate;
  }
  
  
  /**
    * Gets the person's primary affiliation
    *
    * @return string The person's affiliation may be one of the following: student, faculty, staff, affiliate, or null if unknown
    */
    
  function get_primary_affiliation() {
    $this->fetch_info();
    
    return $this->primary_affiliation;
  }
  
  
  /**
    * Checks if the person is authorized to access the current resource.
    *
    * @param StanfordAuthorization auth The authorization object
    *
    * @return boolean  True if authorized, false if denied
    */
    
  function is_authorized($auth=0) {
    
    // Check SUNetID
    if($this->sunetid == '') {
      throw new Exception("Cannot call StanfordPerson::is_authorized() -- StanfordPerson's SUNetID is null.");
    }
    
    // Check $auth
    if($auth instanceof StanfordAuthorization == false) {
      throw new Exception("Error -- is_authorized takes one argument which must be an object of type StanfordAuthorization");
    }
    
    // Check if the user is authorized and return the result
    return $auth->user_is_authorized($this->sunetid);
      
  }

  
  /**
    * Does error checking and preparation before performing a directory search
    *
    * @throws An Exception on null or invalid SUNetID or StanfordDirectory
    *
    * @return boolean  True on success
    */
  
  private function check_before_query() {
    
    // Check SUNetID
    if($this->sunetid == null) {
      throw new Exception("Unable to fetch info from directory -- SUNetID is null.  Use \$person->set_sunetid() to set the SUNetID.");
    }
    
    // Check directory
    if( ($this->directory instanceof StanfordDirectory) == false) {
      
      // Create a new, temporary StanfordDirectory object (less efficient for multiple queries, more efficient and straightforward for single use)
      $this->directory = new StanfordDirectory();
      
      // Close the connection after we are finished
      $this->close_ldap_after_search = true;
    }
    
    // Connect if necessary
    $this->directory->connect_and_bind();
    
    // Check connection
    if($this->directory->is_connected() == false) {
      throw new Exception("Unable to connect and bind to the directory");
    }
    
    return true;
  }
}

?>