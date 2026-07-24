from pathlib import Path


def replace(path: str, old: str, new: str) -> None:
    file = Path(path)
    source = file.read_text()
    if old not in source:
        raise RuntimeError(f"Expected pattern missing in {path}: {old[:180]!r}")
    file.write_text(source.replace(old, new))


# MySQL SSL options must use the PDO MySQL driver constant. The previous
# generic PDO constant does not exist and prevented Laravel from booting during
# Composer package discovery.
replace(
    "backend/config/database.php",
    "PDO::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),",
    "PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),",
)

# The standard Laravel controller base class was missing even though every
# HTTP controller imports and extends it. Restore the minimal framework
# boundary rather than modifying every controller independently.
controller = Path("backend/app/Http/Controllers/Controller.php")
if controller.exists():
    raise RuntimeError("Controller.php unexpectedly already exists")
controller.write_text(
    """<?php

namespace App\\Http\\Controllers;

abstract class Controller
{
    // Shared HTTP controller boundary for the Rosta API.
}
"""
)

# CI must provide an ephemeral process-level application key before Composer
# scripts boot Laravel. It must not mutate .env through artisan key:generate.
workflow = ".github/workflows/backend-ci.yml"
replace(
    workflow,
    '''      APP_URL: http://localhost:8000
      FRONTEND_ALLOWED_ORIGINS: http://localhost:5173,http://127.0.0.1:5173
''',
    '''      APP_URL: http://localhost:8000
      LOG_CHANNEL: stack
      LOG_STACK: single
      FRONTEND_ALLOWED_ORIGINS: http://localhost:5173,http://127.0.0.1:5173
''',
)
replace(
    workflow,
    '''      - name: Require deterministic dependency lock
        run: test -s composer.lock
''',
    '''      - name: Create ephemeral application key
        shell: bash
        run: |
          set -Eeuo pipefail
          echo "APP_KEY=base64:$(php -r 'echo base64_encode(random_bytes(32));')" >> "$GITHUB_ENV"

      - name: Require deterministic dependency lock
        run: test -s composer.lock
''',
)
replace(
    workflow,
    '''      - name: Generate application key
        run: php artisan key:generate --force

''',
    '',
)
