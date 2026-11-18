# com_mitgliedsfoto – Foto-Erfassung für Mitgliedsausweise

Joomla-5.4-Komponente für den Schützenverein Volker Alzey 1952 e.V.
Mitglieder reichen ihr Foto über die Homepage ein (persönlicher Link oder Aushang-QR-Code),
der Vorstand erfasst Fotos für Mitglieder ohne Web-Zugang im Backend. Konzept: siehe Auftrag (Stand 17.11.2026).

## ZIP bauen

```bash
./build.sh          # führt tests/run.php aus und erzeugt dist/com_mitgliedsfoto.zip
```

Automatisch per GitHub Action (`.github/workflows/build.yml`):

- **Pull Request**: Prüfung (PHP-Syntax, XML, JSON, JS), Tests und Probe-Build – kein Release.
- **Merge/Push auf `master`**: zusätzlich GitHub-Release `v<Version>` (Version aus `com_mitgliedsfoto/mitgliedsfoto.xml`)
  mit `com_mitgliedsfoto.zip` → Download unter *Releases*. Für eine neue Version vor dem Merge die `<version>` im Manifest erhöhen;
  bei gleicher Version wird das ZIP im bestehenden Release ersetzt.

Voraussetzungen auf dem Server: Joomla 5.4, PHP ≥ 8.1 mit GD, funktionierender Joomla-Mailversand.

## Installation

1. Joomla-Backend → System → Erweiterungen → Installieren → `com_mitgliedsfoto.zip` hochladen.
   Dabei werden die Tabellen `#__mitgliedsfoto`, `#__mitgliedsfoto_versuche` angelegt und ein zufälliger HMAC-Schlüssel erzeugt.
2. Komponenten → Mitgliedsfoto → **Optionen** → Reiter „Mitgliedertabelle“, Datenquelle **Joomla-Benutzer mit Benutzerfeldern**.
   Empfohlene Zuordnung für sv-alzey.de (Feldgruppe „Mitglieds Informationen“):

   | Option | Auswahl |
   |---|---|
   | Mitgliedsnummer | Benutzerfeld *Mitgliedsnummer (mitgliedsnummer, text)* |
   | Vorname | *Name – alles vor dem letzten Wort (Vorname)* (bzw. „Nachname, Vorname“-Variante, je nach Format des Kontonamens) |
   | Nachname | *Name – letztes Wort (Nachname)* |
   | Eintritt | Benutzerfeld *Eintritt (eintritt, text)* |
   | Geburtsdatum | – (nicht vorhanden → QR-Code-Weg mit Geburtsdatum nicht verfügbar) |
   | E-Mail | Benutzerfeld *E-Mail (e-mail, text)* oder *E-Mail-Adresse des Kontos* |
   | Filter: Vereinsmitglied | Benutzerfeld *Vereinsmitglied (vereinsmitglied, radio)*, erlaubte Werte = gespeicherter Wert der Option „Ja“ (z. B. `1`) |
   | Filter: Austritt | Benutzerfeld *Austritt (austritt, text)* – ausgetretene Mitglieder werden ausgeblendet |
   | Nur nicht gesperrte Konten | Nein (Mitglieder melden sich nicht an) |

   Hinweis: Bei mehrteiligen Nachnamen („Hans Peter von Berg“) liefert die Aufteilung am letzten Wort „Hans Peter von“ / „Berg“.
   Alternativ kann eine eigene Datenbanktabelle als Quelle gewählt werden.
   Weitere Reiter:
   - **Foto und Speicherort**: Speicherordner (empfohlen: absoluter Pfad **außerhalb** des Webroots, für den Webserver-Benutzer beschreibbar),
     Adresse der Foto-Seite, z. B. `https://www.sv-alzey.de/foto` (leer = automatisch aus dem Menüeintrag).
   - **Mail**: Empfängeradresse (Vorschlag: `ausweisfoto@sv-alzey.de`, nicht `mitglieder@sv-alzey.de`).
   - **Datenschutzhinweis**: Speicherort-/Löschfrist-Texte und Link zur Datenschutzerklärung prüfen und anpassen (Vorschlagswerte!).
   - **Berechtigungen**: Aktion „Mitgliedsfotos verwalten“ der gewünschten Gruppe (Vorstand) erlauben (Super User hat sie immer).
