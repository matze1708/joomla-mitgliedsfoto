<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailerFactoryInterface;

/**
 * Mailversand über den Joomla-Mailer. Betreff und Body sind maschinenlesbar (Power Automate).
 */
final class Mail
{
    /** @throws  FotoException */
    public static function senden(object $mitglied, string $quelle, string $dateiname): void
    {
        $empfaenger = trim((string) Einstellungen::get('mail_empfaenger'));
        $pfad       = Speicher::pfad($dateiname);

        if ($empfaenger === '' || !filter_var($empfaenger, FILTER_VALIDATE_EMAIL)) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_MAIL_EMPFAENGER');
        }

        if (!is_file($pfad)) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_DATEI_FEHLT');
        }

        $body = implode("\n", [
            'Mitgliedsnummer: ' . self::zeile($mitglied->nr),
            'Vorname: ' . self::zeile($mitglied->vorname),
            'Nachname: ' . self::zeile($mitglied->nachname),
            'Quelle: ' . ($quelle === 'vorstand' ? 'vorstand' : 'web'),
        ]) . "\n";

        try {
            $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
            $mailer->isHtml(false);
            $mailer->addRecipient($empfaenger);
            $mailer->setSubject('FOTO|' . self::zeile($mitglied->nr));
            $mailer->setBody($body);
            $mailer->addAttachment($pfad, $dateiname, 'base64', 'image/jpeg');
            $ergebnis = $mailer->Send();
        } catch (\Throwable $e) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_MAIL');
        }

        if ($ergebnis !== true) {
            throw new FotoException('COM_MITGLIEDSFOTO_ERR_MAIL');
        }
    }

    /** Einzeilig, ohne Steuerzeichen (kein Header-/Body-Injection, Format bleibt parsebar). */
    private static function zeile(?string $wert): string
    {
        return trim((string) preg_replace('/[\r\n\p{Cc}|]+/u', ' ', (string) $wert));
    }
}
