<?php
// +----------------------------------------------------------------------+
// | Copyright (c) 2011 Digital Spy Ltd                                   |
// +----------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or        |
// | modify it under the terms of the GNU Lesser General Public           |
// | License as published by the Free Software Foundation; either         |
// | version 2.1 of the License, or (at your option) any later version.   |
// |                                                                      |
// | This library is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU    |
// | Lesser General Public License for more details.                      |
// |                                                                      |
// | You should have received a copy of the GNU Lesser General Public     |
// | License along with this library; if not, write to the Free Software  |
// | Foundation, Inc., 59 Temple Place, Suite 330,Boston,MA 02111-1307 USA|
// +----------------------------------------------------------------------+
// | Authors: Digital Spy <engineering.software@digitalspy.co.uk>         |
// +----------------------------------------------------------------------+
//

/**
 * An SQL parser to split up joined MySQL query statements
 * into single select queries, dynamically.
 *
 * @name SQL Parser
 * @author Digital Spy <engineering.software@digitalspy.co.uk>
 * @version 0.1
 * @credit Xataface Web Application Framework for PHP and MySQL (C) 2005-2010  Steve Hannah <shannah@sfu.ca>
 */

// Load SQL Parser
include_once("SQL/Parser.php");
include_once("SQL/Compiler.php");

class DS_SQL_Parser {
	
	/*
	 * SQL parser instance
	 */
	private $sql_parser;
	
	/*
	 * Key = table name
	 * Value = array of table fields to return
	 */
	private $tables = array();
	
	/*
	 * Key = table name
	 * Value = array of actual table fields from table
	 */
	private $table_cols = array();
	
	/*
	 * Key = table name
	 * Value = alias table name
	 */
	private $tables_alias = array();
	
	/*
	 * Table from
	 */
	private $table_from = "";
	
	/*
	 * Key = table name
	 * Value = array of table fields to actually select from for all required data
	 */
	private $fields = array();
	
	/*
	 * Key = table name . field name
	 * Value = alias field name
	 */
	private $alias = array();
	
	/*
	 * Key = table name
	 * Value = join statement
	 */
	private $joins = array();
	
	/*
	 * Key = table name
	 * Value = join type
	 */
	private $join_types = array();
	
	/*
	 * Key = table name
	 * Value = where statement
	 */
	private $where = array();
	
	/*
	 * Table name . field name ASC/DESC
	 */
	private $order = array();
	
	/*
	 * List of supported functions
	 */
	private $supported_functions = array('count', 'sum', 'if');
	
	/*
	 * Functions to perform
	 */
	private $functions = array();
	
	/*
	 * Limit string
	 */
	private $limit = "";
	
	/*
	 * Last table name used to parse WHERE
	 */
	private $last_table_name = "";
	
	/*
	 * Debug
	 */
	private $debug = false;
	
	/*
	 * Constructor
	 */
	public function __construct() {
		
		// Set an instance of the SQL parser, turns SQL into an array
		$this->sql_parser = new SQL_Parser(null, "MySQL");
		
		// Turn on debugging
		if (isset($_GET['debug'])) $this->debug = true;
	}
	
	/*
	 * Reset all variables
	 */
	private function reset() {
		$this->tables = array();
		$this->table_cols = array();
		$this->tables_alias = array();
		$this->table_from = "";
		$this->fields = array();
		$this->alias = array();
		$this->joins = array();
		$this->join_types = array();
		$this->where = array();
		$this->order = array();
		$this->functions = array();
		$this->limit = "";
		$this->last_table_name = "";
	}
	
