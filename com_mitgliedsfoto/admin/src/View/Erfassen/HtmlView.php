<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\View\Erfassen;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Fotos;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Mitglieder;

class HtmlView extends BaseHtmlView
{
    public ?object $mitglied = null;
    public bool $bereits = false;

    public function display($tpl = null)
    {
        $nr = trim(Factory::getApplication()->getInput()->getString('nr', ''));
        $this->mitglied = Mitglieder::finden($nr);

        if (!$this->mitglied) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_MITGLIEDSFOTO_ERR_UNBEKANNT'), 'error');
            Factory::getApplication()->redirect('index.php?option=com_mitgliedsfoto&view=mitglieder');
        }

        $this->bereits = (bool) Fotos::finden($this->mitglied->nr);

        ToolbarHelper::title(Text::_('COM_MITGLIEDSFOTO_FOTO_ERFASSEN'), 'camera');
        ToolbarHelper::back('JTOOLBAR_BACK', 'index.php?option=com_mitgliedsfoto&view=mitglieder');

        parent::display($tpl);
    }
}
