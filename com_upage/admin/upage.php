<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_upage
 */

defined('_JEXEC') or die;

//load classes
JLoader::registerPrefix('Upage', JPATH_COMPONENT_ADMINISTRATOR);

//application
$app = JFactory::getApplication();
 
// Require specific controller if requested
$controller = $app->input->get('controller', 'display');

// Create the controller
$classname  = 'UpageControllers' . ucwords($controller);
$controller = new $classname();
// Perform the Request task
$controller->execute();