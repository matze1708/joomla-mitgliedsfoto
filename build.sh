#!/usr/bin/env bash
# Baut das installierbare Joomla-ZIP: dist/com_mitgliedsfoto.zip
set -euo pipefail
cd "$(dirname "$0")"
php tests/run.php
mkdir -p dist
rm -f dist/com_mitgliedsfoto.zip

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
cp -a com_mitgliedsfoto/. "$STAGE/"

# Texte gibt es nur auf Deutsch. Damit Backend/Optionen auch bei englischer Oberfläche lesbar sind,
# liegt eine Kopie als en-GB-Fallback im Komponentenordner (nicht im globalen Sprachordner).
mkdir -p "$STAGE/admin/language/en-GB"
cp "$STAGE/admin/language/de-DE/"*.ini "$STAGE/admin/language/en-GB/"

(cd "$STAGE" && zip -rq "$OLDPWD/dist/com_mitgliedsfoto.zip" . -x '*.DS_Store')
echo "Gebaut: dist/com_mitgliedsfoto.zip"
