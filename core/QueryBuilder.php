<?php

namespace Nanoframe\Core;

use Exception;
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


  private $conditions = [];

  private $join = [];
  private $groupBy = '';
  private $having = '';

  private $orderBy = '';
  private $limit = '';

  private $params = [];

  private $clausesAndParams = [];

  private $groupStart = false;
  private $groupEnd = false;
  private $pendingGroupEnd = false;


  private $lastQuery = '';
  private $affectedRows = 0;
  

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


  private function _where($logicalOperator, $field, $value = null, $escape = TRUE ) {


    $gStart = $this->_groupStart();
    $gEnd = $this->_groupEnd();


    if (is_array($field)) {

      $i = 0;

      foreach ($field as $key => $val) {

        if (strpos($key, ' ') !== false) {
          list($column, $operator) = explode(' ', $key, 2);
        } else {
          $column = $key;
          $operator = '=';
        }

        $startCond = $this->conditions || $i ? $logicalOperator : 'WHERE';
        

        if($escape){
          $conditionsStr = implode(' ', array_filter([$gEnd, $startCond, $gStart, $column, $operator]));

          $this->conditions[] = "$conditionsStr ?";
          $value[] = $val;
        }else{
          $conditionsStr = implode(' ', array_filter([$gEnd, $startCond, $gStart, $column, $operator, $val]));

          $this->conditions[] = $conditionsStr;
        }


        $i++;
      }

    }else{

      if (strpos($field, ' ') !== false) {
          list($column, $operator) = explode(' ', $field, 2);
      } else {
          $column = $field;
          $operator = '=';
      }

      $startCond = $this->conditions ? $logicalOperator : 'WHERE';

      $binding = $escape ? '?' : $value;

      $conditionsStr = implode(' ', array_filter([$gEnd, $startCond, $gStart, $column, $operator, $binding]));
      $this->conditions[]  = $conditionsStr;

    }

    if( $escape ){
      if( $this->conditions ){

        $this->params = array_merge($this->params, is_array($value) ? $value : [$value]);
      }else{

        $this->params = is_array($value) ? $value : [$value];
      }
    }

    return $this;
  }

  public function where( $field, $value = null, $escape = TRUE ) {

    $this->_where('AND', $field, $value, $escape);

    return $this;
  }

  public function orWhere($field, $value = [], $escape = TRUE) {

    $this->_where('OR', $field, $value, $escape);
    
    return $this;
  }

  private function _whereIn($logicalOperator, $indexColumn, $params = [], $not = false) {

    $gStart = $this->_groupStart();
    $gEnd = $this->_groupEnd();

    $placeholders = implode(', ', array_fill(0, count($params), '?'));

    $conditionsStr = implode(' ', array_filter([$gEnd, $logicalOperator, $gStart, $indexColumn]));

    $not = ($not) ? ' NOT' : '';

    $this->conditions[] = $this->conditions
     ? "{$conditionsStr}{$not} IN (" .$placeholders . ")"
     : "WHERE {$gStart}{$indexColumn}{$not} IN (" .$placeholders . ")";

    $this->params = array_merge($this->params, $params);

    return $this;
  }

  public function whereIn($indexColumn, $params = []) {

    $this->_whereIn('AND', $indexColumn, $params);
    return $this;
  }

  public function whereNotIn($indexColumn, $params = []) {

    $this->_whereIn('AND', $indexColumn, $params, true);
    return $this;
  }

  public function orWhereIn($indexColumn, $params = []) {

    $this->_whereIn('OR', $indexColumn, $params);
    return $this;
  }

  public function orWhereNotIn($indexColumn, $params = []) {

    $this->_whereIn('OR', $indexColumn, $params, true);
    return $this;
  }


  private function _like($logicalOperator, $column, $value, $not = false)
  {
    $gStart = $this->_groupStart();
    $gEnd = $this->_groupEnd();

    $not = ($not) ? ' NOT' : '';

    if( $this->conditions ){
      $conditionsStr = implode(' ', array_filter([$gEnd, $logicalOperator, $gStart, $column]));

      $this->conditions[] = "{$conditionsStr}{$not} LIKE ?";
      $this->params = array_merge($this->params, ["%$value%"]);
    }else{
      $conditionsStr = implode(' ', array_filter([$gStart, $column]));

      $this->conditions[] = "WHERE {$conditionsStr}{$not} LIKE ?";
      $this->params = ["%$value%"];
    }

    return $this;
  }

  public function like($column, $value)
  {

    $this->_like('AND', $column, $value);

    return $this;
  }

  public function notLike($column, $value)
  {

    $this->_like('AND', $column, $value, true);

    return $this;
  }

  public function orLike($column, $value)
  {
    $this->_like('OR', $column, $value);

    return $this;
  }

  public function orNotLike($column, $value)
  {
    $this->_like('OR', $column, $value, true);

    return $this;
  }

  public function groupStart()
  {
    $this->groupStart =  true;
  }

  public function groupEnd()
  {
    $this->groupEnd =  true;
  }

  public function _groupStart()
  {
    $gStart = '';

    if( $this->groupStart ){
      $gStart = ' ( ';
      $this->groupStart = false;
      $this->pendingGroupEnd = true;
    }

    return $gStart;
  }

  public function _groupEnd()
  {
    $gEnd = '';

    if( $this->groupEnd ){
      $gEnd = ' ) ';
      $this->groupEnd = false;
      $this->pendingGroupEnd = false;
    }

    return $gEnd;
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

  public function orderBy($column, $direction = '') {

    $direction = strtoupper(trim($direction));

    if($direction !== ''){
      $direction = in_array($direction, array('ASC', 'DESC'), TRUE) ? ' '.$direction : '';
      $this->orderBy = "ORDER BY {$column}{$direction}";
    }else{
      $this->orderBy = "ORDER BY {$column}";
    }

    return $this;
  }

  public function limit($count, $offset = 0) {
    $this->limit = "LIMIT {$offset}, {$count}";
    return $this;
  }

  private function sqlSelectStringMount()
  {
    $sql = "SELECT {$this->select} FROM {$this->table}";


    $properties = [ implode(' ', $this->join) ];

    $properties = array_merge($properties, $this->conditions);

    if( $this->groupEnd ){
      $properties = array_merge($properties, [ $this->_groupEnd() ]);
    }

    if( $this->pendingGroupEnd ){
      throw new Exception('Nenhum "GroupEnd" identificado para o "GroupStart" iniciado.');
    }

    $properties = array_merge($properties, [
      $this->groupBy,
      $this->having,
      $this->orderBy,
      $this->limit,
    ]);




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


  public function update($data, $escape = TRUE) {

    if(is_object($data)){
      $data = get_object_vars($data);
    }

    $set = '';
    foreach ($data as $column => $value) {
      if($escape == TRUE){
        $set .= "{$column} = ?, ";
        $params[] = $value;
      }else{
        $set .= "{$column} = {$value}, ";
        $params = [];
      }
    }

    $set = rtrim($set, ', ');

    $conditions = implode(' ', $this->conditions);

    $joins = implode(' ', array_filter($this->join)) ;

    $updateParamsStr = implode(' ', array_filter([ $joins, 'SET', $set, $conditions ]));

    $sql = "UPDATE {$this->table} {$updateParamsStr}";

    $this->_query($sql, array_merge($params, $this->params), TRUE );
  }

  public function updateBatch(array $data, string $indexColumn): void {
 
    if (empty($data)) {
      throw new \InvalidArgumentException('Nenhum dado fornecido para updateBatch.');
    }

    $cases = [];
    $ids = [];
    $params = [];
    $columns = [];

    if(is_object($data[0])){
      foreach ($data as $key => $row) {
        if(is_object($row)){
          $data[$key] = get_object_vars($row);
        }
      }
    }
    
    // identifica todas as colunas existentes
    foreach ($data as $row) {
      if (!isset($row[$indexColumn])) {
        throw new \InvalidArgumentException("Coluna de índice '{$indexColumn}' ausente nos dados.");
      }
      foreach ($row as $column => $value) {
        if ($column !== $indexColumn) {
          $columns[$column] = true;
        }
      }
    }

    // constroí os casos WHEN para cada coluna
    foreach ($columns as $column => $unused) {
      $cases[$column] = [];
      foreach ($data as $row) {
          $id = $row[$indexColumn];
        if (!in_array($id, $ids)) {
          $ids[] = $id;
        }
        
        if (isset($row[$column])) {
          $cases[$column][] = "WHEN ? THEN ?";
          $params[] = $id;
          $params[] = $row[$column];
        }
      }
    }

    if (empty($cases)) {
      throw new \InvalidArgumentException('Nenhuma coluna para atualizar.');
    }

    // Constroi as partes SQL do CASE
    $caseSqlParts = [];
    foreach ($cases as $column => $caseStatements) {
      if (!empty($caseStatements)) {
        $caseSqlParts[] = "`$column` = CASE `$indexColumn` " . 
                           implode(' ', $caseStatements) . 
                           " ELSE `$column` END";
      }
    }

    $caseSql = implode(', ', $caseSqlParts);
    $idPlaceholders = implode(', ', array_fill(0, count($ids), '?'));
    $params = array_merge($params, $ids);

    $sql = "UPDATE `{$this->table}` SET $caseSql WHERE `$indexColumn` IN ($idPlaceholders)";
    
    $this->_query($sql, $params, true);
  }

  public function insert($data) {

    if(is_object($data)){
      $data = get_object_vars($data);
    }

    $columns = implode(', ', array_keys($data));
    $values = implode(', ', array_fill(0, count($data), '?'));

    $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$values})";


    $this->_query($sql, array_values($data), TRUE);
  }

  public function insertBatch($data) {

    if (empty($data)) {
      return;
    }

    if(is_object($data[0])){
      foreach ($data as $key => $row) {
        if(is_object($row)){
          $data[$key] = get_object_vars($row);
        }
      }
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

    $this->_query($sql, $params, TRUE);
  }

  public function replace($data) {
    $columns = implode(', ', array_keys($data));
    $values = implode(', ', array_fill(0, count($data), '?'));

    $sql = "REPLACE INTO {$this->table} ({$columns}) VALUES ({$values})";


    $this->_query($sql, array_values($data), TRUE);
  }



  public function replaceBatch($data)
  {

    if(is_object($data[0])){
      foreach ($data as $key => $row) {
        if(is_object($row)){
          $data[$key] = get_object_vars($row);
        }
      }
    }

    $fields = implode(', ', array_keys($data[0]));
    $placeholders = rtrim(str_repeat('?, ', count($data[0])), ', ');
    $values = [];

    foreach ($data as $row) {
      $values[] = array_values($row);
    }

    $placeholdersBatch = rtrim(str_repeat("($placeholders), ", count($data)), ', ');

    $sql = "REPLACE INTO {$this->table} ($fields) VALUES $placeholdersBatch";

    $params = array_merge(...$values);

    $this->_query($sql, $params, TRUE);
  }
  

  public function affectedRows() {
    $ret = $this->affectedRows;
    $this->affectedRows = 0;
    return $ret;
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
    $conditions = implode(' ', $this->conditions);

    $sql = "DELETE FROM {$this->table} {$conditions}";

    $this->_query($sql, $this->params, TRUE);
  }


  public function getNumRows($clearQueryString = FALSE)
  {
    if (!$this->conn) {
      $this->connect();
    }

    try {

      $sql = $this->sqlSelectStringMount();

      $statement = $this->conn->prepare($sql);

      $statement->execute($this->params);

      $this->lastQuery = $this->getSqlWithParams($sql, $this->params);

      if( $clearQueryString ){
        $this->resetWrite();
      }

      return $statement->rowCount();

    } catch (\PDOException $e) {

      die("Query failed: " . $e->getMessage());
    }
  }


  public function query($sql, $params = [], $returnObject = TRUE)
  {
    if (!$this->conn) {
      $this->connect();
    }

    try {

      $statement = $this->conn->prepare($sql);

      $statement->execute($params);

      $sqlNormalized = trim(preg_replace('/\s+/', ' ', $sql));


      if( ! preg_match('/^\s*(SELECT|WITH)/i', $sqlNormalized) ){
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


  private function _query($sql, $params = [], $rowCount = TRUE) {
    if (!$this->conn) {
      $this->connect();
    }

    try {

      $statement = $this->conn->prepare($sql);

      $statement->execute($params);

      $this->lastQuery = $this->getSqlWithParams($sql, $params);

      if( $rowCount ){
        $this->affectedRows = $statement->rowCount();
      }

      $this->resetWrite();

      return $statement;

    } catch (\PDOException $e) {

      die("Query failed: " . $e->getMessage());
    }
  }


  public function resetWrite()
  {
    $this->conditions = [];
    $this->table = '';
    $this->select = '*';
    $this->join = [];
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
    $this->groupBy = $this->clausesAndParams['groupBy'];
    $this->having  = $this->clausesAndParams['having'];
    $this->orderBy = $this->clausesAndParams['orderBy'];
    $this->limit   = $this->clausesAndParams['limit'];
    $this->params  = $this->clausesAndParams['params'];
  
  }
}