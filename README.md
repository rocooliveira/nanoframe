
# Nanoframe

NanoFrame é um pequeno framework extremamente simples para php. Para projetos pequenos onde você precisa de dinamismo e rapidez 

## Instalação

Clone o repositório ou faça download manual dos arquivos para seu diretório

```bash
git clone https://github.com/your-username/nanoframe.git
```
#### Usando composer

```bash
composer create-project roco/nanoframe meu-diretorio
```
Substitua *meu-diretorio* pelo nome do diretório  no qual deseja que o projeto seja executado.

O comando acima criará uma pasta **meu-diretorio** .

Se você omitir o argumento “meu-diretorio”, o comando criará um diretório “nanoframe”, que pode ser renomeado conforme apropriado.

## Uso
### Setando rotas
Definia suas rotas no arquivo `route.php` no diretório `app/Config`

```php
// routes.php

return [
    'index' => 'HomeController',
    'about' => 'AboutController',
    'contact[POST]' => 'ContactController',
    'admin[GET]' => 'admin\AdminController',
    'admin/dashboard[GET]' => 'admin\DashboardController',
];
```
 
 Neste exemplo, a rota `index` mapeia para o `HomeController`. Por `index` é a rota padrão que deve ser setada como rota de entrada. A rota `about` mapeia para o `AboutController`, e a rota `contact` com `[POST]` especifica que aceita apenas solicitações POST e mapeia para o `ContactController`. Além disso, a rota `admin` mapeia para o `AdminController`, e a rota `admin/dashboard` mapeia para o `DashboardController`.

Você também pode especificar mais de um método permitido passando cada um separado por virgula entre os colchetes. ex: `[GET,POST]` (Por padrão o sistema assume todas rotas com sem métodos definidos como `GET`)

### Subnamespaces em Rotas

Você pode definir subnamespaces dentro de suas rotas usando colchetes. Por exemplo:

```php
// routes.php

return [
    'admin[GET]' => 'admin\[Admin\AdminController]',
    'admin/dashboard[GET]' => 'admin\[Admin/DashboardController]',
];
```
Neste exemplo, a rota `admin` especifica o `AdminController`, e a rota `admin/dashboard` especifica o `DashboardController` ambos dentro do namespace `Admin`.

Por padrão cada controller que criar deve estar dentro do namespace raiz `App/Controller`. No caso acima os controllers foram separados em um sub-namespace  especifico para seu caso de uso, onde o namespace foi definido desta forma  `namespace App/Controller/Admin;`

### Controllers

Crie seus controllers no diretório `controller`. Os controllers devem seguir a convenção de nomenclatura PSR-4

Seus controllers devem extender o controller base do Nanoframe o `BaseControler`:

```php
// HomeController.php

namespace App\Controller;
use Nanoframe\Core\BaseController;

class HomeController extends BaseController
{
    public function index()
    {
        echo "Bem-vindo à página inicial!";
    }
}

// admin/AdminController.php

namespace App\Controller\Admin;
use Nanoframe\Core\BaseController;

class AdminController extends BaseController
{
    public function index()
    {
        echo "Bem-vindo ao painel de administração!";
    }
}

// admin\DashboardController.php

namespace App\Controller\Admin;
use Nanoframe\Core\BaseController;

class DashboardController extends BaseController
{
    public function index()
    {
        echo "Este é o painel!";
    }
}
```

### Model

Crie seus models no diretório `model`. Os models devem seguir a convenção de nomenclatura PSR-4

Seus models devem extender o model base do Nanoframe o `BaseModel`

A classe `BaseModel` é uma camada de abstração  para interagir com o seu banco de dados `MYSQL`.

```php
<?php
namespace App\Model;

use Nanoframe\Core\BaseModel;

class OrderModel extends BaseModel
{
    public function getOrder($orderId)
    {
        return $this->db->table('orders')->where('id = ?', [ $orderId ])->get();
    }

    public function register($data)
    {

        $this->db->table('orders')->insert($data);

        return $this->db->insertId();
    }

}
```
#### Seguem Abaixo algumas definições de uso

**Propriedades**

