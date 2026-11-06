#!/usr/bin/env bash
# EDUZIYO uchun ALOHIDA baza va foydalanuvchi yaratadi (boshqa loyiha bazalariga tegmaydi).
# Ishlatish:  ./deploy/db-create.sh <db-konteyner-nomi> [eduziyo]
#   Root paroli so'raladi (yoki MYSQL_ROOT_PASSWORD o'zgaruvchisi). Yangi parol chop etiladi — .env ga qo'ying.
set -euo pipefail
CONT="${1:?DB konteyner nomi kerak (docker ps)}"
NAME="${2:-eduziyo}"
PASS="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-28)"
ROOTPW="${MYSQL_ROOT_PASSWORD:-}"
if [ -z "$ROOTPW" ]; then read -r -s -p "DB root paroli: " ROOTPW; echo; fi

# IF NOT EXISTS: mavjud baza/foydalanuvchi buzilmaydi. Foydalanuvchiga faqat shu bazaga huquq beriladi.
SQL="CREATE DATABASE IF NOT EXISTS \`$NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$NAME'@'%' IDENTIFIED BY '$PASS';
GRANT ALL PRIVILEGES ON \`$NAME\`.* TO '$NAME'@'%';
FLUSH PRIVILEGES;"
CLI=mysql
docker exec "$CONT" sh -c 'command -v mariadb' >/dev/null 2>&1 && CLI=mariadb
docker exec -e MYSQL_PWD="$ROOTPW" "$CONT" "$CLI" -uroot -e "$SQL"
echo "Baza: $NAME   Foydalanuvchi: $NAME"
echo "DB_PASSWORD=$PASS   <-- .env ga yozing (foydalanuvchi oldindan bor bo'lsa, parol o'zgarmagan bo'lishi mumkin)"
