<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Eingabe;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Einreichung;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Einstellungen;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\FotoException;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Fotos;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Mitglieder;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Speicher;

/**
 * Aktionen im Backend. Zugriff ist bereits im Dispatcher auf "mitgliedsfoto.verwalten" beschränkt.
 */
class FotoController extends BaseController
{
    private const LISTE = 'index.php?option=com_mitgliedsfoto&view=mitglieder';

    /** Vorstandserfassung (JSON, per fetch). */
    public function erfassen(): void
    {
        $input = $this->app->getInput();

        try {
            if (!Session::checkToken('post')) {
                throw new FotoException('JINVALID_TOKEN_NOTICE');
            }

            $mitglied = Mitglieder::finden(trim($input->post->getString('nr', '')));

            if (!$mitglied) {
                throw new FotoException('COM_MITGLIEDSFOTO_ERR_UNBEKANNT');
            }

            if (!$input->post->getInt('einwilligung')) {
                throw new FotoException('COM_MITGLIEDSFOTO_ERR_BESTAETIGUNG');
            }

            $datei = Einreichung::uploadPfad($input->files->get('foto', null, 'raw'));

            $mailOk = Einreichung::speichern($mitglied, $datei, 'vorstand', (int) $this->app->getIdentity()->id);

            $this->app->enqueueMessage(Text::sprintf('COM_MITGLIEDSFOTO_OK_ERFASST', $mitglied->vorname . ' ' . $mitglied->nachname), 'success');

            if (!$mailOk) {
                $this->app->enqueueMessage(Text::_('COM_MITGLIEDSFOTO_WARN_MAIL') . ' ' . Einreichung::$mailFehler, 'warning');
            }

            $this->json(true, '', \Joomla\CMS\Router\Route::_(self::LISTE, false));
        } catch (FotoException $e) {
            $this->json(false, Text::_($e->getMessage()));
        } catch (\Throwable $e) {
            $this->json(false, Text::_('COM_MITGLIEDSFOTO_ERR_ALLGEMEIN'));
        }
    }

    /** Vorstandserfassung ohne Joomla-Eintrag (JSON, per fetch). */
    public function erfassenFrei(): void
    {
        $input = $this->app->getInput()->post;

        try {
            if (!Session::checkToken('post')) {
                throw new FotoException('JINVALID_TOKEN_NOTICE');
            }

            $nr       = trim($input->getString('mitgliedsnr', ''));
            $vorname  = Eingabe::name(mb_substr($input->getString('vorname', ''), 0, 120));
            $nachname = Eingabe::name(mb_substr($input->getString('nachname', ''), 0, 120));
            $gebText  = trim($input->getString('geburtsdatum', ''));
            $datum    = $gebText === '' ? null : Eingabe::geburtsdatum($gebText);

            if (!Mitglieder::nrGueltig($nr) || $vorname === null || $nachname === null || ($gebText !== '' && $datum === null)) {
                throw new FotoException('COM_MITGLIEDSFOTO_ERR_EINGABE');
            }

            if (Mitglieder::finden($nr)) {
                throw new FotoException('COM_MITGLIEDSFOTO_ERR_IN_JOOMLA');
            }

            if (!$input->getInt('einwilligung')) {
                throw new FotoException('COM_MITGLIEDSFOTO_ERR_BESTAETIGUNG');
            }

            $datei    = Einreichung::uploadPfad($this->app->getInput()->files->get('foto', null, 'raw'));
            $mitglied = (object) ['nr' => $nr, 'vorname' => $vorname, 'nachname' => $nachname, 'geburtsdatum' => $datum, 'geprueft' => 1];
            $mailOk   = Einreichung::speichern($mitglied, $datei, 'vorstand', (int) $this->app->getIdentity()->id, true);

            $this->app->enqueueMessage(Text::sprintf('COM_MITGLIEDSFOTO_OK_ERFASST', $vorname . ' ' . $nachname), 'success');

            if (!$mailOk) {
                $this->app->enqueueMessage(Text::_('COM_MITGLIEDSFOTO_WARN_MAIL') . ' ' . Einreichung::$mailFehler, 'warning');
            }

            $this->json(true, '', \Joomla\CMS\Router\Route::_(self::LISTE, false));
        } catch (FotoException $e) {
            $this->json(false, Text::_($e->getMessage()));
        } catch (\Throwable $e) {
            $this->json(false, Text::_('COM_MITGLIEDSFOTO_ERR_ALLGEMEIN'));
        }
    }

    /** Unbestätigte Einträge (QR-Code ohne Joomla-Eintrag) nach Prüfung freigeben. */
    public function bestaetigen(): void
    {
        $this->checkToken();
        $n = 0;

        foreach ($this->auswahl() as $nr) {
            $foto = Fotos::finden($nr);

            if ($foto && !(int) $foto->geprueft) {
                Fotos::bestaetigen($nr);
                $n++;
            }
        }

        $this->setRedirect(self::LISTE, Text::plural('COM_MITGLIEDSFOTO_N_BESTAETIGT', $n));
    }

