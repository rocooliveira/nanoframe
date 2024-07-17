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

			$classContent = "<?php\n\n";
			$classContent .= "class $className {\n\n";

			foreach ($columns as $column) {
				$classContent .= "  public \${$column['Field']};\n";
			}

			$classContent .= "\n  public function __construct(\$data = []) {\n";
			foreach ($columns as $column) {
        $field = ucfirst($column['Field']);
        $classContent .= "    \$this->set$field(\$data['{$column['Field']}'] ?? null);\n";
			}
			$classContent .= "  }\n\n";

			foreach ($columns as $column) {
				$field = ucfirst($column['Field']);
				$type = $this->mapColumnTypeToPHP($column['Type']);
				$nullable = $column['Null'] === 'YES' ? 'true' : 'false';


				$classContent .= "  public function get$field(): $type {\n";
				$classContent .= "    return \$this->{$column['Field']};\n";
				$classContent .= "  }\n\n";


				$classContent .= "  public function set$field($type \$value): void {\n";
        if ($nullable === 'false') {
          $classContent .= "    if (\$value === null) {\n";
          $classContent .= "      throw new \InvalidArgumentException('{$column['Field']} não pode ser nulo');\n";
          $classContent .= "    }\n";
        }
				$classContent .= "    if (!is_{$type}(\$value)) {\n";
				$classContent .= "      throw new \InvalidArgumentException('O tipo esperado para {$column['Field']} é {$type}');\n";
				$classContent .= "    }\n";

				$classContent .= "    \$this->{$column['Field']} = \$value;\n";
				$classContent .= "  }\n\n";
			}

			$classContent .= "}\n";

			file_put_contents($filePath, $classContent);
			
			$this->printSuccess("Classe $className gerada com sucesso!");

		}
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
