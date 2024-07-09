<?php

namespace Nanoframe\Core;

use \mysqli;

/**
 * Classe base de migrações.
 * Inpirado na simplicidade de uso do Codeigniter 3 (Agradecimentos a equipe desenvolvedora, para esta inspiração).
 *
 */
class Migration 
{
	protected $migrationPath = APP_PATH . '/migrations/';

	protected $host;
	protected $database;
	protected $user;
	protected $pass;

	public function __construct()
	{

		$this->host 		= 	$_ENV['DB_HOST'] ?? NULL;
		$this->database =   $_ENV['DB_NAME'] ?? NULL;
		$this->user 		= 	$_ENV['DB_USER'] ?? NULL;
		$this->pass 		= 	$_ENV['DB_PASSWORD'] ?? '';

		if( !$this->host || !$this->database ||!$this->user ){
			echo "\033[31m". "Os dados de acesso ao banco não foram definidos no arquivo '.env'" ."\033[0m".PHP_EOL;
			die;
		}
	}	

	protected function checkDatabase()
	{

		// Configurações do banco de dados

		$conn = new mysqli($this->host, $this->user, $this->pass);

		// Verifica conexão
		if ($conn->connect_error) {
			echo "\033[31m". "Conexão falhou: " . $conn->connect_error ."\033[0m".PHP_EOL;
			die;
		}


		// Consulta para verificar a existência do banco de dados
		$result = $conn->query("SHOW DATABASES LIKE '$this->database'");

		// Verificar se o banco de dados não existe
		if ($result->num_rows < 1) {

			echo "Banco de dados não encontrado. Deseja criar um banco com o nome definido no seu arquivo .env? (y/n): ";
			$createDatabase = trim(fgets(STDIN));

			if (strtolower($createDatabase) === 'y') {

				$createDatabaseQuery = "CREATE DATABASE $this->database CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
				if ($conn->query($createDatabaseQuery) === TRUE) {

					echo "\032[31m". "Banco de dados '$this->database' criado com sucesso.\n" . "\033[0m".PHP_EOL;

				} else {

					echo "\033[31m". "Erro ao criar o banco de dados: " . $conn->error . "\n" ."\033[0m".PHP_EOL;

				}


			} else {
				echo "\nExecução encerrada. Crie o banco de dados manualmente ou solicite a criação por aqui .\n";
				$conn->close();
				exit;
			}
		}

		$conn->select_db($this->database);

		// Verificar se a tabela "migrations" existe
		$tableExistsQuery = "SHOW TABLES LIKE 'migrations'";
		$tableExistsResult = $conn->query($tableExistsQuery);

		if ($tableExistsResult->num_rows > 0) {
	    // A tabela "migrations" existe, verificar os campos
	    $columnsQuery = "SHOW COLUMNS FROM migrations LIKE 'version'";
	    $versionColumnResult = $conn->query($columnsQuery);

	    $columnsQuery = "SHOW COLUMNS FROM migrations LIKE 'latest_file_version'";
	    $latestFileVersionColumnResult = $conn->query($columnsQuery);

	    if ($versionColumnResult->num_rows == 0 || $latestFileVersionColumnResult->num_rows == 0) {
        // Um ou ambos os campos estão ausentes, adicionar os campos

        if ($versionColumnResult->num_rows == 0) {
            $conn->query("ALTER TABLE migrations ADD COLUMN version BIGINT(10) DEFAULT 0");
        }

        if ($latestFileVersionColumnResult->num_rows == 0) {
            $conn->query("ALTER TABLE migrations ADD COLUMN latest_file_version BIGINT(10) DEFAULT 0");
        }

				echo PHP_EOL  . "\033[33m" . 'Tabela de migração customizada com sucesso'  . "\033[0m" .  PHP_EOL;
	    }

		} else {
	    // A tabela "migrations" não existe, criar a tabela
	    $createTableQuery = "CREATE TABLE migrations (
	                            version BIGINT(10) DEFAULT 0,
	                            latest_file_version BIGINT(10) DEFAULT 0
	                        )";
	   $conn->query($createTableQuery);

	   $conn->query( "INSERT INTO migrations (version) VALUES (0)" );

	   echo "\033[33m". "Tabela de migração criada com sucesso." ."\033[0m".PHP_EOL;

		}