	/*
	 * Parse the query and split it into the appropriate arrays
	 * @param $sql the SQL string
	 */
	public function parse($sql) {
		
		// Parse the SQL using SQL parser
		$arr = $this->sql_parser->parse($sql);
		
		// Create table references
		for ($i = 0; $i < count($arr['table_names']); $i++) {
			$table_name = $arr['table_names'][$i];
			$this->tables[$table_name] = array();
			if ($i == 0) $this->table_from = $table_name;
			
			// Alias
			if ($arr['table_aliases'][$i] !== "") {
				$this->tables_alias[$arr['table_aliases'][$i]] = $table_name;
			}
		}
		
		// Select the table columns
		$this->table_cols = $this->selectColumns();
		
		// Save SELECT
		foreach ($arr['columns'] as &$column) {
			
			// Function
			if ($column['type'] == "func") {
				if (in_array(strtolower($column['value']['name']), $this->supported_functions)) {
					
					// Solve unknown field names
					foreach ($column['value']['args'] as &$arg) {
						if ($arg['type'] == "ident" && $arg['value'] !== '*') {
							if ($field = $this->lookupField($arg['value'])) {
								$arg['value'] = $field;
							}
						}
					}
					
					$this->functions[] = array('name' => strtolower($column['value']['name']), 'args' => $column['value']['args'], 'alias' => $column['alias']);
				}					
				else {
					throw new Exception('Unsupported function ' . $column['value']['name']);
					return false;
				}
			}
			else {
				
				// All fields
				if ($column['value'] == '*') {
					if ($column['table'] == "") {
						foreach ($this->table_cols as $table_name => $fields) {
							foreach ($fields as $field) {
								if (!in_array($field, $this->tables[$table_name])) {
									$this->tables[$table_name][] = $field;
								}
							}
						}
					}
					else {
						if ($table_name = $this->lookupTableFromTable($column['table'])) {
							foreach ($this->table_cols[$table_name] as $field) {
								if (!in_array($field, $this->tables[$table_name])) {
									$this->tables[$table_name][] = $field;
								}
							}							
						}
					}
				}
				else {
				
					// Solve unknown table name
					if ($column['table'] == "") {
						if ($table_name = $this->lookupTableFromField($column['value'])) {
							$column['table'] = $table_name;
						}
					}
					
					// Solve table alias
					else {
						if ($table_name = $this->lookupTableFromTable($column['table'])) {
							$column['table'] = $table_name;
						}
					}
					
					// Save table fields
					if (!in_array($column['value'], $this->tables[$column['table']])) {
						$this->tables[$column['table']][] = $column['value'];
					}
					
					// Save field alias
					if ($column['alias'] !== "") {
						$this->alias[$column['table'] . "." . $column['value']] = $column['alias'];
					}
				}
			}
		}
		
		// Attempt to solve unknown field names in JOIN
		foreach ($arr['table_join_clause'] as $key => &$join_array) {
			if (is_array($join_array)) {
				foreach ($join_array as $j_key => &$j_array) {
					if (substr($j_key, 0, 3) == "arg" && $j_array['type'] == "ident") {
						if ($field = $this->lookupField($j_array['value'])) {
							$j_array['value'] = $field;
						}
					}
				}		
			}
		}
		
		// Save JOIN
		$i = 0;
		$joins_done = array($this->table_from);
		foreach ($arr['table_join_clause'] as $key => $join_arr) {
			if (is_array($join_arr)) {
				$str = "";
				$table_name = "";
				foreach ($join_arr as $j_key => $j_arr) {
					if (substr($j_key, 0, 3) == "arg" && $j_arr['type'] == "ident") {
						
						// This would be the table joined regardless of which way round the statment is
						if ($table = $this->lookupTableFromField($j_arr['value'])) {
							if (!in_array($table, $joins_done)) {
								$table_name = $table;
								$joins_done[] = $table_name;
							}
						}
						
						// Part of join statement
						$str .= $j_arr['value'] . " ";
					}
					
					// Join operator
					elseif ($j_key == "op") {
						$str .= $j_arr . " ";
					}
				}
				
				// Save join string and join type
				$str = rtrim($str);
				$type = explode(" ", $arr['table_join'][$i]);
				$this->joins[$table_name] = $str;
				$this->join_types[$table_name] = strtoupper($type[0]);
				$i++;
			}
		}
		
		// Save WHERE (recursive function)
		if (isset($arr['where_clause']) && !empty($arr['where_clause'])) {
			$this->where = $this->parseWhere($arr['where_clause']);
		}
		
		// Save ORDER BY
		if (isset($arr['sort_order']) && !empty($arr['sort_order'])) {
			foreach ($arr['sort_order'] as &$order_array) {				
				if ($field = $this->lookupField($order_array['value'])) {
					$order_array['value'] = $field;
				}
				
				$this->order[] = $order_array['value'] . " " . strtoupper($order_array['order']);
			}
		}
		
		// Save Limit
		if (isset($arr['limit_clause']) && !empty($arr['limit_clause'])) {
			if (isset($arr['limit_clause']['start']) && isset($arr['limit_clause']['length'])) {
				$this->limit = $arr['limit_clause']['start'] . ", " . $arr['limit_clause']['length'];
			}
			elseif (isset($arr['limit_clause']['length'])) {
				$this->limit = $arr['limit_clause']['length'];
			}
		}
		
		if ($this->debug) {
			$this->show_vars();
		}
		
		// Now execute the queries
		return $this->execute();
	}
	
