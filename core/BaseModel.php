<?php

namespace Nanoframe\Core;

class BaseModel {

  public $db;
  
  public function __construct() {

    $this->db = new QueryBuilder;

  }

}

