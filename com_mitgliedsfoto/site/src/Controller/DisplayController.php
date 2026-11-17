<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

class DisplayController extends BaseController
{
    protected $default_view = 'foto';

    public function display($cachable = false, $urlparams = [])
    {
        // Seite enthält personenbezogene Daten und Token: nie cachen.
        return parent::display(false, $urlparams);
    }
}
