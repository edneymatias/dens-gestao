Vou explicar de novo, direto ao ponto: o que aconteceu, por que aconteceu e por que as mudanças que fiz resolvem o problema.

Resumo do problema
O teste de soft-delete do User falhou porque o modelo User grava no banco central (tem protected $connection = 'central'), enquanto os comandos de teste e asserções estavam operando no banco padrão da suíte de testes.
Resultado: o registro criado não apareceu no banco que a asserção estava verificando, então a tabela "parecia vazia" para o teste.
Por que isso aconteceu (detalhes técnicos)
A aplicação usa múltiplas conexões: a conexão default (configurada para os testes como sqlite em memória) e uma conexão central (arquivo sqlite em local de desenvolvimento).
Durante o teste:
As factories / criação de modelos escreveram no banco central (porque User::$connection = 'central').
Inicialmente não havia garantias de que as migrations tinham sido executadas para a conexão central no contexto do teste — portanto, a tabela users não existia no banco central ou asserções estavam sendo avaliadas contra o banco errado.
Além disso, asserções como assertDatabaseHas sem passar explicitamente a conexão verificam por padrão a conexão default, não central.
Há também complexidade extra com transações e múltiplas conexões durante testes: a trait/infra de teste pode usar transações somente na conexão default, deixando a central fora da transação de teste.
O que eu mudei e por que resolve
Rodei as migrations para ambas as conexões no setUp do teste:
migrate:fresh (default) e migrate:fresh --database=central
Por que: garante que a tabela users existe na conexão central usada pelo modelo User.
No teste do usuário, passei a inserir o registro diretamente na conexão central (via DB::connection('central')->table('users')->insert(...)) e carregar o modelo User::find($id):
Por que: evita problemas com factories que podem usar transações apenas na conexão default ou outros efeitos colaterais de multi-connection durante os testes.
Atualizei as assertivas para checar explicitamente a conexão central (assertDatabaseHas(..., 'central') / assertSoftDeleted(..., 'central')):
Por que: agora a verificação olha pro banco correto onde os dados realmente foram gravados.
Com essas mudanças os testes passaram porque:

o esquema está presente na conexão correta,
os dados são escritos/consultados no mesmo banco,
e não há ambiguidade entre conexões durante asserções.
Recomendações (próximos passos)
Para uma solução mais limpa e duradoura (evitar inserts manuais nos testes), sugiro uma das opções seguintes:

Configurar o TestCase para suportar múltiplas conexões em transação/migração. Por exemplo:
usar RefreshDatabase / DatabaseMigrations e garantir que você também chame as migrations para central no setUp, ou
definir protected $connectionsToTransact = ['central']; no TestCase.php quando usar transações (para que a central participe das transações de teste).
Ajustar as factories para criar registros na conexão correta (por ex. invocar a factory e então rebindar para a central, ou criar helpers de teste que façam Model::on('central')->factory()->create()).
Alternativa operacional: rodar os testes configurando DB_CONNECTION=central quando o foco é testar modelos que vivem na central (menos ideal se você tiver muitos testes que usam conexões diferentes).
Se quiser, eu implemento agora uma dessas melhorias no TestCase.php (por exemplo, adicionar protected $connectionsToTransact = ['central']; e garantir as migrations) para evitar inserts diretos nos testes. Quer que eu faça isso?
