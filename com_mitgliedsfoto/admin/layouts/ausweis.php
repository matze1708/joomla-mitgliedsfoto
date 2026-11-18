<?php

/**
 * Vorder- und Rückseite des Mitgliedsausweises (Design: Tomoschat Design für den Schützenverein Volker Alzey 1952 e.V.).
 * Auf der Rückseite optional Name/Nummer/Eintritt und ein Fotofeld, in das die Live-Vorschau des Zuschnitts läuft.
 *
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 *
 * @var array $displayData  daten: ?array{vorname,nachname,nr,seit}, fotofeld: bool, klein: bool
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$daten    = $displayData['daten'] ?? null;
$fotofeld = !empty($displayData['fotofeld']);
$e        = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$bild     = static fn ($name) => HTMLHelper::_('image', 'com_mitgliedsfoto/ausweis-' . $name . '.webp', '', [], true, 1);

Factory::getApplication()->getDocument()->getWebAssetManager()->useStyle('com_mitgliedsfoto.uploader.css');
?>
<div class="mf-ausweis<?php echo !empty($displayData['klein']) ? ' mf-ausweis-klein' : ''; ?>">
    <figure class="mf-karte">
        <img src="<?php echo $e($bild('vorne')); ?>" alt="<?php echo $e(Text::_('COM_MITGLIEDSFOTO_AUSWEIS_VORNE')); ?>" width="638" height="1016" loading="lazy">
        <figcaption class="visually-hidden"><?php echo Text::_('COM_MITGLIEDSFOTO_AUSWEIS_VORNE'); ?></figcaption>
    </figure>
    <figure class="mf-karte mf-karte-hinten">
        <img src="<?php echo $e($bild('hinten')); ?>" alt="<?php echo $e(Text::_('COM_MITGLIEDSFOTO_AUSWEIS_HINTEN')); ?>" width="638" height="1016" loading="lazy">
        <?php if ($fotofeld) : ?>
            <div class="mf-k-fotofeld" aria-hidden="true"><div class="mf-k-foto"><span><?php echo Text::_('COM_MITGLIEDSFOTO_AUSWEIS_DEIN_FOTO'); ?></span></div></div>
        <?php endif; ?>
        <?php if ($daten !== null) : ?>
            <div class="mf-k-text mf-k-vorname" data-mf-feld="vorname"><?php echo $e($daten['vorname'] ?? ''); ?></div>
            <div class="mf-k-text mf-k-nachname" data-mf-feld="nachname"><?php echo $e($daten['nachname'] ?? ''); ?></div>
            <div class="mf-k-text mf-k-nr" data-mf-feld="mitgliedsnr"><?php echo $e($daten['nr'] ?? ''); ?></div>
            <div class="mf-k-text mf-k-seit"><?php echo $e($daten['seit'] ?? ''); ?></div>
        <?php endif; ?>
        <figcaption class="visually-hidden"><?php echo Text::_('COM_MITGLIEDSFOTO_AUSWEIS_HINTEN'); ?></figcaption>
    </figure>
</div>
