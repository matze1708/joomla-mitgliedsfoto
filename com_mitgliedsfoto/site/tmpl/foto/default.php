<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 *
 * @var \SvAlzey\Component\Mitgliedsfoto\Site\View\Foto\HtmlView $this
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Einstellungen;

$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<div class="com-mitgliedsfoto">
    <h1><?php echo Text::_('COM_MITGLIEDSFOTO_SEITENTITEL'); ?></h1>

    <?php if ($this->zustand === 'nicht_konfiguriert') : ?>
        <div class="alert alert-warning"><?php echo Text::_('COM_MITGLIEDSFOTO_ERR_NICHT_KONFIGURIERT'); ?></div>

    <?php elseif ($this->zustand === 'ungueltig') : ?>
        <div class="alert alert-danger"><?php echo Text::_('COM_MITGLIEDSFOTO_ERR_LINK'); ?></div>

    <?php elseif ($this->zustand === 'gesperrt') : ?>
        <div class="alert alert-danger"><?php echo Text::_('COM_MITGLIEDSFOTO_ERR_ZU_VIELE_VERSUCHE'); ?></div>

    <?php elseif ($this->zustand === 'bereits') : ?>
        <div class="alert alert-info"><?php echo Text::_('COM_MITGLIEDSFOTO_BEREITS'); ?></div>

    <?php elseif ($this->zustand === 'ident') : ?>
        <?php if ($this->geburtsdatumMoeglich) : ?>
            <p><?php echo Text::_('COM_MITGLIEDSFOTO_IDENT_INTRO'); ?></p>
            <form method="post" action="<?php echo $e($this->identUrl); ?>" class="mb-3" style="max-width:28rem">
                <div class="mb-3">
                    <label class="form-label" for="mf-nr"><?php echo Text::_('COM_MITGLIEDSFOTO_MITGLIEDSNR'); ?></label>
                    <input class="form-control" id="mf-nr" name="mitgliedsnr" type="text" inputmode="numeric" autocomplete="off" maxlength="50" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="mf-gb"><?php echo Text::_('COM_MITGLIEDSFOTO_GEBURTSDATUM'); ?></label>
                    <input class="form-control" id="mf-gb" name="geburtsdatum" type="text" inputmode="numeric" placeholder="TT.MM.JJJJ" autocomplete="off" maxlength="10" required>
                </div>
                <?php echo HTMLHelper::_('form.token'); ?>
                <button class="btn btn-primary" type="submit"><?php echo Text::_('COM_MITGLIEDSFOTO_WEITER'); ?></button>
            </form>
        <?php else : ?>
            <div class="alert alert-info"><?php echo Text::_('COM_MITGLIEDSFOTO_NUR_MIT_LINK'); ?></div>
        <?php endif; ?>

    <?php else : ?>
        <p class="lead">
            <?php echo Text::sprintf('COM_MITGLIEDSFOTO_HALLO', $e($this->mitglied->vorname . ' ' . $this->mitglied->nachname), $e($this->mitglied->nr)); ?>
        </p>
        <?php echo LayoutHelper::render('uploader', [
            'url'          => $this->submitUrl . (str_contains($this->submitUrl, '?') ? '&' : '?') . 'task=foto.absenden',
            'felder'       => ['m' => $this->mitglied->nr, 't' => $this->token],
            'binIch'       => true,
            'einwilligung' => $this->einwilligung,
            'absendenText' => Text::_('COM_MITGLIEDSFOTO_ABSENDEN'),
            'minBreite'    => (int) Einstellungen::get('min_breite', 360),
            'minHoehe'     => (int) Einstellungen::get('min_hoehe', 480),
        ], JPATH_ADMINISTRATOR . '/components/com_mitgliedsfoto/layouts'); ?>
    <?php endif; ?>
</div>
