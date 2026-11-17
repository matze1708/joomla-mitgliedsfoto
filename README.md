# com_mitgliedsfoto – Foto-Erfassung für Mitgliedsausweise

Joomla-5.4-Komponente für den Schützenverein Volker Alzey 1952 e.V.
Mitglieder reichen ihr Foto über die Homepage ein (persönlicher Link oder Aushang-QR-Code),
der Vorstand erfasst Fotos für Mitglieder ohne Web-Zugang im Backend. Konzept: siehe Auftrag (Stand 17.11.2026).

## ZIP bauen

```bash
./build.sh          # führt tests/run.php aus und erzeugt dist/com_mitgliedsfoto.zip
```

Automatisch per GitHub Action (`.github/workflows/build.yml`):

- **Jeder Push / Pull Request**: Prüfung + Tests + ZIP. Das ZIP liegt im Lauf unter *Actions → Build → Artifacts*.
- **Release**: Version in `com_mitgliedsfoto/mitgliedsfoto.xml` erhöhen, committen, dann Tag setzen und pushen:
  `git tag v1.0.0 && git push origin v1.0.0`. Die Action legt ein GitHub-Release mit `com_mitgliedsfoto.zip` an
  (Tag und Manifest-Version müssen übereinstimmen). Download dann unter *Releases*.

Voraussetzungen auf dem Server: Joomla 5.4, PHP ≥ 8.1 mit GD, funktionierender Joomla-Mailversand.

## Installation

1. Joomla-Backend → System → Erweiterungen → Installieren → `com_mitgliedsfoto.zip` hochladen.
   Dabei werden die Tabellen `#__mitgliedsfoto`, `#__mitgliedsfoto_versuche` angelegt und ein zufälliger HMAC-Schlüssel erzeugt.
2. Komponenten → Mitgliedsfoto → **Optionen**:
   - **Mitgliedertabelle**: Tabellenname (mit `#__` oder voller Name) und Spalten für Nummer, Vorname, Nachname, Eintritt;
     optional Geburtsdatum (Aushang-Weg) und E-Mail (Link-Export).
   - **Foto und Speicherort**: Speicherordner (empfohlen: absoluter Pfad **außerhalb** des Webroots, für den Webserver-Benutzer beschreibbar),
     Adresse der Foto-Seite, z. B. `https://www.sv-alzey.de/foto`.
   - **Mail**: Empfängeradresse (Vorschlag: `ausweisfoto@sv-alzey.de`, nicht `mitglieder@sv-alzey.de`).
   - **Datenschutzhinweis**: Speicherort-/Löschfrist-Texte und Link zur Datenschutzerklärung prüfen und anpassen (Platzhalter-Vorschläge!).
   - **Berechtigungen**: Aktion „Mitgliedsfotos verwalten“ der gewünschten Gruppe (Vorstand) erlauben (Super User hat sie immer).
3. Menüeintrag anlegen: Menüs → (Menü) → Neuer Eintrag → Typ **Mitgliedsfoto → Foto einreichen**, Alias `foto`
   (Menü am besten „versteckt“, die Seite wird nur über Link/QR-Code erreicht).
4. Falls das Plugin **System – Seitencache** aktiv ist: die Seite `/foto` dort ausschließen
   (sonst würden Formular-Token und Namen zwischengespeichert).
5. Sprachdateien liegen in `de-DE`; die Website muss Deutsch als Sprache verwenden.

## Testanleitung

**Testmitglied** (falls noch keine Echtdaten in der Joomla-Mitgliedertabelle): z. B. in phpMyAdmin

```sql
CREATE TABLE jos_mf_test (nr VARCHAR(20) PRIMARY KEY, vorname VARCHAR(60), nachname VARCHAR(60),
                          eintritt DATE, geburtsdatum DATE, email VARCHAR(120));
INSERT INTO jos_mf_test VALUES ('9001','Jörg','Müller-Groß','2020-01-01','1980-05-17','test@example.org');
```

Optionen: Tabelle `jos_mf_test` (bzw. `#__mf_test`), Spalten `nr`, `vorname`, `nachname`, `eintritt`, `geburtsdatum`, `email`.

1. **Link erzeugen**: Backend → Mitgliedsfoto → Toolbar „Link-Export (alle, CSV)“ → Zeile für 9001 enthält den Link
   `…/foto?m=9001&t=<64 Hex-Zeichen>`.
2. **Link öffnen** (am besten mit dem Smartphone): Name/Nummer erscheinen, „Das bin ich“ + Foto per Kamera oder Datei,
   Zuschnitt, Datenschutz-Checkbox, Absenden. Erwartung: Erfolgsmeldung; in der Liste Status „eingereicht“ mit Vorschau;
   erneutes Öffnen des Links zeigt „Foto bereits eingereicht“.
3. **Mailtest**: Im Postfach kommt `FOTO|9001` mit Anhang `Mueller-Gross_Joerg_9001.jpg` und Body
   `Mitgliedsnummer / Vorname / Nachname / Quelle: web`. Kommt nichts an, zeigt die Liste „Mail nicht gesendet“;
   nach Korrektur der Joomla-Mailkonfiguration/Empfängeradresse: Zeile markieren → „Mail erneut senden“.
4. **Negativtests**: Token im Link ändern → neutrale Fehlermeldung (gleiche Meldung bei unbekannter Nummer);
   Aushang-Weg `…/foto` ohne Parameter: falsches Geburtsdatum 6× → „Zu viele Versuche“ (Standard 5 pro 15 Minuten und IP).
5. **Vorstandserfassung**: Liste → bei einem offenen Mitglied „Foto erfassen“ → gleiche Oberfläche ohne Token,
   Status „durch Vorstand erfasst“, Mail mit `Quelle: vorstand`.
6. **Zurücksetzen / Löschen**: Zeile markieren → „Zurücksetzen“ (Zeile + Datei weg, Mitglied wieder offen) bzw.
   „Foto löschen“ (nur Datei weg, Zeile bleibt mit Löschzeitpunkt, keine erneute Einreichung).
7. Speicherordner prüfen: Foto-Datei vorhanden, `https://…/<ordner>/<datei>.jpg` darf **nicht** abrufbar sein.

Automatische Tests der Joomla-unabhängigen Logik (Token, Dateinamen, Bildprüfung, Datumsvergleich): `php tests/run.php`.

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
- Nicht in einer echten Joomla-Instanz getestet (hier keine Datenbank/Joomla verfügbar): getestet sind PHP-Syntax, XML-Dateien,
  die Joomla-unabhängige Logik und der komplette Browser-Ablauf (Zuschnitt → 600×800 JPEG → Serverprüfung) im Chromium. Bitte den Installationstest oben einmal durchlaufen.
