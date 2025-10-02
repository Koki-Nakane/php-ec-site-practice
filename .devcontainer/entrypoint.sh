#!/usr/bin/env bash
set -euo pipefail

# Adjust ownership only for root-owned files inside the bind mount to avoid expensive full chown each start.
TARGET_DIR=/var/www/html
if [ -d "$TARGET_DIR" ]; then
  # Find only files/dirs owned by root (uid 0) at depth <=5; adjust as needed
  # Suppress errors for permission denied just in case.
  mapfile -t ROOT_ITEMS < <(find "$TARGET_DIR" -maxdepth 5 -user root -print 2>/dev/null || true)
  if [ ${#ROOT_ITEMS[@]} -gt 0 ]; then
    echo "[entrypoint] Fixing ownership for ${#ROOT_ITEMS[@]} item(s) owned by root..."
    printf '%s\n' "${ROOT_ITEMS[@]}" | xargs -r chown appuser:appgroup
  fi
fi

# Start Apache (original CMD)
exec /usr/local/bin/docker-php-entrypoint apache2-foreground
