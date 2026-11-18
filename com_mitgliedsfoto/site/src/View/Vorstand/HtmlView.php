<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Site\View\Vorstand;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Fotos;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Mitglieder;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Vorstand;

/**
 * Vorstandsbereich im Frontend: Mitglied suchen, Foto erfassen, Mitglied ohne Joomla-Konto erfassen.
 * Nur für angemeldete Benutzer mit der Berechtigung „Mitgliedsfotos verwalten“.
 */
class HtmlView extends BaseHtmlView
{
    public string $suche = '';
    public array $treffer = [];
    public array $freie = [];
    public ?object $mitglied = null;
    public bool $bereits = false;
    public bool $konfiguriert = false;
    public int $itemid = 0;

    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $app->allowCache(false);
        $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private', true);
        $app->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
        $this->getDocument()->setMetaData('robots', 'noindex, nofollow');

        $user = $app->getIdentity();

        if (!$user || $user->guest) {
            $zurueck = base64_encode(Uri::getInstance()->toString());
            $app->enqueueMessage(Text::_('COM_MITGLIEDSFOTO_VORSTAND_LOGIN'), 'notice');
            $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . $zurueck, false));
        }

        if (!Vorstand::darf($app)) {
            $app->setHeader('status', '403', true);
            $this->setLayout('kein_zugriff');
            parent::display($tpl);

            return;
        }

        $input              = $app->getInput();
        $this->itemid       = (int) $input->getInt('Itemid', 0);
        $this->konfiguriert = Mitglieder::konfiguriert();
        $layout             = $this->getLayout();

        if ($layout === 'erfassen') {
            $this->mitglied = Mitglieder::finden(trim($input->getString('nr', '')));

            if (!$this->mitglied) {
                $app->enqueueMessage(Text::_('COM_MITGLIEDSFOTO_ERR_UNBEKANNT'), 'error');
                $app->redirect($this->url());
            }

            $this->bereits = (bool) Fotos::finden($this->mitglied->nr);
        } elseif ($layout !== 'frei') {
            $this->setLayout('default');
            $this->suche = mb_substr(trim($input->getString('suche', '')), 0, 100);

            if ($this->konfiguriert && mb_strlen($this->suche) >= 2) {
                $db    = Factory::getContainer()->get(DatabaseInterface::class);
                $query = Mitglieder::mitStatusQuery($db)->select([
                    $db->quoteName('f.status', 'status'),
                    $db->quoteName('f.geprueft', 'geprueft'),
                ]);
                Mitglieder::sucheAnwenden($query, $db, $this->suche);
                $this->treffer = $db->setQuery($query, 0, 50)->loadObjectList() ?: [];

                // Einträge ohne Joomla-Datensatz, die zur Suche passen
                $like  = '%' . addcslashes($this->suche, '%_\\') . '%';
                $like0 = '%' . addcslashes(Mitglieder::ohneNullen($this->suche), '%_\\') . '%';
                $q2    = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__mitgliedsfoto'))
                    ->where($db->quoteName('vorname') . ' IS NOT NULL')
                    ->where('(' . $db->quoteName('nachname') . ' LIKE :f1 OR ' . $db->quoteName('vorname') . ' LIKE :f2 OR '
                        . $db->quoteName('mitgliedsnr') . ' LIKE :f3)')
                    ->order($db->quoteName('nachname') . ' ASC')
                    ->bind(':f1', $like, ParameterType::STRING)
                    ->bind(':f2', $like, ParameterType::STRING)
                    ->bind(':f3', $like0, ParameterType::STRING);
                $this->freie = $db->setQuery($q2, 0, 50)->loadObjectList() ?: [];
            }
        }

        parent::display($tpl);
    }

    /** URL innerhalb des Vorstandsbereichs (behält den Menüeintrag). */
    public function url(string $zusatz = ''): string
    {
        return Route::_('index.php?option=com_mitgliedsfoto&view=vorstand' . $zusatz . ($this->itemid ? '&Itemid=' . $this->itemid : ''), false);
    }
}
