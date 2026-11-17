<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Site\View\Foto;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Begrenzung;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Einstellungen;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Fotos;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Mitglieder;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Token;

class HtmlView extends BaseHtmlView
{
    /** ident | ungueltig | gesperrt | bereits | formular | nicht_konfiguriert */
    public string $zustand = 'ident';

    public ?object $mitglied = null;
    public string $token = '';
    public string $einwilligung = '';
    public string $submitUrl = '';
    public string $identUrl = '';
    public bool $geburtsdatumMoeglich = false;

    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $app->allowCache(false);
        $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private', true);
        $app->setHeader('Referrer-Policy', 'no-referrer', true);
        $app->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
        $this->getDocument()->setMetaData('robots', 'noindex, nofollow');

        $this->submitUrl = Route::_('index.php?option=com_mitgliedsfoto', false);
        $this->identUrl  = Route::_('index.php?option=com_mitgliedsfoto&task=foto.ident', false);
        $this->geburtsdatumMoeglich = Mitglieder::hatGeburtsdatum();

        $input = $app->getInput();
        $nr    = trim($input->get->getString('m', ''));
        $token = trim($input->get->getString('t', ''));

        if (!Mitglieder::konfiguriert()) {
            $this->zustand = 'nicht_konfiguriert';
        } elseif ($nr === '' && $token === '') {
            $this->zustand = 'ident';
        } elseif (Begrenzung::blockiert()) {
            $this->zustand = 'gesperrt';
        } else {
            $mitglied = Mitglieder::finden($nr);

            // Ungültiger Token und unbekannte Nummer führen zur selben neutralen Meldung.
            if (!$mitglied || !Token::pruefen($mitglied->nr, $token, Einstellungen::schluessel())) {
                $this->zustand = 'ungueltig';
                Begrenzung::fehlversuch();
            } elseif (Fotos::finden($mitglied->nr)) {
                $this->zustand = 'bereits';
                $this->mitglied = $mitglied;
            } else {
                $this->zustand = 'formular';
                $this->mitglied = $mitglied;
                $this->token = $token;
                $this->einwilligung = self::einwilligungsText();
            }
        }

        parent::display($tpl);
    }

    public static function einwilligungsText(): string
    {
        $text = Text::sprintf(
            'COM_MITGLIEDSFOTO_EINWILLIGUNG',
            htmlspecialchars((string) Einstellungen::get('ds_speicherort'), ENT_QUOTES),
            htmlspecialchars((string) Einstellungen::get('ds_loeschfrist'), ENT_QUOTES)
        );
        $link = (string) Einstellungen::get('ds_link');

        if ($link !== '') {
            $text .= ' <a href="' . htmlspecialchars($link, ENT_QUOTES) . '" target="_blank" rel="noopener">'
                . Text::_('COM_MITGLIEDSFOTO_DATENSCHUTZ_LINK') . '</a>';
        }

        return $text;
    }
}
