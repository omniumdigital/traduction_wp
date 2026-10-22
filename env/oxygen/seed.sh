#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

npx wp-env run cli wp eval-file wp-content/plugins/omnium-translate/env/oxygen/seed.php

echo "Recette Oxygen disponible sur http://localhost:8883/atelier-oxygen/"
