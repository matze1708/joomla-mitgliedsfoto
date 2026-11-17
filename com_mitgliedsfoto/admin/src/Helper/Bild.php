<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Serverseitige Prüfung und Neu-Encoding des Fotos (GD): nie wird die Originaldatei gespeichert.
 */
final class Bild
{
    public const BREITE = 600;
    public const HOEHE = 800;
    public const MIN_BREITE = 300;
    public const MIN_HOEHE = 400;
    public const MAX_PIXEL = 8000000; // ~40 MB GD-Speicher; der Browser liefert 600x800

    /**
     * @param   string  $datei      Pfad der hochgeladenen Datei
     * @param   int     $maxBytes   Größenlimit
     *
     * @return  string  JPEG-Binärdaten, 600x800
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

        $info = @getimagesize($datei);

        if ($info === false || !\in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_FORMAT');
        }

        [$w, $h] = $info;

        if ($w < 1 || $h < 1 || $w * $h > self::MAX_PIXEL) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_FORMAT');
        }

        if ($w < self::MIN_BREITE || $h < self::MIN_HOEHE) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_ZU_KLEIN');
        }

        // Der Browser liefert bereits 3:4. Abweichungen > 3 % werden abgelehnt.
        if (abs(($w / $h) - 0.75) > 0.03) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_SEITENVERHAELTNIS');
        }

        $quelle = @imagecreatefromstring((string) file_get_contents($datei));

        if ($quelle === false) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_FORMAT');
        }

        $ziel = imagecreatetruecolor(self::BREITE, self::HOEHE);
        imagefill($ziel, 0, 0, (int) imagecolorallocate($ziel, 255, 255, 255));
        imagecopyresampled($ziel, $quelle, 0, 0, 0, 0, self::BREITE, self::HOEHE, $w, $h);

        ob_start();
        $ok = imagejpeg($ziel, null, 90);
        $jpeg = (string) ob_get_clean();

        unset($quelle, $ziel);

        if (!$ok || $jpeg === '') {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_VERARBEITUNG');
        }

        return $jpeg;
    }
}
