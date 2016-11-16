<?php

/**
  * Class for parsing, retrieving, and displaying different types of data.
  *
  * @author ddonahue
  *
  * @date October 1, 2008
  * 
  * Copyright 2008,2009 Board of Trustees, Leland Stanford Jr. University
  * See LICENSE for licensing terms.
  *
  */

class StanfordData {
  
  // Version
  const VERSION = '1.0.1';
  
  // Sort order
  const ASCENDING = "ASC";
  const DESCENDING = "DESC";
  
  // Data type
  const UNDEF = 0;
  const DATE = 1;
  const UNSIGNED_INT_IP = 2;
  
  // Select options
  public $fields_to_retrieve = array();       // The list of fields to retrieve from the file or DB table ('field 1', 'field 2')
  public $keys = array();                     // Names of the keys (0 => 'field name') 
  public $types = array();                    // Data types ('field name' => 'date type')
  public $date_formats = array();             // Date formats ('field name' => 'date format')
  public $display_functions = array();        // Display functions ('field name' => 'displayFunction')
  public $order_by = array();                 // Sorting directives (field => field name, direction => 'asc/desc')
  public $limit = 0;                          // How many results to retrieve
  public $offset = 0;                         // Starting at which result
  public $page = 1;                           // Page number (for computing offset)
  
  // Display options
  public $css_class = '';                     // CSS class of the table displayed by display_as_html_table
  public $alternate_row_class = 'alternate';  // CSS class of every other row in the table
  public $headings_class = 'headings';        // CSS class of headings row
  public $display_headings = true;            // Whether or not to display headings
  public $headings = array();                 // Custom headings, either (0 => 'Heading') or ('field name' => 'Heading')
  public $fields_to_display = array();        // List of fields to display ('field 1', 'field 2')
  public $allow_html_in_table = false;        // Allow HTML in table?
  public $allow_html_in_column = array();     // Allow HTML in column? ('field_name' => true/false)
  
  // Result
  public $result;                             // The resulting data array created by retrieve()
  public $num_results;                        // Number of results retrieved
  public $num_results_available;                  // Total number of results available
  
  
  /**
    * Gets the version number of the class
    *
    * @return string  The version number
    */
    
  function get_version() {
    return self::VERSION;
  }
  
  
  /**
    * Sets whether or not to allow HTML code to be parsed in the table
    *
    * @param boolean value  True or false
    */
    
  function set_allow_html_in_table($value) {
    $this->allow_html_in_table = $value;
  }
  
  
  /**
    * Sets whether or not to allow HTML code to be parsed in a particular column
    *
    * @param string field The name of a field in the dataset
    * @param boolean value True or false (defaults to true)
    */
    
  function set_allow_html_in_column($field, $value=true) {
    $this->allow_html_in_column[$field] = $value;
  }
  
  
  /**
    * Sets the name of the class to use for alternate rows in display_as_html_table
    *
    * @param string class  The name of the CSS class
    */
    
  function set_alternate_row_class($class) {
    $this->alternate_row_class = $class;
  }
  
  
  /**
    * Sets a custom CSS class for the table displayed using display_as_html_table
    *
    * @param string class   The name of the CSS class
    */
  
  function set_css_class($class) {
    $this->css_class = $class;
    return true;
  }  
    
  
  /**
    * Sets the date format for a date field (for PHP's date() function)
    *
    * @param string field The field name
    * @param string date_format The date format
    */
    
  function set_date_format($field, $date_format) {
    $this->date_formats[$field] = $date_format;
  }
    
  
  /**
    * Should display_as_html_table show table headings?
    *
    * @param boolean display_headings True if headings should be displayed in <th> tags, false for no headings
    */
  
  function set_display_headings($val) {
    $this->display_headings = $val;
    return true;
  }  

    
  /**
    * Sets which fields to be displayed by display_as_html_table.
    * Accepts a variable-length list of arguments.
    *
    * @param string field   Name of a field.  Must provide at least one.
    *
    * @return boolean True on success, false on failure
    */
    
  function set_fields_to_display($field) {
    
    $args = func_get_args();
    
    if($this->check_args($args) == true) {
      $this->fields_to_display = $args;
      return true;
    }
    else {
      return false;
    }
    
  }
  
  
  /**
    * Sets the fields to retrieve from the file or database table.
    * Accepts a variable-length list of arguments.
    *
    * @param string field   Name of a field.  Must provide at least one.
    *
    * @return boolean True on success, false on failure
    */
    
  function set_fields_to_retrieve($field) {
    
    $args = func_get_args();
    
    if(func_num_args() == 1 && gettype($args[0]) == 'array') {
      
      $this->fields_to_retrieve = $args[0];
      return true;
      
    } else if($this->check_args($args, "string") == true) {
      
      $this->fields_to_retrieve = $args;
      return true;
      
    }
    else {
      return false;
    }
    
  }
  
  
  /**
    * Changes the heading associated with a particular field.
    *
    * @param string field_name    The name of the field
    * @param string heading       The heading to display
    */
  
