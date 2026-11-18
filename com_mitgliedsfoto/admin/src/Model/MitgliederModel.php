<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\Mitglieder;

/**
 * Mitgliederliste: live aus der Joomla-Mitgliedertabelle, verknüpft mit #__mitgliedsfoto.
 */
class MitgliederModel extends ListModel
{
    public function __construct($config = [], $factory = null)
    {
        $config['filter_fields'] = ['search', 'status'];

        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = null, $direction = null)
    {
        parent::populateState('nachname', 'ASC');
    }

    protected function getStoreId($id = '')
    {
        return parent::getStoreId($id . ':' . $this->getState('filter.search') . ':' . $this->getState('filter.status'));
    }

    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = Mitglieder::mitStatusQuery($db)->select([
            $db->quoteName('f.status', 'status'),
            $db->quoteName('f.eingereicht_am', 'eingereicht_am'),
            $db->quoteName('f.dateiname', 'dateiname'),
            $db->quoteName('f.mail_gesendet_am', 'mail_gesendet_am'),
            $db->quoteName('f.geloescht_am', 'geloescht_am'),
            $db->quoteName('f.exportiert_am', 'exportiert_am'),
        ]);

        $status = (string) $this->getState('filter.status');

        if ($status === 'offen') {
            $query->where($db->quoteName('f.status') . ' IS NULL');
        } elseif ($status === 'eingereicht' || $status === 'vorstand') {
            $query->where($db->quoteName('f.status') . ' = :status')->bind(':status', $status);
        } elseif ($status === 'geloescht') {
            $query->where($db->quoteName('f.geloescht_am') . ' IS NOT NULL');
        } elseif ($status === 'mailfehler') {
            $query->where($db->quoteName('f.status') . ' IS NOT NULL')
                ->where($db->quoteName('f.mail_gesendet_am') . ' IS NULL')
                ->where($db->quoteName('f.geloescht_am') . ' IS NULL');
        }

        Mitglieder::sucheAnwenden($query, $db, (string) $this->getState('filter.search'));

        return $query;
    }
}
