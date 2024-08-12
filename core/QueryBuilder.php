<?php

namespace Nanoframe\Core;

use \PDO;

class QueryBuilder {
  protected static $instance = null;
  
  private $host;
  private $dbname;
  private $username;
  private $password;
  private $conn;

  private $table;
  private $select = '*';
  private $where = '';
  private $orWhere = '';
  private $whereIn = '';
  private $join = [];
  private $groupBy = '';
  private $having = '';

  private $orderBy = '';
  private $limit = '';

  private $params = [];

  private $clausesAndParams = [];


  private $lastQuery = '';
  

  public function __construct() {

    $this->host     = $_ENV['DB_HOST'];
    $this->dbname   = $_ENV['DB_NAME'];
    $this->username = $_ENV['DB_USER'];
    $this->password = $_ENV['DB_PASSWORD'];

  }

  private function connect() {
    $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";

    try {
      $this->conn = new PDO($dsn, $this->username, $this->password);
      $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (\PDOException $e) {
      die("Connection failed: " . $e->getMessage());
    }
  }

  public static function getInstance() {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function beginTransaction()
  {
    if (!$this->conn) {
      $this->connect();
    }
    
    $this->conn->beginTransaction();
  }

  public function commit()
  {
    $this->conn->commit();
  }
  
  public function rollBack()
  {
    $this->conn->rollBack();
  }

  public function lastQuery()
  {
    return $this->lastQuery;
  }

  public function table($tableName) {
    $this->table = $tableName;
    return $this;
  }

  public function select($columns) {
    if (is_array($columns)) {
      $columns = implode(', ', $columns);
    }

    $this->select = $columns;
    return $this;
  }

  public function where($condition, $params = []) {

    if( $this->where || $this->whereIn || $this->orWhere){
      $this->where .= " AND {$condition}" ;
      $this->params = array_merge($this->params, $params);
    }else{
      $this->where = "WHERE {$condition}";
      $this->params = $params;
    }
    return $this;
  }

  public function orWhere($condition, $params = []) {

    if( $this->where || $this->whereIn || $this->orWhere){
      $this->where .= " OR {$condition}" ;
      $this->params = array_merge($this->params, $params);
    }else{
      $this->where = "WHERE {$condition}";
      $this->params = $params;
    }
    return $this;
  }

  public function whereIn($indexColumn, $params = []) {

    $placeholders = implode(', ', array_fill(0, count($params), '?'));

    $this->whereIn .= ( $this->where || $this->whereIn || $this->orWhere)
     ? " AND {$indexColumn} IN (" .$placeholders . ")"
     : " WHERE {$indexColumn} IN (" .$placeholders . ")";

    $this->params = array_merge($this->params, $params);

    return $this;
  }

  public function join($table, $condition, $type = 'INNER')
  {
    $this->join[] = "{$type} JOIN {$table} ON {$condition}";
    return $this;
  }

  public function having($condition)
  {
    $this->having = $condition;
    return $this;
  }

  public function groupBy($by)
  {
    $this->groupBy = "GROUP BY $by";
    return $this;
  }

  public function orderBy($column, $direction = 'ASC') {
    $this->orderBy = "ORDER BY {$column} {$direction}";
    return $this;
  }

  public function limit($count, $offset = 0) {
    $this->limit = "LIMIT {$offset}, {$count}";
    return $this;
  }

  private function sqlSelectStringMount()
  {
    $sql = "SELECT {$this->select} FROM {$this->table}";


    $properties = [
      implode(' ', $this->join),
      $this->where,
      $this->whereIn,
      $this->orWhere,
      $this->groupBy,
      $this->having,
      $this->orderBy,
      $this->limit,
    ];

    foreach ($properties as $prop) {
      if(!empty($prop)){
        $sql .= " $prop";
      }
    }

    return $sql;
  }


  public function getArray() {
    $sql = $this->sqlSelectStringMount();

    $result = $this->_query($sql, $this->params);

    return $result->fetchAll(PDO::FETCH_ASSOC);
  }

  public function get() {
    $sql = $this->sqlSelectStringMount();

    $result = $this->_query($sql, $this->params);

    return $result->fetchAll(PDO::FETCH_OBJ);
  }


  public function getRow($field = '') {
    $this->limit(1);

    $sql = $this->sqlSelectStringMount();

    $result = $this->_query($sql, $this->params);

    $data = $result->fetchAll(PDO::FETCH_OBJ)[0] ?? NULL;

    return $field ? ($data->$field ?? NULL) : $data;
  }


  public function update($data) {

    if(is_object($data)){
      $data = get_object_vars($data);
    }

    $set = '';
    foreach ($data as $column => $value) {
      $set .= "{$column} = ?, ";
      $params[] = $value;
    }

    $set = rtrim($set, ', ');

    $sql = "UPDATE {$this->table} SET {$set} {$this->where}";
    $this->_query($sql, array_merge($params, $this->params) );
  }

  public function updateBatch($data, $indexColumn) {
    if (empty($data)) {
        return;
    }

    $sql = "UPDATE {$this->table} SET ";

    $indexValues = [];
    foreach ($data as $index => $row) {
      $indexValues[] = $row[$indexColumn];
      unset($row[$indexColumn]);

      $set = '';
      foreach ($row as $column => $value) {
        $set .= "{$column} = ?, ";
      }
      $set = rtrim($set, ', ');

      $sql .= "CASE WHEN {$indexColumn} = ? THEN {$set} ";
    }

    $sql .= "END WHERE {$indexColumn} IN (" . implode(', ', array_fill(0, count($data), '?')) . ")";

    $params = array_merge(array_values($data), $indexValues);

    $this->_query($sql, $params);
  }

  public function insert($data) {

    if(is_object($data)){
      $data = get_object_vars($data);
    }

    $columns = implode(', ', array_keys($data));
    $values = implode(', ', array_fill(0, count($data), '?'));

    $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$values})";
    $this->_query($sql, array_values($data));
  }

