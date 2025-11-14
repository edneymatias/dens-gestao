# Arquitetura e checklist — Tenancy multi-db com auth central (ULIDs)

Última atualização: 2025-11-14

Este documento é o checklist autoritativo do trabalho que estamos fazendo — inclui o que já foi realizado, o raciocínio por trás das decisões e os próximos passos. Siga-o passo-a-passo; cada etapa pequena deve ser concluída e marcada como "RESOLVIDO" antes de avançar. A segurança da implementação final é um prioridade: considerar o gerenciamento de sessions, cache, armazenamento em disco como parte do problema do tentant. Você nunca implementa nada, para cada tarefa, você apresenta passos didáticos para que eu execute, inclusive apresentando código para copiar e colar. Estou aprendendo, você é meu coach.

## Objetivo final (resumido)

-   Autenticação central (landlord) com `users` em um banco central.
-   Tenants multi-database isolados (cada tenant tem seu próprio DB).
-   Tenants e recursos identificados por ULIDs (IDs ordenáveis, 26 chars).
-   Sessão central (não troca de cookie) + `tenant_id` na sessão para trocar contexto.
-   Per-tenant roles/permissions (Spatie) e integração com Filament Shield.
-   Auditoria por-tenant (owen-it/laravel-auditing) gravando no DB do tenant.

## Convenções e decisões fixas (não há escolha)

-   Pacote de tenancy: stancl/tenancy com storage em database (padrão do pacote).
-   Identificadores (PKs) públicos: ULIDs para `users` e `tenants` e recursos tenant-scoped.
-   Nomes de banco dos tenants: `db_name` seguro (prefix + short hash), NÃO usar ULID cru como nome do DB.
-   Sessão: central; trocar `tenant_id` na sessão e regenerar sessão ao trocar tenant.
-   Roles/permissions: por-tenant (Spatie) — migrações do Spatie rodadas dentro do DB do tenant.

## O que já foi feito (RESOLVIDO)

Cada item inclui os arquivos alterados e comandos usados.

-   [RESOLVIDO] Usuários com ULID (PK)

        -   Arquivos alterados:
            -   `database/migrations/0001_01_01_000000_create_users_table.php` — `id` alterado para `ulid('id')->primary()`; `sessions.user_id` alterado para `ulid('user_id')`.
            -   `app/Models/User.php` — `HasAppDefaults`/`HasUlids` usado; trait ajustada para `getKeyType()`/`isIncrementing()`.
        -   Comandos executados:
            -   `php artisan migrate:fresh --seed` (ambiente dev)
            -   Verificação via Tinker: `

    \App\Models\User::factory()->create()->id` → ULID (26 chars) - Racional: ULIDs são ordenáveis, seguros para exposição em URLs e uniformes entre tenants.

-   [RESOLVIDO] Trait padrão `HasAppDefaults`

    -   Arquivo criado: `app/Concerns/HasAppDefaults.php` (trait central que aplica `HasUlids` e comportamento PK string/non-incrementing sem declarar propriedades que conflitem).
    -   Racional: evita repetição em todos os models que usarão ULID; mantém padrão central.

-   [RESOLVIDO] Instalado e configurado stancl/tenancy

    -   Comandos executados:
        -   `composer require stancl/tenancy`
        -   `php artisan tenancy:install` (instalador do package)
    -   Arquivo `config/tenancy.php` existe e foi ajustado para apontar o `tenant_model` para `App\Models\Tenant` (feito na sequência).
    -   Racional: stancl é moderno, testado e fornece bootstrappers (database, cache, filesystem, queue) para tenancy.

-   [RESOLVIDO] Migration `tenants` adaptada para ULID + `db_name`

    -   Arquivo editado: `database/migrations/2019_09_15_000010_create_tenants_table.php`.
        -   `id` agora é `ulid('id')->primary()`.
        -   Adicionada coluna `db_name` (unique) e `name`, `data`.
    -   Comando executado: `php artisan migrate:fresh` (dev).
    -   Racional: stancl armazena tenants em DB; usamos ULID para PK e `db_name` para mapear o ULID para um nome de banco seguro.