  function set_heading($field_name, $heading) {
    $this->headings[$field_name] = $heading;
  }
  
  
  /**
    * Sets the headings for the table outputted by display_as_html_table.
    * Accepts a variable-length list of arguments.  Order must correspond to the fields in the display order.
    *
    * @param string heading   A heading.  Must provide at least one.
    *
    * @return boolean True on success, false on failure
    */
    
  function set_headings($heading) {
    
    $args = func_get_args();
    
    // Set headings using an associative array (e.g. "first_name" => "First Name")
    if(func_num_args() == 1 && gettype($args[0]) == 'array') {
      
      foreach($args[0] as $field_name => $heading) {
        $this->set_heading($field_name, $heading);
      }
      
      return true;
    }
    
    // Set headings in order (e.g. "First name", "Last name", "E-mail")
    else if($this->check_args($args, "string") == true) {
      
      for($i=0; $i < sizeof($args); $i++) {
        $this->headings[$i] = $args[$i];
      }
      
      return true;
    }
    
    // Unsupported operation
    else {
      return false;
    }
  }
  
  
  /**
    * Sets the CSS class for the row of headings displayed by display_as_html_table
    *
    * @param string class   The CSS class
    */
  
  function set_headings_class($class) {
    $this->headings_class = $class;
  }
  
  
  /**
    * Limits the result to a certain number of rows.
    *
    * @param int num  The number of rows to return
    */
    
  function set_limit($num) {
    if($num >= 0) {
      $this->limit = $num;
      return true;
    }
    else {
      throw new Exception("Invalid argument for StanfordData::set_limit: " . $num);
    }
  }
  
  
  /**
    * Start returning rows at a certain offset
    *
    * @param int num  The offset
    */
    
  function set_offset($num) {
    if($offset >= 0) {
      $this->offset = $num;
      return true;
    }
    else {
      throw new Exception("Invalid argument for StanfordData::set_offset: " . $num);
    }
  }
  
  
  /**
    * Sets the page number of the resultset.  Limit must be set (using set_limit) before calling set_page so that an offset may be computed.
    *
    * @param int page   The page number
    */
    
  function set_page($page) {
    
    // Check page and limit values
    if($page > 0 && $this->limit > 0) {
      
      // Compute the offset
      $this->page = $page;
      $this->offset = ($page - 1) * $this->limit;
      
    }
    else {
      if($this->limit <= 0) {
        throw new Exception("Limit must be set before calling set_page (use StanfordData::set_limit)");
      }
      else if($page <= 0) {
        throw new Exception("Page number must be nonzero integer");
      }
    }
  }

  
  /**
    * Sets the data type of a field.  Used for identifying dates for proper sorting.
    *
    * @param string field_name  The name of the field
    * @param int data_type      The type of data (StanfordData::DATE or StanfordData::UNDEF)
    */
    
  function set_type($field_name, $data_type) {
    $this->types[$field_name] = $data_type;
  }

  
  /**
    * Adds a preprocessing/formatting function to be called before displaying a field in display_as_html_table.
    * May add multiple display functions for one field (they will be called in order given).
    * When setting parameters, use the string '%FIELD%' to match the value of the current cell.
    * For example, $data->add_display_function("my_date_field", "date", array("Y-m-d", "%FIELD%")).
    *
    * @param string field_name    The name of the field/column
    * @param string function_name The name of the function to call
    * @param array params         List of arguments (optional)
    */
    
  function add_display_function($field_name, $function_name, $params=0) {
  
    // Initialize array
    if(!$this->display_functions[$field_name]) {
      $this->display_functions[$field_name] = array();
    }
    
    // Create new entry
    $index = sizeof($this->display_functions[$field_name]);
    $this->display_functions[$field_name][$index] = array();
    
    // Set function name
    $this->display_functions[$field_name][$index]['function'] = $function_name;
  
    // Set parameters to function
    if($params) {
      
      // Parameters given explicitly
      $this->display_functions[$field_name][$index]['params'] = $params;
    }
    else {
      
      // No parameters -- assuming 'function_name($field_value);'
      $this->display_functions[$field_name][$index]['params'] = array('%FIELD%');
    }
    
  }
  
  
  /**
    * Sets which field(s) to order the resulting dataset by.
    * Call sort_by multiple times to order by multiple parameters.
    *
    * @param string field     The field name
    * @param string direction The direction - StanfordData::ASCENDING (default) or StanfordData::DESCENDING.
    */
    
  function sort_by($field, $direction="ASC") {
    
    // Check field
    if(!$field) {
      throw new Exception("Field name is null");
    }
    
    // Check direction
    if($direction != StanfordData::ASCENDING && $direction != StanfordData::DESCENDING) {
      throw new Exception("Invalid sort direction: " . $direction);
    }
    
    // Add clause to end of order_by list
    $this->order_by[] = array('field' => $field, 'direction' => $direction);
  }
  
  
  /**
    * Gets the data type of a particular field.  Used for dates.
    *
    * @return int   The StanfordData code representing the data type (DATE or UNDEF)
    */
    