  public function insertBatch($data) {

    if (empty($data)) {
        return;
    }

    $columns = implode(', ', array_keys($data[0]));
    $placeholders = '(' . implode(', ', array_fill(0, count($data[0]), '?')) . ')';
    $values = [];

    foreach ($data as $row) {
        $values[] = array_values($row);
    }

    $placeholders = implode(', ', array_fill(0, count($values), $placeholders));

    $sql = "INSERT INTO {$this->table} ({$columns}) VALUES {$placeholders}";
    $params = array_merge(...$values);

    $this->_query($sql, $params);
  }

  public function replace($data) {
    $columns = implode(', ', array_keys($data));
    $values = implode(', ', array_fill(0, count($data), '?'));

    $sql = "REPLACE INTO {$this->table} ({$columns}) VALUES ({$values})";


    $this->_query($sql, array_values($data));
  }



  public function replaceBatch($data)
  {

    $fields = implode(', ', array_keys($data[0]));
    $placeholders = rtrim(str_repeat('?, ', count($data[0])), ', ');
    $values = [];



    foreach ($data as $row) {
      $values[] = array_values($row);
    }

    $placeholdersBatch = rtrim(str_repeat("($placeholders), ", count($data)), ', ');

    $sql = "REPLACE INTO {$this->table} ($fields) VALUES $placeholdersBatch";

    $params = array_merge(...$values);

    $this->_query($sql, $params);
  }
  



  private function getBatchReplaceIds($columns, $values) {
    $sql = "SELECT LAST_INSERT_ID() AS id FROM {$this->table} WHERE ({$columns}) IN (" . implode(', ', $values) . ")";
    $result = $this->_query($sql);

    $ids = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $ids[] = $row['id'];
    }

