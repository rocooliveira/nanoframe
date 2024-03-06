<?php 
namespace App\Controller\Command;

use Nanoframe\Core\Migration;

if ( PHP_SAPI !== 'cli' ) exit('No web access allowed');


/**
 * Classe para migrations em banco de dados mysql
 * Uma tabela chamamada "migrarions" será criada e configurada automaticamente
 * Utilize o terminal a partir da raiz do projeto para criar suas migrations
 * Rode o comando "php cli.php command/Migrate make" para criar novos arquivos de migration no diretório "migrarions"
 * 
 * Além de "make" também estão disponíveis os parâmetros: 
 * - "latest": 	 Migra para a versão mais recente
 * - "last": 		 Retorna para versão anterior com base na atual definida no banco (tabela migrations)
 * - "rollback": Retorna para uma versão indicada
 * - "combine":  Consolida todos arquivos de de migrações em um único arquivo. Isso pode ser útil no casos onde já
 * 							 existem muitos arquivos no diretorio "migrations", eles acabam sendo acumulados ao longo do tempo
 * 							 a medida que você desenvolve sua aplicação.
 * 
 * Nota: Caso necessite de algo não disponível nesta classe não altere esse arquivo para atender seus propósitos
 * se necessário crie um novo arquivo extendendo "Migration"
 */
class Migrate extends Migration
{

	private $migrationFileName = '';

	private $migrationFileTable = '';

	public function __construct()
	{
		parent::__construct();

		$this->checkDatabase();		
		
	}


	public function help()
	{
		print_r(
      "\n\33[32m===========================================================\33[0m" .
      "\n\33[32mParâmetros Disponíveis\n===========================================================\33[0m \n" .
      "\33[32m     make:\33[0m Método de criação do arquivo de migração.\n".
      "\33[32m   latest:\33[0m Migra para a versão de migração mais recente.\n".
      "\33[32m rollback:\33[0m Migra para versão estpulada.\n".
      "\33[32m  combine:\33[0m Cria um arquivo consolidado com todas as migrações disponíveis.\n".
      "\33[32m     info:\33[0m Uma tabela que mostra informações sobre o status de suas migrações.\n".
      "\33[32m     help:\x1b[0m Exibe a seção de ajuda.\n" .
      "\33[32m===========================================================\33[0m\n\n" 

		);
	}

	public function make()
	{
		echo 'Insira o nome do arquivo da migration: ';

		$fileName = trim(fgets(STDIN));

		if (empty($fileName) OR $fileName === "")
		{
			echo "\033[33m". 'Insira um nome válido para o arquivo de migration!' ."\033[0m" .  PHP_EOL;
			die;
		}

		$migrationsList = $this->findMigrations();


		$mFounded = 0;
		$fileNameTmp = $fileName;

		foreach($migrationsList as $key => $item){
	    if (strpos($item, $fileName) !== FALSE){
        $mFounded++;
        if($mFounded)
          $fileNameTmp=  "{$fileName}_v" . ($mFounded + 1);
	    }
		}

		$fileName = ($fileName != $fileNameTmp) ? $fileNameTmp : $fileName;

		$this->migrationFileName = $fileName;


		echo 'Nome da tabela no DB: ';

		$migrationFileTable = trim(fgets(STDIN));

		if (empty($migrationFileTable) OR $migrationFileTable === "")
		{
			echo "\033[31m". 'Insira um nome de tabela válido para o arquivo de migration!' ."\033[0m" .  PHP_EOL;
			die;
		}

		$this->migrationFileTable = $migrationFileTable;


		$fileName = $this->formatFileName($fileName);


		$migrationVersion = date("YmdHis");

		// anexa a migartion numero ou timestamp
		$fileFullName = $migrationVersion  .  '_'  .  $fileName  .  '.php';



		// Obtem o conteúdo padrão do arquivo de migração
		$fileContent = $this->getFileContent($fileName);


		if ( ! $this->writeMigrationFile($fileFullName, $fileContent))
		{
			echo "\033[31m" . 'Não foi possível criar o arquivo!' ."\033[0m" .  PHP_EOL;
			die;
		}

		// Atualiza a versão de migração no Schema
		$this->updateLatestFileVersion($migrationVersion);


		echo "\033[32m".'Arquivo de migração "' . $fileFullName . '" criado com sucesso!' ."\033[0m" . PHP_EOL;

		return;
	}

