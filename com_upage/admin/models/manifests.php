<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_upage
 */

defined('_JEXEC') or die;

/**
 * Class UpageModelsManifests
 */
class UpageModelsManifests
{
    /**
     * @return string
     */
    public static function getManifest()
    {
        $db = JFactory::getDBO();
        $query = $db->getQuery(true);
        $query->select('*');
        $query->from('#__upage_manifests');
        $db->setQuery($query);
        $rows = $db->loadObjectList();
        if (count($rows) > 0 && ($row = $rows[0]) !== null && $row->manifest) {
            return $row;
        }
        return '';
    }

    /**
     * @param string $version Manifest version
     * @param string $content Manifest content
     */
    public static function setManifest($version, $content)
    {
        $db = JFactory::getDBO();
        // delete previous data
        $db->getQuery(true);
        $db->setQuery('DELETE FROM #__upage_manifests');
        $db->execute();
        // add new manifest
        $query = $db->getQuery(true);
        $query->insert('#__upage_manifests');
        $query->set('version=' . $db->quote($version));
        $query->set('manifest=' . $db->quote($content));
        $db->setQuery($query);
        $db->query();
    }
}