<?php

namespace Nanoframe\Core;

class BaseModel {
  /** @var QueryBuilder Instancia do query builder */
  protected $db;
  
  public function __construct() {

    $this->db = QueryBuilder::getInstance();

  }

}