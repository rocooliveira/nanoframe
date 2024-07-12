<?php

namespace Nanoframe\Core;

class BaseModel {

  protected $db;
  
  public function __construct() {

    $this->db = new QueryBuilder;

  }

}