  function get_field_type($field_name) {
    if(isset($this->types[$field_name]) == true) {
      return $this->types[$field_name];
    }
    else {
      return self::UNDEF;
    }
  }
  
  
  /**
    * Gets the number of results returned
    *
    * @return int The number of results returned
    */
    
  function get_num_results() {
    return $this->num_results;
  }
    
  
  /**
    * Gets the total number of results available (useful when using set_limit)
    *
    * @return int The total number of results found
    */
    
  function get_num_results_available() {
    return $this->num_results_available;
  }
  
  
  /**
    * Outputs the selected data as an HTML table
    *
    * @param boolean output  Display the table (true) or just return the HTML code (false). Default is true.
    */
    
  function display_as_html_table($output = true) {
    
    // Initialize $html
    $html = '';
    
    // Get the data if get_selected has not been called yet
    if($this->is_populated_array($this->result) == false) {
      
      $this->retrieve();
      
      // Check once more to ensure that there is data to display
      if($this->is_populated_array($this->result) == false) {
        return;
      }
    }
    
    // Get the data
    $data = $this->result;
    
    // Get keys..
    if($this->is_populated_array($this->fields_to_display) == true) {
      
      // From list of fields to display
      $keys = $this->fields_to_display;
    }
    else {
      
      // From the data itself, when no list has been specified
      $keys = array_keys($data[0]);
    }
    
    // Start displaying table
    if($this->css_class != '') {
      $html .= "<table class='" . $this->css_class . "' cellpadding='0' cellspacing='0' border='0'>\n";
    }
    else {
      $html .= "<table cellpadding='0' cellspacing='0' border='0'>\n";
    }
    
    // Display headings
    if($this->display_headings == true && $this->is_populated_array($keys) == true) {
      
      $html .= "\t<tr class = '$this->headings_class'>\n";
      
      foreach($keys as $index => $key) {
        
        if(isset($this->headings[$key])) {
          $heading = $this->headings[$key];
        }
        else if(isset($this->headings[$index])) {
          $heading = $this->headings[$index];
        }
        else {
          $heading = $key;
        }
        
        $html .= "\t\t<th>$heading</th>\n";
      }
      
      $html .= "\t</tr>\n";
      
    }
    
    // Initialize counter for displaying alternate rows
    $alternate = false;
    
    // Display data
    foreach($data as $row) {
      
      if($alternate == true) {
        $html .= "\t<tr class='$this->alternate_row_class'>\n";
        $alternate = false;
      }
      else {
        $html .= "\t<tr>\n";
        $alternate = true;
      }
      
      foreach($keys as $key) {
        
        // Get data to display
        $cell = $row[$key];
        
        // Execute display functions
        if(sizeof($this->display_functions[$key])) {
          
          // Loop through each display function
          foreach($this->display_functions[$key] as $function) {
            
            // Replace %FIELD% placeholder with cell value
            foreach($function['params'] as $index => $value) {
              if($value == '%FIELD%') {
                $function['params'][$index] = $cell;
              }
            }
            
            // Call the function and set the cell to the new value
            $cell = call_user_func_array($function['function'], $function['params']);
          }
          
        }
        
        // Set date format
        else if(sizeof($this->date_formats) && isset($this->date_formats[$key])) {
          $cell = date($this->date_formats[$key], strtotime($row[$key]));
        }
         
        // Escape HTML
        if(isset($this->allow_html_in_column[$key])) {
          if($this->allow_html_in_column[$key] == false) {
            $cell = htmlspecialchars($cell);
          }
        }        
        else if($this->allow_html_in_table == false) {
          $cell = htmlspecialchars($cell);
        }
        
        // Display the cell
        $html .= "\t\t<td>$cell</td>\n";
      }
      
      $html .= "\t</tr>\n";
      
    }
    
    $html .= "</table>\n";
    
    // Display the table
    if($output == true) {
      echo $html;
    }
    
    // Return the HTML code
    return $html;
    
  }
  
  
  /**
    * Checks if a variable is an array and contains data
    *
    * @return boolean True if populated array, false otherwise
    */
    
  function is_populated_array($array) {
    return (is_array($array) && sizeof($array) > 0);
  }
  
  
  /**
    * Checks existence of function arguments and optionally verifies the type of data
    *
    * @param array args         The function arguments
    * @param string data_type   PHP data type
    */
    
  function check_args($args, $data_type = '') {
    
    // If array is populated
    if(sizeof($args) > 0) {
      
      // Check data type of each argument
      if($data_type != '') {
        foreach($args as $arg) {
          $arg_type = gettype($arg);
          if($arg_type != $data_type) {
            
            // Wrong data type
            throw new Exception("Invalid data type for $arg: $arg_type, expected $data_type");
            return false;
          }
        }
      }
      
      // Valid array
      return true;
    }
    else {
      
      // Empty array
      return false;
    }
    
  }
    
};


?>
