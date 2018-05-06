<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_upage
 */

defined('_JEXEC') or die;

/**
 * Class UpageControllersDisplay
 */
class UpageControllersDisplay extends JControllerBase
{
    /**
     * Execute display controller
     *
     * @return bool
     */
    public function execute()
    {

        // Get the application
        $app = $this->getApplication();

        $layoutName = $app->input->getWord('layout', 'default');

        // Get the document object.
        $document = JFactory::getDocument();

        $viewName = $app->input->getWord('view', 'display');
        $viewFormat = $document->getType();
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

        return true;
    }

}