-   [RESOLVIDO] `Tenant` model criado e ligado ao stancl

    -   Arquivo criado: `app/Models/Tenant.php` estendendo `Stancl\Tenancy\Database\Models\Tenant` e usando `HasAppDefaults`.
    -   `config/tenancy.php` atualizado: `'tenant_model' => \App\Models\Tenant::class`.
    -   Comando: `composer dump-autoload`.
    -   Racional: estendemos o model do pacote para poder adicionar convenções/traits da aplicação.

-   [RESOLVIDO] Migrations aplicadas com sucesso

    -   `php artisan migrate:fresh` executado sem erros e tabelas criadas: `users`, `tenants`, `domains`, `sessions`, etc.

-   [RESOLVIDO] Comando `dens:provision-tenant` e serviço de provisionamento

    -   Arquivos alterados/criados:
        -   `app/Console/Commands/TenantProvision.php` — comando artisan `dens:provision-tenant` implementado (assinatura: `dens:provision-tenant {name} {--id=} {--seed} {--async}`).
        -   `app/Services/TenantProvisionService.php` — serviço `TenantProvisionService` que cria o registro do tenant, gera `db_name`, cria o banco físico via o manager do stancl, roda `tenants:migrate` e opcionalmente `tenants:seed`.
        -   `app/Jobs/ProvisionTenantJob.php` — job assíncrono para provisionamento e reconciliação.
    -   Comandos/Verificações:
        -   `php artisan dens:provision-tenant "Acme Inc."` (ou com `--async` para enfileirar).
        -   Testes unitários presentes: `tests/Unit/TenantProvisionServiceTest.php`, `TenantProvisionServiceSeedTest.php`, `TenantProvisionServiceFailureTest.php`, `TenantProvisionCommandAsyncTest.php`.
    -   Racional: automatiza a criação do DB do tenant, execução de migrações e seeds, e fornece modo assíncrono/reconciler.

-   [RESOLVIDO] Tabela pivot `tenant_user` criada no landlord

    -   Arquivo criado: `database/migrations/2025_11_13_000030_create_tenant_user_table.php` — usa `foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete()` e `foreignUlid('user_id')->constrained('users')->cascadeOnDelete()`, com `role`, `is_owner`, índices e `unique(['tenant_id','user_id'])`.
    -   Comando executado: `php artisan migrate` (dev).
    -   Racional: fonte de verdade para membros do tenant e papéis.

-   [RESOLVIDO] Ajuste da migration `domains` para usar `foreignUlid`

    -   Arquivo alterado: `database/migrations/2019_09_15_000020_create_domains_table.php` — `tenant_id` agora é `foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete()`.
    -   Racional: garante integridade referencial e tipo consistente com `tenants.id`.

-   [RESOLVIDO] Migrações do Spatie/permission rodando por tenant (13.4)

    -   Arquivos criados/alterados:
        -   `composer.json`/`composer.lock` — instalado `spatie/laravel-permission` v6.
        -   `database/migrations/tenant/2025_11_14_000000_create_permission_tables.php` — cópia ajustada da migration do pacote, mantendo `roles`/`permissions` com `bigIncrements`, mas convertendo colunas `model_id` para `ulid()`/`string` conforme necessário e garantindo execução apenas no banco do tenant.
        -   `app/Models/Tenant.php` — sobrescreve `getCustomColumns()` para que `name`, `db_name`, `provision_state` etc. permaneçam como colunas reais (evitando que o Stancl mova esses campos para `data`).
    -   Comandos/Testes:
        -   `php artisan migrate:fresh`
        -   `php artisan test --filter=TenantPermissionMigrationsTest`
    -   Racional: provisionar um tenant agora cria automaticamente as tabelas de roles/perms isoladas em cada banco, pré-requisito para Filament Shield.

