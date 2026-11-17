<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

/**
 * Zugriff auf die Komponenten-Optionen.
 */
final class Einstellungen
{
    public const KOMPONENTE = 'com_mitgliedsfoto';

    public static function get(string $name, $standard = '')
    {
        return ComponentHelper::getParams(self::KOMPONENTE)->get($name, $standard);
    }

    /** Geheimer HMAC-Schlüssel; wird bei Bedarf einmalig erzeugt. */
    public static function schluessel(): string
    {
        $schluessel = trim((string) self::get('schluessel'));

        if (\strlen($schluessel) < 32) {
            $schluessel = bin2hex(random_bytes(32));
            self::speichern('schluessel', $schluessel);
        }

        return $schluessel;
    }

    public static function speichern(string $name, string $wert): void
    {
        $params = ComponentHelper::getParams(self::KOMPONENTE);
        $params->set($name, $wert);

        $json    = $params->toString('JSON');
        $element = self::KOMPONENTE;
        $typ     = 'component';
        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $query   = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('element') . ' = :element')
            ->where($db->quoteName('type') . ' = :typ')
            ->bind(':params', $json)
            ->bind(':element', $element)
            ->bind(':typ', $typ);
        $db->setQuery($query)->execute();
    }

    public static function maxBytes(): int
    {
        return max(100, (int) self::get('max_upload_kb', 8192)) * 1024;
    }

    public static function speicherordner(): string
    {
        $ordner = rtrim(trim((string) self::get('speicherordner')), '/\\');

        return $ordner !== '' ? $ordner : JPATH_ADMINISTRATOR . '/components/com_mitgliedsfoto/fotos';
    }

    /** Absolute URL der Seite /foto (ohne Parameter). */
    public static function seitenUrl(): string
    {
        $url = trim((string) self::get('seiten_url'));

        if ($url !== '') {
            return $url;
        }

        return Route::link('site', 'index.php?option=com_mitgliedsfoto&view=foto', false, Route::TLS_IGNORE, true)
            ?: rtrim(Uri::root(), '/') . '/foto';
    }

    public static function linkFuer(string $mitgliedsnr): string
    {
        $basis = self::seitenUrl();

        return $basis . (str_contains($basis, '?') ? '&' : '?')
            . 'm=' . rawurlencode($mitgliedsnr) . '&t=' . Token::erzeugen($mitgliedsnr, self::schluessel());
    }
}
