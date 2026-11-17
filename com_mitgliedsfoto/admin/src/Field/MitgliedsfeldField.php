<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\GroupedlistField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * Auswahl: Kernfeld des Benutzerkontos oder ein Benutzerfeld (com_fields, Kontext com_users.user).
 */
class MitgliedsfeldField extends GroupedlistField
{
    protected $type = 'Mitgliedsfeld';

    protected function getGroups()
    {
        // Optionen aus der XML (z. B. „nicht verwendet“) bleiben als erste, unbenannte Gruppe erhalten.
        $groups = parent::getGroups();

        $konto = [];

        foreach (['name', 'name_vorname', 'name_nachname', 'name_komma_vorname', 'name_komma_nachname', 'username', 'email', 'registerDate'] as $kern) {
            $konto[] = HTMLHelper::_('select.option', 'core:' . $kern, Text::_('COM_MITGLIEDSFOTO_CFG_KERN_' . strtoupper($kern)));
        }

        $groups[Text::_('COM_MITGLIEDSFOTO_CFG_GRUPPE_KONTO')] = $konto;

        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $kontext = 'com_users.user';
        $query   = $db->getQuery(true)
            ->select($db->quoteName(['id', 'title', 'name', 'type', 'state']))
            ->from($db->quoteName('#__fields'))
            ->where($db->quoteName('context') . ' = :kontext')
            ->where($db->quoteName('state') . ' >= 0')
            ->order($db->quoteName('title') . ' ASC')
            ->bind(':kontext', $kontext);

        $felder = [];

        foreach ($db->setQuery($query)->loadObjectList() as $feld) {
            $text = $feld->title . ' (' . $feld->name . ', ' . $feld->type . ')' . ((int) $feld->state === 1 ? '' : ' – ' . Text::_('JUNPUBLISHED'));
            $felder[] = HTMLHelper::_('select.option', 'field:' . (int) $feld->id, $text);
        }

        $groups[Text::_('COM_MITGLIEDSFOTO_CFG_GRUPPE_FELDER')] = $felder;

        return $groups;
    }
}
