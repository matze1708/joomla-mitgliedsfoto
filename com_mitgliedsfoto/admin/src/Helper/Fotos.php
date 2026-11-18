<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Zugriff auf die eigene Tabelle #__mitgliedsfoto.
 */
final class Fotos
{
    public static function finden(string $mitgliedsnr): ?object
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__mitgliedsfoto'))
            ->where($db->quoteName('mitgliedsnr') . ' = :nr')
            ->bind(':nr', $mitgliedsnr, ParameterType::STRING);

        return $db->setQuery($query)->loadObject() ?: null;
    }

    /**
     * Legt die Zeile an. Der Primärschlüssel verhindert doppelte Einreichungen (auch bei gleichzeitigen Aufrufen).
     *
     * @throws  FotoException
     */
    public static function anlegen(string $mitgliedsnr, string $status, string $dateiname, ?int $userId, array $zusatz = []): void
    {
        $db   = Factory::getContainer()->get(DatabaseInterface::class);
        $jetzt = Factory::getDate()->toSql();
        $zeile = (object) [
            'mitgliedsnr'     => $mitgliedsnr,
            'status'          => $status,
            'eingereicht_am'  => $jetzt,
            'dateiname'       => $dateiname,
            'einwilligung_am' => $jetzt,
            'erfasst_von'     => $userId,
        ];

        // Nur bei Erfassung ohne Joomla-Eintrag: Name/Geburtsdatum aus der Eingabe, Kennzeichen „geprüft“.
        foreach (['vorname', 'nachname', 'geburtsdatum', 'geprueft'] as $feld) {
            if (\array_key_exists($feld, $zusatz)) {
                $zeile->$feld = $zusatz[$feld];
            }
        }

        try {
            $db->insertObject('#__mitgliedsfoto', $zeile);
        } catch (\Throwable $e) {
            throw new FotoException(self::finden($mitgliedsnr) ? 'COM_MITGLIEDSFOTO_ERR_BEREITS' : 'COM_MITGLIEDSFOTO_ERR_SPEICHERN');
        }
    }

    public static function entfernen(string $mitgliedsnr): void
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__mitgliedsfoto'))
            ->where($db->quoteName('mitgliedsnr') . ' = :nr')
            ->bind(':nr', $mitgliedsnr, ParameterType::STRING);
        $db->setQuery($query)->execute();
    }

    /** Foto-Datei wurde gelöscht (Datenschutz); Zeile bleibt, damit nicht erneut eingereicht wird. */
    public static function alsGeloeschtMarkieren(string $mitgliedsnr): void
    {
        self::setzen($mitgliedsnr, ['geloescht_am' => Factory::getDate()->toSql(), 'dateiname' => null, 'geburtsdatum' => null]);
    }

    public static function bestaetigen(string $mitgliedsnr): void
    {
        self::setzen($mitgliedsnr, ['geprueft' => 1]);
    }

    /** @return  object[]  Einträge ohne Joomla-Mitgliedsdatensatz (Name in der eigenen Tabelle) */
    public static function freieEintraege(): array
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__mitgliedsfoto'))
            ->where($db->quoteName('vorname') . ' IS NOT NULL')
            ->order($db->quoteName('geprueft') . ' ASC')
            ->order($db->quoteName('nachname') . ' ASC')
            ->order($db->quoteName('vorname') . ' ASC');

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    public static function mailGesendet(string $mitgliedsnr): void
    {
        self::setzen($mitgliedsnr, ['mail_gesendet_am' => Factory::getDate()->toSql()]);
    }

    private static function setzen(string $mitgliedsnr, array $werte): void
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $zeile = (object) array_merge(['mitgliedsnr' => $mitgliedsnr], $werte);
        $db->updateObject('#__mitgliedsfoto', $zeile, 'mitgliedsnr', true);
    }
}