	/*
	 * Show class variables for debugging
	 */
	private function show_vars() {
		$vars = get_class_vars(get_class($this));
		foreach ($vars as $key => $value) {
			if ($key !== "sql_parser") {
				echo "-----------------\n" . $key . ":\n-----------------\n" . print_r($this->$key, true) . "\n\n";
			}
		}
	}
	
	/*
	 * Execute the queries and return the results
	 */
	private function execute() {
		
		// Select the actual fields required for all select queries
		foreach ($this->tables as $table_name => $table_fields) {
			$this->fields[$table_name] = $table_fields;
		}
		
		// In JOIN
		foreach ($this->joins as $table_name => $join_fields) {
			$j = $this->explodeJoin($join_fields);
			if (@!in_array($j['from_field'], $this->fields[$j['from_table']])) {
				$this->fields[$j['from_table']][] = $j['from_field'];
			}
			if (@!in_array($j['to_field'], $this->fields[$j['to_table']])) {
				$this->fields[$j['to_table']][] = $j['to_field'];
			}
		}
		
		// In ORDER BY
		foreach ($this->order as $ord) {
			$f = $this->fieldName($ord);
			if (@!in_array($f['field'], $this->fields[$f['table']])) {
				$this->fields[$f['table']][] = $f['field'];
			}	
		}
		
		// In functions
		foreach ($this->functions as $func) {
			foreach ($func['args'] as $arg) {
				if ($arg['type'] == "ident") {
					$table_name = $this->lookupTableFromField($arg['value']);
					if (@!in_array($arg['value'], $this->fields[$table_name])) {
						$this->fields[$table_name][] = $arg['value'];
					}
				}
			}
		}
		
		// Create from table query
		$sql = "SELECT " . implode(", ", $this->fields[$this->table_from]) . " FROM " . $this->table_from;
		if (isset($this->where[$this->table_from])) $sql .= " WHERE " . $this->where[$this->table_from];
		$queries = array($sql);
		
		// Create subsequent queries
		foreach ($this->tables as $table_name => $table_fields) {
			if ($table_name !== $this->table_from) {
				
				$sql = "SELECT " . implode(", ", $this->fields[$table_name]) . " FROM " . $table_name;
				$join_where = false;
				
				// Add WHERE for joined field
				if (isset($this->joins[$table_name])) {
					$j = $this->explodeJoin($this->joins[$table_name]);
					$sql .= " WHERE " . $j['to_field'] . " = {%" . $j['from_table'] . "." . $j['from_field'] . "%}";
					$join_where = true;
				}
				
				// Add custom WHERE
				if (isset($this->where[$table_name])) {
					$sql .= (!$join_where) ? " WHERE " : " AND ";
					$sql .= $this->where[$table_name];
				}
				
				$queries[] = $sql;
			}
		}
		
		if ($this->debug) {
			echo "-----------------\nQueries:\n-----------------\n" . print_r($queries, true) . "\n\n";
		}
		
		// Run main query
		$results = array();
		$sql = $queries[0];
		$query = mysql_query($sql);
		while ($row = mysql_fetch_array($query, MYSQL_ASSOC)) {
			
			// Row array key should have table name prefix
			$data = array();
			foreach ($row as $key => $value) {
				$data[$this->table_from . "." . $key] = $value;
			}
			$row = $data;
			
			// Loop through each subsequent query
			for ($i = 1; $i < count($queries); $i++) {
				
				$sql_next = $queries[$i];
				$table_name = $this->tableName($sql_next);
				
				// Replace field references to actual row data
				if (preg_match_all('/\{%([a-zA-Z0-9_.]+)%\}/', $sql_next, $matches)) {
					for ($m = 0; $m < count($matches[0]); $m++) {
						if (array_key_exists($matches[1][$m], $row)) {
							$sql_next = str_replace($matches[0][$m], $row[$matches[1][$m]], $sql_next);
						}
					}
				}
				
				if ($this->debug) {
					echo "-----------------\n" . $sql_next . "\n\n";
				}
				
				// Perform query
				$query_next = mysql_query($sql_next);
				if ($query_next !== false) {
					$row_next = mysql_fetch_array($query_next, MYSQL_ASSOC);
				}
				else {
					throw new Exception('MySQL Error: ' . mysql_error() . '<br />Query: ' . $sql_next);
					return false;
				}
				
				if ($row_next !== false) {
					
					// Row array key should have table name prefix
					$data = array();
					foreach ($row_next as $key => $value) {
						$data[$table_name . "." . $key] = $value;
					}
					
					// Merge into row
					$row = array_merge($row, $data);
				}
				
				// No query results
				else {
					
					// INNER JOIN rule says no row should be returned or if there's a WHERE statement
					if ($this->join_types[$table_name] == "INNER" || isset($this->where[$table_name])) {
						$row = false;
						break;
					}
					
					// Other JOIN rule says the row should have null values
					else {
						$data = array();
						foreach ($this->fields[$table_name] as $field) {
							$data[$table_name . "." . $field] = null;
						}
						$row = array_merge($row, $data);
					}
				}
			}
			
			if ($row !== false) $results[] = $row;
		}
		
		// Order results
		if (!empty($results) && !empty($this->order)) {
			
			$params = array($results);
			
			foreach ($this->order as $ord) {
				if (strpos($ord, " ") !== false) {
					$x = explode(" ", $ord);
					$col = trim($x[0]);
					$sort = (strtoupper(trim($x[1])) == "DESC") ? SORT_DESC : SORT_ASC;
				}
				else {
					$col = trim($ord);
					$sort = SORT_ASC;
				}
				
				$params[] = $col;
				$params[] = $sort;
			}
			
			$results = call_user_func_array(array($this, 'sortArray'), $params);
		}
			
		// Apply functions to results
		$results = $this->applyFunctions($results);
			
		if (!empty($results)) {
			
			// Limit results
			if ($this->limit !== "") {
				$results = $this->limitArray($results, $this->limit);
			}
			
			// Clean up results
			$results = $this->cleanArray($results);
		}
		
		return $results;
	}
	
