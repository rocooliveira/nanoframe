<?php
namespace Nanoframe\Core;

class Transactor {

  protected $queryBuilder;

  public function __construct() {
    $this->queryBuilder = QueryBuilder::getInstance();
  }

  public function beginTransaction() {
    $this->queryBuilder->beginTransaction();
  }

  public function commit() {
    $this->queryBuilder->commit();
  }

  public function rollback() {
    $this->queryBuilder->rollback();
  }
}