	/**
	 * Exibe lista de arquivos de migrations e o status deles na linha de migrações
	 * @return void
	 */
	public function info()
	{


		$content = 'Nenhuma migration encontrada!';

		$headings = ['Status','Migration'];

		$migrations = $this->findMigrations();

		if ((is_array($migrations)) && (count($migrations) > 0))
		{
			$row_width = 0;
			$content = '';
			$header = '';
			$rows = [];
			$version = $this->getVersion('version');

			foreach($headings as $key => $value)
			{
				$header .= '| '  .  $value  .  ' ';
			}

			foreach ($migrations as $value) 
			{
				if ( ! empty($value))
				{	
					$string = '| 1      | ';


					if ( (int) $this->getMigrationNumber($value) > $version)
					{
						$string = '| 0      | ';
					}

					$string = $string  .  basename( str_replace('.php', '', $value) ) .  ' ';
					$rows[] = $string;
					$row_width = (strlen($string) > $row_width) ? strlen($string) : $row_width;
				}
			}

			if (count($rows) > 0)
			{
				foreach ($rows as $row)
				{
					$content .= str_pad($row, $row_width, " ", STR_PAD_RIGHT)  .  '|'  .  PHP_EOL;
				}

							// Camada inferior
				$content = $content  .  '+'  .  str_pad("", $row_width - 1, "-", STR_PAD_LEFT)  .  '+'  .  PHP_EOL;
			}

						// Paddings
			$header = str_pad($header, $row_width, " ", STR_PAD_RIGHT)  .  '|'  .  PHP_EOL;

						// Camada Superior
			$header = '+'  .  str_pad("", $row_width - 1, "-", STR_PAD_LEFT)  .  '+'  .  PHP_EOL  .  $header;

						// Camada inferior
			$header = $header  .  '+'  .  str_pad("", $row_width - 1, "-", STR_PAD_LEFT)  .  '+'  .  PHP_EOL;


			$content = PHP_EOL  .  $header  .  $content;
		}

		echo $content;
	}

	private function runMigration($file, $method){

  	include_once($file);

  	$migrationClassName = $this->getMigrationClassName( $file );

  	$migrationInstance = new $migrationClassName();

  	if( !class_exists($migrationClassName) ){
			echo "\033[31m" . "Classe $migrationClassName Não encontrada no arquivo" ."\033[0m" .  PHP_EOL;
  		echo "- $file" .  PHP_EOL;
  		die;
  	}

  	$migrationInstance->$method();

	}

	/**
	 * Busca arquivos de versão no diretório de migrations e migra para a versão mais recente
	 * @return void
	 */
	public function latest()
	{

		try {
				
			$files = $this->findMigrations(TRUE);

			$latestVersion = array_key_last($files);

			$currentVersion = $this->getVersion('version');

	    if($currentVersion == $latestVersion){
				echo "\033[32m".'Nada para migrar por aqui. Você já possui versão mais recente! ' ."\033[0m" . PHP_EOL;
	      return;
	    }

	    foreach ($files as $itemVersion => $file) {

	    	if( $itemVersion <= $currentVersion){
	    		continue;
	    	}

	    	$this->runMigration($file, 'up');

	    	$this->updateCurrentVersion($itemVersion);

	    }

			echo PHP_EOL . "\033[32m".'✅ Migrado para a versão mais recente!' ."\033[0m" . PHP_EOL;

		} catch (\Exception $e) {
			echo "\nErro durante a transação: " . $e->getMessage();
			die;
		}
	}

	/**
	 * Busca arquivos de versão no diretório de migrations e migra para a versão anterior
	 * @return void
	 */
	public function last()
	{
		try {
			
			$files = $this->findMigrations(TRUE);

			$currentVersion = $this->getVersion('version');


			$this->runMigration( $files[$currentVersion], 'down' );

    	$lastVersion = 0;

    	foreach ($files as $key => $item) {

    		if($key == $currentVersion){
    			break;
    		}

    		$lastVersion = $key;

    	}

    	$this->updateCurrentVersion($lastVersion);


    	echo PHP_EOL . "\033[32m".'✅ Migrado para a versão anterior!' ."\033[0m" . PHP_EOL;

		} catch (\Exception $e) {
			echo "\nErro durante a transação: " . $e->getMessage();
			die;
		}
	}

