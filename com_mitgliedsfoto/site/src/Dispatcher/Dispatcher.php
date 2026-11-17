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
        // Eine gemeinsame Sprachdatei (liegt im Administrator-Sprachordner) für Frontend und Backend.
        $this->app->getLanguage()->load('com_mitgliedsfoto', JPATH_ADMINISTRATOR);

        parent::dispatch();
    }
}
