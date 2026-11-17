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
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;

/**
 * Liest die Mitgliederstammdaten live aus Joomla. Es werden keine Stammdaten kopiert.
 *
 * Zwei Quellen (Optionen → „Datenquelle“):
 *  - „benutzer“: Joomla-Benutzer (#__users) mit Benutzerfeldern (#__fields / #__fields_values)
 *  - „tabelle“:  beliebige Tabelle mit konfigurierbaren Spalten
 *
 * Alle Namen aus den Optionen werden gegen eine Whitelist geprüft und nie ungeprüft in SQL eingesetzt.
 */
final class Mitglieder
{
    public const FELDER = ['nr', 'vorname', 'nachname', 'eintritt', 'geburtsdatum', 'email'];
    private const PFLICHT = ['nr', 'vorname', 'nachname'];

    /** Erlaubte Kernfelder von #__users bzw. daraus abgeleitete Ausdrücke. */
    private const KERN = ['name', 'username', 'email', 'registerDate', 'name_vorname', 'name_nachname'];

    /** Zulässiges Format einer Mitgliedsnummer (Eingaben werden vor jeder Abfrage geprüft). */
    public const NR_MUSTER = '/^[A-Za-z0-9][A-Za-z0-9._\/-]{0,49}$/D';

    public static function quelle(): string
    {
        return Einstellungen::get('quelle', 'benutzer') === 'tabelle' ? 'tabelle' : 'benutzer';
    }

    /**
     * Zuordnung logischer Felder, nur gültige Einträge.
     *
     * @return  array<string,string>  z. B. ['nr' => 'field:12', 'email' => 'core:email'] bzw. ['nr' => 'spalte']
     */
    public static function zuordnung(): array
    {
        $zuordnung = [];

        foreach (self::FELDER as $feld) {
            if (self::quelle() === 'tabelle') {
                $wert = trim((string) Einstellungen::get('spalte_' . $feld));

                if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $wert)) {
                    $zuordnung[$feld] = $wert;
                }

                continue;
            }

            $wert = trim((string) Einstellungen::get('feld_' . $feld));

