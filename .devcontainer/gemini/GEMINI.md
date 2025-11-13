<laravel-boost-guidelines>

=== regras de fundação =====

**# Diretrizes do Laravel Boost**

As diretrizes do Laravel Boost são especificamente selecionadas pelos mantenedores do Laravel para este aplicativo. Essas diretrizes devem ser seguidas à risca para aumentar a satisfação do usuário na criação de aplicativos Laravel.

**## Contexto fundamental**

Este aplicativo é um aplicativo Laravel e seu principal pacote e versões de ecossistemas Laravel estão abaixo. Você é um especialista em todos eles. Certifique-se de respeitar esses pacotes e versões específicos.

- php - 8.4.13
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11

**## Convenções**

- Você deve seguir todas as convenções de código existentes usadas neste aplicativo. Ao criar ou editar um arquivo, verifique os arquivos irmãos para ver se a estrutura, a abordagem e a nomenclatura estão corretas.
- Use nomes descritivos para variáveis e métodos. Por exemplo, `isRegisteredForDiscounts`, e não `discount()`.
- Verifique se há componentes existentes para reutilizar antes de escrever um novo.

**## Scripts de verificação**

- Não crie scripts de verificação nem faça ajustes quando os testes cobrirem essa funcionalidade e provarem que ela funciona. Os testes de unidade e de recursos são mais importantes.

**## Estrutura e arquitetura do aplicativo**

- Atenha-se à estrutura de diretórios existente - não crie novas pastas de base sem aprovação.
- Não altere as dependências do aplicativo sem aprovação.

**## Agrupamento de front-end**

- Se o usuário não vir uma alteração de front-end refletida na interface do usuário, isso pode significar que ele precisa executar `npm run build`, `npm run dev` ou `composer run dev`. Pergunte a eles.

**## Respostas**

- Seja conciso em suas explicações - concentre-se no que é importante em vez de explicar detalhes óbvios.

**## Arquivos de documentação**

- Você só deve criar arquivos de documentação se for explicitamente solicitado pelo usuário.

=== regras do boost =====

**## Laravel Boost**

- O Laravel Boost é um servidor MCP que vem com ferramentas poderosas projetadas especificamente para esse aplicativo. Use-as.

**## Artisan**

- Use a ferramenta `list-artisan-commands` quando precisar chamar um comando Artisan para verificar os parâmetros disponíveis.

**## URLs**

- Sempre que compartilhar um URL de projeto com o usuário, use a ferramenta `get-absolute-url` para garantir que esteja usando o esquema, o domínio/IP e a porta corretos.

**## Tinker / Depuração**

- Você deve usar a ferramenta `tinker` quando precisar executar PHP para depurar código ou consultar modelos Eloquent diretamente.
- Use a ferramenta `database-query` quando precisar apenas ler do banco de dados.

**## Leitura de registros do navegador com a ferramenta `browser-logs**

- Você pode ler os registros do navegador, os erros e as exceções usando a ferramenta `browser-logs` do Boost.
- Somente os registros recentes do navegador serão úteis - ignore os registros antigos.

**## Pesquisa de documentação (extremamente importante)**

- O Boost vem com uma poderosa ferramenta `search-docs` que você deve usar antes de qualquer outra abordagem. Essa ferramenta passa automaticamente uma lista de pacotes instalados e suas versões para a API remota do Boost, de modo que retorna apenas a documentação específica da versão para a circunstância do usuário. Você deve passar um array de pacotes para filtrar se souber que precisa de documentos para determinados pacotes.
- A ferramenta "search-docs" é perfeita para todos os pacotes relacionados ao Laravel, incluindo Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch etc.
- Você deve usar essa ferramenta para pesquisar a documentação do ecossistema Laravel antes de recorrer a outras abordagens.
- Pesquise a documentação antes de fazer alterações no código para garantir que estamos adotando a abordagem correta.
- Use consultas múltiplas, amplas, simples e baseadas em tópicos para começar. Por exemplo: `['rate limiting', 'routing rate limiting', 'routing']`.
- Não adicione nomes de pacotes às consultas - as informações sobre os pacotes já são compartilhadas. Por exemplo, use `test resource table`, e não `filament 4 test resource table`.

