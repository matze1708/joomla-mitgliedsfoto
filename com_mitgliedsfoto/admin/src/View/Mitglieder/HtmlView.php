<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\View\Mitglieder;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Einstellungen;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Fotos;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Mitglieder;

class HtmlView extends BaseHtmlView
{
    public array $items = [];
    public $pagination;
    public $state;
    public $filterForm;
    public array $activeFilters = [];
    public bool $konfiguriert = false;

    /** @var object[]  Einträge ohne Joomla-Mitgliedsdatensatz */
    public array $freie = [];

    public function display($tpl = null)
    {
        $this->konfiguriert = Mitglieder::konfiguriert();

        if ($this->konfiguriert) {
            $model = $this->getModel();
            $this->items         = $model->getItems() ?: [];
            $this->pagination    = $model->getPagination();
            $this->state         = $model->getState();
            $this->filterForm    = $model->getFilterForm();
            $this->activeFilters = $model->getActiveFilters();

            if (\count($errors = $model->getErrors())) {
                throw new GenericDataException(implode("\n", $errors), 500);
            }
        }

        if ($this->konfiguriert && trim((string) Einstellungen::get('seiten_url')) === '' && !Einstellungen::menueintrag()) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_MITGLIEDSFOTO_WARN_MENUE'), 'warning');
        }

        if (Einstellungen::speicherordnerImWebroot()) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_MITGLIEDSFOTO_WARN_WEBROOT'), 'warning');
        }

        // Einträge ohne Joomla-Datensatz (Vorstand frei erfasst bzw. QR-Code unbestätigt)
        $this->freie = array_values(array_filter(
            Fotos::freieEintraege(),
            static fn ($f) => !Mitglieder::finden((string) $f->mitgliedsnr)
        ));

        $this->addToolbar();

        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_MITGLIEDSFOTO_MITGLIEDER_TITEL'), 'image');
        $toolbar = Toolbar::getInstance('toolbar');

        if ($this->konfiguriert) {
            $toolbar->linkButton('frei', 'COM_MITGLIEDSFOTO_FREI_BUTTON')
                ->url('index.php?option=com_mitgliedsfoto&view=frei')->icon('icon-plus');
            $toolbar->standardButton('bestaetigen', 'COM_MITGLIEDSFOTO_BESTAETIGEN', 'foto.bestaetigen')
                ->icon('icon-check')->listCheck(true);
            $toolbar->standardButton('mailsenden', 'COM_MITGLIEDSFOTO_MAIL_ERNEUT', 'foto.mailsenden')
                ->icon('icon-envelope')->listCheck(true);
            $toolbar->standardButton('zuruecksetzen', 'COM_MITGLIEDSFOTO_ZURUECKSETZEN', 'foto.zuruecksetzen')
                ->icon('icon-undo')->listCheck(true);
            $toolbar->standardButton('loeschen', 'COM_MITGLIEDSFOTO_FOTO_LOESCHEN', 'foto.loeschen')
                ->icon('icon-trash')->listCheck(true);
            $toolbar->standardButton('druckexport', 'COM_MITGLIEDSFOTO_DRUCK_BUTTON', 'foto.druckexport')
                ->icon('icon-print')->listCheck(false);
            $toolbar->standardButton('export', 'COM_MITGLIEDSFOTO_EXPORT_ALLE', 'foto.export')
                ->icon('icon-download')->listCheck(false);
            $toolbar->standardButton('exportoffen', 'COM_MITGLIEDSFOTO_EXPORT_OFFENE', 'foto.exportOffene')
                ->icon('icon-download')->listCheck(false);
        }

        ToolbarHelper::preferences('com_mitgliedsfoto');
    }
}
