FROM mcr.microsoft.com/devcontainers/php:8.4

# 1. Instala dependências do Linux necessárias para as extensões PHP
RUN apt-get update && export DEBIAN_FRONTEND=noninteractive \
    && apt-get -y install --no-install-recommends libicu-dev libzip-dev libpq-dev

# 2. Instala as extensões do PHP oficialmente
RUN docker-php-ext-install intl zip pdo_pgsql

# 3. Copia as configurações do Gemini
COPY gemini/ /home/vscode/.gemini/

# 4. Altera as permissões dos arquivos de configuração do Gemini
RUN chown -R vscode:vscode /home/vscode/.gemini/