    return $ids;
  }

  /**
   * Insere se nao existe com base no campo unico passado no parametro
   * @param  array         $data         dados a inserir
   * @param  string|array  $uniqueFields campo(s) a serem verificados para definir se haverá insert
   * @param  boolean       $batch        se true espera "data" como array multi dimencional para insert em lote
   * @return int|array                   id(s) de registro(s) inserido(s)
   */
  public function insertIfNotExists($data, $uniqueFields, $batch = FALSE) {
    

    if( $batch == FALSE ){
      $columns = implode(', ', array_keys($data));

      $placeholders = implode(', ', array_fill(0, count($data), '?'));

      if (is_array($uniqueFields)) {
        $whereConditions = [];
        $params = array_values($data);

        foreach ($uniqueFields as $field) {
          $whereConditions[] = "BINARY {$field} = ?";
          $params[] = $data[$field];
        }

        $whereClause = implode(' AND ', $whereConditions);
      } else {
        $whereClause = "BINARY {$uniqueFields} = ?";
        $params = array_merge(array_values($data), [$data[$uniqueFields]]);
      }
    
      $sql = "INSERT INTO {$this->table} ({$columns}) SELECT {$placeholders} FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {$this->table} WHERE {$whereClause})";


      $this->_query($sql, $params);

      return $this->conn->lastInsertId();
    }else{

      if(!is_array($uniqueFields)){
        $uniqueFields = [$uniqueFields];
      }
      
      return $this->insertBatchIfNotExists($data, $uniqueFields);

    }
  }



  public function insertBatchIfNotExists($data, $uniqueFields)
  {
    $this->storeClausesAndParams();

    foreach ($data as $item) {
      $this->insertIfNotExists($item, $uniqueFields);

      $this->setClausesAndParams();

    }

    $this->clausesAndParams = [];


    return $this->getInsertedBatchIds($data, $uniqueFields);
  }


  private function getInsertedBatchIds($data, $uniqueFields)
  {

    $params = [];

    $where = '';

    if( !is_array($uniqueFields) ){
      $uniqueFields = [$uniqueFields];
    }

    foreach ($uniqueFields as $key =>  $uq) {

      $arr = array_column($data, $uq);

      $params[] = $arr;

      $placeholders = implode(', ', array_fill(0, count($arr), '?'));

      if( $where != '' ){
        $where.= "AND ";
      }

      $where.= "{$uq} IN({$placeholders}) ";
    }

    $params = array_merge(...$params);

    $sql = "SELECT id FROM {$this->table} WHERE {$where}";
    

    $result = $this->_query($sql, $params);
    
    $ret = $result->fetchAll(PDO::FETCH_ASSOC);

    return array_column($ret, 'id');
  }



  public function insertId() {
    return $this->conn->lastInsertId();
  }

  public function delete() {
    $sql = "DELETE FROM {$this->table} {$this->where}";
    $this->_query($sql, $this->params);
  }



  public function query($sql, $params = [], $returnObject = TRUE)
  {
    if (!$this->conn) {
      $this->connect();
    }

    try {

      $statement = $this->conn->prepare($sql);

      $statement->execute($params);

      $sqlExplode = explode(" ", $sql); 

      if( strcasecmp($sqlExplode[0], 'select') != 0 ){
        return;
      }

      if($returnObject == TRUE){
        return $statement->fetchAll(PDO::FETCH_OBJ);
      }else{
        return $statement->fetchAll(PDO::FETCH_ASSOC);
      }

    } catch (\PDOException $e) {

      die("Query failed: " . $e->getMessage());
    }
  }


  private function getSqlWithParams($sql, $params) {

    $indexed = $params == array_values($params);

    foreach ($params as $key => $value) {
      if (is_string($value)) {
        $value = "'$value'";
      } elseif ($value === null) {
        $value = 'NULL';
      }
      if ($indexed) {
        $sql = preg_replace('/\?/', $value, $sql, 1);
      } else {
        $sql = str_replace($key, $value, $sql);
      }
    }
    return $sql;
  }


  private function _query($sql, $params = []) {
    if (!$this->conn) {
      $this->connect();
    }

    try {

      $statement = $this->conn->prepare($sql);

      $statement->execute($params);

      $this->lastQuery = $this->getSqlWithParams($sql, $params);

      $this->resetWrite();

      return $statement;

    } catch (\PDOException $e) {

      die("Query failed: " . $e->getMessage());
    }
  }


  public function resetWrite()
  {
    $this->table = '';
    $this->select = '*';
    $this->join = [];
    $this->where = '';
    $this->orWhere = '';
    $this->whereIn = '';
    $this->groupBy = '';
    $this->having = '';
    $this->orderBy = '';
    $this->limit = '';
    $this->params = [];
  }

  private function storeClausesAndParams(){
    $this->clausesAndParams = [
      'table'   => $this->table,
      'select'  => $this->select,
      'join'    => $this->join,
      'where'   => $this->where,
      'orWhere' => $this->orWhere,
      'whereIn' => $this->whereIn,
      'groupBy' => $this->groupBy,
      'having'  => $this->having,
      'orderBy' => $this->orderBy,
      'limit'   => $this->limit,
      'params'  => $this->params,
    ];

  }


  private function setClausesAndParams(){

    $this->table   = $this->clausesAndParams['table'];
    $this->select  = $this->clausesAndParams['select'];
    $this->join    = $this->clausesAndParams['join'];
    $this->where   = $this->clausesAndParams['where'];
    $this->whereIn = $this->clausesAndParams['whereIn'];
    $this->orWhere = $this->clausesAndParams['orWhere'];
    $this->groupBy = $this->clausesAndParams['groupBy'];
    $this->having  = $this->clausesAndParams['having'];
    $this->orderBy = $this->clausesAndParams['orderBy'];
    $this->limit   = $this->clausesAndParams['limit'];
    $this->params  = $this->clausesAndParams['params'];

     
  }
}