-   `host`: Armazena o endereço do host do banco de dados.
-   `dbname`: Armazena o nome do banco de dados.
-   `username`: Armazena o nome de usuário do banco de dados.
-   `password`: Armazena a senha do banco de dados.
-   `conn`: Armazena o objeto de conexão PDO.
-   `table`: Especifica a tabela a ser usada na consulta.
-   `select`: Especifica as colunas a serem recuperadas.
-   `where`: Especifica a cláusula WHERE para filtrar resultados.
-   `whereIn`: Especifica a cláusula WHERE IN para filtrar resultados com uma lista de valores.
-   `orderBy`: Especifica a cláusula ORDER BY para classificar resultados.
-   `limit`: Especifica a cláusula LIMIT para restringir o número de resultados.
-   `params`: Armazena um array de parâmetros para instruções preparadas.

**Métodos**

**Métodos do Builder de Consultas**

-   **`table($tableName)`**: Define o nome da tabela a ser usada na consulta.
-   **`select($columns)`**: Define as colunas a serem recuperadas.
-   **`where($condition, $params = [])`**: Adiciona uma cláusula WHERE à consulta.
-   **`whereIn($indexColumn, $params = [])`**: Adiciona uma cláusula WHERE IN à consulta.
-   **`orderBy($column, $direction = 'ASC')`**: Adiciona uma cláusula ORDER BY à consulta.
-   **`limit($count, $offset = 0)`**: Adiciona uma cláusula LIMIT à consulta.

**Métodos de Manipulação de Dados**

-   **`insert($data)`**: Insere uma nova linha no banco de dados.
-   **`insertBatch($data)`**: Insere várias linhas no banco de dados.
-   **`replace($data)`**: Substitui uma linha existente no banco de dados.
-   **`replaceBatch($data)`**: Substitui várias linhas existentes no banco de dados.
-   **`update($data)`**: Atualiza uma linha existente no banco de dados.
-   **`updateBatch($data, $indexColumn)`**: Atualiza várias linhas existentes no banco de dados com base em uma coluna de índice.
-   **`delete()`**: Exclui linhas do banco de dados.

**Métodos de Recuperação de Dados**

-   **`getArray()`**: Recupera todas as linhas como um array associativo.
-   **`get()`**: Recupera todas as linhas como um array de objetos.
-   **`getRow()`**: Recupera a primeira linha como um objeto.

**Outros Métodos**

-   **`query($sql, $params = [])`**: Permite a execução direta de consultas SQL personalizadas.
-   **`resetWrite()`**: Reinicia todas as propriedades de escrita para o estado inicial.


### Loader

#### Views

Arquivos de views devem ser alocados dentro do diretório `app/Views`  e deve ser utilizado o `Loader` do core do NanoFrame para carrega-los. O `BaseController` já possui uma instancia deste Loader em `$this->load`

```php
// HomeController.php

namespace App\Controller;
use Nanoframe\Core\BaseController;

class HomeController extends BaseController
{
    public function index()
    {
        $data = [
            'valor' => 100,
            'cor'   => 'azul'
        ];
        $this->load->view('contato', $data);
    }
}
```

No exemplo acima o arquivo de view `contato.php` e exibido na tela e também são passadas duas variáveis que estarão disponíveis neste arquivo de view utilizando `$valor` e `$cor` 

Ainda é disponível um terceiro parâmetro no métodom view que por padrão é setado como FALSE.  Caso seja setado como true "`$html = Loader::view('contato', $data, TRUE)`" ao invés de imprimir os dados na tela a função retorna a string com o html compilado desta view.

#### Utils

Arquivos para utilitários devem ser alocados dentro do diretório `app/Utils`, eles devem ser arquivos php com funções explicitas e não arquivos de classes.  e deve ser utilizado o `Loader` do core do NanoFrame para carrega-los. O `BaseController` já possui uma instancia deste Loader em `$this->load. 

Pode ser passado um nome de arquivo util ou um array de nomes

```php
// HomeController.php

namespace App\Controller;
use Nanoframe\Core\BaseController;

class HomeController extends BaseController
{
    public function index()
    {
        $this->load->utils('meuUtil1');
        
        minhaFuncao1();
    }
    
    public function loremIpsum(){
        $this->load->utils(['meuUtil1','meuUtil2']);
        minhaFuncao1();
        minhaFuncao2();
    }
}
```
No exemplo acima o arquivo `meuUtil.php` é carregado e a função `minhaFuncao` do arquivo é executada.


### Requisições do servidor (input)
A class `Input` pode ser utilizada para recuperar dados de requisições. 
O `BaseController` já possui uma instancia deste da classe Input em `$this->input`. Por padrão já são aplicado filtros básicos para limpeza da string para manter a segurança dos dados, para evitar a limpeza basta passar o segundo parâmetor como `FALSE`


```php
// HomeController.php

