<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_upage
 */

defined('_JEXEC') or die;

/**
 * Class UpageViewsActionsJson
 */
class UpageViewsActionsJson extends JViewHtml
{
    /**
     * Render actions html page
     *
     * @return string
     */
    function render()
    {
        //retrieve task list from model
        $model = new UpageModelsActions();

        $data = array_merge($_GET, $_POST);

        $this->result = $model->{$data['action']}($data);
        //display
        return parent::render();
    }

}