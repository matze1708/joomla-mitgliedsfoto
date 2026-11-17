<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Site\Dispatcher;

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcher;

class Dispatcher extends ComponentDispatcher
{
    public function dispatch()
    {
        // Eine gemeinsame Sprachdatei (liegt im Administrator-Sprachordner) für Frontend und Backend;
        // Texte gibt es nur auf Deutsch – bei anderer Website-Sprache wird de-DE nachgeladen.
        $sprache = $this->app->getLanguage();
        $sprache->load('com_mitgliedsfoto', JPATH_ADMINISTRATOR);

        if (!$sprache->hasKey('COM_MITGLIEDSFOTO_SEITENTITEL')) {
            $sprache->load('com_mitgliedsfoto', JPATH_ADMINISTRATOR, 'de-DE', true)
                || $sprache->load('com_mitgliedsfoto', JPATH_ADMINISTRATOR . '/components/com_mitgliedsfoto', 'de-DE', true);
        }

        parent::dispatch();
    }
}
