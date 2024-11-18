<?php
namespace Nanoframe\Core;

use \PDO;

class DatabaseForge
{
  private $host;
  private $dbname;
  private $username;
  private $password;
  private $pdo;

  public function __construct() {

    try {

	    $this->host     = $_ENV['DB_HOST'];
	    $this->dbname   = $_ENV['DB_NAME'];
	    $this->username = $_ENV['DB_USER'];
	    $this->password = $_ENV['DB_PASSWORD'];

      $this->pdo = new PDO("mysql:host=$this->host;dbname=$this->dbname", $this->username, $this->password);
      $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (\PDOException $e) {
      echo "Erro na conexão com o banco de dados: " . $e->getMessage();
      die();
    }
  }

  public function executeQuery($sql) {
    try {
      $this->pdo->exec($sql);
    } catch (\PDOException $e) {

      throw new \Exception( $e->getMessage() );
          
      exit;
    }
  }

  public function closeConnection() {
    $this->pdo = null;
  }


  public function beginTransaction() {
    $this->pdo->beginTransaction();
  }

  public function commit() {
    $this->pdo->commit();
  }

  public function rollBack($e = NULL) {
    $this->pdo->rollBack();

    if($e){
    	echo PHP_EOL . "\033[31m". "Erro durante a transação: \n - " . $e->getMessage() ."\033[0m" . PHP_EOL . PHP_EOL;
    	exit;
    }

  }

  /**
   * recupera os dados enviados pelo usuario referente a coluna e atributos pra montar a consulta sql
   * para criacao ou alteracao do campo na tabela
   * @param  string $columnName nome do campo
   * @param  array $attribute  atributos
   * @return string
   */
  private function getColumnWithAttributes($columnName, $attribute)
  {
    $field = array();

    $field[] = $columnName;

    $newName = $attribute['new_name'] ?? NULL;

    if( $newName ){
    	$field[] = $newName;
    }

    $field[] = $attribute['type'];
    $field[] = isset($attribute['constraint']) ? '(' . $attribute['constraint'] . ')' : '';
    $field[] = isset($attribute['unsigned']) && $attribute['unsigned'] ? 'UNSIGNED' : '';
    $field[] = isset($attribute['auto_increment']) && $attribute['auto_increment'] ? 'AUTO_INCREMENT' : '';
    $field[] = isset($attribute['binary']) && $attribute['binary'] ? 'BINARY' : '';
    $field[] = isset($attribute['unique']) && $attribute['unique'] ? 'UNIQUE' : '';
    $field[] = isset($attribute['zerofill']) && $attribute['zerofill'] ? 'ZEROFILL' : '';

    $nullDef = isset($attribute['null']) && $attribute['null'] ? 'NULL' : 'NOT NULL';
    $field[] = $nullDef; 


    if( isset($attribute['default']) ){

      if (is_string($attribute['default'])) {

        // Caso especial: se o valor padrão começar com "NULL" ou outras expressões SQL especiais
        if (preg_match('/^(NULL|CURRENT_TIMESTAMP|CURRENT_DATE|NOW|ON UPDATE)/i', $attribute['default'])) {
          $defaultValue = strtoupper($attribute['default']); 
        } else {
          // usa aspas para strings
          $defaultValue = "'" . addslashes($attribute['default']) . "'";
        }

      } elseif (is_numeric($attribute['default']) || is_bool($attribute['default'])) {
        // Para tipos numericos e boleanos nao usa aspas
        // converte boleano true/false para 1/0

        $defaultValue = $attribute['default'] ? 1 : 0;

      } else {
        $defaultValue = 'NULL';
      }

      $field[] = isset($defaultValue) ? "DEFAULT $defaultValue" : 'DEFAULT NULL';

      if( strpos( $attribute['default'], 'NULL' ) !== FALSE && $nullDef == 'NOT NULL' ){
        throw new \PDOException("Campo $columnName definido como 'NOT NULL' e com padrão setado como 'null' ");
      }

    }

    $field[] = !empty($attribute['after']) ? "AFTER {$attribute['after']}" : '';


    $strFieldAttr = implode(' ', array_filter($field));
    
    return $strFieldAttr;
  }



	public function createTable($tableName, $attributes = [], $ifNotExists = FALSE ) {
   
    // Verifica se a tabela deve ser criada apenas se não existir
    $ifNOTExistsClause = $ifNotExists ? 'IF NOT EXISTS' : '';

    // Monta a consulta SQL para criar a tabela
    $sql = "CREATE TABLE $ifNOTExistsClause $tableName (" . PHP_EOL;

    // Adiciona os atributos à consulta SQL
    foreach ($attributes as $columnName => $attribute) {

      $sqlPart = $this->getColumnWithAttributes($columnName, $attribute);

      // Se o campo for único, adiciona a criação do índice
      if (isset($attribute['unique']) && $attribute['unique']) {
        $sqlPart = str_replace('UNIQUE ', '', $sqlPart);
        $sqlPart .= ', UNIQUE INDEX ' . $columnName . '_UNIQUE (' . $columnName . ')';
      }

      $sql .= $sqlPart;
      

      // Se a coluna possui a chave primária, adiciona a criação do índice primário
      if (isset($attribute['primary_key']) && $attribute['primary_key']) {
        $sql .= ', PRIMARY KEY (' . $columnName . ')';
      }

      $sql .= ', ' . PHP_EOL;

    }


    // Remove a vírgula extra no final da lista de atributos
    $sql = rtrim($sql, ", \n") . PHP_EOL;

    // Fecha a declaração da tabela
    $sql .= ");";


    // Executa a consulta SQL para criar a tabela
    $this->executeQuery($sql);


	}

  public function dropTable($tableName, $ifExists = FALSE) {

  	$ifExistsClause = $ifExists ? 'IF EXISTS ' : NULL;
    $sql = "DROP TABLE {$ifExistsClause}{$tableName};";

    try {
      $this->executeQuery($sql);

    } catch (\PDOException $e) {
      throw new \PDOException("Erro ao remover a tabela '$tableName': " . $e->getMessage());
    }
  }

  public function renameTable($oldName, $newName) {

    $sql = "RENAME TABLE $oldName TO $newName;";

    try {
        $this->executeQuery($sql);

    } catch (\PDOException $e) {
      throw new \PDOException("Erro ao renomear a tabela '$oldName': " . $e->getMessage());
    }
}


  private function isColumnExists($tableName, $columnName) {
    $sql = "SHOW COLUMNS FROM $tableName LIKE '$columnName'";
    $result = $this->pdo->query($sql);

    if (!$result) {
      throw new \Exception("Erro ao verificar a existência da coluna.");
    }

    return $result->rowCount() > 0;
  }

  public function addColumn($tableName, $columnAttributes) {
    foreach ($columnAttributes as $columnName => $attribute) {
      if (!$this->isColumnExists($tableName, $columnName)) {

      	$sql = "ALTER TABLE $tableName ADD COLUMN ";

    		$sql .= $this->getColumnWithAttributes($columnName, $attribute);

        $this->executeQuery($sql);

      } else {
        throw new \Exception("O campo '$columnName' já existe na tabela '$tableName'.");
      }
    }
  }

  public function dropColumn($tableName, $columnName) {
    if ($this->isColumnExists($tableName, $columnName)) {
      $sql = "ALTER TABLE $tableName
              DROP COLUMN $columnName;";

      $this->executeQuery($sql);
    } else {
      throw new \Exception("O campo '$columnName' não existe na tabela '$tableName'.");
    }
  }


  public function modifyColumn($tableName, $columnModifications) {
    foreach ($columnModifications as $columnName => $modifications) {
      if ($this->isColumnExists($tableName, $columnName)) {
        
        $newName = isset($modifications['new_name']) ? $modifications['new_name'] : $columnName;
        
				$field = $this->getColumnWithAttributes($columnName, $modifications);

        // Se o novo nome for diferente do nome atual, renomeia a coluna
        if ($columnName !== $newName) {
          $sql = "ALTER TABLE $tableName
                  CHANGE COLUMN $field;";
        } else {
          $sql = "ALTER TABLE $tableName
                  MODIFY COLUMN $field;";
        }

        $this->executeQuery($sql);

      } else {
        throw new \Exception("O campo '$columnName' não existe na tabela '$tableName'.");
      }
    }
  }


	private function isForeignKeyExists($tableName, $constraintName)
	{
    
    $result = $this->pdo->query("
        SELECT *
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = '{$this->dbname}'
        AND TABLE_NAME = '{$tableName}'
        AND CONSTRAINT_NAME = '{$constraintName}'
    ");

    return $result->rowCount() > 0;
	}

	public function isIndexExists($tableName, $index)
	{
		$result = $this->pdo->query("SHOW INDEX FROM {$tableName} where Key_name = '{$index}'");

		return $result->rowCount() > 0;
	}


  /**
   * Cria um indice para tabela
   * @param string       $tableName   nome da tabela
   * @param string|array $indexColumn nome da coluna ou array de colunas
   * @param boolean      $isUnique se verdadeiro cria indice unico
   * @param string       $indexName   nome do indice
   */
  public function addIndex($tableName, $indexColumn, $isUnique = FALSE, $indexName = '')
  {

    if( ! $indexName ){
      $indexNameArray = is_array($indexColumn) ? $indexColumn : [$indexColumn];
      $formattedIndexesName = implode('-', $indexNameArray);
      $suffix = $isUnique ? 'UNIQUE' : 'idx';
      $indexName = "{$tableName}-{$formattedIndexesName}_{$suffix}";
    }

    if(! $this->isIndexExists( $tableName, $indexName ) ){

      $unique = $isUnique ? ' UNIQUE ' : ' ';

      $indexColumnArray = is_array($indexColumn) ? $indexColumn : [$indexColumn];

      $formattedIndexesArray = array_map(fn($i) => "`{$i}` ASC", $indexColumnArray);


      $formattedIndexes = implode(',', $formattedIndexesArray);


      $this->executeQuery("ALTER TABLE `{$tableName}` ADD{$unique}INDEX `{$indexName}` ({$formattedIndexes})");
    }
  }


  public function dropIndex($tableName, $indexColumn, $isUnique = FALSE, $indexName = '')
  {
    if( ! $indexName ){
      $indexNameArray = is_array($indexColumn) ? $indexColumn : [$indexColumn];
      $formattedIndexesName = implode('-', $indexNameArray);
      $suffix = $isUnique ? 'UNIQUE' : 'idx';
      $indexName = "{$tableName}-{$formattedIndexesName}_{$suffix}";
    }

    if( $this->isIndexExists( $tableName, $indexName ) ){
      $this->executeQuery("ALTER TABLE `{$tableName}` DROP INDEX `{$indexName}`");
    }
  }


	private function constraintMinify($constraint)
	{

	    $constraint = ltrim($constraint, 'fk_');
	    $constraintArr = explode('-',$constraint);
	    
	    $tbl1Arr = explode('_', $constraintArr[0]);
	    $tbl2Arr = explode('_', $constraintArr[2]);
	    
	    $tbl1Min = implode('_', array_map(function($item){
	        return substr($item, 0, 3);
	    }, $tbl1Arr));
	    
	    $tbl2Min = implode('_', array_map(function($item){
	        return substr($item, 0, 3);
	    }, $tbl2Arr));

	    $constraint = "fk_{$tbl1Min}-$constraintArr[1]-{$tbl2Min}";

	    if( strlen($constraint) > 59 ){

	      throw new \Exception("O identificador da foreing key é longo demais", 1);
	      
	    }

	    return $constraint;

	}

	public function addForeignKey(
		$table,
		$foreign_key,
		$references,
		$refField,
		$onUpdate = 'NO ACTION',
		$onDelete = 'NO ACTION' 
	){


		// FOREIGN KEY
		// ------------------------------------------------------------------------
      $constraint = "fk_{$table}-{$foreign_key}-{$references}";

      if( strlen($constraint) > 59 ){
        $constraint =  $this->constraintMinify($constraint);
      }


      if( ! $this->isForeignKeyExists( $table, $constraint ) ){
	      $this->executeQuery("
	      	ALTER TABLE `{$table}` 
	      		ADD CONSTRAINT `{$constraint}` 
	      		FOREIGN KEY (`{$foreign_key}`) REFERENCES `{$references}`(`{$refField}`) 
	      		ON DELETE {$onDelete} 
	      		ON UPDATE {$onUpdate}
	      ");
	    }

      // INDEX
      // ------------------------------------------------------------------------

	    if( ! $this->isIndexExists($table, $constraint) ){

	      $index =  "{$constraint}_idx";

	      $this->executeQuery("ALTER TABLE `{$table}` ADD INDEX `{$index}` (`{$foreign_key}` ASC)");
	    }
	}


  public function dropForeignKey($table, $foreign_key, $references)
  {

		// FOREIGN KEY
		// ------------------------------------------------------------------------

    $constraint = "fk_{$table}-{$foreign_key}-{$references}";

    if( strlen($constraint) > 59 ){
       $constraint = $this->constraintMinify($constraint);
    }

    
    if( $this->isForeignKeyExists( $table, $constraint ) ){
      $this->executeQuery("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
    }

    // INDEX
    // ------------------------------------------------------------------------

    $index =  "{$constraint}_idx";

    $hasIndex = $this->isIndexExists($table, $index);

    if($hasIndex)
      $this->executeQuery("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
  }



}