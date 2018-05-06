<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_upagemenu
 */

defined('_JEXEC') or die;

$user = JFactory::getUser();
if ($user->guest) {
    return;
}

// Include the module helper classes.
if (!class_exists('ModUpageMenuHelper')) {
    include dirname(__FILE__) . '/helper.php';
}
$upageComponentItems = ModUpageMenuHelper::getUpageComponent(true);
// Render the module layout
require JModuleHelper::getLayoutPath('mod_upagemenu', $params->get('layout', 'default'));
