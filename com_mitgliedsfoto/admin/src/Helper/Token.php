<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Link-Token = HMAC-SHA256(Mitgliedsnummer, geheimer Schlüssel). Wird nirgends gespeichert.
 */
final class Token
{
    public static function erzeugen(string $mitgliedsnr, string $schluessel): string
    {
        return hash_hmac('sha256', $mitgliedsnr, $schluessel);
    }

    public static function pruefen(string $mitgliedsnr, string $token, string $schluessel): bool
    {
        if ($mitgliedsnr === '' || $schluessel === '' || !preg_match('/^[0-9a-f]{64}$/', $token)) {
            return false;
        }

        return hash_equals(self::erzeugen($mitgliedsnr, $schluessel), $token);
    }
}
