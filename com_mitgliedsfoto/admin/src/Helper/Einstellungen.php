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

    /** Standardwerte (identisch mit config.xml): Joomla speichert diese bei der Installation nicht. */
    private const STANDARD = [
        'max_upload_kb'      => 8192,
        'min_breite'         => 360,
        'min_hoehe'          => 480,
        'ratelimit_versuche' => 5,
        'ratelimit_minuten'  => 15,
        'ds_speicherort'     => 'auf dem Server der Vereinshomepage und im Vereins-Postfach sowie in der Mitgliederverwaltung des Vereins',
        'ds_loeschfrist'     => 'nach Erstellung der Mitgliedsausweise, spätestens jedoch nach Ablauf der Mitgliedschaft',
    ];

    private static ?string $seitenUrl = null;

    public static function get(string $name, $standard = '')
    {
        $wert = ComponentHelper::getParams(self::KOMPONENTE)->get($name);

        if ($wert === null || $wert === '') {
            return self::STANDARD[$name] ?? $standard;
        }

        return $wert;
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

    /** Installierte Version (aus dem Manifest-Cache in #__extensions). */
    public static function version(): string
    {
        try {
            $db      = Factory::getContainer()->get(DatabaseInterface::class);
            $element = self::KOMPONENTE;
            $typ     = 'component';
            $query   = $db->getQuery(true)
                ->select($db->quoteName('manifest_cache'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = :element')
                ->where($db->quoteName('type') . ' = :typ')
                ->bind(':element', $element)
                ->bind(':typ', $typ);
            $cache = json_decode((string) $db->setQuery($query)->loadResult(), true);

            return \is_array($cache) ? (string) ($cache['version'] ?? '') : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function maxBytes(): int
    {
        return max(100, (int) self::get('max_upload_kb', 8192)) * 1024;
    }

    public static function speicherordner(): string
    {
        $ordner = rtrim(trim((string) self::get('speicherordner')), '/\\');

        // Nur absolute Pfade ohne „..“; sonst Standardordner.
        if ($ordner === '' || str_contains($ordner, '..') || !preg_match('#^(/|[A-Za-z]:[\\\\/])#', $ordner)) {
            return JPATH_ADMINISTRATOR . '/components/com_mitgliedsfoto/fotos';
        }

        return $ordner;
    }

    /** true, wenn der Foto-Ordner im Webroot liegt (dann nur per .htaccess/web.config geschützt). */
    public static function speicherordnerImWebroot(): bool
    {
        $root   = rtrim(str_replace('\\', '/', (string) realpath(JPATH_ROOT)), '/') . '/';
        $ordner = str_replace('\\', '/', self::speicherordner());
        $echt   = realpath($ordner);
        $ordner = rtrim($echt !== false ? str_replace('\\', '/', $echt) : $ordner, '/') . '/';

        return str_starts_with($ordner, $root);
    }

    /** Absolute URL der Seite /foto (ohne Parameter). */
    public static function seitenUrl(): string
    {
        if (self::$seitenUrl !== null) {
            return self::$seitenUrl;
        }

        $url = trim((string) self::get('seiten_url'));

        if ($url === '') {
            $itemid = self::menueintrag();
            $url    = Route::link(
                'site',
                'index.php?option=com_mitgliedsfoto&view=foto' . ($itemid ? '&Itemid=' . $itemid : ''),
                false,
                Route::TLS_IGNORE,
                true
            )
                ?: rtrim(Uri::root(), '/') . '/foto';
        }

        return self::$seitenUrl = $url;
    }

    /** ID des veröffentlichten Frontend-Menüeintrags „Foto einreichen“ (0 = keiner). */
    public static function menueintrag(): int
    {
        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $link  = 'index.php?option=com_mitgliedsfoto&view=foto';
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('client_id') . ' = 0')
                ->where($db->quoteName('published') . ' = 1')
                ->where($db->quoteName('type') . " = 'component'")
                ->where($db->quoteName('link') . ' = :link')
                ->order($db->quoteName('id') . ' ASC')
                ->bind(':link', $link);

            return (int) $db->setQuery($query, 0, 1)->loadResult();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function linkFuer(string $mitgliedsnr): string
    {
        $basis = self::seitenUrl();

        return $basis . (str_contains($basis, '?') ? '&' : '?')
            . 'm=' . rawurlencode($mitgliedsnr) . '&t=' . Token::erzeugen($mitgliedsnr, self::schluessel());
    }
}