3. **Menüeintrag anlegen (Pflicht, sonst liefert `/foto` „404 Seite nicht gefunden“):** Menüs → (Menü) → Neuer Eintrag → Typ **Mitgliedsfoto → Foto einreichen**, Alias `foto`
   (Menü am besten „versteckt“, die Seite wird nur über Link/QR-Code erreicht).
4. Falls das Plugin **System – Seitencache** aktiv ist: die Seite `/foto` dort ausschließen
   (sonst würden Formular-Token und Namen zwischengespeichert).
5. Sprachdateien liegen in `de-DE`; die Website muss Deutsch als Sprache verwenden.

## Wege zur Fotoerfassung

| Weg | Wer | Prüfung | Ergebnis |
|---|---|---|---|
| Persönlicher Link `…/foto?m=…&t=…` (Newsletter/CSV-Export) | Mitglied, ohne Anmeldung | HMAC-Token | Status „eingereicht“ |
| QR-Code `…/foto` – Mitgliedsnummer, Vorname, Nachname, Geburtsdatum | Mitglied, ohne Anmeldung | Nummer in Joomla bekannt: Name (und Geburtsdatum, falls hinterlegt) müssen passen; Nummer unbekannt: wird angenommen | „eingereicht“ bzw. **„unbestätigt“** |
| Backend → „Foto erfassen“ | Vorstand | Joomla-Mitglied | „durch Vorstand erfasst“ |
| Backend → „Ohne Joomla-Konto erfassen“ (auch am Smartphone) | Vorstand | – | „durch Vorstand erfasst“ |
| Frontend-Menüeintrag „Vorstand: Fotos erfassen“ (Suche, Foto erfassen, ohne Joomla-Konto erfassen) | angemeldeter Vorstand | Berechtigung „Mitgliedsfotos verwalten“ | „durch Vorstand erfasst“ |

**Vorstandsbereich im Frontend:** Menüeintrag vom Typ *Mitgliedsfoto → Vorstand: Fotos erfassen* z. B. im versteckten Menü
anlegen (Zugriffsebene „Registered“ oder „Special“). Gäste werden zum Login geleitet, angemeldete Benutzer ohne die Berechtigung
„Mitgliedsfotos verwalten“ sehen nur einen Hinweis. Zurücksetzen, Löschen, Export und Mail erneut senden bleiben im Backend.

**Führende Nullen:** Bei manuellen Eingaben (QR-Formular, Vorstandserfassung, Suche) werden führende Nullen ignoriert –
`99997855` findet `0099997855`. Persönliche Links prüfen die Nummer exakt.

Einträge ohne Joomla-Datensatz stehen in einer eigenen Liste unter der Mitgliederliste; unbestätigte Einträge nach Prüfung
markieren und „Als geprüft markieren“.

**Mail-Body** (maschinenlesbar):
```
Mitgliedsnummer: <Nr>
Vorname: <Vorname>
Nachname: <Nachname>
Quelle: web|vorstand
Geprueft: ja|nein
Geburtsdatum: TT.MM.JJJJ      (nur wenn bekannt)
```

## Testanleitung

1. **Link erzeugen**: Backend → Mitgliedsfoto → „Link-Export (alle, CSV)“ → Zeile eines Testmitglieds enthält
   `…/foto?m=<Nr>&t=<64 Hex-Zeichen>`.
2. **Link öffnen** (Smartphone, ohne Anmeldung): Name/Nummer erscheinen, „Das bin ich“ + Foto per Kamera oder Datei,
   Zuschnitt, Datenschutz-Checkbox, Absenden → Erfolgsmeldung; in der Liste Status „eingereicht“ mit Vorschau;
   erneutes Öffnen zeigt „Foto bereits eingereicht“.
3. **Mailtest**: Betreff `FOTO|<Nr>`, Anhang `Nachname_Vorname_Nr.jpg`, Body `Mitgliedsnummer / Vorname / Nachname / Quelle: web`.
   Kommt nichts an, zeigt die Liste „Mail nicht gesendet“ → nach Korrektur „Mail erneut senden“.
4. **Negativtests**: Token im Link ändern → neutrale Fehlermeldung (gleich bei unbekannter Nummer).
5. **Vorstandserfassung**: Liste → „Foto erfassen“ → Status „durch Vorstand erfasst“, Mail mit `Quelle: vorstand`.
6. **Zurücksetzen / Löschen**: „Zurücksetzen“ (Zeile + Datei weg) bzw. „Foto löschen“ (nur Datei weg, keine erneute Einreichung).
7. Speicherordner: Foto-Datei vorhanden, direkter Aufruf per URL darf **nicht** möglich sein.

