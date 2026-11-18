<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

/**
 * Vorstandserfassung – gemeinsam für Backend und Frontend-Vorstandsbereich.
 * Die Zugriffsprüfung (mitgliedsfoto.verwalten) erfolgt im jeweiligen Aufrufer.
 */
final class Vorstand
{
    public static function darf(CMSApplicationInterface $app): bool
    {
        $user = $app->getIdentity();

        return $user && !$user->guest && $user->authorise('mitgliedsfoto.verwalten', 'com_mitgliedsfoto');
    }

    /**
     * Foto für ein Mitglied speichern.
     *
     * @param   bool    $ohneJoomla  true = Nummer/Name aus dem Formular (Mitglied ohne Joomla-Eintrag)
     * @param   string  $weiter      Ziel-URL nach Erfolg
     *
     * @return  array{ok: bool, message: string, redirect: string}
     */
    public static function erfassen(CMSApplicationInterface $app, bool $ohneJoomla, string $weiter): array
    {
        $post = $app->getInput()->post;

        try {
            if (!Session::checkToken('post')) {
                throw new FotoException('JINVALID_TOKEN_NOTICE');
            }

            if (!self::darf($app)) {
                throw new FotoException('JERROR_ALERTNOAUTHOR');
            }

            if ($ohneJoomla) {
                $nr       = trim($post->getString('mitgliedsnr', ''));
                $vorname  = Eingabe::name(mb_substr($post->getString('vorname', ''), 0, 120));
                $nachname = Eingabe::name(mb_substr($post->getString('nachname', ''), 0, 120));
                $gebText  = trim($post->getString('geburtsdatum', ''));
                $datum    = $gebText === '' ? null : Eingabe::geburtsdatum($gebText);

                if (!Mitglieder::nrGueltig($nr) || $vorname === null || $nachname === null || ($gebText !== '' && $datum === null)) {
                    throw new FotoException('COM_MITGLIEDSFOTO_ERR_EINGABE');
                }

                // Manuelle Eingabe: führende Nullen ignorieren.
                if (Mitglieder::finden($nr, true)) {
                    throw new FotoException('COM_MITGLIEDSFOTO_ERR_IN_JOOMLA');
                }

                if (Fotos::finden($nr, true)) {
                    throw new FotoException('COM_MITGLIEDSFOTO_ERR_BEREITS');
                }

                $mitglied = (object) ['nr' => $nr, 'vorname' => $vorname, 'nachname' => $nachname, 'geburtsdatum' => $datum, 'geprueft' => 1];
            } else {
                $mitglied = Mitglieder::finden(trim($post->getString('nr', '')));

                if (!$mitglied) {
                    throw new FotoException('COM_MITGLIEDSFOTO_ERR_UNBEKANNT');
                }
            }

            if (!$post->getInt('einwilligung')) {
                throw new FotoException('COM_MITGLIEDSFOTO_ERR_BESTAETIGUNG');
            }

            $datei  = Einreichung::uploadPfad($app->getInput()->files->get('foto', null, 'raw'));
            $mailOk = Einreichung::speichern($mitglied, $datei, 'vorstand', (int) $app->getIdentity()->id, $ohneJoomla);

            $app->enqueueMessage(Text::sprintf('COM_MITGLIEDSFOTO_OK_ERFASST', $mitglied->vorname . ' ' . $mitglied->nachname), 'success');

            if (!$mailOk) {
                $app->enqueueMessage(Text::_('COM_MITGLIEDSFOTO_WARN_MAIL') . ' ' . Einreichung::$mailFehler, 'warning');
            }

            // Meldungen für die nächste Seite (Weiterleitung im Browser) in der Session ablegen.
            $app->getSession()->set('application.queue', $app->getMessageQueue());

            return ['ok' => true, 'message' => '', 'redirect' => $weiter];
        } catch (FotoException $e) {
            return ['ok' => false, 'message' => Text::_($e->getMessage()), 'redirect' => ''];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => Text::_('COM_MITGLIEDSFOTO_ERR_ALLGEMEIN'), 'redirect' => ''];
        }
    }

    /** JSON-Antwort senden und Anfrage beenden. */
    public static function json(CMSApplicationInterface $app, array $antwort): void
    {
        $app->allowCache(false);
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->sendHeaders();
        echo json_encode($antwort, JSON_UNESCAPED_UNICODE);
        $app->close();
    }
}