**### Sintaxe de pesquisa disponível**

- Você pode e deve passar várias consultas de uma vez. Os resultados mais relevantes serão retornados primeiro.

1. Pesquisas de palavras simples com formação automática de haste - query=authentication - encontra 'authenticate' e 'auth'

2. Várias palavras (lógica AND) - consulta=limite de taxa - encontra conhecimento contendo "taxa" E "limite"

3. Frases citadas (posição exata) - consulta="rolagem infinita" - as palavras devem ser adjacentes e nessa ordem

4. Consultas mistas - query=middleware "rate limit" - "middleware" E a frase exata "rate limit"

5. Consultas múltiplas - queries=["authentication", "middleware"] - QUALQUER um desses termos

=== Regras do php =====

**## PHP**

- Sempre use chaves para estruturas de controle, mesmo que tenham uma linha.

**### Construtores**

- Use a promoção da propriedade do construtor do PHP 8 em `__construct()`.

- <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>

- Não permita métodos `__construct()` vazios com zero parâmetros.

**### Declarações de tipo**

- Sempre use declarações explícitas de tipo de retorno para métodos e funções.
- Use dicas de tipo PHP apropriadas para parâmetros de métodos.

<code-snippet *name=*"Tipos de retorno explícitos e parâmetros de métodos" *lang=*"php">

protected function isAccessible(User $user, ?string $path = null): bool

{

...

}

</code-snippet>

**## Comentários**

- Prefira blocos PHPDoc em vez de comentários. Nunca use comentários dentro do próprio código, a menos que haja algo *_muito_* complexo acontecendo.

**## Blocos do PHPDoc**

- Adicione definições úteis de tipos de forma de matriz para matrizes quando apropriado.

**## Enums**

- Normalmente, as chaves em um Enum devem estar em TitleCase. Por exemplo: `FavoritePerson`, `BestLake`, `Monthly`.

=== regras do laravel/core ===

**## Faça as coisas do jeito Laravel**

- Use os comandos `php artisan make:` para criar novos arquivos (ou seja, migrações, controladores, modelos etc.). Você pode listar os comandos Artisan disponíveis usando a ferramenta `list-artisan-commands`.
- Se estiver criando uma classe PHP genérica, use `artisan make:class`.
- Passe `--no-interaction` para todos os comandos do Artisan para garantir que eles funcionem sem a entrada do usuário. Você também deve passar as `--opções` corretas para garantir o comportamento correto.

**### Banco de dados**

- Sempre use métodos de relacionamento Eloquent adequados com dicas de tipo de retorno. Prefira métodos de relacionamento em vez de consultas brutas ou uniões manuais.
- Use modelos e relacionamentos do Eloquent antes de sugerir consultas brutas ao banco de dados
- Evite `DB::`; prefira `Model::query()`. Gere código que aproveite os recursos ORM do Laravel em vez de contorná-los.
- Gere código que evite problemas de consulta N+1 usando o carregamento ansioso.
- Use o construtor de consultas do Laravel para operações de banco de dados muito complexas.

**### Criação de modelos**

- Ao criar novos modelos, crie também fábricas e seeders úteis para eles. Pergunte ao usuário se ele precisa de outras coisas, usando `list-artisan-commands` para verificar as opções disponíveis para `php artisan make:model`.

**### APIs e recursos do Eloquent**

- Para APIs, o padrão é usar recursos de API do Eloquent e controle de versão de API, a menos que as rotas de API existentes não o façam, então você deve seguir a convenção de aplicativo existente.

**### Controladores e validação**

- Sempre crie classes de solicitação de formulário para validação em vez de validação em linha nos controladores. Inclua regras de validação e mensagens de erro personalizadas.
- Verifique os Form Requests irmãos para ver se o aplicativo usa regras de validação baseadas em array ou string.

**### Filas**

- Use trabalhos em fila para operações demoradas com a interface `ShouldQueue`.

**### Autenticação e autorização**

- Use os recursos integrados de autenticação e autorização do Laravel (gates, políticas, Sanctum etc.).

**### Geração de URL**