	/**
	 * Retorna até a versão estopulada
	 * @param  string $entryText Texto de entrada da funcao
	 * @return void
	 */
	public function rollback($entryText = NULL)
	{
		echo $entryText ?? 'Entre com o numero da versão desejada (entrada parcial exibirá uma lista): ';

		$rollbackEntry = trim(fgets(STDIN));

		if (empty($rollbackEntry) || !ctype_digit($rollbackEntry) )
		{
			echo "\033[33m". 'Insira um numero de versão válido para o arquivo de migration!' ."\033[0m" .  PHP_EOL;

			$this->rollback();

			return;
		}

		$files = $this->findMigrations();

		$migrationsFound = array_filter($files, function($key) use ($rollbackEntry){
			return strpos($key, $rollbackEntry) === 0;
		}, ARRAY_FILTER_USE_KEY);

		if( ! $migrationsFound ){
			echo "\033[33m". 'Nenhum migation encontrada com a entrada fornecida' ."\033[0m" .  PHP_EOL;

			$this->rollback();

			return;
		}

		$content = NULL;

		$headings = ['Versão	','Migration'];


		if (count($migrationsFound) > 1)
		{

			echo PHP_EOL . 'Utilize uma das versões de migration encontradas na lista a baixo:';

			$row_width = 0;
			$content = '';
			$header = '';
			$rows = [];
			$version = $this->getVersion('version');

			foreach($headings as  $value)
			{
				$header .= '| '  .  $value  .  ' ';
			}

			foreach ($migrationsFound as $versionKey => $value) 
			{
				if ( ! empty($value))
				{	
					$string = "| $versionKey | ";

 
					$string = $string  .  substr(basename( str_replace('.php', '', $value) ), 15)  .  ' ';
					$rows[] = $string;
					$row_width = (strlen($string) > $row_width) ? strlen($string)  : $row_width;
				}
			}

			if (count($rows) > 0)
			{
				foreach ($rows as $row)
				{
					$content .= str_pad($row, $row_width, " ", STR_PAD_RIGHT)  .  '|'  .  PHP_EOL;
				}

				// Camada inferior
				$content = $content  .  '+'  .  str_pad("", $row_width - 1, "-", STR_PAD_LEFT)  .  '+'  .  PHP_EOL;
			}

			// Paddings
			$header = str_pad($header, $row_width - 6, " ", STR_PAD_RIGHT)  .  '|'  .  PHP_EOL;

			// Camada Superior
			$header = '+'  .  str_pad("", $row_width - 1, "-", STR_PAD_LEFT)  .  '+'  .  PHP_EOL  .  $header;

			// Camada inferior
			$header = $header  .  '+'  .  str_pad("", $row_width - 1, "-", STR_PAD_LEFT)  .  '+'  .  PHP_EOL;


			$content = PHP_EOL  .  $header  .  $content;
		
			echo $content;

			$this->rollback('Entre com uma da versões da lista acima ou tente outro valor: ');

			return;

		}


		$foundVersion = [
			'key'  => current(array_keys($migrationsFound)),
			'file' => current($migrationsFound),
		];


		$currentVersion = $this->getVersion('version');

		if( $foundVersion['key'] >= $currentVersion ){
			echo PHP_EOL  . "\033[33m" . "A versão escolhida deve ser menor que a atual"  . "\033[0m" .  PHP_EOL;
	   	$this->rollback();
	    exit;
		}


		echo PHP_EOL  . "\033[33m"."Deseja efetuar rollback para a versão {$foundVersion['key']}?". "\033[0m" .  PHP_EOL;
		echo "Continuar? (Y/N) - ";

		$stdin = fopen('php://stdin', 'r');
		$response = fgetc($stdin);

		if ( strtoupper( $response ) != 'Y' ) {
	   echo "Cancelado operação..." . PHP_EOL;
	   sleep(2);
	   $this->rollback();
	   exit;
		}


		try{

			$migrationsReverseList = array_reverse($files);
			$retrieve = FALSE;

			$startVersion = $currentVersion;
			$endVersion = $foundVersion['key'];

			$version = NULL;

			foreach ($migrationsReverseList as $file) {
			
				$version = $this->getMigrationNumber($file);

				if($version == $endVersion) {
					$this->updateCurrentVersion($version);
					break;
				}

				if($version == $startVersion) $retrieve = TRUE;

				if( ! $retrieve ) continue;


				$this->runMigration( $this->migrationPath . $file, 'down' );


				$this->updateCurrentVersion($version);

			}

			if( $version  ){
    		echo PHP_EOL . "\033[32m"."✅ Migrado para a versão: $version" ."\033[0m" . PHP_EOL;
			}else{

				echo PHP_EOL . "\033[31m"."❌ Algo errado. Rollback não pode ser efetuado" ."\033[0m" . PHP_EOL;
			}
    	

		} catch (\Exception $e) {
			echo "\nErro durante a transação: " . $e->getMessage();
			die;
		}

	}

