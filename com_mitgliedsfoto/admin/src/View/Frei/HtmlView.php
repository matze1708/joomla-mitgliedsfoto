<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\View\Frei;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Vorstand erfasst ein Mitglied ohne Joomla-Eintrag (Nummer, Name, Foto) – z. B. am Smartphone.
 */
class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        ToolbarHelper::title(Text::_('COM_MITGLIEDSFOTO_FREI_TITEL'), 'camera');
        ToolbarHelper::back('JTOOLBAR_BACK', 'index.php?option=com_mitgliedsfoto&view=mitglieder');

        parent::display($tpl);
    }
}