	/*
	 * Return the table columns
	 */
	private function selectColumns() {
		$out = array();
		foreach ($this->tables as $table_name => $table_fields) {
			$sql = "SHOW COLUMNS FROM " . $table_name;
			$result = $this->databaseQuery($sql);
			foreach ($result as $row) {
				$out[$table_name][] = $row['Field'];
			}
		}
		return $out;
	}
	
	/*
	 * Parse the WHERE statement
	 * @param $arr the where clause array
	 */
	private function parseWhere($arr) {
		$out = array();
		if (is_array($arr)) {			
			foreach ($arr as $key => $value) {
				
				if ($key == "op") {
					$out[$this->last_table_name] .= $value . " ";
				}
				
				elseif (is_array($value)) {
					
					if (!array_key_exists('value', $value) || is_array($value['value'])) {
						$row = $this->parseWhere($value);
						if (!empty($row)) {
							foreach ($row as $row_table_name => $row_value) {
								$out[$row_table_name] .= $row_value;
							}
						}
					}
					
					else {
		
						// Solve unknown field names
						if ($value['type'] == "ident") {
							if ($field = $this->lookupField($value['value'])) {
								$value['value'] = $field;
							}
							
							$this->last_table_name = $this->lookupTableFromField($value['value']);
						}
						
						// Escape strings
						elseif ($value['type'] == "text_val") {
							$value['value'] = "'" . addslashes($value['value']) . "'";
						}
						
						// Null
						elseif ($value['type'] == "null") {
							$value['value'] = "null";
						}
						
						// Add value to statement
						$out[$this->last_table_name] .= $value['value'] . " ";
					}
				}
			}
		}
		
		return $out;
	}
	
	/*
	 * Return table.field from table.field or field
	 * @param $value the field name
	 */
	private function lookupField($value) {
		
		// Table.field
		if (strpos($value, ".") !== false) {
			
			list($table_name, $field) = explode(".", $value);
			
			// Table is in alias
			if (array_key_exists($table_name, $this->tables_alias)) {
				$table_name = $this->tables_alias[$table_name];
			}
			
			// Check table exists
			if (!array_key_exists($table_name, $this->table_cols)) {
				throw new Exception('Table ' . $table_name . ' not recognised');
				return false;
			}
			
			// Field does not exist but does in alias
			if (!in_array($field, $this->table_cols[$table_name])) {
				$alias_found = false;
				foreach ($this->alias as $key => $value) {
					if (substr($key, 0, strlen($table_name)) == $table_name && $value == $field) {
						list($table_name, $field) = explode(".", $key);
						$alias_found = true;
						break;
					}
				}
				if (!$alias_found) {
					throw new Exception('Field ' . $field . ' not recognised');
					return false;
				}
			}
		}
		
		// Single field
		else {
			
			$table_name = "";
			$field = $value;
			
			// Field is in alias
			$alias_found = false;
			foreach ($this->alias as $key => $value) {
				if ($value == $field) {
					list($table_name, $field) = explode(".", $key);
					$alias_found = true;
					break;
				}
			}
			
			// Field is in columns
			if (!$alias_found) {
				$field_found = false;
				foreach ($this->table_cols as $table_name => $table_fields) {
					foreach ($table_fields as $table_field) {
						if ($table_field == $field) {
							$field_found = true;
							break 2;
						}
					}
				}
				if (!$field_found) {
					throw new Exception('Field ' . $field . ' not recognised');
					return false;
				}
			}
		}
		
		return $table_name . "." . $field;
	}
	
