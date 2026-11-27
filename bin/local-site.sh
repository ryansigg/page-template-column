#!/usr/bin/env bash
# A throwaway WordPress site for developing and testing this plugin, on PHP's built-in
# server and SQLite. No Docker, no MySQL.
#
#   bash bin/local-site.sh setup   download WordPress, link the plugin and test theme, add sample content
#   bash bin/local-site.sh start   serve it at http://localhost:8890 (in the background)
#   bash bin/local-site.sh stop
#   bash bin/local-site.sh wp ...  run WP-CLI against the site
#
# Needs: php 8.1+ with pdo_sqlite and gd, git, curl.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SITE="${SITE_DIR:-$ROOT/.wp-site}"
PORT="${PORT:-8890}"
WP_VERSION="${WP_VERSION:-7.1.4}"
SQLITE_VERSION="${SQLITE_VERSION:-v3.1.3}"
WPCLI_VERSION="${WPCLI_VERSION:-v2.12.0}"
ROOT_FLAG=""; [ "$(id -u)" = "0" ] && ROOT_FLAG="--allow-root"

wp() { php "$SITE/wp-cli.phar" $ROOT_FLAG --path="$SITE/web" "$@"; }

setup() {
  if [ -f "$SITE/web/wp-config.php" ]; then
    echo "Demo site already exists at $SITE. Delete that folder to start over."; exit 0
  fi
  mkdir -p "$SITE/data"
  echo "Downloading WP-CLI $WPCLI_VERSION, WordPress $WP_VERSION and SQLite integration $SQLITE_VERSION..."
  curl -fsSL -o "$SITE/wp-cli.phar" \
    "https://github.com/wp-cli/wp-cli/releases/download/$WPCLI_VERSION/wp-cli-${WPCLI_VERSION#v}.phar"
  git clone -q --depth 1 --branch "$WP_VERSION" https://github.com/WordPress/WordPress.git "$SITE/web"
  git clone -q --depth 1 --branch "$SQLITE_VERSION" https://github.com/WordPress/sqlite-database-integration.git "$SITE/sqlite-src"

  # The plugin lives in a monorepo package and symlinks its driver; copy it with links resolved.
  local plugin="$SITE/web/wp-content/plugins/sqlite-database-integration"
  cp -rL "$SITE/sqlite-src/packages/plugin-sqlite-database-integration" "$plugin"
  sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$plugin#" \
      -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
      "$plugin/db.copy" > "$SITE/web/wp-content/db.php"

  cat > "$SITE/router.php" <<'PHP'
<?php
// Front controller for `php -S`: serve real files, send everything else to WordPress.
$root = __DIR__ . '/web';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $root . $path;
if ($path !== '/' && is_file($file)) {
  if (substr($path, -4) !== '.php') return false;
} elseif (is_dir($file) && is_file(rtrim($file, '/') . '/index.php')) {
  $file = rtrim($file, '/') . '/index.php';
  $path = rtrim($path, '/') . '/index.php';
} else {
  $file = $root . '/index.php';
  $path = '/index.php';
}
$_SERVER['SCRIPT_NAME'] = $path;
$_SERVER['SCRIPT_FILENAME'] = $file;
chdir(dirname($file));
require $file;
PHP

  wp config create --dbname=wp --dbuser=wp --dbpass=wp --skip-check --quiet
  wp config set DB_DIR "$SITE/data/" --quiet
  wp core install --url="http://localhost:$PORT" --title="Template Column" \
    --admin_user=admin --admin_password=password --admin_email=admin@example.test --skip-email
  # WP-CLI can guess a URL that includes the web/ folder depending on where it runs; pin both.
  wp option update home "http://localhost:$PORT" --quiet
  wp option update siteurl "http://localhost:$PORT" --quiet
  ln -s "$ROOT" "$SITE/web/wp-content/plugins/template-column"
  ln -s "$ROOT/tests/fixtures/template-column-test-theme" "$SITE/web/wp-content/themes/template-column-test"
  wp theme activate template-column-test
  wp plugin activate template-column
  wp rewrite structure '/%postname%/' --hard --quiet
  wp eval-file "$ROOT/bin/seed.php"
  rm -rf "$SITE/sqlite-src"
  echo "Site ready. Start it with: npm run site:start"
}

start() {
  [ -f "$SITE/router.php" ] || { echo "Run setup first: npm run site:setup"; exit 1; }
  if [ -f "$SITE/server.pid" ] && kill -0 "$(cat "$SITE/server.pid")" 2>/dev/null; then
    echo "Already running at http://localhost:$PORT"; exit 0
  fi
  PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-6}" \
    nohup php -S "localhost:$PORT" -t "$SITE/web" "$SITE/router.php" > "$SITE/server.log" 2>&1 &
  echo $! > "$SITE/server.pid"
  for _ in $(seq 1 30); do
    curl -fs -o /dev/null "http://localhost:$PORT/" && { echo "Serving http://localhost:$PORT (log: $SITE/server.log)"; return; }
    sleep 0.5
  done
  echo "Server did not come up; see $SITE/server.log"; exit 1
}

stop() {
  if [ -f "$SITE/server.pid" ]; then kill "$(cat "$SITE/server.pid")" 2>/dev/null || true; rm -f "$SITE/server.pid"; fi
  echo "Stopped."
}

case "${1:-}" in
  setup) setup ;;
  start) start ;;
  stop) stop ;;
  wp) shift; wp "$@" ;;
  *) echo "Usage: bash bin/local-site.sh setup|start|stop|wp <args>"; exit 1 ;;
esac