-   [RESOLVIDO] Middleware `InitializeTenancyBySession` (13.5)

    -   Arquivos criados/alterados:
        -   `app/Http/Middleware/InitializeTenancyBySession.php` — lê `session('tenant_id')`, inicializa ou encerra tenancy, ajusta `cache.prefix` dinamicamente e limpa o contexto após a resposta.
        -   `bootstrap/app.php` — registra o middleware no grupo `web` (antes do `InitializeLocale`).
        -   `tests/Feature/InitializeTenancyBySessionTest.php` — cobre tanto o fluxo feliz quanto sessões com `tenant_id` inválido.
    -   Comandos/Testes:
        -   `php artisan test`
    -   Racional: requests web agora sempre executam no tenant selecionado e o prefixo de cache fica isolado por tenant, evitando vazamentos de sessão/cache.

## Itens pendentes (PENDENTE) — próximos passos ordenados

Execute um item por vez e reporte quando concluído; eu então liberarei o próximo.

1. (13.6) Tenant selection flow (UI + controller)

    - Objetivo: rota/controller para listar tenants aos quais o usuário tem acesso e trocar `session('tenant_id')` com `session()->regenerate()`.
    - Racional: UX para trocar de contexto sem logout.
    - Observações: garantir que o controller utilize o middleware recém-criado para ativar tenancy após atualizar a sessão e expor feedback claro ao usuário; aproveitar a tabela `tenant_user` como fonte dos tenants disponíveis.

2. (13.7) Jobs / Queues: padronizar tenant context in background jobs

    - Objetivo: garantir que jobs carreguem `tenant_id` no payload, e no `handle()` chamem `tenancy()->initialize($tenant)` antes de operar.
    - Racional: workers não têm sessão — precisamos passar contexto explicitamente.
    - Observações: reutilizar o prefixo de cache por tenant quando necessário e limpar o contexto no `finally` para evitar vazamentos entre jobs.

3. (13.8) Filament 4 + Filament Shield integration

    - Objetivo: garantir Filament resources consultem Spatie tables do tenant quando tenancy estiver inicializada e separar painel landlord/global do painel tenant.
    - Observações: agora que as tabelas do Spatie existem por tenant, alinhar o Filament Shield para trabalhar em cima dessas tabelas isoladas e assegurar que o painel landlord continue usando o contexto central.

4. (13.9) owen-it/laravel-auditing

    - Objetivo: configurar auditing para escrever em DB do tenant (rodar migração do auditing no tenant durante provisionamento).
    - Observações: seguir o mesmo padrão das migrations do Spatie (copiar para `database/migrations/tenant`) e validar em conjunto com o provisionamento.

5. (13.10) Tests automáticos mínimos

    - Escrever testes feature para fluxo: login central → escolher tenant → criar recurso tenant-scoped → confirmar está no DB do tenant; job with tenant_id; Spatie permissions são isoladas e o middleware de sessão mantém o contexto correto.

## Racionais e notas técnicas (curtas)

-   ULID vs int: ULID é preferível para recursos expostos, pesquisa por tempo e escalabilidade. Mantivemos ints em tabelas internas onde não há ganho (ex.: `domains.id` pode permanecer INT).
-   db_name: usar `db_name` mapeado evita problemas de caracteres maiúsculos ou regras de nomes de DB no MySQL/Postgres. O stancl fornece hooks (`DatabaseConfig::generateDatabaseNamesUsing`) — o comando de provisionamento usará um hash curto do ULID.
-   Sessions: mantemos sessão central para não invalidar o login; apenas gravamos `tenant_id` na sessão.
-   Pacotes (Spatie / Filament / Auditing): migrar suas tabelas no contexto do tenant; não alterar as tabelas do package para ULIDs sem planejar (para Spatie, manter PKs originais é aceitável; apenas FK que apontam para tenant/user devem usar ULID).

## Comandos úteis (para retomar rápido amanhã)

-   Verificar tabela tenants:

```bash
php artisan tinker
>>> \Schema::hasTable('tenants')
>>> \Schema::getColumnListing('tenants')
```

-   Criar um usuário de teste e checar ULID:

```bash
php artisan tinker
>>> \App\Models\User::factory()->create()->id
```

-   Verificar model Tenant:

```bash
php artisan tinker
>>> \App\Models\Tenant::class
```

-   Rodar migrações (dev):

```bash
php artisan migrate:fresh
```

## Próximo passo imediato (faça só um item)

-   Nossa prioridade atual é implementar a infraestrutura de seleção de tenant via Filament + Spatie (13.6 → 13.8).

