<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

return new class () implements InstallerScriptInterface {
    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        if (version_compare(PHP_VERSION, '8.1.0', '<') || !extension_loaded('gd')) {
            Factory::getApplication()->enqueueMessage('com_mitgliedsfoto benötigt PHP 8.1+ mit GD.', 'error');

            return false;
        }

        return true;
    }

    /** Erzeugt beim Installieren den zufälligen HMAC-Schlüssel (sofern noch keiner existiert). */
    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $element = 'com_mitgliedsfoto';
        $typ     = 'component';
        $query   = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = :element')
            ->where($db->quoteName('type') . ' = :typ')
            ->bind(':element', $element)
            ->bind(':typ', $typ);

        $params = new Registry((string) $db->setQuery($query)->loadResult());

        if (strlen((string) $params->get('schluessel')) < 32) {
            $params->set('schluessel', bin2hex(random_bytes(32)));
            $json  = $params->toString('JSON');
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = :params')
                ->where($db->quoteName('element') . ' = :element')
                ->where($db->quoteName('type') . ' = :typ')
                ->bind(':params', $json)
                ->bind(':element', $element)
                ->bind(':typ', $typ);
            $db->setQuery($query)->execute();
        }

        return true;
    }
};
