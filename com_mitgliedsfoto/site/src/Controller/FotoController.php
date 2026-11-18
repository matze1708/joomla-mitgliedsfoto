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
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Eingabe;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Fotos;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Einreichung;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Einstellungen;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\FotoException;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Mitglieder;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Token;

class FotoController extends BaseController
{
    /** Session-Schlüssel für Eingaben ohne Joomla-Eintrag (QR-Code, unbestätigt). */
    public const SESSION_FREI = 'com_mitgliedsfoto.frei';

    /**
     * Aushang-Weg (QR-Code): Mitgliedsnummer, Vorname, Nachname, Geburtsdatum.
     *
     * - Nummer in Joomla bekannt: Name (und Geburtsdatum, falls in Joomla hinterlegt) müssen passen,
     *   dann Weiterleitung auf den persönlichen Link. Sonst generische Meldung + Fehlversuch.
     * - Nummer unbekannt: Eingaben werden (geprüft auf Format) in der Session gemerkt; das Foto wird
     *   als „unbestätigt“ angenommen und vom Vorstand kontrolliert.
     */
    public function ident(): void
    {
        $this->checkToken('post');

        $seite = Einstellungen::seitenUrl();

        if (Begrenzung::blockiert()) {
            $this->setRedirect($seite, Text::_('COM_MITGLIEDSFOTO_ERR_ZU_VIELE_VERSUCHE'), 'error');

            return;
        }

        $input   = $this->app->getInput()->post;
        $nr      = trim($input->getString('mitgliedsnr', ''));
        $vorname = Eingabe::name(mb_substr($input->getString('vorname', ''), 0, 120));
        $nachname = Eingabe::name(mb_substr($input->getString('nachname', ''), 0, 120));
        $datum   = Eingabe::geburtsdatum($input->getString('geburtsdatum', ''));

        if (!Mitglieder::nrGueltig($nr) || $vorname === null || $nachname === null || $datum === null) {
            Begrenzung::fehlversuch();
            $this->setRedirect($seite, Text::_('COM_MITGLIEDSFOTO_ERR_EINGABE'), 'error');

            return;
        }

        $mitglied = Mitglieder::finden($nr, true);

        if ($mitglied) {
            $hatDatum  = trim((string) ($mitglied->geburtsdatum ?? '')) !== '';
            $namePasst = Eingabe::namenGleich($vorname, $nachname, (string) $mitglied->vorname, (string) $mitglied->nachname);

            if (!$namePasst || ($hatDatum && !Mitglieder::geburtsdatumPasst($mitglied, $datum))) {
                Begrenzung::fehlversuch();
                $this->setRedirect($seite, Text::_('COM_MITGLIEDSFOTO_ERR_IDENT'), 'error');

                return;
            }

            $this->setRedirect(Einstellungen::linkFuer($mitglied->nr));

            return;
        }

        if (Fotos::finden($nr, true)) {
            Begrenzung::fehlversuch();
            $this->setRedirect($seite, Text::_('COM_MITGLIEDSFOTO_BEREITS'), 'notice');

            return;
        }

        // Unbekannte Nummer: zählt ebenfalls zum Rate-Limit (bremst massenhafte Fremd-Einträge).
        Begrenzung::fehlversuch();
        $this->app->getSession()->set(self::SESSION_FREI, [
            'nr' => $nr, 'vorname' => $vorname, 'nachname' => $nachname, 'geburtsdatum' => $datum, 'zeit' => time(),
        ]);

        $this->setRedirect($seite . (str_contains($seite, '?') ? '&' : '?') . 'frei=1');
    }

    /** Eingaben ohne Joomla-Eintrag aus der Session (max. 1 Stunde alt) oder null. */
    public static function freieDaten(\Joomla\CMS\Application\CMSApplicationInterface $app): ?array
    {
        $daten = $app->getSession()->get(self::SESSION_FREI);

        if (!\is_array($daten) || (int) ($daten['zeit'] ?? 0) < time() - 3600 || !Mitglieder::nrGueltig((string) ($daten['nr'] ?? ''))) {
            return null;
        }

        return $daten;
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

            $ohneJoomla = $input->post->getInt('frei') === 1;

            if ($ohneJoomla) {
                // Daten stammen aus der Session (QR-Formular), nicht aus diesem Request.
                $daten = self::freieDaten($this->app);

                if (!$daten || Mitglieder::finden($daten['nr'], true) || Fotos::finden($daten['nr'], true)) {
                    throw new FotoException('COM_MITGLIEDSFOTO_ERR_SITZUNG');
                }

                $mitglied = (object) [
                    'nr' => $daten['nr'], 'vorname' => $daten['vorname'], 'nachname' => $daten['nachname'],
                    'geburtsdatum' => $daten['geburtsdatum'], 'geprueft' => 0,
                ];
            } else {
                $mitglied = Mitglieder::finden(trim($input->post->getString('m', '')));

                // Token erneut serverseitig prüfen; generisch bei Fehler.
                if (!$mitglied || !Token::pruefen($mitglied->nr, $input->post->getString('t', ''), Einstellungen::schluessel())) {
                    Begrenzung::fehlversuch();
                    throw new FotoException('COM_MITGLIEDSFOTO_ERR_LINK');
                }
            }

            if (!$input->post->getInt('bin_ich') || !$input->post->getInt('einwilligung')) {
                throw new FotoException('COM_MITGLIEDSFOTO_ERR_BESTAETIGUNG');
            }

            $datei = Einreichung::uploadPfad($input->files->get('foto', null, 'raw'));

            Einreichung::speichern($mitglied, $datei, 'web', null, $ohneJoomla);

            if ($ohneJoomla) {
                $this->app->getSession()->remove(self::SESSION_FREI);
            }

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