Escolha explícita de ação abaixo (faça um item por vez):

-   `Instalar e configurar Filament` — cria o painel landlord que fornecerá a UI para seleção de tenant e administração.
-   `Criar endpoint de seleção de tenant` — endpoint seguro que valida membership e seta `session('tenant_id')` com `session()->regenerate()` (infra para Filament consumir).
-   `Gerar comando provision` — (opcional) gerar o comando artisan `dens:provision-tenant` pronto para colar em `app/Console/Commands/TenantProvision.php`.

Se escolher `Instalar e configurar Filament`, eu: instalarei o pacote Filament (versão compatível com Laravel 12), aplicarei as publicações necessárias, e criarei um Page/Table action mínimo que consome o endpoint de seleção de tenant.

Se escolher `Criar endpoint de seleção de tenant`, eu: criarei o controller, rotas e testes feature que garantem que a sessão é atualizada e que o middleware `InitializeTenancyBySession` inicializa o tenancy no próximo request.

## Super‑Admin (Landlord) — implementação e segurança (novo)

Última atualização: 2025-11-14

Para proteger o painel landlord recomendamos um fluxo simples e auditável:

-   **Flag no usuário**: campo booleano `is_superadmin` em `users` (default `false`).
-   **Gate::before**: regra global que concede todas as abilities quando `is_superadmin === true`.
-   **Middleware de proteção**: `EnsureSuperAdmin` para proteger rotas/painel sensíveis (Filament landlord).
-   **Seeder operacional**: `SuperAdminSeeder` para criar/atualizar o usuário inicial usando `SUPERADMIN_EMAIL`/`SUPERADMIN_PASSWORD` do ambiente.
-   **Testes**: cobertura feature que valida Gate::before e o middleware de acesso ao painel.

Implementação recomendada (passos concretos):

1. Migration: `2025_11_14_000100_add_is_superadmin_to_users_table.php` — adiciona `is_superadmin` boolean (default false) após `password`.
2. Seeder: `database/seeders/SuperAdminSeeder.php` — `User::firstOrNew(['email' => env('SUPERADMIN_EMAIL')])`, define `is_superadmin = true` e `password` vindo de `env('SUPERADMIN_PASSWORD')` (recomenda-se trocar a senha em produção e habilitar 2FA).
3. `Gate::before`: adicionar em `App\\Providers\\AppServiceProvider::boot()` (ou `AuthServiceProvider` se preferir) a regra:

```php
Gate::before(fn (?User $user, $ability) => $user && $user->is_superadmin ? true : null);
```

4. Middleware: `app/Http/Middleware/EnsureSuperAdmin.php` — aborta `403` quando o usuário autenticado não é superadmin. Registrar o middleware nas rotas Filament landlord via `config/filament.php` (adicionar à chave `middleware`).

5. Tests: `tests/Feature/SuperAdminAccessTest.php` — casos:

    - Super‑admin obtém `Gate::allows('any-ability') === true`.
    - Usuário normal não obtém acesso a abilities não definidas.
    - Middleware `EnsureSuperAdmin` retorna 403 para usuário não-superadmin quando acessa rota protegida.

Operacional / segurança (checklist curto):

-   Habilitar 2FA para contas superadmin em produção.
-   Registrar auditoria (quem tornou alguém superadmin) — preferencialmente via eventos/listener que emite `UserPromotedToSuperAdmin`.
-   Ter processo manual (ou via CI) para rotacionar a senha do superadmin quando necessário; evitar uso de senha padrão em produção.
-   Limitar acesso administrativo por IP/ACL e monitorar falhas de autenticação.

Critério de aceitação:

-   O painel landlord só é acessível por usuários com `is_superadmin = true` (ou accounts autorizadas manualmente).
-   `Gate::before` garante permissões elevadas no contexto landlord sem afetar a consistência das verificações de permissão per-tenant.

## Suporte a Mobile (adicionado)

Última atualização: 2025-11-14

Observação: a interface do usuário será provida pelo Filament (landlord). Para clientes mobile futuros, precisamos garantir autenticação token-based e inicialização explícita do tenancy por token/headers — a sessão central usada pelo navegador não serve para mobile.