- Ao gerar links para outras páginas, prefira rotas nomeadas e a função `route()`.

**### Configuração**

- Use variáveis de ambiente somente em arquivos de configuração - nunca use a função `env()` diretamente fora dos arquivos de configuração. Sempre use `config('app.name')` e não `env('APP_NAME')`.

**### Testes**

- Ao criar modelos para testes, use as fábricas para os modelos. Verifique se a fábrica tem estados personalizados que podem ser usados antes de configurar manualmente o modelo.
- Faker: Use métodos como `$this->faker->word()` ou `fake()->randomDigit()`. Siga as convenções existentes para usar `$this->faker` ou `fake()`.
- Ao criar testes, utilize `php artisan make:test [options] <name>` para criar um teste de recurso e passe `--unit` para criar um teste de unidade. A maioria dos testes deve ser de recursos.

**### Erro de vídeo**

- Se você receber uma mensagem "Illuminate\Foundation\ViteException: Não foi possível localizar o arquivo no manifesto do Vite", execute o `npm run build` ou peça ao usuário para executar o `npm run dev` ou o `composer run dev`.

=== regras do laravel/v12 ===

**## Laravel 12**

- Utilize a ferramenta `search-docs` para obter a documentação específica da versão.
- Desde o Laravel 11, o Laravel tem uma nova estrutura de arquivos simplificada que este projeto utiliza.

**### Estrutura do Laravel 12**

- Nenhum arquivo de middleware em `app/Http/Middleware/`.
- O `bootstrap/app.php` é o arquivo para registrar middleware, exceções e arquivos de roteamento.
- O `bootstrap/providers.php` contém provedores de serviços específicos do aplicativo.
- ***Sem aplicativo\Console\Kernel.php**** - use `bootstrap/app.php` ou `routes/console.php` para a configuração do console.
- ***Registro automático de comandos**** - os arquivos em `app/Console/Commands/` estão automaticamente disponíveis e não exigem registro manual.

**### Banco de dados**

- Ao modificar uma coluna, a migração deve incluir todos os atributos que foram definidos anteriormente na coluna. Caso contrário, eles serão descartados e perdidos.
- O Laravel 11 permite limitar nativamente os registros carregados com ansiedade, sem pacotes externos: `$query->latest()->limit(10);`.

**### Modelos**

- Os casts podem e provavelmente devem ser definidos em um método `casts()` em um modelo em vez da propriedade `$casts`. Siga as convenções existentes em outros modelos.

=== regras do pint/core ===

**## Formatador de código do Laravel Pint**

- Você deve executar `vendor/bin/pint --dirty` antes de finalizar as alterações para garantir que seu código corresponda ao estilo esperado do projeto.
- Não execute `vendor/bin/pint --test`, simplesmente execute `vendor/bin/pint` para corrigir quaisquer problemas de formatação.

=== Regras do phpunit/core ===

**## Núcleo do PHPUnit**

- Este aplicativo usa o PHPUnit para testes. Todos os testes devem ser escritos como classes PHPUnit. Utilize `php artisan make:test --phpunit <name>` para criar um novo teste.
- Se você vir um teste usando "Pest", converta-o para PHPUnit.
- Toda vez que um teste for atualizado, execute esse teste específico.
- Quando os testes relacionados ao seu recurso estiverem passando, pergunte ao usuário se ele gostaria de executar também todo o conjunto de testes para garantir que tudo ainda esteja passando.
- Os testes devem testar todos os caminhos felizes, caminhos de falha e caminhos estranhos.
- Você não deve remover nenhum teste ou arquivo de teste do diretório de testes sem aprovação. Esses não são arquivos temporários ou auxiliares, são essenciais para o aplicativo.

**### Execução de testes**

- Execute o número mínimo de testes, usando um filtro apropriado, antes de finalizar.
- Para executar todos os testes: `php artisan test`.
- Para executar todos os testes em um arquivo: `php artisan test tests/Feature/ExampleTest.php`.
- Para filtrar em um nome de teste específico: `php artisan test --filter=testName` (recomendado após fazer uma alteração em um arquivo relacionado).

</laravel-boost-guidelines>