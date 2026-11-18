<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Einstellungen;

$feld = static function (string $name, string $label, string $attr) {
    return '<div class="mb-3" style="max-width:32rem"><label class="form-label" for="mf-' . $name . '">' . Text::_($label) . '</label>'
        . '<input class="form-control" id="mf-' . $name . '" name="' . $name . '" type="text" ' . $attr . '></div>';
};

$zusatz = $feld('mitgliedsnr', 'COM_MITGLIEDSFOTO_MITGLIEDSNR_PFLICHT', 'inputmode="numeric" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9._/\\-]{0,49}" autocomplete="off" required')
    . $feld('vorname', 'COM_MITGLIEDSFOTO_VORNAME_PFLICHT', 'maxlength="100" autocomplete="off" required')
    . $feld('nachname', 'COM_MITGLIEDSFOTO_NACHNAME_PFLICHT', 'maxlength="100" autocomplete="off" required')
    . $feld('geburtsdatum', 'COM_MITGLIEDSFOTO_GEBURTSDATUM_OPTIONAL', 'inputmode="numeric" placeholder="TT.MM.JJJJ" maxlength="10" pattern="\\d{1,2}\\.\\d{1,2}\\.\\d{4}" autocomplete="off"');
?>
<p class="lead"><?php echo Text::_('COM_MITGLIEDSFOTO_FREI_INTRO'); ?></p>
<?php echo LayoutHelper::render('uploader', [
    'url'          => Route::_('index.php?option=com_mitgliedsfoto&task=foto.erfassenFrei', false),
    'felder'       => [],
    'zusatz'       => $zusatz,
    'binIch'       => false,
    'einwilligung' => Text::_('COM_MITGLIEDSFOTO_EINWILLIGUNG_VORSTAND'),
    'absendenText' => Text::_('COM_MITGLIEDSFOTO_SPEICHERN'),
    'minBreite'    => (int) Einstellungen::get('min_breite', 360),
    'minHoehe'     => (int) Einstellungen::get('min_hoehe', 480),
], JPATH_ADMINISTRATOR . '/components/com_mitgliedsfoto/layouts'); ?>