Recomendações e checklist mínimo:

-   **Autenticação API**: adicionar `laravel/sanctum` para emitir personal access tokens, ou `laravel/passport` se OAuth2 for necessário. Tokens podem ser scoped por `tenant_id`.
-   **Middleware API de tenancy**: criar `InitializeTenancyByTokenOrHeader` que extrai `tenant_id` do token claims ou do header `X-Tenant-Id`, valida o acesso do usuário ao tenant (checar `tenant_user`) e chama `tenancy()->initialize($tenant)`.
-   **Tokens scoped**: preferir emitir tokens já vinculados a um `tenant_id` (scoped tokens) para reduzir superfície de erro do cliente.
-   **Endpoints**: fornecer endpoints `/api/v1/login`, `/api/v1/token/revoke`, `/api/v1/me` com exemplos de payload e explicações de headers obrigatórios (`Authorization: Bearer <token>`, `X-Tenant-Id: <ulid>` — se não for scoped).
-   **Jobs & background**: jobs disparados pela API devem receber `tenant_id` no payload e inicializar tenancy no worker (13.7).
-   **Rate limiting & observability**: aplicar rate limits por token/tenant e registrar `tenant_id` em logs/metrics.

Próximo passo relacionado a mobile (depois de Filament+Spatie): implementar `Sanctum` + `InitializeTenancyByTokenOrHeader` e um conjunto mínimo de testes que cobrem login, seleção de tenant por token, e criação de recurso tenant-scoped via API.

## Filament 4 — integração (detalhes e próximos passos)

Última atualização: 2025-11-14

Esta seção resume as recomendações práticas para instalar e integrar o **Filament 4** com a nossa infraestrutura de tenancy e com o `spatie/laravel-permission` (Filament Shield). Segue um plano de trabalho alinhado às práticas da versão 4 do Filament.

Principais decisões

-   O painel **landlord** (admin central) roda sobre o `users` central e deve usar a sessão central (`web` guard).
-   Os recursos que operam sobre dados do tenant devem executar somente depois que `InitializeTenancyBySession` tiver inicializado o tenant.
-   A integração com permissões (Spatie) será feita via Filament Shield (ou equivalente) e executará no DB do tenant quando o tenancy estiver ativo.

Instalação mínima

1. Instalar o Filament (ajuste a versão se necessário):

```bash
composer require filament/filament:^4.1
php artisan filament:install
php artisan vendor:publish --tag=filament-config
npm install && npm run build
```

2. Publicar e revisar o `config/filament.php` gerado.

Configurações recomendadas (ajustes em `config/filament.php`)

-   `auth` / `guard`: garanta que o painel landlord use o `web` guard apontando para o `App\\Models\\User` central. Ex.: `guard => 'web'`.
-   `middleware`: inclua `web` e `auth` como padrão (o instalador faz isso). Para ter certeza que o tenancy está disponível dentro das rotas do Filament, confirme que o middleware `InitializeTenancyBySession` está registrado no grupo `web` (já o registramos em `bootstrap/app.php`). Se preferir, adicione explicitamente o middleware nas rotas do Filament via `config/filament.php` — por exemplo:

```php
'middleware' => ['web', \\App\\Http\\Middleware\\InitializeTenancyBySession::class, 'auth'],
```

Observação: a ordem importa — `InitializeTenancyBySession` precisa executar antes de qualquer checagem de permissões/recursos que dependam do tenant.

Landlord x Tenant panels

-   Recomendamos manter o painel landlord como o painel Filament principal (prefix `/admin` ou `filament`) autenticado contra o usuário central.
-   Para o painel tenant (se for necessário oferecer painel por tenant), criar um painel separado (outro prefix/guard) ou usar o mesmo painel com o middleware de tenancy ativo — dependerá da UX desejada. Importante: não permitir que o painel landlord use o contexto do tenant por acidente.

Filament Shield e Spatie

