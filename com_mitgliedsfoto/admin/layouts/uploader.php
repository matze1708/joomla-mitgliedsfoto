<?php

/**
 * Gemeinsame Foto-Oberfläche (Frontend + Backend): Kamera/Upload, Zuschnitt 3:4, Vorschau, Einwilligung.
 *
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 *
 * @var array $displayData  url, felder (name=>wert), binIch (bool), einwilligung (HTML), absendenText
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$d = $displayData;

foreach (['JS_KEIN_BILD', 'JS_ZU_KLEIN', 'JS_FEHLER', 'JS_SENDET', 'JS_LADEN'] as $k) {
    Text::script('COM_MITGLIEDSFOTO_' . $k);
}

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('com_mitgliedsfoto.uploader')->useStyle('com_mitgliedsfoto.uploader.css');
?>
<div class="mf-uploader" data-url="<?php echo htmlspecialchars($d['url'], ENT_QUOTES); ?>"
     data-breite="600" data-hoehe="800"
     data-min-breite="<?php echo (int) $d['minBreite']; ?>" data-min-hoehe="<?php echo (int) $d['minHoehe']; ?>">
    <div class="mf-alert alert alert-danger d-none" role="alert" aria-live="assertive"></div>

    <form method="post" action="<?php echo htmlspecialchars($d['url'], ENT_QUOTES); ?>" class="mf-form">
        <?php foreach ($d['felder'] as $name => $wert) : ?>
            <input type="hidden" name="<?php echo htmlspecialchars($name, ENT_QUOTES); ?>" value="<?php echo htmlspecialchars((string) $wert, ENT_QUOTES); ?>">
        <?php endforeach; ?>
        <?php echo HTMLHelper::_('form.token'); ?>

        <?php echo $d['zusatz'] ?? ''; // vom Aufrufer erzeugtes, bereits escaptes HTML (Eingabefelder) ?>

        <?php if (!empty($d['binIch'])) : ?>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="mf-binich" name="bin_ich" value="1" required>
                <label class="form-check-label" for="mf-binich"><strong><?php echo Text::_('COM_MITGLIEDSFOTO_BIN_ICH'); ?></strong></label>
            </div>
        <?php endif; ?>

        <fieldset class="mb-3">
            <legend class="fs-5"><?php echo Text::_('COM_MITGLIEDSFOTO_SCHRITT_FOTO'); ?></legend>
            <p class="text-muted small"><?php echo Text::_('COM_MITGLIEDSFOTO_FOTO_HINWEIS'); ?></p>
            <div class="d-flex flex-wrap gap-2">
                <label class="btn btn-primary mb-0">
                    <?php echo Text::_('COM_MITGLIEDSFOTO_KAMERA'); ?>
                    <input type="file" class="mf-file-kamera visually-hidden" accept="image/*" capture="user">
                </label>
                <label class="btn btn-outline-primary mf-btn-datei mb-0">
                    <?php echo Text::_('COM_MITGLIEDSFOTO_DATEI'); ?>
                    <input type="file" class="mf-file-datei visually-hidden" accept="image/jpeg,image/png,image/webp,image/*">
                </label>
            </div>
        </fieldset>

        <div class="mf-stage d-none mb-3">
            <div class="row g-3 align-items-start">
                <div class="col-12 col-md-8">
                    <div class="mf-crop"><img class="mf-bild" alt="<?php echo Text::_('COM_MITGLIEDSFOTO_ZUSCHNITT'); ?>"></div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-mf="links"><?php echo Text::_('COM_MITGLIEDSFOTO_DREHEN_LINKS'); ?></button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-mf="rechts"><?php echo Text::_('COM_MITGLIEDSFOTO_DREHEN_RECHTS'); ?></button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-mf="zurueck"><?php echo Text::_('COM_MITGLIEDSFOTO_ZURUECKSETZEN_ZUSCHNITT'); ?></button>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="small fw-bold mb-1"><?php echo Text::_('COM_MITGLIEDSFOTO_VORSCHAU'); ?></div>
                    <div class="mf-vorschau"></div>
                    <p class="text-muted small mt-2"><?php echo Text::_('COM_MITGLIEDSFOTO_VORSCHAU_HINWEIS'); ?></p>
                </div>
            </div>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="mf-einwilligung" name="einwilligung" value="1" required>
            <label class="form-check-label" for="mf-einwilligung"><?php echo $d['einwilligung']; ?></label>
        </div>

        <button type="submit" class="mf-absenden btn btn-success btn-lg" disabled>
            <?php echo htmlspecialchars($d['absendenText'], ENT_QUOTES); ?>
        </button>
    </form>

    <div class="mf-fertig d-none alert alert-success" role="status" aria-live="polite"></div>
</div>
