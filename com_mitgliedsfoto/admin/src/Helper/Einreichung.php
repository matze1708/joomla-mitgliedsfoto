<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Gemeinsamer Ablauf für Web-Einreichung und Vorstandserfassung.
 */
final class Einreichung
{
    /**
     * @param   object       $mitglied  Datensatz aus Mitglieder::finden()
     * @param   string       $tmpDatei  hochgeladene Datei ($_FILES[..]['tmp_name'])
     * @param   string       $quelle    'web' | 'vorstand'
     * @param   int|null     $userId    Joomla-User bei Vorstandserfassung
     *
     * @return  bool  true, wenn auch die Mail versendet wurde
     * @throws  FotoException
     */
    public static function speichern(object $mitglied, string $tmpDatei, string $quelle, ?int $userId): bool
    {
        $nr = (string) $mitglied->nr;

        if (Fotos::finden($nr)) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_BEREITS');
        }

        $jpeg     = Bild::verarbeiten($tmpDatei, Einstellungen::maxBytes());
        $dateiname = Dateiname::erzeugen((string) $mitglied->nachname, (string) $mitglied->vorname, $nr);

        // Erst die Zeile (Primärschlüssel = Schutz vor Doppel-Einreichung), dann die Datei.
        Fotos::anlegen($nr, $quelle === 'vorstand' ? 'vorstand' : 'eingereicht', $dateiname, $userId);

        try {
            Speicher::schreiben($dateiname, $jpeg);
        } catch (FotoException $e) {
            Fotos::entfernen($nr);
            throw $e;
        }

        return self::mailSenden($mitglied, $quelle, $dateiname);
    }

    public static function mailSenden(object $mitglied, string $quelle, string $dateiname): bool
    {
        try {
            Mail::senden($mitglied, $quelle, $dateiname);
            Fotos::mailGesendet((string) $mitglied->nr);

            return true;
        } catch (FotoException $e) {
            return false;
        }
    }
}
