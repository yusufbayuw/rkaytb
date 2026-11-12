#!/usr/bin/env bash
set -euo pipefail

# Destructive operations are permitted only inside this isolated CI test DB.
# Never run against production or a developer's existing business database.
if [[ "${GITHUB_ACTIONS:-}" != "true" || "${CI:-}" != "true" ]]; then
  echo "REFUSED: migration rehearsal is restricted to GitHub Actions CI" >&2
  exit 2
fi
if [[ "$APP_ENV" != "testing" ]]; then
  echo "REFUSED: APP_ENV must be testing" >&2
  exit 2
fi
case "$DB_CONNECTION" in
  mysql)
    if [[ "$DB_DATABASE" != "rka_test" || "$DB_HOST" != "127.0.0.1" ]]; then
      echo "REFUSED: MySQL must be the local CI rka_test database" >&2
      exit 2
    fi
    ;;
  sqlite)
    if [[ "$(basename "$DB_DATABASE")" != "ci_upgrade_test.sqlite" ]]; then
      echo "REFUSED: SQLite must use ci_upgrade_test.sqlite" >&2
      exit 2
    fi
    mkdir -p "$(dirname "$DB_DATABASE")"
    touch "$DB_DATABASE"
    ;;
  *)
    echo "REFUSED: unsupported CI DB" >&2
    exit 2
    ;;
esac

legacy_dir="$(mktemp -d "$PWD/database/.ci-legacy-migrations-XXXXXX")"
cleanup() {
  rm -rf "$legacy_dir"
  if [[ "$DB_CONNECTION" == "sqlite" ]]; then
    rm -f "$DB_DATABASE"
  fi
}
trap cleanup EXIT

cp database/migrations/2014_*.php database/migrations/2019_*.php \
  database/migrations/2023_*.php "$legacy_dir/"

echo "[CI] Resetting isolated test database"
php artisan db:wipe --force

echo "[CI] Establishing pre-platform legacy schema"
php artisan migrate --path="$legacy_dir" --realpath --force
php scripts/ci_rka_upgrade.php baseline

echo "[CI] Expanding schema without rewriting legacy budget rows"
php artisan migrate --force
php scripts/ci_rka_upgrade.php expanded

platform_migration_count=$(find database/migrations -maxdepth 1 -type f -name '2026_*.php' | wc -l | tr -d ' ')
echo "[CI] Rolling back only the ${platform_migration_count} additive platform migrations"
php artisan migrate:rollback --step="$platform_migration_count" --force
php scripts/ci_rka_upgrade.php rolledback

echo "[CI] Re-applying new migrations after rollback"
php artisan migrate --force
php scripts/ci_rka_upgrade.php expanded

echo "[CI] PASS: upgrade/rollback/reapply retained historical sentinel amounts"
