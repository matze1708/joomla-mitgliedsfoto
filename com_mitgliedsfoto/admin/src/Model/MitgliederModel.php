<?php

/**
 * @package     com_mitgliedsfoto
 * @copyright   Schützenverein Volker Alzey 1952 e.V.
 * @license     GNU General Public License version 2 or later
 */

namespace SvAlzey\Component\Mitgliedsfoto\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
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
        $spalten = Mitglieder::spalten();
        $query = Mitglieder::mitStatusQuery($db)->select([
            $db->quoteName('f.status', 'status'),
            $db->quoteName('f.eingereicht_am', 'eingereicht_am'),
            $db->quoteName('f.dateiname', 'dateiname'),
            $db->quoteName('f.mail_gesendet_am', 'mail_gesendet_am'),
            $db->quoteName('f.geloescht_am', 'geloescht_am'),
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

        $suche = trim((string) $this->getState('filter.search'));

        if ($suche !== '') {
            $like = '%' . addcslashes($suche, '%_\\') . '%';
            $query->where(
                '(' . $db->quoteName('m.' . $spalten['nachname']) . ' LIKE :s1 OR '
                . $db->quoteName('m.' . $spalten['vorname']) . ' LIKE :s2 OR '
                . 'CONVERT(' . $db->quoteName('m.' . $spalten['nr']) . ' USING utf8mb4) LIKE :s3)'
            )
                ->bind(':s1', $like, ParameterType::STRING)
                ->bind(':s2', $like, ParameterType::STRING)
                ->bind(':s3', $like, ParameterType::STRING);
        }

        return $query;
    }
}
