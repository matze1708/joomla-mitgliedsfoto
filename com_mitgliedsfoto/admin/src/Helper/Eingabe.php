<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Prüfung freier Eingaben (Name, Geburtsdatum) – Whitelist, nie ungeprüft weiterverwenden.
 */
final class Eingabe
{
    /** Vor-/Nachname: Buchstaben (inkl. Umlaute/Akzente), Leerzeichen, Bindestrich, Apostroph, Punkt; max. 100 Zeichen. */
    public static function name(string $wert): ?string
    {
        $wert = trim((string) preg_replace('/\s+/u', ' ', $wert));

        if (class_exists(\Normalizer::class)) {
            $wert = (string) \Normalizer::normalize($wert, \Normalizer::FORM_C);
        }

        return preg_match("/^\\p{L}[\\p{L}\\p{M} .'’\\-]{0,99}$/uD", $wert) ? $wert : null;
    }

    /** Geburtsdatum als JJJJ-MM-TT, nur plausible Werte (nach 1900, nicht in der Zukunft). */
    public static function geburtsdatum(string $wert): ?string
    {
        $wert = mb_substr(trim($wert), 0, 10);
        $iso  = Mitglieder::normalisiereDatum($wert);

        return ($iso !== null && $iso <= date('Y-m-d')) ? $iso : null;
    }

    /**
     * Vergleich „Vorname Nachname“ unabhängig von Groß-/Kleinschreibung, Umlaut-Schreibweise,
     * Leerzeichen/Bindestrichen und davon, wo der Kontoname in Vor-/Nachname aufgeteilt wurde.
     */
    public static function namenGleich(string $vorA, string $nachA, string $vorB, string $nachB): bool
    {
        $norm = static fn (string $v, string $n) => strtolower(str_replace('-', '', Dateiname::ascii($v . ' ' . $n)));

        $a = $norm($vorA, $nachA);
        $b = $norm($vorB, $nachB);

        return $a !== 'x' && hash_equals($b, $a);
    }

    public static function datumDeutsch(?string $iso): string
    {
        return ($iso && preg_match('/^(\\d{4})-(\\d{2})-(\\d{2})/', $iso, $m)) ? $m[3] . '.' . $m[2] . '.' . $m[1] : '';
    }
}
