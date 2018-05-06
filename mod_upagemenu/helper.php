<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_upagemenu
 */

defined('_JEXEC') or die;

/**
 * Class ModUpageMenuHelper
 */
abstract class ModUpageMenuHelper
{
    /**
     * @return bool|stdClass
     */
    public static function getUpageComponent()
    {
        $db = JFactory::getDBO();
        $q = 'SELECT m.id, m.title, m.alias, m.link, m.parent_id, m.img, e.element FROM `#__menu` as m
				LEFT JOIN #__extensions AS e ON m.component_id = e.extension_id
		         WHERE m.client_id = 1 AND e.enabled = 1 AND m.id > 1 AND e.element = \'com_upage\'
		         AND (m.parent_id=1 OR m.parent_id =
			                        (SELECT m.id FROM `#__menu` as m
									LEFT JOIN #__extensions AS e ON m.component_id = e.extension_id
			                        WHERE m.parent_id=1 AND m.client_id = 1 AND e.enabled = 1 AND m.id > 1 AND e.element = \'com_upage\'))
		         ORDER BY m.lft';
        $db->setQuery($q);
        $upageComponentItems = $db->loadObjectList();

        $result = new stdClass();
        $lang = JFactory::getLanguage();
        if ($upageComponentItems) {
            // Parse the list of extensions.
            foreach ($upageComponentItems as &$upageComponentItem) {
                if ($upageComponentItem->parent_id == 1) {
                    $result = $upageComponentItem;
                    if (!isset($result->submenu)) {
                        $result->submenu = array();
                    }

                    if (empty($upageComponentItem->link)) {
                        $upageComponentItem->link = 'index.php?option=' . $upageComponentItem->element;
                    }

                    $upageComponentItem->text = $lang->hasKey($upageComponentItem->title) ? JText::_($upageComponentItem->title) : $upageComponentItem->alias;
                } else {
                    // Sub-menu level.
                    if (isset($result)) {
                        // Add the submenu link if it is defined.
                        if (isset($result->submenu) && !empty($upageComponentItem->link)) {
                            $upageComponentItem->text = $lang->hasKey($upageComponentItem->title) ? JText::_($upageComponentItem->title) : $upageComponentItem->alias;
                            $class = preg_replace('#\.[^.]*$#', '', basename($upageComponentItem->img));
                            $class = preg_replace('#\.\.[^A-Za-z0-9\.\_\- ]#', '', $class);
                            $upageComponentItem->class = '';
                            $result->submenu[] = &$upageComponentItem;
                        }
                    }
                }
            }
            $props = get_object_vars($result);
            if (!empty($props)) {
                return $result;
            }
        }
        return false;
    }
}