		$conn->close();
	}

	public function findMigrations($includePath = FALSE, $ignoreConsolidatedFiles = FALSE): array
	{
		$migrations = [];

		// carrega todos arquivos .php do diretório de migrations
		foreach (glob($this->migrationPath.'*_*.php') as $key => $file)
		{

			$name = basename($file, '.php');

			// Filtrar arquivos de migração
			if (preg_match('/^\d{14}_(\w+)$/', $name))
			{
				$number = $this->getMigrationNumber($name);

				// Nao pode haver migations com mesmo numero de versao
				if (isset($migrations[$number]))
				{
					$error = sprintf('Migrações com mesmo numero de versao %s.', $number);
					exit($error);
				}

				if( $ignoreConsolidatedFiles ){

					$isConsolidated = strpos($name, 'consolidated_migration') !== FALSE;

					if( $isConsolidated ){
						continue;
					}
				}

				$migrations[$number] = $includePath ? $file : basename($file);
			}
		}

		ksort($migrations);
		return $migrations;
	}

	public function countConsolidatedMigrations(): int
	{
		$migrations = [];

		// carrega todos arquivos .php do diretório de migrations
		foreach (glob($this->migrationPath.'*_*.php') as $key => $file)
		{

			$name = basename($file, '.php');

			// Filtrar arquivos de migração
			if (preg_match('/^\d{14}_(\w+)$/', $name))
			{

				$isConsolidated = strpos($name, 'consolidated_migration') !== FALSE;

				if( ! $isConsolidated ){
					continue;
				}

				$migrations[] = $file;
			}
		}


		return count($migrations);
	}

	public function writeMigrationFile($fileName, $data)
	{
		if ( ! $fp = @fopen($this->migrationPath . $fileName, 'wb'))
		{
			return FALSE;
		}

		flock($fp, LOCK_EX);

		for ($result = $written = 0, $length = strlen($data); $written < $length; $written += $result)
		{
			if (($result = fwrite($fp, substr($data, $written))) === FALSE)
			{
				break;
			}
		}

		flock($fp, LOCK_UN);
		fclose($fp);

		return is_int($result);
	
	}

	/**
	 * Extrai o nome da class da migration a partir do nome do arquivo
	 * @param  string $value nome do arquivo de migration
	 * @return string
	 */
	protected function getMigrationClassName($migration)
	{
		$parts = explode('_', $migration);
		array_shift($parts);

		$name =  'Migration_'. implode('_', $parts);
		return basename($name, '.php');
	}

	protected function formatFileName($file_name = '')
	{
		return str_replace(' ', '_', $file_name);
	}

	/**
	 * Atualiza latest_file_version com o nome do ultimo arquivo de migration criado
	 * @param  int $fileVersion versao
	 */
	protected function updateLatestFileVersion($fileVersion)
	{
		$conn = new mysqli($this->host, $this->user, $this->pass, $this->database);

		$conn->query("UPDATE migrations SET latest_file_version = '$fileVersion'");

		$conn->close();
	}

	/**
	 * Registra no banco a versão atual da ultima migração efetuada.
	 * @param  int $fileVersion versao
	 */
	protected function updateCurrentVersion($fileVersion)
	{
		$conn = new mysqli($this->host, $this->user, $this->pass, $this->database);

		$conn->query("UPDATE migrations SET version = '$fileVersion'");

		$conn->close();
	}

	/**
	 * Extrai o número de migração de um nome de arquivo
	 *
	 * @param	string	$migration
	 * @return	string	Parte numérica de um nome de arquivo de migração
	 */
	protected function getMigrationNumber($migration)
	{
		return sscanf($migration, '%[0-9]+', $number)
			? $number : '0';
	}


	/**
	 * Retorna a versão mais recente do arquivo da tabela de migrações ou a versao atual migrada no banco
	 *
	 * @param string $select Nome "version" para versao atual no banco ou "latest_file_version" para versao
	 *  mais recente disponivel
	 * @return	int	last migration versão do arquivo
	 */
	protected function getVersion($select = 'latest_file_version')
	{
		$conn = new mysqli($this->host, $this->user, $this->pass, $this->database);

		// Verifica conexão
		if ($conn->connect_error) {
			echo "\033[31m". "Conexão falhou: " . $conn->connect_error ."\033[0m".PHP_EOL;
			die;
		}

		$result = $conn->query("SELECT * FROM migrations;");
		
		$row = $result->fetch_object();

		$conn->close();

		return $row ? $row->$select : '0';
	}

	protected function isUpdatedDb()
	{
		$conn = new mysqli($this->host, $this->user, $this->pass, $this->database);

		// Verifica conexão
		if ($conn->connect_error) {
			echo "\033[31m". "Conexão falhou: " . $conn->connect_error ."\033[0m".PHP_EOL;
			die;
		}

		$result = $conn->query("SELECT * FROM migrations;");
		
		$row = $result->fetch_object();

		$conn->close();

		return $row->version == $row->latest_file_version;
	}



}