Automatische Tests:
- `php tests/run.php` – Joomla-unabhängige Logik (Token, Dateinamen, Bildprüfung inkl. Schadcode-Dateien, Eingabe-Whitelist, Datum).
- `tests/e2e/joomla-e2e.js` – Browser-Test (Playwright) gegen eine echte Joomla-5.4-Instanz mit Apache/MariaDB:
  QR-Weg, Link, Upload, Angriffsversuche (PHP-Datei, JPEG mit Code, falsche Tokens/Nummern, XSS, CSRF), Rate-Limit,
  Mail, Backend-Liste, Filter Vereinsmitglied/Austritt, Optionen, Vorstandserfassung, Export, Zurücksetzen/Löschen.

## Updates

Die Komponente meldet neue Versionen automatisch im Joomla-Backend (System → Aktualisieren → Erweiterungen bzw. Hinweis im Dashboard).
Joomla liest dazu `update.xml` aus dem neuesten GitHub-Release (`…/releases/latest/download/update.xml`) – das Repository muss dafür
**öffentlich** sein. Das Update lässt sich per Klick installieren; Daten, Fotos und Optionen bleiben erhalten.

Neue Version veröffentlichen: `<version>` in `com_mitgliedsfoto/mitgliedsfoto.xml` erhöhen (bei Datenbankänderungen zusätzlich
`admin/sql/updates/mysql/<version>.sql` anlegen) und auf `master` mergen – die Action baut ZIP, `update.xml` und Release.

## Deinstallation

Beim Deinstallieren werden gelöscht: die Tabellen `#__mitgliedsfoto` und `#__mitgliedsfoto_versuche`, alle Optionen
(inkl. Schlüssel) und Berechtigungen, die Programmdateien sowie **alle in der Tabelle verzeichneten Fotos** – auch in einem
eigenen Speicherordner. Andere Dateien in diesem Ordner bleiben unangetastet; ist er danach leer, wird er entfernt.
Nicht betroffen: versendete Mails, Kopien in OneDrive/SharePoint/SPG Verein und die Joomla-Benutzerkonten/-felder.

## Abweichungen/Ergänzungen zum Konzept

- `#__mitgliedsfoto` hat zwei zusätzliche Spalten: `mail_gesendet_am` (für „Mail erneut senden“/Fehleranzeige)
  und `geloescht_am` (Löschfunktion nach Ausweiserstellung). Zusätzliche Tabelle `#__mitgliedsfoto_versuche` für das Rate-Limit (nur gesalzene IP-Hashes).
- Option „Spalte E-Mail“ ergänzt (nötig für den CleverReach-Export); Export wahlweise für alle oder nur Mitglieder mit Status „offen“ (Erinnerungsmail).
- Auf Smartphones gibt es zwei Buttons („Mit Kamera aufnehmen“ mit `capture="user"` und „Foto aus Galerie/Datei wählen“), da `capture` allein die Galerie sperrt.
- Aushang-Weg: Bei richtigem Nummer + Geburtsdatum wird auf den persönlichen Link weitergeleitet.
- Cropper.js 1.6.2 (MIT) liegt lokal in `media/` (kein CDN, keine Datenübertragung an Dritte).
- Eine gemeinsame Sprachdatei im Administrator-Sprachordner wird auch vom Frontend geladen.

## Noch offen / bewusst entscheiden

- Konkrete Tabelle/Spalten der Mitgliedertabelle und ob ein Geburtsdatum vorhanden ist (Konzept Abschnitt 11).
- Zieladresse der Mail; Texte „Speicherort“ und „Löschfrist“ im Datenschutzhinweis (Vorschlagswerte, rechtlich prüfen).
- Der Standard-Speicherordner liegt im Komponentenverzeichnis (durch `.htaccess`/`web.config` geschützt) und würde beim Deinstallieren gelöscht –
  für den Echtbetrieb einen Ordner außerhalb des Webroots eintragen.
- Folgeschritt (Power Automate, SPG Verein) ist nicht Teil dieser Komponente.
- Getestet in einer lokalen Joomla-5.4.0-Instanz (Apache 2.4, PHP 8.3, MariaDB 10.11) mit nachgebauter Feldstruktur – nicht auf sv-alzey.de selbst.
