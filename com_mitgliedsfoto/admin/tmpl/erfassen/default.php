<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 *
 * @var \SvAlzey\Component\Mitgliedsfoto\Administrator\View\Erfassen\HtmlView $this
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Einstellungen;

$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<?php if ($this->bereits) : ?>
    <div class="alert alert-info"><?php echo Text::_('COM_MITGLIEDSFOTO_BEREITS_ADMIN'); ?></div>
<?php else : ?>
    <p class="lead"><?php echo Text::sprintf('COM_MITGLIEDSFOTO_ERFASSEN_FUER', $e($this->mitglied->vorname . ' ' . $this->mitglied->nachname), $e($this->mitglied->nr)); ?></p>
    <?php echo LayoutHelper::render('uploader', [
        'url'          => Route::_('index.php?option=com_mitgliedsfoto&task=foto.erfassen', false),
        'felder'       => ['nr' => $this->mitglied->nr],
        'binIch'       => false,
        'einwilligung' => Text::_('COM_MITGLIEDSFOTO_EINWILLIGUNG_VORSTAND'),
        'absendenText' => Text::_('COM_MITGLIEDSFOTO_SPEICHERN'),
        'minBreite'    => (int) Einstellungen::get('min_breite', 360),
        'minHoehe'     => (int) Einstellungen::get('min_hoehe', 480),
    ], JPATH_ADMINISTRATOR . '/components/com_mitgliedsfoto/layouts'); ?>
<?php endif; ?>
