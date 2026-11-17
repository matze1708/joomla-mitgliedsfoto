#!/usr/bin/env bash
# Baut das installierbare Joomla-ZIP: dist/com_mitgliedsfoto.zip
set -euo pipefail
cd "$(dirname "$0")"
php tests/run.php
mkdir -p dist
rm -f dist/com_mitgliedsfoto.zip
(cd com_mitgliedsfoto && zip -rq ../dist/com_mitgliedsfoto.zip . -x '*.DS_Store')
echo "Gebaut: dist/com_mitgliedsfoto.zip"
