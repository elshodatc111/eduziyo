#!/usr/bin/env bash
# EDUZIYO — serverda yangilash:  ./deploy/deploy.sh [--no-pull] [--rollback]
# Faqat `eduziyo` loyihasiga tegadi (compose loyiha nomi: eduziyo). Boshqa konteynerlar ishlashda davom etadi.
set -euo pipefail
cd "$(dirname "$0")/.."
DC=(docker compose --env-file .env -f deploy/docker-compose.yml)
log() { printf '\033[1;36m==> %s\033[0m\n' "$*"; }

[ -f .env ] || { echo ".env yo'q: cp deploy/env.production.example .env va to'ldiring"; exit 1; }

if [ "${1:-}" = "--rollback" ]; then
  log "Orqaga qaytarish: oldingi tasvir (eduziyo-app:previous)"
  docker image inspect eduziyo-app:previous >/dev/null 2>&1 || { echo "Oldingi tasvir yo'q"; exit 1; }
  docker tag eduziyo-app:previous eduziyo-app:latest
  docker tag eduziyo-web:previous eduziyo-web:latest 2>/dev/null || true
  "${DC[@]}" up -d --no-build --force-recreate app web queue scheduler watchdog
  echo "Eslatma: migratsiyalar orqaga qaytmaydi (faqat additive). Zarur bo'lsa bazani zaxiradan tiklang."; exit 0
fi

if [ "${1:-}" != "--no-pull" ]; then
  log "git pull"
  git fetch origin && git pull --ff-only
fi

# Joriy tasvirni zaxiraga belgilash (rollback uchun)
docker tag eduziyo-app:latest eduziyo-app:previous 2>/dev/null || true
docker tag eduziyo-web:latest eduziyo-web:previous 2>/dev/null || true

log "Tasvirni yig'ish"
"${DC[@]}" build

# APP_KEY birinchi marta
if ! grep -qE '^APP_KEY=.+' .env; then
  log "APP_KEY yaratilmoqda"
  KEY="$(docker run --rm eduziyo-app:latest php -r 'echo "base64:".base64_encode(random_bytes(32));')"
  sed -i "s|^APP_KEY=.*|APP_KEY=$KEY|" .env
fi

# Chiqarishdan oldin: tenant izolyatsiyasi skaneri (yangi xom SQL / withoutGlobalScopes bo'lsa to'xtaydi)
log "tenancy:scan (kod)"
"${DC[@]}" run --rm --no-deps -e EDU_SKIP_OPTIMIZE=1 app php artisan tenancy:scan

log "Migratsiya"
"${DC[@]}" run --rm --no-deps app php artisan migrate --force

log "Ishga tushirish"
"${DC[@]}" up -d --remove-orphans app web queue scheduler watchdog
# Modem: .env da MODEM_ENABLED=1 bo'lsa
if grep -qE '^MODEM_ENABLED=1' .env; then "${DC[@]}" --profile modem up -d modem; fi

log "Tenant ma'lumot auditi (tenancy:audit)"
"${DC[@]}" exec -T app php artisan tenancy:audit || echo "OGOHLANTIRISH: audit muammo topdi — yuqoridagi natijani ko'ring"

log "Holat"
"${DC[@]}" ps
curl -fsS -o /dev/null "http://${WEB_BIND:-127.0.0.1}:${WEB_PORT:-7005}/up" && echo "OK: /up javob berdi" || echo "OGOHLANTIRISH: /up javob bermadi (docker logs eduziyo_web)"
docker image prune -f >/dev/null
