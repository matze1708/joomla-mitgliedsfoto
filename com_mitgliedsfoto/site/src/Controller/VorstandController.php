<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Vorstand;

/**
 * Vorstandsbereich im Frontend (nur angemeldet, Berechtigung „Mitgliedsfotos verwalten“ – Prüfung in Vorstand::erfassen).
 */
class VorstandController extends BaseController
{
    public function erfassen(): void
    {
        Vorstand::json($this->app, Vorstand::erfassen($this->app, false, $this->zurueck()));
    }

    public function erfassenFrei(): void
    {
        Vorstand::json($this->app, Vorstand::erfassen($this->app, true, $this->zurueck()));
    }

    private function zurueck(): string
    {
        $itemid = (int) $this->app->getInput()->getInt('Itemid', 0);

        return Route::_('index.php?option=com_mitgliedsfoto&view=vorstand' . ($itemid ? '&Itemid=' . $itemid : ''), false);
    }
}
