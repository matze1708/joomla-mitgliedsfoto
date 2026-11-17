<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Erzeugt Dateinamen der Form Nachname_Vorname_Mitgliedsnummer.jpg (reines ASCII).
 */
final class Dateiname
{
    public static function erzeugen(string $nachname, string $vorname, string $mitgliedsnr): string
    {
        $nr = preg_replace('/[^A-Za-z0-9-]+/', '', self::ascii($mitgliedsnr)) ?? '';

        return self::ascii($nachname) . '_' . self::ascii($vorname) . '_' . ($nr !== '' ? $nr : 'x') . '.jpg';
    }

    public static function ascii(string $text): string
    {
        $text = strtr($text, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
            'ß' => 'ss', 'ẞ' => 'SS',
        ]);

        if (\function_exists('transliterator_transliterate')) {
            $t = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
            $text = $t !== false ? $t : $text;
        } elseif (\function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            $text = $t !== false ? $t : $text;
        }

        $text = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $text), '-');

        return $text !== '' ? $text : 'x';
    }
}
