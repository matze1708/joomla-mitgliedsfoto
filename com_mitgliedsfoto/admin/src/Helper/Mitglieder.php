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
 * Liest die (synchronisierte) Joomla-Mitgliedertabelle live. Tabelle und Spalten sind in den Optionen konfiguriert.
 * Es werden keine Stammdaten kopiert.
 */
final class Mitglieder
{
    private const PFLICHT = ['nr', 'vorname', 'nachname', 'eintritt'];
    private const OPTIONAL = ['geburtsdatum', 'email'];

    /** @return  array<string,string>  logischer Name => Spaltenname (nur gültige, nicht leere) */
    public static function spalten(): array
    {
        $spalten = [];

        foreach (array_merge(self::PFLICHT, self::OPTIONAL) as $feld) {
            $name = trim((string) Einstellungen::get('spalte_' . $feld));

            if ($name !== '' && self::gueltig($name)) {
                $spalten[$feld] = $name;
            }
        }

        return $spalten;
    }

    public static function tabelle(): string
    {
        $tabelle = trim((string) Einstellungen::get('tabelle'));

        return self::gueltig($tabelle, true) ? $tabelle : '';
    }

    public static function konfiguriert(): bool
    {
        $spalten = self::spalten();

        foreach (self::PFLICHT as $feld) {
            if (!isset($spalten[$feld])) {
                return false;
            }
        }

        return self::tabelle() !== '';
    }

    public static function hatGeburtsdatum(): bool
    {
        return self::konfiguriert() && isset(self::spalten()['geburtsdatum']);
    }

    /** Nur Buchstaben, Ziffern, Unterstrich (Tabelle zusätzlich Präfix-Platzhalter #__). */
    private static function gueltig(string $name, bool $tabelle = false): bool
    {
        return (bool) preg_match($tabelle ? '/^(#__)?[A-Za-z0-9_]+$/' : '/^[A-Za-z0-9_]+$/', $name);
    }

    /**
     * SELECT-Spalten mit festen Aliassen (nr, vorname, nachname, eintritt, geburtsdatum, email).
     *
     * @return  string[]
     */
    public static function selectSpalten(DatabaseInterface $db, string $alias = ''): array
    {
        $spalten = self::spalten();
        $select  = [];

        foreach (array_merge(self::PFLICHT, self::OPTIONAL) as $feld) {
            $select[] = isset($spalten[$feld])
                ? $db->quoteName(($alias !== '' ? $alias . '.' : '') . $spalten[$feld], $feld)
                : 'NULL AS ' . $db->quoteName($feld);
        }

        return $select;
    }

    public static function finden(string $mitgliedsnr): ?object
    {
        if (!self::konfiguriert() || $mitgliedsnr === '' || \strlen($mitgliedsnr) > 50) {
            return null;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $spalten = self::spalten();
        $query = $db->getQuery(true)
            ->select(self::selectSpalten($db))
            ->from($db->quoteName(self::tabelle()))
            ->where($db->quoteName($spalten['nr']) . ' = :nr')
            ->bind(':nr', $mitgliedsnr, ParameterType::STRING);

        $zeile = $db->setQuery($query, 0, 1)->loadObject();

        if (!$zeile) {
            return null;
        }

        $zeile->nr = trim((string) $zeile->nr);

        return $zeile;
    }

    /** @return  object[]  alle Mitglieder inkl. Fotostatus (status NULL = offen), sortiert nach Name */
    public static function alleMitStatus(): array
    {
        if (!self::konfiguriert()) {
            return [];
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = self::mitStatusQuery($db)->select($db->quoteName('f.status', 'status'));

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /** Basis-Query: Mitglieder (m) links verknüpft mit #__mitgliedsfoto (f). */
    public static function mitStatusQuery(DatabaseInterface $db)
    {
        $spalten = self::spalten();

        return $db->getQuery(true)
            ->select(self::selectSpalten($db, 'm'))
            ->from($db->quoteName(self::tabelle(), 'm'))
            ->join(
                'LEFT',
                $db->quoteName('#__mitgliedsfoto', 'f') . ' ON ' . $db->quoteName('f.mitgliedsnr')
                . ' = CONVERT(' . $db->quoteName('m.' . $spalten['nr']) . ' USING utf8mb4) COLLATE utf8mb4_unicode_ci'
            )
            ->order($db->quoteName('m.' . $spalten['nachname']) . ' ASC')
            ->order($db->quoteName('m.' . $spalten['vorname']) . ' ASC');
    }

    /** Vergleicht eine Eingabe (TT.MM.JJJJ oder JJJJ-MM-TT) mit dem gespeicherten Geburtsdatum. */
    public static function geburtsdatumPasst(object $mitglied, string $eingabe): bool
    {
        $soll = self::normalisiereDatum((string) ($mitglied->geburtsdatum ?? ''));
        $ist  = self::normalisiereDatum($eingabe);

        return $soll !== null && $ist !== null && hash_equals($soll, $ist);
    }

    public static function normalisiereDatum(string $text): ?string
    {
        $text = trim($text);

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $text, $m)) {
            [$j, $mo, $t] = [(int) $m[3], (int) $m[2], (int) $m[1]];
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $text, $m)) {
            [$j, $mo, $t] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        return checkdate($mo, $t, $j) ? sprintf('%04d-%02d-%02d', $j, $mo, $t) : null;
    }
}
