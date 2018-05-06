<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Сom_Upage
 */

defined('_JEXEC') or die;

/**
 * Class UpageControllersActions
 */
class UpageControllersActions extends JControllerBase
{
    /**
     * Execute actions controller
     */
    public function execute()
    {

        // Get the application
        $app = $this->getApplication();

        // Get the document object.
        $document = JFactory::getDocument();

        $viewName = $app->input->getWord('view', 'actions');
        $viewFormat = $app->input->getWord('format', 'json');
        $layoutName = $app->input->getWord('layout', 'default');

        $app->input->set('view', $viewName);

        // Register the layout paths for the view
        $paths = new SplPriorityQueue;
        $paths->insert(JPATH_COMPONENT . '/views/' . $viewName . '/tmpl', 'normal');

        $viewClass = 'UpageViews' . ucfirst($viewName) . ucfirst($viewFormat);
        $modelClass = 'UpageModels' . ucfirst($viewName);
        $view = new $viewClass(new $modelClass, $paths);

        $view->setLayout($layoutName);

        // Render our view.
        echo $view->render();
    }

}