namespace App\Controller;
use Nanoframe\Core\BaseController;

class HomeController extends BaseController
{
    public function index()
    {   
        // obtem todos dados de $_GET com a limpeza da string já aplicada
        $var1 = $this->input->get();
        // obtem todos dados sem aplicar a limpeza da string
        $var2 = $this->input->get(NULL, FALSE);
        
        // obtem o email de $_GET
        $var3 = $this->input->get('email');

        // obtem o name e email de $_GET
        $var4 = $this->input->get(['name', 'email']);
        
    }
}
```

O mesmo pode ser usado seguindo o padrão do exemplo acima para `post` com `$this->input->post()`, para get ou post com `$this->input->getPost()`, e para os métodos `PUT`,  `DELETE`    e `PATCH` deve ser usado: `$this->input->inputStream()`


### Migrations
Um sistema de migrations focado em MYSQL está diponível para ser utilizado via CLI. 
Basta acessar o terminal e utilizar o comando:
```
php cli.php Command/Migrate parametro_desejado 
```
Os parámetros diponíveis são:

**make**: Método de criação do arquivo de migração.

**latest**: Migra para a versão de migração mais recente.

**rollback**: Migra para versão estpulada.

**combine**: Cria um arquivo consolidado com todas as migrações disponíveis

**info**: Uma tabela que mostra informações sobre o status de suas migrações.

**help**: Exibe a seção de ajuda.

O diretório onde serão armazenados os arquivos de migration fica em `app/Migrations` (diretorio reservado exclusivamente para este propósito).

Ao utilizar o sistema de migration será criado automaticamente uma tabela `migrations` em seu banco de dados para fazer o controle de verão das suas migrações no banco.

Ao utilizar o comando **make** será solicitado o nome da sua migration (*ex: create_user_table*) e o nome da tabela: (*ex: user*). Um arquivo com o nome de migration acrescido de um sufixo com timestamp será criado no diretorio de migrations. Dentro desse arquivo você deverá utilizar as funções  up() e donw() para setar as alterações no seu banco de dados, criando uma atualização no banco inserindo novas estruturas ou desfazendo as alterações respectivamente. Todos os arquivos de migração extendem as funcionalidades da classe `DatabaseForge`, com funções de criação, exclusão e de tabela, crianção de chaves estrangeira, índices e outros. Utilize os metodos fornecidos por essa classe para desenvolver suas migrations.

Após criar suas migration você poderá migrar seu banco para versão mais recente utilizando o seguinte comando: 
`php cli.php Command/Migrate latest`

E para desfazer as alterações migrando para versão anterior você pode utilizar o seguinte comando:
`php cli.php Command/Migrate last`

*Lembrando que o método **down()** deve estar devidamente configurado, visando desfazer as alterações efetuadas com o método **up()***

Utilizando o comando **rollback** você poderá retroceder para uma versão especifica do banco de dados. Será solicitado um numero de versão (timestamp do arquivo).

Ao longo do desenvolvimento da sua aplicação seu diretório de `migrations` pode ficar muito "inflado", com muitos arquivos  de migrações conforme sua aplicação cresce. Tendo isso em mente pode ser útil consolidar esse inumeros arquivos em um único arquivo de migração. Isso pode ser bem útil para deixar as coisas mais organizadas. Para fazer isso rode o comando:
`php cli.php Command/Migrate combine`

Todos arquivos de migração existentes serão consolidados em um único arquivo. Ao final do processo será questionado se deseja apagar os arquivos originais. Caso confirme todos arquivos originais serão apagados e você tera apenas seu(s) arquivo(s) de consolidação de migrations. Caso contrário, a exclusão não será feita. Você poderá analisar o que foi gerado, sem apagar os arquivos originais, porém você deve excluir os arquivos originas antes de prosseguir. Manter os arquivos e rodar novamente um comando upgrade ou downgrade de versões de migrações irá gerar um conflito de versões, pois todos os arquivos já foram consolidados em um único arquivo.



## Contribuindo

Sinta-se à vontade para contribuir abrindo issues ou enviando pull requests.

## Licença

Este projeto está licenciado sob a Licença MIT - consulte o arquivo LICENSE para obter detalhes.