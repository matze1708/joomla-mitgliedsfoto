<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Serverseitige Prüfung und Neu-Encoding des Fotos (GD). Gespeichert werden ausschließlich die von GD neu
 * erzeugten Pixel – nie die hochgeladenen Bytes. Eingebetteter Code, EXIF/Metadaten, Polyglott-Dateien o. Ä.
 * gehen dabei verloren. Akzeptiert wird nur, was der Browser nach dem Zuschnitt liefert: JPEG im Seitenverhältnis des Ausweis-Fotofelds, max. 1200x1600.
 */
final class Bild
{
    /**
     * Druckformat für das Fotofeld des Ausweises (Kartendesign: 19,3 x 24,7 mm) bei 600 dpi – so kann die Druckerei
     * das Foto ohne Umrechnung einsetzen (Retransfer-Farbdruck, RGB, 300–600 dpi).
     */
    public const BREITE = 456;
    public const HOEHE = 584;
    public const DPI = 600;
    /** Untergrenze = 300 dpi im Fotofeld */
    public const MIN_BREITE = 228;
    public const MIN_HOEHE = 292;
    public const MAX_BREITE = 1200;
    public const MAX_HOEHE = 1600;

    /**
     * @param   string  $datei      Pfad der hochgeladenen Datei
     * @param   int     $maxBytes   Größenlimit
     *
     * @return  string  JPEG-Binärdaten, 456x584 px bei 600 dpi (RGB)
     * @throws  FotoException  (Sprachschlüssel)
     */
    public static function verarbeiten(string $datei, int $maxBytes): string
    {
        if (!\extension_loaded('gd')) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_GD');
        }

        if (!is_file($datei) || !is_readable($datei)) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_UPLOAD');
        }

        $groesse = filesize($datei);

        if ($groesse === false || $groesse < 1 || $groesse > $maxBytes) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_GROESSE');
        }

        // Inhalt prüfen (nie Dateiname/Endung/Content-Type des Clients): Magic Bytes + Bildkopf.
        $kopf = (string) file_get_contents($datei, false, null, 0, 3);

        if ($kopf !== "\xFF\xD8\xFF") {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_FORMAT');
        }

        if (\function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = $finfo ? finfo_file($finfo, $datei) : false;

            if ($mime !== false && $mime !== 'image/jpeg') {
                throw new FotoException('COM_MITGLIEDSFOTO_ERR_FORMAT');
            }
        }

        $info = @getimagesize($datei);

        if ($info === false || $info[2] !== IMAGETYPE_JPEG) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_FORMAT');
        }

        [$w, $h] = $info;

        // Obergrenze verhindert Speicher-Erschöpfung (Dekompressionsbombe) vor dem Dekodieren.
        if ($w < 1 || $h < 1 || $w > self::MAX_BREITE || $h > self::MAX_HOEHE) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_FORMAT');
        }

        if ($w < self::MIN_BREITE || $h < self::MIN_HOEHE) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_ZU_KLEIN');
        }

        // Der Browser liefert bereits das Seitenverhältnis des Fotofelds. Abweichungen > 3 % werden abgelehnt.
        if (abs(($w / $h) - (self::BREITE / self::HOEHE)) > 0.03) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_SEITENVERHAELTNIS');
        }

        $quelle = @imagecreatefromjpeg($datei);

        if ($quelle === false) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_FORMAT');
        }

        $ziel = imagecreatetruecolor(self::BREITE, self::HOEHE);
        imagefill($ziel, 0, 0, (int) imagecolorallocate($ziel, 255, 255, 255));
        imagecopyresampled($ziel, $quelle, 0, 0, 0, 0, self::BREITE, self::HOEHE, $w, $h);

        // Auflösung im JPEG (JFIF) eintragen: 600 dpi -> 19,3 x 24,7 mm
        if (\function_exists('imageresolution')) {
            imageresolution($ziel, self::DPI, self::DPI);
        }

        ob_start();
        $ok = imagejpeg($ziel, null, 92);
        $jpeg = (string) ob_get_clean();

        unset($quelle, $ziel);

        if (!$ok || $jpeg === '') {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_VERARBEITUNG');
        }

        return $jpeg;
    }
}
