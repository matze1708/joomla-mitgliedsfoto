<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 *
 * @var \SvAlzey\Component\Mitgliedsfoto\Administrator\View\Mitglieder\HtmlView $this
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$datum = static fn ($s) => $s ? HTMLHelper::_('date', $s, Text::_('DATE_FORMAT_LC4')) : '';
$zeit  = static fn ($s) => $s ? HTMLHelper::_('date', $s, Text::_('DATE_FORMAT_LC6')) : '';
$labels = ['eingereicht' => 'bg-info', 'vorstand' => 'bg-primary'];
?>
<form action="<?php echo Route::_('index.php?option=com_mitgliedsfoto&view=mitglieder'); ?>" method="post" name="adminForm" id="adminForm">
<?php if (!$this->konfiguriert) : ?>
    <div class="alert alert-warning">
        <?php echo Text::_('COM_MITGLIEDSFOTO_ERR_NICHT_KONFIGURIERT_ADMIN'); ?>
        <a href="<?php echo Route::_('index.php?option=com_config&view=component&component=com_mitgliedsfoto'); ?>"><?php echo Text::_('JOPTIONS'); ?></a>
    </div>
<?php else : ?>
    <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

    <?php if (empty($this->items)) : ?>
        <div class="alert alert-info"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></div>
    <?php else : ?>
        <table class="table table-striped align-middle" id="mitgliederList">
            <caption class="visually-hidden"><?php echo Text::_('COM_MITGLIEDSFOTO_MITGLIEDER_TITEL'); ?></caption>
            <thead>
                <tr>
                    <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
                    <th scope="col"><?php echo Text::_('COM_MITGLIEDSFOTO_NACHNAME'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_MITGLIEDSFOTO_VORNAME'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_MITGLIEDSFOTO_MITGLIEDSNR'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_MITGLIEDSFOTO_EINTRITT'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_MITGLIEDSFOTO_FOTO'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_MITGLIEDSFOTO_STATUS'); ?></th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($this->items as $i => $m) :
                $nrUrl = rawurlencode($m->nr);
                $status = $m->status ?: 'offen';
                ?>
                <tr>
                    <td class="text-center">
                        <?php if ($m->status) : ?><?php echo HTMLHelper::_('grid.id', $i, $m->nr); ?><?php endif; ?>
                    </td>
                    <th scope="row"><?php echo $e($m->nachname); ?></th>
                    <td><?php echo $e($m->vorname); ?></td>
                    <td><?php echo $e($m->nr); ?></td>
                    <td><?php echo $e($datum($m->eintritt)); ?></td>
                    <td>
                        <?php if ($m->status && $m->dateiname && !$m->geloescht_am) : ?>
                            <a href="<?php echo Route::_('index.php?option=com_mitgliedsfoto&task=foto.bild&nr=' . $nrUrl); ?>" target="_blank" rel="noopener">
                                <img src="<?php echo Route::_('index.php?option=com_mitgliedsfoto&task=foto.bild&nr=' . $nrUrl); ?>"
                                     alt="<?php echo $e($m->vorname . ' ' . $m->nachname); ?>" width="45" height="60" loading="lazy"
                                     style="object-fit:cover;border:1px solid #bbb">
                            </a>
                        <?php elseif ($m->geloescht_am) : ?>
                            <span class="text-muted small"><?php echo Text::sprintf('COM_MITGLIEDSFOTO_GELOESCHT_AM', $e($datum($m->geloescht_am))); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?php echo $labels[$status] ?? 'bg-secondary'; ?>"><?php echo Text::_('COM_MITGLIEDSFOTO_STATUS_' . strtoupper($status)); ?></span>
                        <?php if ($m->eingereicht_am) : ?><div class="small text-muted"><?php echo $e($zeit($m->eingereicht_am)); ?></div><?php endif; ?>
                        <?php if ($m->status && !$m->mail_gesendet_am && !$m->geloescht_am) : ?>
                            <div class="small text-danger"><?php echo Text::_('COM_MITGLIEDSFOTO_MAIL_NICHT_GESENDET'); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if (!$m->status) : ?>
                            <a class="btn btn-sm btn-primary" href="<?php echo Route::_('index.php?option=com_mitgliedsfoto&view=erfassen&nr=' . $nrUrl); ?>">
                                <?php echo Text::_('COM_MITGLIEDSFOTO_FOTO_ERFASSEN'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php echo $this->pagination->getListFooter(); ?>
    <?php endif; ?>
<?php endif; ?>
    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
