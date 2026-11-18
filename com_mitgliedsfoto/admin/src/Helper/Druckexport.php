<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Druckpaket für den variablen Kartendruck: ZIP mit Excel-Datei (eine Zeile je Karte, Spalte mit Bild-Dateinamen)
 * und Ordner mit den Fotos. Reines PHP (kein ZipArchive nötig).
 */
final class Druckexport
{
    public const KOPF = ['Mitgliedsnummer', 'Vorname', 'Nachname', 'Mitglied seit', 'Foto'];

    /**
     * @param   array<int, array<int, string>>  $zeilen  Werte in der Reihenfolge von KOPF
     * @param   array<string, string>           $fotos   Dateiname => JPEG-Daten
     *
     * @return  string  ZIP-Binärdaten
     */
    public static function paket(array $zeilen, array $fotos, string $hinweis = ''): string
    {
        $dateien = ['druckdaten.xlsx' => self::xlsx(self::KOPF, $zeilen)];

        foreach ($fotos as $name => $daten) {
            $dateien['fotos/' . basename($name)] = $daten;
        }

        if ($hinweis !== '') {
            $dateien['LIESMICH.txt'] = $hinweis;
        }

        return self::zip($dateien);
    }

    public static function liesmich(int $anzahl): string
    {
        return implode("\r\n", [
            'Druckpaket Mitgliedsausweise - Schuetzenverein Volker Alzey 1952 e.V.',
            '',
            'Erstellt: ' . date('d.m.Y H:i'),
            'Anzahl Karten: ' . $anzahl,
            '',
            'druckdaten.xlsx  - eine Zeile je Karte: Mitgliedsnummer (Text, mit fuehrenden Nullen), Vorname, Nachname,',
            '                   Mitglied seit, Foto (= Dateiname im Ordner fotos/)',
            'fotos/           - JPEG, RGB, ' . Bild::BREITE . ' x ' . Bild::HOEHE . ' Pixel, ' . Bild::DPI . ' dpi = 19,3 x 24,7 mm',
            '                   (Fotofeld auf der Rueckseite des Ausweises)',
            '',
        ]);
    }

    /** Minimale XLSX-Datei (eine Tabelle, alle Zellen als Text – führende Nullen bleiben erhalten). */
    public static function xlsx(array $kopf, array $zeilen): string
    {
        $zellen = static function (array $werte, int $nr): string {
            $xml = '<row r="' . $nr . '">';

            foreach (array_values($werte) as $i => $wert) {
                $xml .= '<c r="' . self::spalte($i) . $nr . '" t="inlineStr"' . ($nr === 1 ? ' s="1"' : '') . '><is><t xml:space="preserve">'
                    . self::xml((string) $wert) . '</t></is></c>';
            }

            return $xml . '</row>';
        };

        $rows = $zellen($kopf, 1);

        foreach (array_values($zeilen) as $i => $zeile) {
            $rows .= $zellen($zeile, $i + 2);
        }

        $breiten = '<cols><col min="1" max="1" width="18" customWidth="1"/><col min="2" max="3" width="22" customWidth="1"/>'
            . '<col min="4" max="4" width="14" customWidth="1"/><col min="5" max="5" width="42" customWidth="1"/></cols>';

        return self::zip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="Ausweise" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
                . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
                . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                . '<cellXfs count="2"><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
                . '<xf numFmtId="49" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyNumberFormat="1"/></cellXfs>'
                . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
                . '</styleSheet>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
                . $breiten . '<sheetData>' . $rows . '</sheetData></worksheet>',
        ]);
    }

    /** A, B, … Z, AA … */
    private static function spalte(int $index): string
    {
        $name = '';

        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = \chr(65 + (($n - 1) % 26)) . $name;
        }

        return $name;
    }

    /** XML-Text: Sonderzeichen maskieren, in XML unzulässige Steuerzeichen entfernen. */
    private static function xml(string $text): string
    {
        $text = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text);

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Einfacher ZIP-Schreiber (Deflate, UTF-8-Dateinamen).
     *
     * @param   array<string, string>  $dateien  Pfad im Archiv => Inhalt
     */
    public static function zip(array $dateien): string
    {
        $daten   = '';
        $zentral = '';
        $anzahl  = 0;
        [$zeit, $datum] = self::dosZeit(time());

        foreach ($dateien as $name => $inhalt) {
            $name       = ltrim(str_replace(['\\', '..'], ['/', ''], (string) $name), '/');
            $inhalt     = (string) $inhalt;
            $komprimiert = (string) gzdeflate($inhalt, 6);
            $crc        = crc32($inhalt);
            $offset     = \strlen($daten);
            $kopf       = pack('vvvvvVVVvv', 20, 0x0800, 8, $zeit, $datum, $crc, \strlen($komprimiert), \strlen($inhalt), \strlen($name), 0);

            $daten   .= pack('V', 0x04034b50) . $kopf . $name . $komprimiert;
            $zentral .= pack('V', 0x02014b50) . pack('v', 20) . $kopf . pack('vvvVV', 0, 0, 0, 0, $offset) . $name;
            $anzahl++;
        }

        return $daten . $zentral
            . pack('VvvvvVVv', 0x06054b50, 0, 0, $anzahl, $anzahl, \strlen($zentral), \strlen($daten), 0);
    }

    /** @return  int[]  [DOS-Zeit, DOS-Datum] */
    private static function dosZeit(int $ts): array
    {
        $d = getdate($ts);

        return [
            ($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2),
            (($d['year'] - 1980) << 9) | ($d['mon'] << 5) | $d['mday'],
        ];
    }
}