	/*
	 * Return table name from table name
	 * @param $value the table name
	 */
	private function lookupTableFromTable($value) {
		
		// Table is in alias
		if (array_key_exists($value, $this->tables_alias)) {
			$value = $this->tables_alias[$value];
		}
		
		// Check table exists
		if (!array_key_exists($value, $this->table_cols)) {
			throw new Exception('Table ' . $value . ' not recognised');
			return false;
		}
		
		return $value;
	}
	
	/*
	 * Return table name from field name
	 * @param $value the field name
	 */
	private function lookupTableFromField($value) {
		
		// Already table name . field name
		if (strpos($value, ".") !== false) {
			list($table_name, $field) = explode(".", $value);
			return $table_name;
		}
		
		// Lookup by field name
		else {
			foreach ($this->table_cols as $table_name => $fields) {
				foreach ($fields as $field) {
					if ($field == $value) {
						return $table_name;
					}
				}
			}
		}
		
		return false;
	}
	
	/*
	 * Return the table name from an SQL string
	 * @param $sql the SQL string
	 */
	private function tableName($sql) {
		if (preg_match('/FROM\s+(\w+)/i', $sql, $matches)) {
			return trim($matches[1]);
		}
	}
	
	/*
	 * Return the join parts as an array from a string
	 * @param $str the join string
	 */
	private function explodeJoin($str) {
		$x = explode("=", $str);
		$from = trim($x[0]);
		$to = trim($x[1]);
		list($from_table, $from_field) = explode(".", $from);
		list($to_table, $to_field) = explode(".", $to);
		return array('from_table' => $from_table, 'from_field' => $from_field, 'to_table' => $to_table, 'to_field' => $to_field);
	}
	
	/*
	 * Return the table and field as an array from a string
	 * @param $str the string
	 */
	private function fieldName($str) {
		$x = explode(".", $str);
		$table = trim($x[0]);
		$field = trim($x[1]);
		
		if (strpos($field, " ") !== false) {
			$x = explode(" ", $field);
			$field = trim($x[0]);
		}
		
		return array('table' => $table, 'field' => $field);
	}
	
	/*
	 * Sort an array from multiple parameters
	 */
	private function sortArray() {
		$args = func_get_args();
		$data = array_shift($args);
		
		foreach ($args as $n => $field) {
			if (is_string($field)) {
				$tmp = array();
				foreach ($data as $key => $row) {
					$tmp[$key] = $row[$field];
				}
				$args[$n] = $tmp;
			}
		}
		
		$args[] = &$data;
		
		// Make the sort case insensitive
		for ($i = 0; $i < count($args) - 1; $i++) {
			if (is_array($args[$i])) {
				$args[$i] = array_map('strtolower', $args[$i]);
			}
		}
	
		call_user_func_array('array_multisort', $args);
		return array_pop($args);
	}
	
	/*
	 * Limit an array from a limit string
	 * @param $data the array
	 * @param $limit the limit string
	 */
	private function limitArray($data, $limit) {
		if (strpos($limit, ",") !== false) {
			$x = explode(",", $limit);
			$offset = (int) trim($x[0]);
			$length = (int) trim($x[1]);
		}
		else {
			$offset = 0;
			$length = (int) trim($limit);
		}
		$data = array_splice($data, $offset, $length);
		return $data;
	}
	