	/**
	 * Consolida todos os arquivos de migração em um único arquivo.
	 * @return void
	 */
	public function combine()
	{

		if( $this->isUpdatedDb() == FALSE ){

			$msg = 'Seu banco de dados foi migrado para última versão disponível.' . PHP_EOL;
			$msg .= 'Para criar um aquivo consolidado primeiramente atualize para versão mais recente.' . PHP_EOL;

			echo "\033[33m". $msg ."\033[0m" .  PHP_EOL;

			exit;
		}

		$timestamp = date('YmdHis');

		$totalConsolidated = $this->countConsolidatedMigrations();

		$suffix = $totalConsolidated  ? '_v'. ($totalConsolidated + 1) : '';

		$consolidatedFilepath = $this->migrationPath . $timestamp . '_consolidated_migration'.$suffix.'.php';

		$migrationFiles = $this->findMigrations(TRUE, TRUE);

		if(  count($migrationFiles) < 2 ){
			$msg = 'Nenhum conjunto de arquivos disponível para criar o arquivo consolidado.';
			echo PHP_EOL  . "\033[33m".$msg. "\033[0m" .  PHP_EOL;
			exit;
		}

		usort($migrationFiles, function($a, $b) {
		  return (int)basename($a, '.php') - (int)basename($b, '.php');
		});


		$fileContent = '<?php'  .  PHP_EOL  .  PHP_EOL;
		$fileContent .= 'use Nanoframe\Core\DatabaseForge;'  .  PHP_EOL;

		$fileContent .= PHP_EOL  .  'class Migration_consolidated_migration' . $suffix;

		$fileContent .= ' extends DatabaseForge {'  .  PHP_EOL  .  PHP_EOL;

		$upMethod = '';
		$downMethod = '';

		foreach ($migrationFiles as $file) {
			$version = $this->getMigrationNumber(basename($file, '.php'));

			$upMethod .= '		$'."this->up_$version();" . PHP_EOL;
		}

		$migrationFilesReverse = array_reverse( $migrationFiles, TRUE );

		foreach ($migrationFilesReverse as $file) {
			$version = $this->getMigrationNumber(basename($file, '.php'));

			$downMethod .= '		$'."this->down_$version();" . PHP_EOL;
		}

		$fileContent .= '	public function up()'  .  PHP_EOL;
		$fileContent .= '	{'  .  PHP_EOL;
		$fileContent .= $upMethod;
		$fileContent .= '	}' . PHP_EOL . PHP_EOL;

		$fileContent .= '	public function down()'  .  PHP_EOL;
		$fileContent .= '	{'  .  PHP_EOL;
		$fileContent .= $downMethod;
		$fileContent .= '	}' . PHP_EOL . PHP_EOL;

		file_put_contents( $consolidatedFilepath, $fileContent);


		$divider = '	//' . str_repeat('-', 72) . PHP_EOL;

		/**
		 * consolida arquivos de migrations em funcoes de up e down
		 * segmentadas pelo timestamp do arquivo original
		 */
		foreach ($migrationFiles as $file) {

			include_once($file);

			$fileContent = '';

	    $basename = basename($file, '.php');

	    $version = $this->getMigrationNumber($basename);

	    $className = $this->getMigrationClassName($file);

	    $code = $this->getFunctionCode($className);

			$formattedTableNameValue = var_export($code->tableName, true);

	    $fileContent .= "  public function up_$version()" . PHP_EOL;
	    $fileContent .= "  {" . PHP_EOL;
	    $fileContent .= "  	\$_tableName = $formattedTableNameValue;" . PHP_EOL;

	    $outMethodUp = str_replace('$this->tableName', '$_tableName', $code->up);


	    $fileContent .= $outMethodUp . PHP_EOL . PHP_EOL;
		
	    $fileContent .= "  }" . PHP_EOL . PHP_EOL;


	    $fileContent .= "  public function down_$version()" . PHP_EOL;
	    $fileContent .= "  {" . PHP_EOL;
	    $fileContent .= "  	\$_tableName = $formattedTableNameValue;" . PHP_EOL . PHP_EOL;

	    $outMethodDown = str_replace('$this->tableName', '$_tableName', $code->down);

	    $fileContent .= $outMethodDown . PHP_EOL . PHP_EOL;
		
	    $fileContent .= "  }" . PHP_EOL;


	    // Adicionar ao arquivo consolidado
	    file_put_contents($consolidatedFilepath, "\n$divider  // Origem: $basename.php\n$divider", FILE_APPEND);
	    file_put_contents($consolidatedFilepath, $fileContent, FILE_APPEND);

		}

		$fileContent = PHP_EOL . '}';

		$success = file_put_contents( $consolidatedFilepath, $fileContent, FILE_APPEND);

		if(! $success ){
			echo PHP_EOL  . "\033[31m"."❌ Erro ao gerar arquivo consolidado!". "\033[0m" .  PHP_EOL;
			die;
		}

		$this->updateLatestFileVersion($timestamp);
		$this->updateCurrentVersion($timestamp);

		echo PHP_EOL  . "\033[32m"."Arquivo consolidado gerado com sucesso!". "\033[0m" .  PHP_EOL;
		echo PHP_EOL  . "\033[33m"."Deseja remover todos arquivos anteriores?". "\033[0m" .  PHP_EOL;
		echo "Continuar? (Y/N) - ";

		$stdin = fopen('php://stdin', 'r');
		$response = fgetc($stdin);

		if ( strtoupper( $response ) != 'Y' ) {

			$msg = 'Lembre-se de remover todos os arquivos de migration manualmente após sua verificação.' .  PHP_EOL;
			$msg .= 'Manter os arquivos e rodar novamente um comando upgrade ou downgrade de versões de migrações irá' .  PHP_EOL;
			$msg .= 'gerar um conflito de versões, pois todos os arquivos já foram consolidados em um único arquivo.' .  PHP_EOL;

			echo PHP_EOL  . "\033[33m". $msg . "\033[0m" .  PHP_EOL;

			exit;

		}


		foreach ($migrationFiles as $file) {
			$ret = unlink($file);

			if( $ret === FALSE ){
				echo PHP_EOL  . "\033[31m"."❌ Erro ao excluir arquivos de migrations anteriores". "\033[0m" .  PHP_EOL;
				echo "\033[33m". 'Verifique o diretório e faça as exclusões manualmente' . "\033[0m" .  PHP_EOL;
				die;
			}
		}


		echo PHP_EOL  . "\033[32m"."Operação concluída com sucesso". "\033[0m" .  PHP_EOL;

	}


