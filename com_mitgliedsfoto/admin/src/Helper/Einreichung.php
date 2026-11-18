<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;

/**
 * Gemeinsamer Ablauf für Web-Einreichung und Vorstandserfassung.
 */
final class Einreichung
{
    /** Lesbare Ursache des letzten fehlgeschlagenen Mailversands (für Admin-Meldungen). */
    public static string $mailFehler = '';

    /**
     * Prüft den Upload-Eintrag aus $_FILES und liefert den temporären Pfad.
     *
     * @throws  FotoException
     */
    public static function uploadPfad(mixed $datei): string
    {
        if (
            !\is_array($datei)
            || !isset($datei['tmp_name'], $datei['error'], $datei['size'])
            || !\is_string($datei['tmp_name'])          // keine Mehrfach-Uploads (foto[])
            || $datei['error'] !== \UPLOAD_ERR_OK
            || !is_uploaded_file($datei['tmp_name'])
        ) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_UPLOAD');
        }

        if ((int) $datei['size'] < 1 || (int) $datei['size'] > Einstellungen::maxBytes()) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_GROESSE');
        }

        return $datei['tmp_name'];
    }

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
        self::$mailFehler = '';

        try {
            Mail::senden($mitglied, $quelle, $dateiname);
            Fotos::mailGesendet((string) $mitglied->nr);

            return true;
        } catch (FotoException $e) {
            self::$mailFehler = Text::_($e->getMessage()) . (Mail::$details !== '' ? ' (' . Mail::$details . ')' : '');

            try {
                Log::addLogger(['text_file' => 'com_mitgliedsfoto.php'], Log::ALL, ['com_mitgliedsfoto']);
                Log::add('Mail für Mitglied ' . $mitglied->nr . ' nicht gesendet: ' . self::$mailFehler, Log::WARNING, 'com_mitgliedsfoto');
            } catch (\Throwable $ignorieren) {
            }

            return false;
        }
    }
}
