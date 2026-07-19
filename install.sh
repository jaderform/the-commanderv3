#!/usr/bin/env bash
# ==================================================================
# COMMANDER - Instalador automatico para VPS (Ubuntu 22.04/24.04)
# ------------------------------------------------------------------
# Instala e configura tudo o que o COMMANDER precisa:
#   - PHP-FPM + extensoes
#   - Caddy (servidor web com SSL automatico + catch-all de dominios)
#   - Swap de 2 GB (seguranca para VPS com 2 GB de RAM)
#   - Ajuste de PHP-FPM para caber em 2 GB
#   - Deploy dos arquivos do COMMANDER em /var/www/commander
#
# Como usar (dentro da VPS, como root):
#   bash install.sh
# ==================================================================

set -e

APP_DIR="/var/www/commander"
GREEN="\033[0;32m"; YELLOW="\033[1;33m"; RED="\033[0;31m"; NC="\033[0m"

say()  { echo -e "${GREEN}==>${NC} $1"; }
warn() { echo -e "${YELLOW}!! ${NC} $1"; }
err()  { echo -e "${RED}ERRO:${NC} $1"; }

if [ "$(id -u)" -ne 0 ]; then
  err "Rode como root:  sudo bash install.sh"
  exit 1
fi

# ------------------------------------------------------------------
# 1) Perguntas iniciais
# ------------------------------------------------------------------
echo ""
say "Vamos configurar o COMMANDER nesta VPS."
echo ""

read -rp "Cole a URL do seu repositorio GitHub (ex: https://github.com/usuario/commander.git): " REPO_URL
while [ -z "$REPO_URL" ]; do
  read -rp "A URL nao pode ser vazia. Cole a URL do GitHub: " REPO_URL
done

read -rp "Seu e-mail (usado pelo Let's Encrypt para os certificados SSL): " LE_EMAIL
while [ -z "$LE_EMAIL" ]; do
  read -rp "O e-mail nao pode ser vazio. Digite seu e-mail: " LE_EMAIL
done

read -rp "Dominio do PAINEL (opcional, ex: painel.seudominio.com) - deixe vazio para acessar so pelo IP: " PANEL_DOMAIN

echo ""
say "Resumo:"
echo "   Repositorio : $REPO_URL"
echo "   E-mail SSL  : $LE_EMAIL"
echo "   Painel      : ${PANEL_DOMAIN:-(somente IP)}"
echo ""
read -rp "Confirma? (s/n): " OK
[ "$OK" = "s" ] || { warn "Cancelado."; exit 0; }

# ------------------------------------------------------------------
# 2) Atualizar sistema e instalar dependencias
# ------------------------------------------------------------------
say "Atualizando o sistema..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y

say "Instalando pacotes basicos..."
apt-get install -y curl git unzip ca-certificates debian-keyring debian-archive-keyring apt-transport-https gnupg

# ------------------------------------------------------------------
# 3) Instalar PHP-FPM + extensoes
# ------------------------------------------------------------------
say "Instalando PHP-FPM..."
apt-get install -y php-fpm php-cli php-curl php-mbstring php-json php-xml

# Descobre a versao do PHP instalada (ex: 8.3)
PHP_VER=$(ls /etc/php/ | grep -E '^[0-9]+\.[0-9]+$' | sort -V | tail -1)
PHP_SOCK="/run/php/php${PHP_VER}-fpm.sock"
say "PHP $PHP_VER detectado. Socket: $PHP_SOCK"

# ------------------------------------------------------------------
# 4) Ajustar PHP-FPM para VPS de 2 GB
# ------------------------------------------------------------------
say "Ajustando PHP-FPM para 2 GB de RAM..."
POOL="/etc/php/${PHP_VER}/fpm/pool.d/www.conf"
if [ -f "$POOL" ]; then
  sed -i 's/^pm = .*/pm = dynamic/' "$POOL"
  sed -i 's/^pm.max_children = .*/pm.max_children = 20/' "$POOL"
  sed -i 's/^pm.start_servers = .*/pm.start_servers = 4/' "$POOL"
  sed -i 's/^pm.min_spare_servers = .*/pm.min_spare_servers = 2/' "$POOL"
  sed -i 's/^pm.max_spare_servers = .*/pm.max_spare_servers = 6/' "$POOL"
fi

# ------------------------------------------------------------------
# 5) Criar swap de 2 GB (se ainda nao existir)
# ------------------------------------------------------------------
if ! swapon --show | grep -q '/swapfile'; then
  say "Criando swap de 2 GB..."
  fallocate -l 2G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=2048
  chmod 600 /swapfile
  mkswap /swapfile
  swapon /swapfile
  grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