    /** Foto ausliefern (nur mit Backend-Berechtigung; Ordner selbst ist nicht öffentlich). */
    public function bild(): void
    {
        $foto = Fotos::finden(trim($this->app->getInput()->getString('nr', '')));
        $pfad = $foto && $foto->dateiname ? Speicher::pfad($foto->dateiname) : '';

        if ($pfad === '' || !is_file($pfad)) {
            $this->app->setHeader('status', '404', true);
            $this->app->sendHeaders();
            $this->app->close();
        }

        $this->app->setHeader('Content-Type', 'image/jpeg', true);
        $this->app->setHeader('Content-Length', (string) filesize($pfad), true);
        $this->app->setHeader('Cache-Control', 'private, no-store', true);
        $this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $this->app->setHeader('Content-Security-Policy', "default-src 'none'; sandbox", true);
        $this->app->setHeader('Content-Disposition', 'inline; filename="' . basename($foto->dateiname) . '"', true);
        $this->app->sendHeaders();
        readfile($pfad);
        $this->app->close();
    }

    /** Zeile und Datei entfernen (z. B. falsches Foto); Mitglied ist danach wieder "offen". */
    public function zuruecksetzen(): void
    {
        $this->checkToken();
        $n = 0;

        foreach ($this->auswahl() as $nr) {
            if ($foto = Fotos::finden($nr)) {
                Speicher::loeschen($foto->dateiname);
                Fotos::entfernen($nr);
                $n++;
            }
        }

        $this->setRedirect(self::LISTE, Text::plural('COM_MITGLIEDSFOTO_N_ZURUECKGESETZT', $n));
    }

    /** Datei löschen (Datenschutz nach Ausweiserstellung); Zeile bleibt mit Löschzeitpunkt. */
    public function loeschen(): void
    {
        $this->checkToken();
        $n = 0;

        foreach ($this->auswahl() as $nr) {
            $foto = Fotos::finden($nr);

            if ($foto && !$foto->geloescht_am) {
                Speicher::loeschen($foto->dateiname);
                Fotos::alsGeloeschtMarkieren($nr);
                $n++;
            }
        }

        $this->setRedirect(self::LISTE, Text::plural('COM_MITGLIEDSFOTO_N_GELOESCHT', $n));
    }

    public function mailsenden(): void
    {
        $this->checkToken();
        $ok = 0;
        $fehler = 0;

        foreach ($this->auswahl() as $nr) {
            $foto     = Fotos::finden($nr);
            $mitglied = Mitglieder::oderFrei($nr);

            if (!$foto || !$mitglied || !$foto->dateiname) {
                $fehler++;
                $this->app->enqueueMessage(Text::sprintf('COM_MITGLIEDSFOTO_MAIL_FEHLER_MITGLIED', $nr, Text::_('COM_MITGLIEDSFOTO_ERR_DATEI_FEHLT')), 'warning');
                continue;
            }

            if (Einreichung::mailSenden($mitglied, $foto->status === 'vorstand' ? 'vorstand' : 'web', $foto->dateiname)) {
                $ok++;
            } else {
                $fehler++;
                $this->app->enqueueMessage(Text::sprintf('COM_MITGLIEDSFOTO_MAIL_FEHLER_MITGLIED', $nr, Einreichung::$mailFehler), 'warning');
            }
        }

        $this->setRedirect(self::LISTE, $ok ? Text::plural('COM_MITGLIEDSFOTO_N_MAIL_GESENDET', $ok) : null);
    }

    public function export(): void
    {
        $this->csv(false);
    }

    public function exportOffene(): void
    {
        $this->csv(true);
    }

    /** CSV für den CleverReach-Import: Nr; Vorname; Nachname; E-Mail; Link */
    private function csv(bool $nurOffene): void
    {
        $this->checkToken();

        $datei = fopen('php://temp', 'w+');
        fwrite($datei, "\xEF\xBB\xBF");
        fputcsv($datei, ['Nr', 'Vorname', 'Nachname', 'E-Mail', 'Link'], ';');

        foreach (Mitglieder::alleMitStatus() as $m) {
            if (($nurOffene && $m->status !== null) || trim((string) $m->email) === '') {
                continue;
            }

            $nr = trim((string) $m->nr);
            fputcsv($datei, [$nr, $this->csvSicher($m->vorname), $this->csvSicher($m->nachname), trim((string) $m->email), Einstellungen::linkFuer($nr)], ';');
        }

        rewind($datei);
        $inhalt = stream_get_contents($datei);
        fclose($datei);

        $name = 'mitgliedsfoto-links' . ($nurOffene ? '-offen' : '') . '-' . date('Y-m-d') . '.csv';
        $this->app->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
        $this->app->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"', true);
        $this->app->setHeader('Cache-Control', 'private, no-store', true);
        $this->app->sendHeaders();
        echo $inhalt;
        $this->app->close();
    }

    /** Schutz vor Formel-Injection beim Öffnen in Excel. */
    private function csvSicher(?string $wert): string
    {
        $wert = trim((string) $wert);

        return $wert !== '' && str_contains('=+-@', $wert[0]) ? "'" . $wert : $wert;
    }

    /** @return  string[] */
    private function auswahl(): array
    {
        $cid = (array) $this->app->getInput()->post->get('cid', [], 'array');

        return array_values(array_unique(array_filter(array_map(static fn ($v) => trim((string) $v), $cid), static fn ($v) => $v !== '')));
    }

    private function json(bool $ok, string $nachricht, string $weiter = ''): void
    {
        // Meldungen für die nächste Seite (nach dem Weiterleiten im Browser) in der Session ablegen.
        if ($weiter !== '' && \count($this->app->getMessageQueue())) {
            $this->app->getSession()->set('application.queue', $this->app->getMessageQueue());
        }

        $this->app->allowCache(false);
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->sendHeaders();
        echo json_encode(['ok' => $ok, 'message' => $nachricht, 'redirect' => $weiter], JSON_UNESCAPED_UNICODE);
        $this->app->close();
    }
}
