<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 *
 * @var \SvAlzey\Component\Mitgliedsfoto\Site\View\Vorstand\HtmlView $this
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$badge = static function (?string $status, $geprueft = 1) use ($e) {
    $status = $status ?: 'offen';
    $farbe  = ['eingereicht' => 'bg-info', 'vorstand' => 'bg-primary'][$status] ?? 'bg-secondary';
    $html   = '<span class="badge ' . $farbe . '">' . $e(Text::_('COM_MITGLIEDSFOTO_STATUS_' . strtoupper($status))) . '</span>';

    if ($geprueft !== null && !(int) $geprueft) {
        $html .= ' <span class="badge bg-warning text-dark">' . $e(Text::_('COM_MITGLIEDSFOTO_UNBESTAETIGT')) . '</span>';
    }

    return $html;
};
?>
<div class="com-mitgliedsfoto mf-vorstand">
    <h1><?php echo Text::_('COM_MITGLIEDSFOTO_VORSTAND_TITEL'); ?></h1>

    <?php if (!$this->konfiguriert) : ?>
        <div class="alert alert-warning"><?php echo Text::_('COM_MITGLIEDSFOTO_ERR_NICHT_KONFIGURIERT'); ?></div>
    <?php else : ?>
        <p>
            <a class="btn btn-outline-primary mf-btn-datei" href="<?php echo $e($this->url('&layout=frei')); ?>"><?php echo Text::_('COM_MITGLIEDSFOTO_FREI_TITEL'); ?></a>
        </p>

        <form method="get" action="<?php echo $e($this->url()); ?>" class="mb-3" style="max-width:32rem" role="search">
            <input type="hidden" name="option" value="com_mitgliedsfoto">
            <input type="hidden" name="view" value="vorstand">
            <?php if ($this->itemid) : ?><input type="hidden" name="Itemid" value="<?php echo (int) $this->itemid; ?>"><?php endif; ?>
            <label class="form-label" for="mf-suche"><?php echo Text::_('COM_MITGLIEDSFOTO_SUCHE'); ?></label>
            <div class="input-group">
                <input class="form-control" id="mf-suche" name="suche" type="search" value="<?php echo $e($this->suche); ?>"
                       placeholder="<?php echo $e(Text::_('COM_MITGLIEDSFOTO_SUCHE_HINT')); ?>" minlength="2" maxlength="100" autocomplete="off">
                <button class="btn btn-primary" type="submit"><?php echo Text::_('COM_MITGLIEDSFOTO_SUCHEN'); ?></button>
            </div>
        </form>

        <?php if ($this->suche !== '' && mb_strlen($this->suche) < 2) : ?>
            <div class="alert alert-info"><?php echo Text::_('COM_MITGLIEDSFOTO_SUCHE_MIN'); ?></div>
        <?php elseif ($this->suche !== '' && !$this->treffer && !$this->freie) : ?>
            <div class="alert alert-info"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></div>
        <?php endif; ?>

        <?php if ($this->treffer || $this->freie) : ?>
            <ul class="list-group mb-3">
                <?php foreach ($this->treffer as $m) : ?>
                    <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <strong><?php echo $e($m->nachname . ', ' . $m->vorname); ?></strong>
                            <div class="small text-muted"><?php echo Text::_('COM_MITGLIEDSFOTO_MITGLIEDSNR'); ?> <?php echo $e($m->nr); ?></div>
                            <?php echo $badge($m->status, $m->status ? $m->geprueft : null); ?>
                        </div>
                        <?php if (!$m->status) : ?>
                            <a class="btn btn-primary" href="<?php echo $e($this->url('&layout=erfassen&nr=' . rawurlencode($m->nr))); ?>"><?php echo Text::_('COM_MITGLIEDSFOTO_FOTO_ERFASSEN'); ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php foreach ($this->freie as $f) : ?>
                    <li class="list-group-item">
                        <strong><?php echo $e($f->nachname . ', ' . $f->vorname); ?></strong>
                        <span class="small text-muted">(<?php echo Text::_('COM_MITGLIEDSFOTO_OHNE_JOOMLA'); ?>)</span>
                        <div class="small text-muted"><?php echo Text::_('COM_MITGLIEDSFOTO_MITGLIEDSNR'); ?> <?php echo $e($f->mitgliedsnr); ?></div>
                        <?php echo $badge($f->status, $f->geprueft); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (\count($this->treffer) >= 50) : ?>
                <p class="small text-muted"><?php echo Text::_('COM_MITGLIEDSFOTO_SUCHE_MEHR'); ?></p>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
