<?php

 namespace JSONDb\Classes;

use JSONDb\Classes\Exception;
use JSONDb\Classes\Database;
use JSONDb\Classes\Relation;
use JSONDb\Classes\Helpers;

defined('JSONDB_SECURE') or die('Permission denied!');

 /**
  * Core class of JSONDB project.
  * 
  * There are classes to use JSON files like file database.
  * 
  * Using style was inspired by ORM classes.
  *
  * @category Core
  * @author Grzegorz Kuźnik
  * @copyright (c) 2013, Grzegorz Kuźnik
  * @license http://opensource.org/licenses/MIT The MIT License
  */
 abstract class Core_Database implements \IteratorAggregate, \Countable {

     /**
      * Contain returned data from file as object or array of objects
      * @var mixed Data from table
      */
     protected $_data;

     /**
      * Name of file (table)
      * @var string Name of table
      */
     protected $_name;

     /**
      * Object with setted data
      * @var object Setted data
      */
     protected $_set;

     /**
      * ID of current row if setted
      * @var integer Current ID
      */
     protected $_current_id;

     /**
      * Key if current row if setted
      * @var integer Current key
      */
     protected $_current_key;

     /**
      * All pending functions parameters in right order
      * @var array
      */
     protected $_pending = array(
         'where' => array(),
         'order_by' => array(),
         'limit' => array(),
         'group_by' => array(),
         'with' => array(),
     );

     /**
      * Information about to reset keys in array or not to
      * @var integer
      */
     protected $_reset_keys = 1;

     /**
      * Factory pattern
      * @param string $name Name of table
      * @return \Jsondb
      * @throws Exception If there's problems with load file
      */
     public static function table($name)
     {
         Helpers\Validate::name($name)->exists();

         $self = new Database;
         $self->_name = $name;

         $self->_set_fields();
//         $self->_data = Helpers\Data::name($self->_name)->get();

         return $self;
     }

     protected function _get_data()
     {
         return Helpers\Data::name($this->_name)->get();
     }

     protected function _set_data()
     {
         $this->_data = $this->_get_data();
     }

     /**
      * Returns array key of row with specified ID
      * @param integer $id Row ID
      * @return integer Row key
      * @throws Exception If there's no data with that ID
      */
     protected function _get_row_key($id)
     {
         foreach ($this->_get_data() as $key => $data)
         {
             if ($data->id == $id)
             {
                 return $key;
                 break;
             }
         }
         throw new Exception('No data found with ID: '.$id);
     }

     /**
      * Setting fields with default values
      */
     protected function _set_fields()
     {
         $this->_set = new \stdClass();
         $schema = $this->schema();

         foreach ($schema as $field => $type)
         {
             if (Helpers\Validate::is_numeric($type) AND $field != 'id')
             {
                 $this->_set->{$field} = 0;
             }
             else
             {
                 $this->_set->{$field} = null;
             }
         }
     }

     /**
      * Validating fields and setting variables to current operations
      * @param string $name Field name
      * @param mixed $value Field value
      */
     public function __set($name, $value)
     {
         if (Helpers\Validate::name($this->_name)->field($name) && Helpers\Validate::name($this->_name)->type($name, $value))
         {
             $this->_set->{$name} = $value;
         }
     }

     /**
      * Returning variable from Object
      * @param string $name Field name
      * @return mixed Field value
      */
     public function __get($name)
     {
         if (isset($this->_set->{$name}))
             return $this->_set->{$name};

         throw new Exception('There is no data');
     }

     /**
      * Execute pending functions
      */
     protected function _pending()
     {
         $this->_set_data();
         foreach ($this->_pending as $func => $args)
         {
             if (!empty($args))
             {
                 call_user_func(array($this, '_'.$func));
             }
         }
     }

     /**
      * Creating new table
      * 
      * For example few fields:
      * 
      * JSONDB::create('news', array(
      *  'title' => 'string',
      *  'content' => 'string',
      *  'rating' => 'double',
      *  'author' => 'integer'
      * ));
      * 
      * Types of field:
      * - boolean
      * - integer
      * - string
      * - double (also for float type)
      * 
      * ID field isn't required (it will be created automatically) but you can specify it at first place.
      * 
      * @param string $name Table name
      * @param array $fields Field configuration
      * @throws Exception If table exist
      */
     public static function create($name, array $fields)
     {
         $fields = Helpers\Validate::arr_to_lower($fields);

         if (Helpers\Data::name($name)->exists() && Helpers\Config::name($name)->exists())
         {
             throw new Exception('helper\Table "'.$name.'" already exists');
         }

         $types = array_values($fields);

         Helpers\Validate::types($types);

         if (!array_key_exists('id', $fields))
         {
             $fields = array('id' => 'integer') + $fields;
         }

         $data = new \stdClass();
         $data->last_id = 0;
         $data->schema = $fields;
         $data->relations = new \stdClass();

         Helpers\Data::name($name)->put(array());
         Helpers\Config::name($name)->put($data);
     }

     /**
      * Removing table with config
      * @param string $name Table name
      * @return boolean|Exception
      */
     public static function remove($name)
     {
         if (Helpers\Data::name($name)->remove() && Helpers\Config::name($name)->remove())
         {
             return TRUE;
         }
     }

     /**
      * Grouping results by one field
      * @param string $column
      * @return \jsondb\classes\core\Core
      */
     public function group_by($column)
     {
         if (Helpers\Validate::name($this->_name)->field($column))
         {
             $this->_reset_keys = 0;
             $this->_pending[__FUNCTION__] = $column;
         }

         return $this;
     }

     /**
      * Grouping array pending method
      */
     protected function _group_by()
     {
         $column = $this->_pending['group_by'];

         $grouped = array();
         foreach ($this->_data as $object)
         {
             $grouped[$object->{$column}][] = $object;
         }

         $this->_data = $grouped;
     }

     /**
      * JOIN other tables
      * @param string $table relations separated by :
      * @return \jsondb\classes\core\Core
      */
     public function with($table)
     {
         $this->_pending['with'][] = explode(':', $table);
         return $this;
     }

     /**
      * Pending function for with(), joining other tables to current
      */
     protected function _with()
     {
         $joins = $this->_pending['with'];
         foreach ($joins as $join)
         {
             $local = (count($join) > 1) ? array_slice($join, -2, 1)[0] : $this->_name;
             $foreign = end($join);

             $relation = Relation::table($local)->with($foreign);

             $data = $this->_data;

             foreach ($join as $part)
             {
                 $data = $relation->build($data, $part);
             }
         }
     }

     /**
      * Sorting data by field
      * @param string $key Field name
      * @param string $direction ASC|DESC
      * @return \Core
      */
     public function order_by($key, $direction = 'ASC')
     {
         if (Helpers\Validate::name($this->_name)->field($key))
         {
             $directions = array(
                 'ASC' => SORT_ASC,
                 'DESC' => SORT_DESC
             );
             $this->_pending[__FUNCTION__][$key] = $directions[$direction];
         }

         return $this;
     }

     /**
      * Sort an array of objects by more than one field.
      * @link http://blog.amnuts.com/2011/04/08/sorting-an-array-of-objects-by-one-or-more-object-property/ It's not mine algorithm
      */
     protected function _order_by()
     {
         $properties = $this->_pending['order_by'];
         uasort($this->_data, function($a, $b) use ($properties)
         {
             foreach ($properties as $column => $direction)
             {
                 if (is_int($column))
                 {
                     $column = $direction;
                     $direction = SORT_ASC;
                 }
                 $collapse = function($node, $props)
                 {
                     if (is_array($props))
                     {
                         foreach ($props as $prop)
                         {
                             $node = (!isset($node->$prop)) ? null : $node->$prop;
                         }
                         return $node;
                     }
                     else
                     {
                         return (!isset($node->$props)) ? null : $node->$props;
                     }
                 };
                 $aProp = $collapse($a, $column);
                 $bProp = $collapse($b, $column);

                 if ($aProp != $bProp)
                 {
                     return ($direction == SORT_ASC) ? strnatcasecmp($aProp, $bProp) : strnatcasecmp($bProp, $aProp);
                 }
             }
             return FALSE;
         });
     }

     /**
      * Where function, like SQL
      * 
      * Operators:
      * - Standard operators (=, !=, >, <, >=, <=)
      * - IN (only for array value)
      * - NOT IN (only for array value)
      * 
      * @param string $field Field name
      * @param string $op Operator
      * @param mixed $value Field value
      * @return \jsondb\core\Core
      */
     public function where($field, $op, $value)
     {
         $this->_pending['where'][] = array(
             'type' => 'and',
             'field' => $field,
             'op' => $op,
             'value' => $value,
         );

         return $this;
     }

     /**
      * Alias for where()
      * @param string $field Field name
      * @param string $op Operator
      * @param mixed $value Field value
      * @return \jsondb\core\Core
      */
     public function and_where($field, $op, $value)
     {
         $this->where($field, $op, $value);

         return $this;
     }

     /**
      * Alias for where(), setting OR for searching
      * @param string $field Field name
      * @param string $op Operator
      * @param mixed $value Field value
      * @return \jsondb\core\Core
      */
     public function or_where($field, $op, $value)
     {
         $this->_pending['where'][] = array(
             'type' => 'or',
             'field' => $field,
             'op' => $op,
             'value' => $value,
         );

         return $this;
     }

     /**
      * Filter function for array_filter() in where()
      * @param object $row
      * @return boolean
      */
     protected function _where()
     {
         $operator = array(
             '=' => '==',
             '!=' => '!=',
             '>' => '>',
             '<' => '<',
             '>=' => '>=',
             '<=' => '<=',
             'and' => '&&',
             'or' => '||'
         );

         $this->_data = array_filter($this->_data, function($row) use ($operator)
         {
             $clause = '';

             foreach ($this->_pending['where'] as $key => $condition)
             {
                 extract($condition);

                 if (is_array($value))
                 {
                     $value = (in_array($row->{$field}, $value)) ? 1 : 0;
                     $op = '==';
                     $field = 1;
                 }
                 else
                 {
                     $value = is_string($value) ?
                             '\''.$value.'\'' :
                             $value;

                     $op = $operator[$op];
                     $field = '$row->'.$field;
                 }

                 $type = (!$key) ?
                         null :
                         $operator[$type];

                 $query = array($type, $field, $op, $value);
                 $clause .= implode(' ', $query).' ';
                 eval('$result = '.$clause.';');
             }

             return $result;
         });
     }

     /**
      * Returning data as indexed or assoc array.
      * @param string $key Field that will be the key, NULL for Indexed
      * @param string $value Field that will be the value
      * @return array
      */
     public function as_array($key = null, $value = null)
     {
         if (!is_null($key))
         {
             Helpers\Validate::name($this->_name)->field($key);
         }
         if (!is_null($value))
         {
             Helpers\Validate::name($this->_name)->field($value);
         }

         $datas = array();
         if (!empty($this->_pending['group_by']))
         {

             foreach ($this->_data as $array)
             {
                 foreach ($array as $data)
                 {
                     if (is_null($key) && is_null($value))
                     {
                         $datas[] = $data;
                     }
                     elseif (is_null($key))
                     {
                         $datas[] = $data->{$value};
                     }
                     elseif (is_null($value))
                     {
                         if ($this->_pending['group_by'] == $key)
                         {
                             $datas[$data->{$key}][] = $data;
                         }
                         else
                         {
                             $datas[$data->{$key}] = $data;
                         }
                     }
                     else
                     {
                         if ($this->_pending['group_by'] == $key)
                         {
                             $datas[$data->{$key}][] = $data->{$value};
                         }
                         else
                         {
                             $datas[$data->{$key}] = $data->{$value};
                         }
                     }
                 }
             }
         }
         else
         {
             foreach ($this->_data as $data)
             {
                 if (is_null($key) && is_null($value))
                 {
                     $datas[] = $data;
                 }
                 elseif (is_null($key))
                 {
                     $datas[] = $data->{$value};
                 }
                 elseif (is_null($value))
                 {
                     $datas[$data->{$key}] = $data;
                 }
                 else
                 {
                     $datas[$data->{$key}] = $data->{$value};
                 }
             }
         }

         return $datas;
     }

     /**
      * Limit returned data
      * 
      * Should be used at the end of chain, before end method
      * @param integer $number Limit number
      * @param integer $offset Offset number
      * @return \Core
      */
     public function limit($number, $offset = 0)
     {
         $this->_pending['limit'] = array(
             'offset' => $offset,
             'number' => $number
         );

         return $this;
     }

     /**
      * Pending function for limit()
      */
     protected function _limit()
     {
         $offset = $this->_pending['limit']['offset'];
         $num = $this->_pending['limit']['number'];
         $this->_data = array_slice($this->_data, $offset, $num);
     }

     /**
      * Add new fields to table, array schema like in create() function
      * @param array $fields Associative array
      */
     public function add_fields(array $fields)
     {
         $fields = Helpers\Validate::arr_to_lower($fields);

         Helpers\Validate::types(array_values($fields));

         $schema = $this->schema();
         $fields = array_diff_assoc($fields, $schema);

         if (!empty($fields))
         {
             $config = $this->config();
             $config->schema = array_merge($schema, $fields);

             $data = $this->_get_data();
             foreach ($data as $key => $object)
             {
                 foreach ($fields as $name => $type)
                 {
                     if (Helpers\Validate::is_numeric($type))
                         $data[$key]->{$name} = 0;
                     else
                         $data[$key]->{$name} = null;
                 }
             }

             Helpers\Data::name($this->_name)->put($data);
             Helpers\Config::name($this->_name)->put($config);
         }
     }

     /**
      * Delete fields from array
      * @param array $fields Indexed array
      */
     public function delete_fields(array $fields)
     {
         $fields = Helpers\Validate::arr_to_lower($fields);

         Helpers\Validate::name($this->_name)->fields($fields);

         $config = $this->config();
         $config->schema = array_diff_key($this->schema(), array_flip($fields));

         $data = $this->_get_data();
         foreach ($data as $key => $object)
         {
             foreach ($fields as $name)
             {
                 unset($data[$key]->{$name});
             }
         }

         Helpers\Data::name($this->_name)->put($data);
         Helpers\Config::name($this->_name)->put($config);
     }

     /**
      * Adding relation to table
      * 
      * Available relations type:
      * - belongs_to
      * - has_many
      * - has_and_belongs_to_many
      * 
      * @param type $type
      * @param type $table
      * @param type $local_key
      * @param type $foreign_key
      */
     public function add_relation($type, $table, $local_key, $foreign_key)
     {
         Helpers\Validate::relation_type($type);
         Helpers\Validate::name($table)->exists();
         Helpers\Validate::name($table)->field($foreign_key);
         Helpers\Validate::name($this->_name)->field($local_key);

         $relation = array(
             $table => array(
                 'type' => $type,
                 'keys' => array(
                     'local' => $local_key,
                     'foreign' => $foreign_key
                 )
             )
         );

         $config = $this->config();
         $config->relations = array_merge($this->relations(), $relation);

         Helpers\Config::name($this->_name)->put($config);

         return $this;
     }

     /**
      * removing relation with tables
      * @param type $table
      */
     public function delete_relations(array $tables)
     {
         foreach ($tables as $table)
         {
             Helpers\Validate::name($table)->exists();
             Helpers\Validate::name($table)->relation($this->_name, $table);
         }

         $config = $this->config();
         $config->relations = array_diff_key($this->relations(), array_flip($tables));

         Helpers\Config::name($this->_name)->put($config);
     }

     /**
      * Returns table name
      * @return string table name
      */
     public function name()
     {
         return $this->_name;
     }

     /**
      * Returning object with config for table
      * @return object Config
      */
     public function config()
     {
         return Helpers\Config::name($this->_name)->get();
     }

     /**
      * Return array with names of fields
      * @return array Fields
      */
     public function fields()
     {
         return Helpers\Config::name($this->_name)->fields();
     }

     /**
      * Returning assoc array with types of fields
      * @return array Fields type
      */
     public function schema()
     {
         return Helpers\Config::name($this->_name)->schema();
     }

     /**
      * Returning assoc array with relationed tables
      * @return array Fields type
      */
     public function relations($tableName = null)
     {
         return Helpers\Config::name($this->_name)->relations($tableName, true);
     }

     /**
      * Returning last ID from table
      * @return integer Last ID
      */
     public function last_id()
     {
         return Helpers\Config::name($this->_name)->last_id();
     }

     /**
      * Saving inserted or updated data
      */
     public function save()
     {
         $data = $this->_get_data();
         if (!$this->_current_id)
         {
             $config = $this->config();
             $config->last_id++;

             $this->_set->id = $config->last_id;
             array_push($data, $this->_set);

             Helpers\Config::name($this->_name)->put($config);
         }
         else
         {
             $this->_set->id = $this->_current_id;
             $data[$this->_current_key] = $this->_set;
         }

         Helpers\Data::name($this->_name)->put($data);

//         $this->_set_fields();
     }

     /**
      * Deleting loaded data
      * @return boolean
      */
     public function delete()
     {
         $data = $this->_get_data();
         if (isset($this->_current_id))
         {
             unset($data[$this->_current_key]);
         }
         elseif (isset($this->_pending['where']) && !empty($this->_pending['where']))
         {
             $this->_data = $data;
             $old = $this->_data;
             call_user_func(array($this, '_where'));
             $data = array_diff_key($old, $this->_data);
         }
         else
         {
             $data = array();
         }
         $this->_data = array_values($data);

         return Helpers\Data::name($this->_name)->put($this->_data) ? true : false;
     }

     /**
      * Return count in integer or array of integers (if grouped)
      * @return mixed 
      */
     public function count()
     {
         $this->_pending();
         if (!empty($this->_pending['group_by']))
         {
             $count = array();
             foreach ($this->_data as $group => $data)
             {
                 $count[$group] = count($data);
             }
         }
         else
         {
             $count = count($this->_data);
         }

         return $count;
     }

     /**
      * Returns one row with specified ID
      * @param integer $id Row ID
      * @return \Core
      */
     public function find($id)
     {
         $data = $this->_get_data();
         $this->_current_id = $id;
         $this->_current_key = $this->_get_row_key($id);
         foreach ($data[$this->_current_key] as $field => $value)
         {
             $this->{$field} = $value;
         }

         return $this;
     }

     /**
      * Make data ready to read
      */
     public function find_all()
     {
         $this->_pending();
         $this->_data = $this->_reset_keys ? array_values($this->_data) : $this->_data;

         return $this;
     }

     /**
      * Iterator for Data
      * @return \ArrayIterator
      */
     public function getIterator()
     {
         return new \ArrayIterator($this->_data);
     }

     /**
      * Debug functions, prints whole query with values
      */
     public function debug()
     {
         $print = "JSONDB::table(".$this->_name.")\n";
         foreach ($this->_pending as $function => $values)
         {
             if (!empty($values))
             {

                 if (is_array($values))
                 {
                     if (is_array(reset($values)))
                     {
                         foreach ($values as $value)
                         {
                             if ($function == 'where')
                             {
                                 array_shift($value);
                             }
                             if ($function == 'with')
                             {
                                 $params = implode(':', $value);
                             }
                             else
                             {
                                 $params = implode(', ', $value);
                             }
                             $print .= "\t".'->'.$function.'('.$params.')'."\n";
                         }
                     }
                     else
                     {
                         $params = implode(', ', $values);
                         $print .= "\t".'->'.$function.'('.$params.')'."\n";
                     }
                 }
                 else
                 {
                     $print .= "\t".'->'.$function.'('.$values.')'."\n";
                 }
             }
         }
         echo '<pre>'.print_r($print, true).'</pre>';
     }

 }
 