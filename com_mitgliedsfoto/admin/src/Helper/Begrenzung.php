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
 * Rate-Limit gegen Raten (Aushang-Weg): Fehlversuche je IP in einem Zeitfenster.
 * Die IP wird nur als gesalzener Hash gespeichert.
 */
final class Begrenzung
{
    private static function ipHash(): string
    {
        $ip = Factory::getApplication()->getInput()->server->getString('REMOTE_ADDR', '');

        return hash_hmac('sha256', $ip, Einstellungen::schluessel());
    }

    private static function db(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }

    private static function grenze(): string
    {
        $minuten = max(1, (int) Einstellungen::get('ratelimit_minuten', 15));

        return Factory::getDate('-' . $minuten . ' minutes')->toSql();
    }

    public static function blockiert(): bool
    {
        $db    = self::db();
        $hash  = self::ipHash();
        $seit  = self::grenze();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__mitgliedsfoto_versuche'))
            ->where($db->quoteName('ip_hash') . ' = :hash')
            ->where($db->quoteName('zeit') . ' >= :seit')
            ->bind(':hash', $hash)
            ->bind(':seit', $seit);

        return (int) $db->setQuery($query)->loadResult() >= max(1, (int) Einstellungen::get('ratelimit_versuche', 5));
    }

    public static function fehlversuch(): void
    {
        $db   = self::db();
        $hash = self::ipHash();
        $seit = Factory::getDate('-1 day')->toSql();

        // Alte Einträge aufräumen.
        $db->setQuery(
            $db->getQuery(true)->delete($db->quoteName('#__mitgliedsfoto_versuche'))
                ->where($db->quoteName('zeit') . ' < :seit')->bind(':seit', $seit)
        )->execute();

        $db->insertObject('#__mitgliedsfoto_versuche', (object) ['ip_hash' => $hash, 'zeit' => Factory::getDate()->toSql()]);
    }
}
