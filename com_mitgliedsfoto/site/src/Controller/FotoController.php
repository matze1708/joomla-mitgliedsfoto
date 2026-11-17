<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Begrenzung;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Einreichung;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Einstellungen;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\FotoException;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Mitglieder;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Token;

class FotoController extends BaseController
{
    /**
     * Aushang-Weg: Mitgliedsnummer + Geburtsdatum. Bei Erfolg Weiterleitung auf den persönlichen Link.
     * Immer dieselbe generische Fehlermeldung (keine Auskunft, ob die Nummer existiert).
     */
    public function ident(): void
    {
        $this->checkToken('post');

        $seite = Einstellungen::seitenUrl();

        if (!Mitglieder::hatGeburtsdatum()) {
            $this->setRedirect($seite, Text::_('COM_MITGLIEDSFOTO_ERR_IDENT'), 'error');

            return;
        }

        if (Begrenzung::blockiert()) {
            $this->setRedirect($seite, Text::_('COM_MITGLIEDSFOTO_ERR_ZU_VIELE_VERSUCHE'), 'error');

            return;
        }

        $input   = $this->app->getInput();
        $nr      = trim($input->post->getString('mitgliedsnr', ''));
        $datum   = mb_substr(trim($input->post->getString('geburtsdatum', '')), 0, 10);
        $mitglied = Mitglieder::finden($nr);

        if (!$mitglied || !Mitglieder::geburtsdatumPasst($mitglied, $datum)) {
            Begrenzung::fehlversuch();
            $this->setRedirect($seite, Text::_('COM_MITGLIEDSFOTO_ERR_IDENT'), 'error');

            return;
        }

        $this->setRedirect(Einstellungen::linkFuer($mitglied->nr));
    }

    /** Foto absenden (JSON-Antwort, per fetch). */
    public function absenden(): void
    {
        $input = $this->app->getInput();

        try {
            if (!Session::checkToken('post')) {
                throw new FotoException('JINVALID_TOKEN_NOTICE');
            }

            if (Begrenzung::blockiert()) {
                throw new FotoException('COM_MITGLIEDSFOTO_ERR_ZU_VIELE_VERSUCHE');
            }

            $mitglied = Mitglieder::finden(trim($input->post->getString('m', '')));

            // Token erneut serverseitig prüfen; generisch bei Fehler.
            if (!$mitglied || !Token::pruefen($mitglied->nr, $input->post->getString('t', ''), Einstellungen::schluessel())) {
                Begrenzung::fehlversuch();
                throw new FotoException('COM_MITGLIEDSFOTO_ERR_LINK');
            }

            if (!$input->post->getInt('bin_ich') || !$input->post->getInt('einwilligung')) {
                throw new FotoException('COM_MITGLIEDSFOTO_ERR_BESTAETIGUNG');
            }

            $datei = Einreichung::uploadPfad($input->files->get('foto', null, 'raw'));

            Einreichung::speichern($mitglied, $datei, 'web', null);

            $this->antwort(true, Text::_('COM_MITGLIEDSFOTO_OK_EINGEREICHT'));
        } catch (FotoException $e) {
            $this->antwort(false, Text::_($e->getMessage()));
        } catch (\Throwable $e) {
            $this->antwort(false, Text::_('COM_MITGLIEDSFOTO_ERR_ALLGEMEIN'));
        }
    }

    private function antwort(bool $ok, string $nachricht): void
    {
        $this->app->allowCache(false);
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->sendHeaders();
        echo json_encode(['ok' => $ok, 'message' => $nachricht], JSON_UNESCAPED_UNICODE);
        $this->app->close();
    }
}