	/*
	 * Apply functions on the array
	 * @param $data the array
	 */
	private function applyFunctions($data) {
		
		foreach ($this->functions as $func) {
			
			// COUNT
			if ($func['name'] == "count") {
				$count = count($data);
				$field = 'COUNT(' . $func['args'][0]['value'] . ')';
				$data[0][$this->table_from . "." . $field] = $count;
				$data = $this->limitArray($data, 1);
				$this->addField($this->table_from, $field, $func['alias']);
			}
			
			// SUM
			elseif ($func['name'] == "sum") {
				$sum = 0;
				foreach ($data as $row) {
					$sum += $row[$func['args'][0]['value']];
				}
				$field = 'SUM(' . $func['args'][0]['value'] . ')';
				$data[0][$this->table_from . "." . $field] = $sum;
				$data = $this->limitArray($data, 1);
				$this->addField($this->table_from, $field, $func['alias']);
			}
			
			// IF
			elseif ($func['name'] == "if") {
				
				$field = 'IF(';				
				$field .= $func['args'][0]['value'] . $func['args'][1]['value'];
				$field .= ($func['args'][2]['type'] == 'text_val') ? "'" . addslashes($func['args'][2]['value']) . "'" : $func['args'][2]['value'];
				$field .= ',';
				$field .= ($func['args'][3]['type'] == 'text_val') ? "'" . addslashes($func['args'][3]['value']) . "'" : $func['args'][3]['value'];
				$field .= ',';
				$field .= ($func['args'][4]['type'] == 'text_val') ? "'" . addslashes($func['args'][4]['value']) . "'" : $func['args'][4]['value'];
				$field .= ')';
				
				$this->addField($this->table_from, $field, $func['alias']);
				
				foreach ($data as &$row) {
					
					if ($func['args'][0]['type'] == 'ident') {
						$field_from = $row[$func['args'][0]['value']];
					}
					else {
						$field_from = $func['args'][0]['value'];
					}
					
					if ($func['args'][2]['type'] == 'ident') {
						$compare_to = $row[$func['args'][2]['value']];
					}
					else {
						$compare_to = $func['args'][2]['value'];
					}
					
					if ($func['args'][3]['type'] == 'ident') {
						$replace_true = $row[$func['args'][3]['value']];
					}
					else {
						$replace_true = $func['args'][3]['value'];
					}

					if ($func['args'][4]['type'] == 'ident') {
						$replace_false = $row[$func['args'][4]['value']];
					}
					else {
						$replace_false = $func['args'][4]['value'];
					}
					
					if ($func['args'][1]['type'] == '=') {
						$out = ($field_from == $compare_to) ? $replace_true : $replace_false;
					}
					elseif ($func['args'][1]['type'] == '<>') {
						$out = ($field_from != $compare_to) ? $replace_true : $replace_false;
					}
					elseif ($func['args'][1]['type'] == '>') {
						$out = ($field_from > $compare_to) ? $replace_true : $replace_false;
					}
					elseif ($func['args'][1]['type'] == '>=') {
						$out = ($field_from >= $compare_to) ? $replace_true : $replace_false;
					}
					elseif ($func['args'][1]['type'] == '<') {
						$out = ($field_from < $compare_to) ? $replace_true : $replace_false;
					}
					elseif ($func['args'][1]['type'] == '<=') {
						$out = ($field_from <= $compare_to) ? $replace_true : $replace_false;
					}
					else {
						$out = null;
					}
					
					$row[$this->table_from . "." . $field] = $out;
				}
			}
		}
		
		return $data;
	}
	
	/*
	 * Add a field to the tables array
	 * @param $table_name the table name
	 * @param $field the field name
	 * @param $alias the alias name
	 */
	private function addField($table_name, $field, $alias = "") {
		if (!in_array($field, $this->tables[$table_name])) {
			$this->tables[$table_name][] = $field;
		}
		
		if ($alias !== "") {
			$this->alias[$table_name . "." . $field] = $alias;
		}
	}
	
	/*
	 * Clean the array removing fields not initially selected and adding aliases
	 * @param $data the array
	 */
	private function cleanArray($data) {
		$out = array();
		$i = 0;
		
		foreach ($data as $row) {
			foreach ($this->tables as $table_name => $table_fields) {
				foreach ($table_fields as $field) {					
					$key = $table_name . "." . $field;
					
					if (array_key_exists($key, $row)) {
						
						// Use alias instead of field name
						if (array_key_exists($key, $this->alias)) {
							$field = $this->alias[$key];
						}
						
						$out[$i][$field] = $row[$key];
					}
				}
			}
			$i++;
		}
		
		return $out;
	}
	
	/*
	 * Perform a database query and return a result set
	 * @param $sql the SQL query
	 */
	public function databaseQuery($sql) {
		$results = array();
		$query = mysql_query($sql);
		while ($row = mysql_fetch_array($query, MYSQL_ASSOC)) {
			$results[] = $row;
		}
		return $results;
	}
}
?>