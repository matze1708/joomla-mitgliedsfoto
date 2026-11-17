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

    /**
     * Datenschutz: Vor dem Entfernen der Tabellen alle in #__mitgliedsfoto verzeichneten Fotos löschen –
     * auch in einem eigenen Speicherordner außerhalb des Komponentenverzeichnisses.
     * Andere Dateien im Ordner werden nicht angefasst.
     */
    public function uninstall(InstallerAdapter $adapter): bool
    {
        try {
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

            $standard = JPATH_ADMINISTRATOR . '/components/com_mitgliedsfoto/fotos';
            $ordner   = rtrim(trim((string) $params->get('speicherordner', '')), '/\\');

            if ($ordner === '' || str_contains($ordner, '..') || !preg_match('#^(/|[A-Za-z]:[\\\\/])#', $ordner)) {
                $ordner = $standard;
            }

            $dateien = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('dateiname'))
                    ->from($db->quoteName('#__mitgliedsfoto'))
                    ->where($db->quoteName('dateiname') . ' IS NOT NULL')
            )->loadColumn();

            $geloescht = 0;

            foreach ($dateien as $datei) {
                $datei = basename((string) $datei);

                // Nur von der Komponente erzeugte Namen (Nachname_Vorname_Nr.jpg).
                if (!preg_match('/^[A-Za-z0-9-]+_[A-Za-z0-9-]+_[A-Za-z0-9-]+\.jpg$/D', $datei)) {
                    continue;
                }

                $pfad = $ordner . '/' . $datei;

                if (is_file($pfad) && @unlink($pfad)) {
                    $geloescht++;
                }
            }

            // Eigenen Ordner aufräumen, falls nur noch die Schutzdateien darin liegen.
            if ($ordner !== $standard && is_dir($ordner)) {
                $rest = array_diff(scandir($ordner) ?: [], ['.', '..', '.htaccess', 'index.html', 'web.config']);

                if ($rest === []) {
                    foreach (['.htaccess', 'index.html', 'web.config'] as $schutz) {
                        @unlink($ordner . '/' . $schutz);
                    }

                    @rmdir($ordner);
                }
            }

            Factory::getApplication()->enqueueMessage(
                sprintf('Mitgliedsfoto: %d Foto(s) gelöscht (Ordner %s). Kopien in Mails, OneDrive/SharePoint oder SPG Verein sind davon nicht betroffen.', $geloescht, $ordner),
                'message'
            );
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage('Mitgliedsfoto: Fotos konnten nicht automatisch gelöscht werden – bitte den Speicherordner manuell prüfen.', 'warning');
        }

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
