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
# Nur für lokale Tests: anderer Update-Server (z. B. UPDATE_SERVER_URL=http://127.0.0.1:8080/updates/update.xml)
if [ -n "${UPDATE_SERVER_URL:-}" ]; then
  sed -i "s#https://github.com/matze1708/joomla-mitgliedsfoto/releases/latest/download/update.xml#${UPDATE_SERVER_URL}#" "$STAGE/mitgliedsfoto.xml"
fi

mkdir -p "$STAGE/admin/language/en-GB"
cp "$STAGE/admin/language/de-DE/"*.ini "$STAGE/admin/language/en-GB/"

(cd "$STAGE" && zip -rq "$OLDPWD/dist/com_mitgliedsfoto.zip" . -x '*.DS_Store')
echo "Gebaut: dist/com_mitgliedsfoto.zip"
