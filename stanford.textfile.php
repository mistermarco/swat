<?php

// Include StanfordData
require_once(dirname(__FILE__) . "/stanford.data.php");


/**
  * StanfordTextFile parses, sorts, and displays CSV and other similarly-formatted files.
  * 
  * @author ddonahue
  *
  * @date October 15, 2008
  * 
  * Copyright 2008,2009 Board of Trustees, Leland Stanford Jr. University
  * See LICENSE for licensing terms.
  *
  */

class StanfordTextFile extends StanfordData {
  
  // Version
  const VERSION = '1.0.0';
  
  // Select options
  public $keys_to_rename = array();           // Custom key names (used for renaming, e.g. 'really long field name' => 'field 1')
  
  // File options
  private $path = '';
  private $delimiter = ',';
  
  // If the source is a file, does the first line contain headings?
  private $file_has_headings = true;
  
  
  /**
    * Creates a new StanfordTextFile
    *
    * @param string path  The path to the file
    */
    
  function __construct($path) {
    $this->path = $path;
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
    * Parses a text file and retrieves selected data.
    *
    * @return array  The selected data
    */
    
  function retrieve() {
    // Check if file exists
    if(!file_exists($this->path)) {
      throw new Exception('File does not exist: ' . $this->path);
    }
    
    // Open text file for read access
    $file = @fopen($this->path, 'r');
   
    // Check if file is open for read access
    if(!$file) {
      throw new Exception('Unable to open file for read access: ' . $this->path);
    }
    
    // Check delimiter (default to comma)
    if($this->delimiter == '') {
      $this->delimiter = ',';
    }
   
    // Get keys from list (using set_field_names)
    if($this->is_populated_array($this->keys) == true) {
      $keys = $this->keys;
    }
        
    // Get keys from headings
    if($this->file_has_headings == true) {
            
      // Get first row of table (headings)
      $row = fgetcsv($file, 4096, $this->delimiter);
      
      if($this->is_populated_array($keys) == false) {
      
        // Count number of fields
        $num = count($row);
        
        // If this is a valid row..
        if($this->line_has_data($row) == true) {
          for($i=0; $i < sizeof($row); $i++) {
            $key_name = $row[$i];
            
            // If key is to be renamed (using set_field_name)
            if(isset($this->keys_to_rename[$key_name]) == true) {
              $key_name = $this->keys_to_rename[$key_name];
            }
            
            $keys[$i] = $key_name;
          }
        }
        
        // If using keys, here is where to check the validity of the field names
        if($this->is_populated_array($this->fields_to_retrieve) == true) {
          foreach($this->fields_to_retrieve as $field_name) {
            if(in_array($field_name, $keys) == false) {
              fclose($file);
              throw new Exception("Invalid field: " . $field_name);
            }
          }
          
        }
      }
    }    
    
          $all_selected_fields = $this->get_all_selected_fields();
          
    // Initialize data array       
    $data = array();
    
    // Initialize index counter
    $index = 0;
    
    // Initialize result counter
    $this->num_results = 0;
    
    // Get data
    while(($row = fgetcsv($file, 4096, $this->delimiter)) != FALSE) {
      
      // Count number of fields
      $num = count($row);
      
      // If there is data on this line..
      if($this->line_has_data($row) == true) {
      
        // Check number of fields
        if($this->is_populated_array($keys) && $num != sizeof($keys)) {
          fclose($file);
          throw new Exception("Key mismatch:  $num fields in row of data, expected " . sizeof($keys) . " (" . implode(", ", $this->keys) . ")");
        }
          
        // Read in each piece of data and store it in data array
        for($j=0; $j < sizeof($row); $j++) {
          
          // Two options - get particular list of fields in particular order..
          if($all_selected_fields) {
            
            // Get the numeric index for correctly ordering the fields in the resultset
            $location = array_search($keys[$j], $all_selected_fields);
            
            if(in_array($keys[$j], $all_selected_fields)) {
              $data[$index][$location] = $row[$j];
            }
          }
          
          // .. or get all fields
          else {
            // With key
            if($this->is_populated_array($keys) == true) {
              $data[$index][$keys[$j]] = $row[$j];
            }
            
            // With numeric index
            else {
              $data[$index][$j] = $row[$j];
            }
          }
        }
          
        // Update index
        $index++;
        
        // Update counter
        $this->num_results_available++;
        
      }
    }
    
    // If using a particular list of fields, must reorder and add associative keys
    if($this->is_populated_array($this->fields_to_retrieve) == true) {
      
      // Go through each row
      foreach($data as $index => $row) {
        
        // Put the fields in the correct order (ksort sorts the array by key - at this point, the keys are numeric and reflect the final ordering)
        ksort($data[$index]);
        
        // Add the associative keys (array_combine maps an array of keys to an array of data, creating an associative array)
        $data[$index] = array_combine($all_selected_fields, $data[$index]);
        
      }
    }
    
    // Sort the array
    if($this->is_populated_array($this->order_by) == true) {
      
      // Do the sorting
      $this->sort($data);

      // Remove any entries not in the list of selected fields (which were used only for sorting)
      if($this->is_populated_array($this->fields_to_retrieve) == true) {
        
        // Go through each row
        foreach($data as $index => $row) {
          
          // Go through each field
          foreach($row as $key => $val) {
            
            // If this field was not explicitly specified, it was used only for sorting, so remove it
            if(in_array($key, $this->fields_to_retrieve) == false) {
              unset($data[$index][$key]);
            }
          }
        }
      }
            
    }
    
    // Get subset of result
    if($this->limit > 0) {
      $data = array_slice($data, $this->offset, $this->limit, true);
    }
    
    // Set the result
    $this->result = $data;
    
    // Set the number of results
    $this->num_results = sizeof($this->result);
    
    // Close the file
    fclose($file);
    
    // Return the result
    return $this->result;
  }
  
  
  /**
    * Sets names for all fields in the file, starting from the beginning. Accepts a variable length list of arguments.
    *
    * @param string field   The name of the field
    */
  
  function set_field_names($field) {
    
    $args = func_get_args();
    
    if($this->check_args($args, "string") == true) {
      $this->keys = $args;
      return true;
    }
    else {
      return false;
    }
    
  }
  
  
  /**
    * Renames a field
    *
    * @param string old_name  The old field name
    * @param string new_name  The new field name
    */
     
  function set_field_name($old_name, $new_name) {
    $this->keys_to_rename[$old_name] = $new_name;
  }
  
  
  /**
    * This flag tells the parser whether to treat the first line as column headings (true) or as data (false)
    *
    * @param boolean val  Set to true if the first line of the file contains headings, false if the first line contains data.
    */
    
  function set_file_has_headings($val) {
    $this->file_has_headings = $val;
   
    return true;
  }
  

  /**
    * Change the data delimiter.  Default is a comma.
    *
    * @param string delimiter   The new delimiter.
    */
    
  function set_delimiter($delimiter) {
    $this->delimiter = $delimiter;
    return true;
  }
  
  
  /**
    * Checks if a line of data from a file is non-empty.
    *
    * @return boolean  True if line contains data, false otherwise
    */
  
  private function line_has_data($row) {
    // Count number of fields
    $num = count($row);
 
    // If this is a valid row..
    if($num > 1 || ($num == 1 && trim($row[0]) != '')) {
      return true;
    }
    else {
      return false;
    }
  }
  
  
  /**
    * Sorts an array based on current settings (use StanfordData::sort_by to configure).
    */
  
  private function sort(&$data) {
    
    // Make sure there is something to sort
    if($this->is_populated_array($this->order_by) == false) {
      return false;
    }
    
    // Initialize the params array - to be sent to PHP function array_multisort as function arguments
    $params = array();
    
    // Go through each order by clause
    foreach($this->order_by as $order_by) {
      
      // Get the field/index and direction
      $index = $order_by['field'];
      $direction = $order_by['direction'];
      
      // Determine the sort direction
      if($direction == StanfordData::ASCENDING) {
        $direction = SORT_ASC;  // Set the direction to SORT_ASC - a PHP constant used by array_multisort
      }
      else if($direction == StanfordData::DESCENDING) {
        $direction = SORT_DESC;
      }
      
      // No direction specified, default is ascending
      else {
        $direction = SORT_ASC;
      }
      
      // Sorting dates
      if($this->get_field_type($index) == self::DATE) {
        $date_mode = true;
      }
      
      // A one dimensional array of the data for one particular field, necessary for array_multisort
      $sort_col = array();
      
      // Go through each row of data
      foreach($data as $row) {
        
        // If sorting dates, change the format to work with sorting
        if($date_mode == true) {
          
          // Create a new DateTime
          $date = new DateTime($row[$index]);
          
          // Normalize the date format
          $sort_col[] = $date->format("Y-m-d H:i:s");
          
        }
        
        // If sorting normally, just get the value from the column
        else {
          $sort_col[] = $row[$index];
        }
        
      }
     
      // Add the data and the direction by which to sort to the parameters array
      $params[] = $sort_col;
      $params[] = $direction;
      
    }
    
    // Add the data array to the list of parameters
    $params[] = &$data;
    
    // Call array_multisort using our constructed parameters
    call_user_func_array("array_multisort", $params);
  }
  
  
  /**
    * Returns a list of all explicitly selected fields (specified in set_fields_to_retrieve and sort_by).
    *
    * @return array The list of field names
    */
    
  function get_all_selected_fields() {
    
    $fields = $this->fields_to_retrieve;
    
    foreach($this->order_by as $order_column) {
      if(in_array($order_column['field'], $this->fields_to_retrieve) == false) {
        $fields[] = $order_column['field'];
      }
    }
    
    return $fields;
    
  }
  
};

?>