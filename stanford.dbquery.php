<?php

// Include StanfordData
require_once(dirname(__FILE__) . "/stanford.data.php");

// Include StanfordDatabase
require_once(dirname(__FILE__) . "/stanford.database.php");

/**
  * A simple SQL query builder for retrieving and displaying data from a MySQL table.
  * 
  * @author ddonahue
  *
  * @date October 15, 2008
  * 
  * Copyright 2008,2009 Board of Trustees, Leland Stanford Jr. University
  * See LICENSE for licensing terms.
  *
  */
  
class StanfordDBQuery extends StanfordData {
  
  // Version
  const VERSION = '1.0.0';
  
  // DB options
  private $db = null;
  private $table = '';
  
  public $where_clause;
    
  /**
    * Creates a new StanfordDBQuery
    */
    
  function __construct($db, $table) {
    $this->db = $db;
    $this->table = $table;
    
    if($this->db instanceof StanfordDatabase == true) {
      
      // Connect if not already connected
      $this->db->connect();
      
      // Check connection
      if($this->db->is_connected() == false) {
        throw new Exception("Unable to connect to database");
      }
      
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
    * Gets the database connection used for this query
    *
    * @return MySQLi The database connection
    */
    
  function get_database_connection() {
    return $this->db;
  }

  
  /**
    * Retrieves the selected data from a database.
    *
    * @return array  The selected data
    */
    
  function retrieve() {
    // Check database
    if($this->db instanceof MySQLi == false) {
      throw new Exception("Database connection is invalid");
    }
    
    // Check table
    if($this->table == null) {
      throw new Exception("MySQL table name is not set (use StanfordData::set_source)");
    }
    
    // Construct query
    
    // Table
    $table = $this->db->escape_string($this->table);
    
    // Fields
    if($this->is_populated_array($this->fields_to_retrieve) == true) {
      $fields = $this->fields_to_retrieve;
    }
    else {
      $fields = $this->get_columns();
    }
    
    foreach($fields as $field_name) {
      switch($this->get_field_type($field_name)) {
        case self::UNSIGNED_INT_IP:
          $fields[] = "INET_NTOA($field_name) AS $field_name";
          break;
        default:
          $fields[] = $field_name;
          break;
      }
    }
    
    $fields = $this->db->escape_string(implode(',', $fields));
    
    // Order
    if($this->is_populated_array($this->order_by) == true) {
      
      $order_by = array();
      
      foreach($this->order_by as $order) {
        $order_by[] = $order['field'] . ' ' . $order['direction'];
      }
      
      $order_by = $this->db->escape_string(implode(',', $order_by));
    }
    
    // Limit
    if($this->limit || $this->offset) {
      
      if($this->limit == 0) {
        $limit = $this->offset . ", " . "18446744073709551615";
      }
      
      else {
        $limit = $this->offset . ", " . $this->limit;
      }
      
      $limit = $this->db->escape_string($limit);
    }
    
    // Put it all together
    $base_query = "SELECT [FIELDS] FROM $table";
    
    // Add constraints
    if($this->where_clause) {
      $base_query .= " $this->where_clause";
    }
    
    // Get total number of results
    $count_query = str_replace("[FIELDS]", "COUNT(*) AS num", $base_query);
    
    // Execute the query
    $result = $this->db->query($count_query);
        
    // Check the result
    if($result) {
      
      // Get the total number of results, not factoring in the LIMIT clause
      $result_array = mysqli_fetch_array($result);
      $this->num_results_available = $result_array['num'];
      
    }
    else {
      // Invalid query or unknown error
      throw new Exception("MySQL error: " . $this->db->error);
    }
    
    // Construct a query to get the specified data
    $query = str_replace("[FIELDS]", $fields, $base_query);
    
    // Order by clause
    if($order_by) {
      $query .= " ORDER BY $order_by";
    }
    
    // Limit clause
    if($limit) {
      $query .= " LIMIT $limit";
    }
    
    // Get the result
    $result = $this->db->query($query);
    
    // Check the result
    if($result) {
      
      // Initialize result array
      $this->result = array();
      
      // Save rows
      while($row = mysqli_fetch_assoc($result)) {
                
        $this->result[] = $row;
        
      }
      
      // Get the number of results
      $this->num_results = sizeof($this->result);
      
      // Return the result
      return $this->result;
    } 
    else {
      
      // Invalid query or unknown error
      throw new Exception("MySQL error: " . $this->db->error);
    }
  }
  
  function get_columns() {
    
    // Construct query
    $sql = "SHOW COLUMNS FROM " . $this->db->escape_string($this->table);
    
    $result = $this->db->query($sql);
    
    $cols = array();
    while($row = mysqli_fetch_row($result)) {
      $cnt=0;
      foreach($row as $word) {
        // First word:
        if($cnt==0) {
          array_push($cols, $word);
          $cnt++;
        } else break;
      }
    }
    
    return $cols;
  }
  
  function set_constraints($where_clause) {
    if($where_clause != '') {
      $this->where_clause = "WHERE " . $where_clause;
    }
    else {
      return false;
    }
  }

};

?>