-   Instale e configure o pacote de integração entre Filament e Spatie (Filament Shield). Ele deve ser instalado após o Spatie já ter suas migrations preparadas para rodar por tenant (já temos `database/migrations/tenant/...` prontas).
-   Workflow recomendado:
    1. Instalar Filament Shield (seguir instruções do pacote escolhido).
    2. Publicar resources/permissions e sincronizar roles/permissions por tenant (quando tenancy estiver inicializado).
    3. Gerar policies e recursos Filament que utilizam `can()`/`hasPermissionTo()` normalmente — as checagens rodarão no contexto do tenant se `tenancy()->initialized` for true.

Pontos de atenção

-   Migrations do Spatie devem ser executadas no DB do tenant durante o provisionamento (já preparado no passo 13.4). Não execute as migrations do Spatie no landlord.
-   Se usar recursos Filament que chamam Eloquent models tenant-scoped, garanta que as queries sejam executadas apenas após a inicialização do tenancy (middleware em posição correta e testes cobrindo o fluxo).
-   Filament's navigation / visibility callbacks podem chamar `auth()->user()->can(...)` — essas chamadas dependem de tenancy se as permissões forem tenant-scoped.

Tarefas e checklist (próximos passos concretos)

1. Instalar Filament (rodar os comandos acima) e revisar `config/filament.php`. [DEV]
2. Ajustar `config/filament.php` para garantir `InitializeTenancyBySession` roda para todas as rotas do Filament ou confiar no registro global do middleware em `web`. [DEV]
3. Criar uma `Filament Page` ou `Resource` (landlord) que consuma `GET /tenants` e dispare `POST /tenants/select` (ação de Table/Modal). [DEV]
4. Instalar Filament Shield e configurar a integração com `spatie/laravel-permission`. Rodar as publicações necessárias. [DEV]
5. Escrever testes feature que cobrem: login landlord → seleção de tenant via Filament action → requests subsequentes executam no DB do tenant; permissões via Spatie estão ativas no contexto do tenant. [TEST]
6. Documentar os passos e arquivos alterados (atualizar este doc com os arquivos criados). [DOC]

Critérios de aceitação

-   Painel Filament landlord lista tenants do usuário e consegue trocar o tenant ativo com sucesso.
-   Após seleção via Filament, `tenancy()->initialized()` é true e as operações CRUD gravam no DB do tenant.
-   Filament Shield aplica permissões usando as tabelas Spatie do tenant (quando tenancy ativo).

Estimativa e ordem de execução sugerida

-   Fase 1 (1 dia): Instalar Filament, publicar config, ajustar middleware e criar uma Page mínima para seleção de tenant (consumindo os endpoints já implementados).
-   Fase 2 (1 dia): Instalar Filament Shield, configurar integração com Spatie, garantir migrations por-tenant.
-   Fase 3 (0.5–1 dia): Escrever testes feature e ajustar pequenas falhas.

Notas finais

Seguindo essa abordagem alinhada ao Filament 4, a interface de administração e seleção de tenant fica padronizada, testável e reaproveitável. Depois de completarmos Filament + Spatie, passamos para o suporte mobile (Sanctum) conforme checklist já adicionado.

---

Se preferir, posso também criar um script README curto com os comandos de verificação e retomar de onde parou. Diga qual dos dois (provision / domains) você quer que eu gere agora e eu forneço o artefato exato.

## Trabalho futuro (prioridade baixa/planejado)

-   (A) Adicionar contador `provision_attempts` e política de retries: Em produção é útil ter um contador que registra quantas vezes tentamos provisionar um tenant. Após N falhas, o tenant pode ser marcado como `failed` de forma permanente e um alerta operacional é enviado. Implementação sugerida: adicionar coluna `provision_attempts` (integer default 0) e incrementar em cada tentativa; reconciler respeita um limite configurável.

-   (C) Eventos e listeners para provisionamento: Disparar eventos `TenantProvisioned` e `TenantProvisionFailed` no `TenantProvisionService` para permitir integração com notificações, métricas e workflows externos. Listeners relevantes (por exemplo, `SendTenantProvisionedNotification`, `NotifyOpsTeamOnProvisionFailure`) devem ser registrados em `app/Providers/EventServiceProvider.php` e, quando fizerem trabalho pesado, implementar `ShouldQueue`.
