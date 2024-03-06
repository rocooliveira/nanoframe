<?php

use Nanoframe\Core\DatabaseForge;

class Migration_consolidated_migration extends DatabaseForge {

	public function up()
	{

		$this->up_20240304211607();
		$this->up_20240304235508();
		$this->up_20240305001340();
		$this->up_20240306144703();
	}

	public function down()
	{

		$this->down_20240306144703();
		$this->down_20240305001340();
		$this->down_20240304235508();
		$this->down_20240304211607();
	}


	//------------------------------------------------------------------------
  // Origem: 20240304211607_create_user_type_table.php
	//------------------------------------------------------------------------
  public function up_20240304211607()
  {
  	$_tableName = 'user_type';

		$this->beginTransaction();

		try {
			$fields = [ 
			    'id' => [
				    'primary_key'    => TRUE,
				    'type'           => 'INT',
				    'constraint'     => 10,
				    'unsigned'       => TRUE,
				    'auto_increment' => TRUE
			    ],
			    'created_at' => [
				    'type'        => 'TIMESTAMP',
				    'default'     => 'CURRENT_TIMESTAMP()',
			    ],
			    'updated_at' => [
				    'type'        => 'TIMESTAMP',
				    'default'     => 'NULL ON UPDATE CURRENT_TIMESTAMP()',
				    'null'				=> TRUE
			    ]
			];

			$this->createTable($_tableName, $fields);

			$this->commit();

		} catch (\Exception $e) {
			$this->rollBack($e);
		}

  }

  public function down_20240304211607()
  {
  	$_tableName = 'user_type';

		$this->dropTable($_tableName, TRUE);

  }

	//------------------------------------------------------------------------
  // Origem: 20240304235508_create_user_table.php
	//------------------------------------------------------------------------
  public function up_20240304235508()
  {
  	$_tableName = 'user';

		$this->beginTransaction();

		try {
			$fields = [ 
			    'id' => [
			    	'primary_key'    => TRUE,
				    'type'           => 'INT',
				    'constraint'     => 10,
				    'unsigned'       => TRUE,
				    'auto_increment' => TRUE
			    ],
			    'user_type_id' => [
				    'type'        => 'INT',
				    'constraint'     => 10,
				    'unsigned'       => TRUE,
			    ],
			    'name' => [
				    'type'        => 'VARCHAR',
				    'constraint'     => 100,
			    ],
			    'created_at' => [
				    'type'        => 'TIMESTAMP',
				    'default'     => 'CURRENT_TIMESTAMP()',
			    ],
			    'updated_at' => [
				    'type'        => 'TIMESTAMP',
				    'default'     => 'NULL ON UPDATE CURRENT_TIMESTAMP()',
				    'null'				=> TRUE
			    ]
			];

			$this->createTable($_tableName, $fields);

			$this->addForeignKey($_tableName, 'user_type_id', 'user_type', 'id', 'CASCADE', 'CASCADE');

			$this->commit();


		} catch (\Exception $e) {
			$this->rollBack($e);
		}

  }

  public function down_20240304235508()
  {
  	$_tableName = 'user';

		$this->dropForeignKey($_tableName, 'user_type_id', 'user_type');
		$this->dropTable($_tableName, TRUE);

  }

	//------------------------------------------------------------------------
  // Origem: 20240305001340_create_table_teste.php
	//------------------------------------------------------------------------
  public function up_20240305001340()
  {
  	$_tableName = 'teste_tbl';

		$this->beginTransaction();

		try {
			$fields = [ 
			    'id' => [
			    	'primary_key'    => TRUE,
				    'type'           => 'INT',
				    'constraint'     => 10,
				    'unsigned'       => TRUE,
				    'auto_increment' => TRUE
			    ],
			    'price' => [
				    'type'        => 'DECIMAL',
				    'constraint'  => '6,2',
			    ],
			    'created_at' => [
				    'type'        => 'TIMESTAMP',
				    'default'     => 'CURRENT_TIMESTAMP()',
			    ],
			    'updated_at' => [
				    'type'        => 'TIMESTAMP',
				    'default'     => 'NULL ON UPDATE CURRENT_TIMESTAMP()',
				    'null'				=> TRUE
			    ]
			];

			$this->createTable($_tableName, $fields);

			$this->commit();

		} catch (\Exception $e) {
			$this->rollBack($e);
		}

  }

  public function down_20240305001340()
  {
  	$_tableName = 'teste_tbl';

		$this->dropTable($_tableName, TRUE);

  }

	//------------------------------------------------------------------------
  // Origem: 20240306144703_create_table_teste2.php
	//------------------------------------------------------------------------
  public function up_20240306144703()
  {
  	$_tableName = 'teste2';

		$this->beginTransaction();

		try {
			$fields = [ 
			    'id' => [
			    	'primary_key'    => TRUE,
				    'type'           => 'INT',
				    'constraint'     => 10,
				    'unsigned'       => TRUE,
				    'auto_increment' => TRUE
			    ],
			    'created_at' => [
				    'type'        => 'TIMESTAMP',
				    'default'     => 'CURRENT_TIMESTAMP()',
			    ],
			    'updated_at' => [
				    'type'        => 'TIMESTAMP',
				    'default'     => 'NULL ON UPDATE CURRENT_TIMESTAMP()',
				    'null'				=> TRUE
			    ]
			];

			$this->createTable($_tableName, $fields);

			$this->commit();

		} catch (\Exception $e) {
			$this->rollBack($e);
		}

  }

  public function down_20240306144703()
  {
  	$_tableName = 'teste2';

		try {
		$this->dropTable($_tableName, TRUE);
		} catch (\Exception $e) {
			$this->rollBack($e);
		}

  }

}