	private function getFunctionCode($className) {
	  $reflectionClass = new \ReflectionClass($className);

	  $methodCode = [];

	  foreach (['up', 'down'] as $methodName) {

		  // Verifica se a classe tem o método (função) especificado
		  if (!$reflectionClass->hasMethod($methodName)) {
		      return null;
		  }

		  $reflectionMethod = $reflectionClass->getMethod($methodName);

		  // Obtém o nome do arquivo que contém a classe
		  $fileName = $reflectionMethod->getFileName();

		  // Verifica se o arquivo existe
		  if (!file_exists($fileName)) {
		    return null;
		  }

		  // Obtém o conteúdo do arquivo
		  $fileContent = file_get_contents($fileName);

		  // Encontra o início e o fim da definição do método 
		  $methodStart = $reflectionMethod->getStartLine() +1;
		  $methodEnd = $reflectionMethod->getEndLine() -1;

		  $methodCode[$methodName] = implode("\n", 
		  	array_slice(explode("\n", $fileContent), $methodStart, $methodEnd - $methodStart)
		  );

		}
		
		if ( $reflectionClass->hasProperty('tableName') ) {
	    $reflectionProperty = $reflectionClass->getProperty('tableName');

	    // Torna a propriedade acessível (necessário para propriedades protegidas)
	    $reflectionProperty->setAccessible(true);

	    // Cria uma instância da classe sem chamar o construtor
	    $classWithoutConstructor = $reflectionClass->newInstanceWithoutConstructor();

	    // Obtém o valor da propriedade usando a instância da classe
	    $propertyValue = $reflectionProperty->getValue($classWithoutConstructor);
	  }else{
	    $propertyValue = '';
	  }


    return (object)['tableName' => $propertyValue, 'up' => $methodCode['up'] , 'down' => $methodCode['down'] ];

	}

