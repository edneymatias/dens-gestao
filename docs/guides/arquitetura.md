# Arquitetura e checklist — Tenancy multi-db com auth central (ULIDs)

Última atualização: 2025-11-13

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

## Itens pendentes (PENDENTE) — próximos passos ordenados

Execute um item por vez e reporte quando concluído; eu então liberarei o próximo.

1. (13.4) Rodar migrações do Spatie/permission no contexto do tenant

    - Objetivo: garantir que cada tenant tenha suas próprias tabelas `roles`, `permissions`, etc.
    - Como: o comando de provisionamento irá executar `tenants:migrate` com os migrations apontando para `database/migrations/tenant` (já configurado em `config/tenancy.php`).
    - Racional: isolar permissões por tenant evita vazamento de privilégio.

2. (13.5) Middleware de inicialização de tenancy por sessão

    - Objetivo: criar middleware `InitializeTenancyBySession` que verifica `session('tenant_id')` e chama `tenancy()->initialize($tenant)` ou `tenancy()->end()` conforme necessário; ajustar `cache.prefix` dinamicamente.
    - Racional: garante que requests web operem no contexto do tenant selecionado pelo usuário.

3. (13.6) Tenant selection flow (UI + controller)

    - Objetivo: rota/controller para listar tenants aos quais o usuário tem acesso e trocar `session('tenant_id')` com `session()->regenerate()`.
    - Racional: UX para trocar de contexto sem logout.

4. (13.7) Jobs / Queues: padronizar tenant context in background jobs

    - Objetivo: garantir que jobs carreguem `tenant_id` no payload, e no `handle()` chamem `tenancy()->initialize($tenant)` antes de operar.
    - Racional: workers não têm sessão — precisamos passar contexto explicitamente.

5. (13.8) Filament 4 + Filament Shield integration

    - Objetivo: garantir Filament resources consultem Spatie tables do tenant quando tenancy estiver inicializada e separar painel landlord/global do painel tenant.

6. (13.9) owen-it/laravel-auditing

    - Objetivo: configurar auditing para escrever em DB do tenant (rodar migração do auditing no tenant durante provisionamento).

7. (13.10) Tests automáticos mínimos

    - Escrever testes feature para fluxo: login central → escolher tenant → criar recurso tenant-scoped → confirmar está no DB do tenant; job with tenant_id; spatie permissions are isolated.

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

-   Quero que você peça explicitamente: `Gerar comando provision`, `Criar tenant_user migration` ou `Editar domains migration`.

Se escolher **Gerar comando provision**, eu te entrego o arquivo completo do comando artisan `dens:provision-tenant` pronto para colar em `app/Console/Commands/TenantProvision.php`. Ele irá:

-   aceitar nome e opcional id ULID,
-   gerar ULID se não fornecido,
-   gerar `db_name` seguro (prefix + md5 short),
-   criar o tenant (persistir em `tenants`),
-   chamar `tenancy()->createDatabase()` via manager (usando `DatabaseConfig` do stancl),
-   executar `tenants:migrate` para rodar migrations do tenant,
-   opcionalmente rodar `tenants:seed`.

Se escolher **Editar domains migration**, eu te entrego o patch pronto para ajustar `tenant_id` para `foreignUlid(...)` e as instruções para rodar uma migration segura em produção.

---

Se preferir, posso também criar um script README curto com os comandos de verificação e retomar de onde parou. Diga qual dos dois (provision / domains) você quer que eu gere agora e eu forneço o artefato exato.

## Trabalho futuro (prioridade baixa/planejado)

-   (A) Adicionar contador `provision_attempts` e política de retries: Em produção é útil ter um contador que registra quantas vezes tentamos provisionar um tenant. Após N falhas, o tenant pode ser marcado como `failed` de forma permanente e um alerta operacional é enviado. Implementação sugerida: adicionar coluna `provision_attempts` (integer default 0) e incrementar em cada tentativa; reconciler respeita um limite configurável.

-   (C) Eventos e listeners para provisionamento: Disparar eventos `TenantProvisioned` e `TenantProvisionFailed` no `TenantProvisionService` para permitir integração com notificações, métricas e workflows externos. Listeners relevantes (por exemplo, `SendTenantProvisionedNotification`, `NotifyOpsTeamOnProvisionFailure`) devem ser registrados em `app/Providers/EventServiceProvider.php` e, quando fizerem trabalho pesado, implementar `ShouldQueue`.
