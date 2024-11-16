<?php

namespace App\Controller\Command;

use Nanoframe\Core\Prompt;

class Entity extends Prompt
{
	private $pdo;

	public function __construct()
	{
		$config =[
			'host'     => $_ENV['DB_HOST'] ?? NULL,
			'dbname'   => $_ENV['DB_NAME'] ?? NULL,
			'username' => $_ENV['DB_USER'] ?? NULL,
			'password' => $_ENV['DB_PASSWORD'] ?? '',
		];

		try {
			$this->pdo = new \PDO("mysql:host={$config['host']};dbname={$config['dbname']}", $config['username'], $config['password']);
			$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		} catch (\PDOException $e) {
			$this->printDanger( "Erro ao se conectar ao banco de dados: " . $e->getMessage() );
			exit;
		}
	}

	public function create()
	{

		$tables = $this->pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);

		$response = $this->readLine('Deseja gerar classes para todas as tabelas? (s/n): ');

		if ( $response === 'n') {
			
			$tableName = $this->readLine('Especifique o nome da tabela: ');
			if (in_array($tableName, $tables)) {
				$tables = [$tableName];
			} else {

				$this->printDanger("Tabela $tableName não encontrada no banco de dados.");
				exit;
			}
		}

		foreach ($tables as $table) {

			// ignora tabela padrao de migrations do sistema
			if( $table == 'migrations' ){
				continue;
			}

			$columns = $this->pdo->query("DESCRIBE $table")->fetchAll(\PDO::FETCH_ASSOC);
			$className = ucfirst($table);

			$filePath = APP_PATH . "/Entity/$className.php";

			if (file_exists($filePath)) {

				$response = $this->readLine("O arquivo $className.php já existe. Deseja sobrescrever? (s/n): ", 'warning');
				if ( $response !== 's') {
					echo "Pulando $className.php...\n";
					continue;
				}
			}

			$classContent = "<?php\n";
			$classContent .= "namespace App\Entity;\n\n";
			$classContent .= "class $className {\n\n";

			foreach ($columns as $column) {
				$classContent .= "  public \${$column['Field']};\n";
			}

			$classContent .= "\n  public function __construct(\$data = []) {\n";
			
      $classContent .= "    if (is_object(\$data)) {\n";
      $classContent .= "      \$data = (array) \$data;\n";
      $classContent .= "    }\n";

      foreach ($columns as $column) {
        $field = ucfirst($column['Field']);
        $classContent .= "    \$this->set$field(\$data['{$column['Field']}'] ?? null);\n";
      }
      $classContent .= "  }\n\n";

      $classContent .= "  public function toArray(array \$omitFields = []): array {\n";
      $classContent .= "    \$data = get_object_vars(\$this);\n";
      $classContent .= "    return array_diff_key(\$data, array_flip(\$omitFields));\n";
      $classContent .= "  }\n\n";

      foreach ($columns as $column) {
        $field = ucfirst($column['Field']);
        $type = $this->mapColumnTypeToPHP($column['Type']);
        $nullable = $column['Null'] === 'YES';
        $autoIncrement = strpos($column['Extra'], 'auto_increment') !== false;
        $default = $this->determineDefaultValue($column['Default'], $column['Extra']);

        $classContent .= "  public function get$field()";
        $classContent .= $nullable ? ": ?$type" : ": $type";
        $classContent .= " {\n";
        $classContent .= "    return \$this->{$column['Field']};\n";
        $classContent .= "  }\n\n";

        $classContent .= "  public function set$field(";
        $classContent .= "\$value = $default): void {\n";

        if (!$nullable && !$autoIncrement) {
          $classContent .= "    if (\$value === null) {\n";
          $classContent .= "      throw new \InvalidArgumentException('{$column['Field']} não pode ser nulo');\n";
          $classContent .= "    }\n";
        }
        
        if ($type === 'float') {
          $classContent .= "    if (\$value !== null && !is_float(\$value) && !is_string(\$value)) {\n";
          $classContent .= "      throw new \InvalidArgumentException('Tipo esperado para {$column['Field']} é float ou string');\n";
          $classContent .= "    }\n";
          $classContent .= "    if (is_string(\$value)) {\n";
          $classContent .= "      \$value = floatval(\$value);\n";
          $classContent .= "    }\n";
        } else {
        	
        	if($type == 'int'){
        		$type = 'numeric';
        	}

	        $classContent .= "    if (\$value !== null && !is_$type(\$value)) {\n";
	        $classContent .= "      throw new \InvalidArgumentException('Tipo esperado para {$column['Field']} é: {$type}');\n";
	        $classContent .= "    }\n";

      	}

        $classContent .= "    \$this->{$column['Field']} = \$value;\n";
        $classContent .= "  }\n\n";
      }

      $classContent .= "}\n";

			file_put_contents($filePath, $classContent);
			
			$this->printSuccess("Classe $className gerada com sucesso!");

		}
	}

	public function determineDefaultValue($default, $extra)
	{

    if (is_null($default)) {
      return 'NULL';
    }

    if (is_numeric($default)) {
      return $default;
    }

    if (strpos($extra, 'on update') !== false || $default === 'CURRENT_TIMESTAMP') {
      return 'NULL';
    }

    // Se for uma string (inclui valores como ''), adicionar aspas
    return "'" . addslashes($default) . "'";
	}

	private function mapColumnTypeToPHP(string $columnType): string
	{
		$columnType = strtolower($columnType);
		if (strpos($columnType, 'int') !== false) {
			return 'int';
		} elseif (strpos($columnType, 'float') !== false || strpos($columnType, 'double') !== false || strpos($columnType, 'decimal') !== false) {
			return 'float';
		} elseif (strpos($columnType, 'bool') !== false) {
			return 'bool';
		} else {
			return 'string';
		}
	}
}