            if (preg_match('/^field:([1-9][0-9]{0,9})$/', $wert, $m)) {
                $zuordnung[$feld] = 'field:' . (int) $m[1];
            } elseif (preg_match('/^core:([A-Za-z_]+)$/', $wert, $m) && \in_array($m[1], self::KERN, true)) {
                $zuordnung[$feld] = 'core:' . $m[1];
            }
        }

        return $zuordnung;
    }

    public static function tabelle(): string
    {
        $tabelle = trim((string) Einstellungen::get('tabelle'));

        return preg_match('/^(#__)?[A-Za-z0-9_]{1,64}$/', $tabelle) ? $tabelle : '';
    }

    public static function konfiguriert(): bool
    {
        $zuordnung = self::zuordnung();

        foreach (self::PFLICHT as $feld) {
            if (!isset($zuordnung[$feld])) {
                return false;
            }
        }

        return self::quelle() === 'benutzer' || self::tabelle() !== '';
    }

    public static function hatFeld(string $feld): bool
    {
        return self::konfiguriert() && isset(self::zuordnung()[$feld]);
    }

    public static function hatGeburtsdatum(): bool
    {
        return self::hatFeld('geburtsdatum');
    }

    public static function nrGueltig(string $nr): bool
    {
        return (bool) preg_match(self::NR_MUSTER, $nr);
    }

    /** SQL-Ausdruck für ein logisches Feld (nur aus Whitelist-Bausteinen) oder null. */
    public static function ausdruck(DatabaseInterface $db, string $feld): ?string
    {
        $wert = self::zuordnung()[$feld] ?? null;

        if ($wert === null) {
            return null;
        }

        if (self::quelle() === 'tabelle') {
            return $db->quoteName('m.' . $wert);
        }

        if (str_starts_with($wert, 'field:')) {
            return $db->quoteName('fv_' . $feld . '.value');
        }

        $name = $db->quoteName('u.name');

        return match (substr($wert, 5)) {
            // Name = „Vorname Nachname“: letztes Wort als Nachname, Rest als Vorname
            'name_nachname' => 'SUBSTRING_INDEX(TRIM(' . $name . "), ' ', -1)",
            'name_vorname'  => 'TRIM(SUBSTRING(TRIM(' . $name . '), 1, CHAR_LENGTH(TRIM(' . $name . ')) - CHAR_LENGTH(SUBSTRING_INDEX(TRIM('
                . $name . "), ' ', -1))))",
            default         => $db->quoteName('u.' . substr($wert, 5)),
        };
    }

    /**
     * Grundabfrage mit festen Aliassen nr, vorname, nachname, eintritt, geburtsdatum, email.
     * Nur Mitglieder mit Mitgliedsnummer; bei Benutzerquelle nur nicht gesperrte Konten (Option).
     */
    public static function basisQuery(DatabaseInterface $db): DatabaseQuery
    {
        $query = $db->getQuery(true);

        if (self::quelle() === 'tabelle') {
            $query->from($db->quoteName(self::tabelle(), 'm'));
        } else {
            $query->from($db->quoteName('#__users', 'u'));

            foreach (self::zuordnung() as $feld => $wert) {
                if (str_starts_with($wert, 'field:')) {
                    $alias = 'fv_' . $feld;
                    $query->join(
                        'LEFT',
                        $db->quoteName('#__fields_values', $alias),
                        $db->quoteName($alias . '.item_id') . ' = ' . $db->quoteName('u.id')
                        . ' AND ' . $db->quoteName($alias . '.field_id') . ' = ' . (int) substr($wert, 6)
                    );
                }
            }

            if ((int) Einstellungen::get('nur_aktive', 0)) {
                $query->where($db->quoteName('u.block') . ' = 0');
            }
        }

        foreach (self::FELDER as $feld) {
            $ausdruck = self::ausdruck($db, $feld);
            $query->select(($ausdruck ?? 'NULL') . ' AS ' . $db->quoteName($feld));
        }

        $nr = self::ausdruck($db, 'nr');
        $query->where($nr . ' IS NOT NULL')->where('TRIM(' . $nr . ") <> ''");

        return $query;
    }

    public static function finden(string $mitgliedsnr): ?object
    {
        $mitgliedsnr = trim($mitgliedsnr);

        if (!self::konfiguriert() || !self::nrGueltig($mitgliedsnr)) {
            return null;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = self::basisQuery($db)
            ->where('TRIM(' . self::ausdruck($db, 'nr') . ') = :nr')
            ->bind(':nr', $mitgliedsnr, ParameterType::STRING);

        $zeilen = $db->setQuery($query, 0, 2)->loadObjectList();

        // Mitgliedsnummer muss eindeutig sein – bei Doppelungen lieber nichts tun.
        if (\count($zeilen) !== 1) {
            return null;
        }

        $zeile     = $zeilen[0];
        $zeile->nr = trim((string) $zeile->nr);

        return self::nrGueltig($zeile->nr) ? $zeile : null;
    }

    /** Mitglieder (Alias m bzw. u) links verknüpft mit #__mitgliedsfoto (f), sortiert nach Name. */
    public static function mitStatusQuery(DatabaseInterface $db): DatabaseQuery
    {
        return self::basisQuery($db)
            ->join(
                'LEFT',
                $db->quoteName('#__mitgliedsfoto', 'f'),
                $db->quoteName('f.mitgliedsnr') . ' = CONVERT(TRIM(' . self::ausdruck($db, 'nr') . ') USING utf8mb4) COLLATE utf8mb4_unicode_ci'
            )
            ->order(self::ausdruck($db, 'nachname') . ' ASC')
            ->order(self::ausdruck($db, 'vorname') . ' ASC');
    }

    /** @return  object[]  alle Mitglieder inkl. Fotostatus (status NULL = offen) */
    public static function alleMitStatus(): array
    {
        if (!self::konfiguriert()) {
            return [];
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = self::mitStatusQuery($db)->select($db->quoteName('f.status', 'status'));

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /** Vergleicht eine Eingabe (TT.MM.JJJJ oder JJJJ-MM-TT) mit dem gespeicherten Geburtsdatum. */
    public static function geburtsdatumPasst(object $mitglied, string $eingabe): bool
    {
        $soll = self::normalisiereDatum(self::utcInOrtszeit((string) ($mitglied->geburtsdatum ?? '')));
        $ist  = self::normalisiereDatum($eingabe);

        return $soll !== null && $ist !== null && hash_equals($soll, $ist);
    }

    /**
     * Kalender-Benutzerfelder speichern ggf. in UTC („1980-05-16 22:00:00“ = 17.05.1980 in Deutschland).
     * Werte mit Uhrzeit ungleich 00:00:00 werden daher in die Zeitzone der Website umgerechnet.
     */
    public static function utcInOrtszeit(string $wert, ?string $zeitzone = null): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T](\d{2}):(\d{2}):(\d{2})/', trim($wert), $m) || $m[1] . $m[2] . $m[3] === '000000') {
            return $wert;
        }

        try {
            $zeitzone ??= class_exists(Factory::class) ? (string) Factory::getApplication()->get('offset', 'UTC') : 'UTC';
            $datum = new \DateTimeImmutable(substr(trim($wert), 0, 19), new \DateTimeZone('UTC'));

            return $datum->setTimezone(new \DateTimeZone($zeitzone ?: 'UTC'))->format('Y-m-d');
        } catch (\Throwable $e) {
            return $wert;
        }
    }

    public static function normalisiereDatum(string $text): ?string
    {
        $text = trim($text);

        if (\strlen($text) > 32) {
            return null;
        }

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $text, $m)) {
            [$j, $mo, $t] = [(int) $m[3], (int) $m[2], (int) $m[1]];
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $text, $m)) {
            [$j, $mo, $t] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        return checkdate($mo, $t, $j) && $j > 1900 ? sprintf('%04d-%02d-%02d', $j, $mo, $t) : null;
    }
}