	/**
	 * Retorna o código padrão encontrado no arquivo de migração
	 *
	 * @param string $fileName Nome do arquivo de migração
	 * @return string 
	 */
	private function getFileContent($fileName = '')
	{

		$fileContent = '<?php'  .  PHP_EOL  .  PHP_EOL;	// <?php
		$fileContent .= 'use Nanoframe\Core\DatabaseForge;'  .  PHP_EOL;


		$fileContent .= PHP_EOL  .  'class Migration_'  .  $fileName;

		$fileContent .= ' extends DatabaseForge {'  .  PHP_EOL  .  PHP_EOL;


		$fileContent .= '	/**'  .  PHP_EOL;
		$fileContent .= '	 * Nome da tabela usada nesta migration!'  .  PHP_EOL;
		$fileContent .= '	 *'  .  PHP_EOL;
		$fileContent .= '	 * @var string'  .  PHP_EOL;
		$fileContent .= '	 */'  .  PHP_EOL;
		$fileContent .= '	protected $'.'tableName = "'  . trim($this->migrationFileTable) .  '";'  .  PHP_EOL  . PHP_EOL;

		// Conteudo da funcao up
		$fileContent .= '	public function up()'  .  PHP_EOL;
		$fileContent .= '	{'  .  PHP_EOL  .  PHP_EOL;
		$fileContent .= '		$this->beginTransaction();'  .  PHP_EOL  .  PHP_EOL;
		$fileContent .= '		try {'  .  PHP_EOL;

		if (strpos($this->migrationFileName, 'modify') !== FALSE)
		{
			$fileContent .= '			$this->addColumn($'.'this->tableName, $'.'this->fields());'  .  PHP_EOL;
		}
		else
		{
			$fileContent .= "			\$fields = [ 
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
			];"  .  PHP_EOL .  PHP_EOL;

			$fileContent .= '			$this->createTable($'.'this->tableName, $fields);'  .  PHP_EOL  .  PHP_EOL;
		}
		
		$fileContent .= '			$this->commit();'  .  PHP_EOL  .  PHP_EOL;

		$fileContent .= '		} catch (\Exception $e) {'  .  PHP_EOL;
		$fileContent .= '			$this->rollBack($e);'  .  PHP_EOL;
		$fileContent .= '		}'  .  PHP_EOL;

		$fileContent .= '	}'  .  PHP_EOL;

		$fileContent .= PHP_EOL;

		// Conteudo da funcao down
		$fileContent .= '	public function down()'  .  PHP_EOL;
		$fileContent .= '	{'  .  PHP_EOL;
		$fileContent .= '		try {'  .  PHP_EOL .  PHP_EOL;

		if (strpos($this->migrationFileName, 'modify') !== FALSE)
		{
			$fileContent .= '		if (is_array($'.'this->fields()))'  .  PHP_EOL;
			$fileContent .= '		{'  .  PHP_EOL;
			$fileContent .= '			foreach($this->fields() as $key => $val)'  .  PHP_EOL;
			$fileContent .= '			{'  .  PHP_EOL;
			$fileContent .= '				$this->dropColumn($'.'this->tableName,$'.'key);'  .  PHP_EOL;
			$fileContent .= '			}'  .  PHP_EOL;
			$fileContent .= '		}'  .  PHP_EOL;
		}
		else
		{
			$fileContent .= '		$this->dropTable($'.'this->tableName, TRUE);'  .  PHP_EOL;
		}

		$fileContent .= '		} catch (\Exception $e) {'  .  PHP_EOL;
		$fileContent .= '			$this->rollBack($e);'  .  PHP_EOL;
		$fileContent .= '		}'  .  PHP_EOL;

		$fileContent .= '	}'  .  PHP_EOL;


		if (strpos($this->migrationFileName, 'modify') !== FALSE)
		{	
			$fileContent .= PHP_EOL;
			$fileContent .= '	/**'  .  PHP_EOL;
			$fileContent .= '	 * Retorna um array dos campos a serem usados nas funções para up e down!'  .  PHP_EOL;
			$fileContent .= '	 *'  .  PHP_EOL;
			$fileContent .= '	 * @return array'  .  PHP_EOL;
			$fileContent .= '	 */'  .  PHP_EOL;
			$fileContent .= '	protected function fields()'  .  PHP_EOL;
			$fileContent .= '	{'  .  PHP_EOL;
			$fileContent .= '		return [];'  .  PHP_EOL;
			$fileContent .= '	}'  .  PHP_EOL;
		}
		
		// Fecha tag da classe
		$fileContent .= PHP_EOL  .  '}'  .  PHP_EOL  .  PHP_EOL;

		$fileContent .= '?>';

		return $fileContent;
	}


}

?>
