<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\Helper;

\defined('_JEXEC') or die;


/**
 * Nicht öffentlicher Foto-Ordner (zusätzliche Kopie). Auslieferung nur über ACL-geprüften Controller.
 */
final class Speicher
{
    public static function pfad(string $dateiname): string
    {
        // Nur der reine Dateiname, nie ein Pfad.
        return Einstellungen::speicherordner() . '/' . basename($dateiname);
    }

    public static function schreiben(string $dateiname, string $jpeg): void
    {
        $ordner = Einstellungen::speicherordner();

        if (!is_dir($ordner) && !@mkdir($ordner, 0750, true) && !is_dir($ordner)) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_SPEICHERN');
        }

        self::schuetzen($ordner);

        if (@file_put_contents(self::pfad($dateiname), $jpeg, LOCK_EX) === false) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_SPEICHERN');
        }

        @chmod(self::pfad($dateiname), 0640);
    }

    public static function loeschen(?string $dateiname): void
    {
        if ($dateiname !== null && $dateiname !== '' && is_file(self::pfad($dateiname))) {
            @unlink(self::pfad($dateiname));
        }
    }

    /** Falls der Ordner (unerwünscht) im Webroot liegt: Zugriffsschutz für Apache/IIS/Listing. */
    public static function schuetzen(string $ordner): void
    {
        $dateien = [
            '.htaccess' => "Require all denied\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n",
            'index.html' => "<!DOCTYPE html><title></title>\n",
            'web.config' => "<?xml version=\"1.0\"?><configuration><system.webServer><authorization><deny users=\"*\"/></authorization></system.webServer></configuration>\n",
        ];

        foreach ($dateien as $name => $inhalt) {
            if (!is_file($ordner . '/' . $name)) {
                @file_put_contents($ordner . '/' . $name, $inhalt);
            }
        }
    }
}
