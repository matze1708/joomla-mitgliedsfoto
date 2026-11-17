<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\Dispatcher;

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\Language\Text;

/**
 * Backend-Zugriff nur mit eigener Berechtigung "mitgliedsfoto.verwalten" (zusätzlich zu core.manage).
 */
class Dispatcher extends ComponentDispatcher
{
    protected function checkAccess(): void
    {
        parent::checkAccess();

        if (!$this->app->getIdentity()->authorise('mitgliedsfoto.verwalten', $this->option)) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
}