else
  say "Swap ja existe, pulando."
fi

# ------------------------------------------------------------------
# 6) Instalar Caddy
# ------------------------------------------------------------------
say "Instalando Caddy..."
if ! command -v caddy >/dev/null 2>&1; then
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
  apt-get update -y
  apt-get install -y caddy
else
  say "Caddy ja instalado."
fi

# ------------------------------------------------------------------
# 7) Baixar os arquivos do COMMANDER
# ------------------------------------------------------------------
say "Baixando o COMMANDER do GitHub..."
if [ -d "$APP_DIR/.git" ]; then
  say "Repositorio ja existe, atualizando (git pull)..."
  git -C "$APP_DIR" pull
else
  rm -rf "$APP_DIR"
  git clone "$REPO_URL" "$APP_DIR"
fi

# Garante a pasta de dados gravavel
mkdir -p "$APP_DIR/data" "$APP_DIR/live"
chown -R www-data:www-data "$APP_DIR"
chmod -R 775 "$APP_DIR/data" "$APP_DIR/live"

# ------------------------------------------------------------------
# 8) Escrever o Caddyfile com os valores corretos
# ------------------------------------------------------------------
say "Configurando o Caddy..."

# Descobre o IP publico para permitir acesso ao painel por HTTP (sem SSL).
# HTTPS por IP nao funciona (certificado so existe para dominios), entao o
# painel administrativo e servido em HTTP puro pelo endereco IP.
SERVER_IP=$(curl -s https://api.ipify.org || echo "")
IP_BLOCK=""
if [ -n "$SERVER_IP" ]; then
  IP_BLOCK="
# Acesso ao painel administrativo pelo IP (HTTP puro, sem SSL)
http://${SERVER_IP} {
	root * ${APP_DIR}
	encode gzip
	php_fastcgi unix/${PHP_SOCK}
	file_server
}
"
fi

PANEL_BLOCK=""
if [ -n "$PANEL_DOMAIN" ]; then
  PANEL_BLOCK="
# Painel em dominio proprio (SSL normal, sem on-demand)
${PANEL_DOMAIN} {
	root * ${APP_DIR}
	encode gzip
	php_fastcgi unix/${PHP_SOCK}
	file_server
}
"
fi

cat > /etc/caddy/Caddyfile <<EOF
{
	email ${LE_EMAIL}
	on_demand_tls {
		ask http://127.0.0.1/domain-check.php
	}
}
${IP_BLOCK}${PANEL_BLOCK}
# Catch-all: qualquer dominio de campanha apontado para este servidor
https:// {
	tls {
		on_demand
	}
	root * ${APP_DIR}
	encode gzip
	php_fastcgi unix/${PHP_SOCK}
	file_server
}

# Endpoint interno de autorizacao de SSL (usado pelo on_demand_tls)
http://127.0.0.1 {
	root * ${APP_DIR}
	php_fastcgi unix/${PHP_SOCK}
	file_server
}
EOF

# Passa o dominio do painel para o domain-check.php via variavel de ambiente
if [ -n "$PANEL_DOMAIN" ]; then
  mkdir -p /etc/systemd/system/caddy.service.d
  cat > /etc/systemd/system/caddy.service.d/override.conf <<EOF
[Service]
Environment=COMMANDER_PANEL_DOMAIN=${PANEL_DOMAIN}
EOF
  systemctl daemon-reload
fi

# ------------------------------------------------------------------
# 9) Subir os servicos
# ------------------------------------------------------------------
say "Reiniciando servicos..."
systemctl enable "php${PHP_VER}-fpm" >/dev/null 2>&1 || true
systemctl restart "php${PHP_VER}-fpm"
caddy fmt --overwrite /etc/caddy/Caddyfile || true
systemctl enable caddy >/dev/null 2>&1 || true
systemctl restart caddy

# ------------------------------------------------------------------
# 10) Pronto
# ------------------------------------------------------------------
IP=$(curl -s https://api.ipify.org || echo "SEU_IP")
echo ""
say "Instalacao concluida!"
echo ""
echo "  IP do servidor : $IP"
if [ -n "$PANEL_DOMAIN" ]; then
  echo "  Painel         : https://$PANEL_DOMAIN"
else
  echo "  Painel         : http://$IP  (acesse pelo IP)"
fi
echo ""
echo "  Proximos passos:"
echo "  1) No painel, va em Dominios > Adicionar dominio."
echo "  2) Aponte o registro A do dominio para: $IP"
echo "  3) Clique em Verificar. Quando ficar Ativo, use na campanha."
echo ""
echo "  Para atualizar o COMMANDER no futuro:  bash $APP_DIR/install.sh"
echo ""
