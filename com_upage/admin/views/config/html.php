<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_upage
 */

defined('_JEXEC') or die;

/**
 * Class UpageViewsConfigHtml
 */
class UpageViewsConfigHtml extends JViewHtml
{
    /**
     * Render config html page
     *
     * @return string
     */
    function render()
    {
        $this->adminUrl = dirname(dirname((JURI::current()))) . '/administrator';
        JToolbarHelper::title(JText::_('COM_UPAGE_CONFIGURATION'), 'equalizer upage config');
        $params = UpageHelpersUpage::getUpageConfig();
        $this->checked = isset($params['jquery']) && $params['jquery'] == '1' ? 'checked' : '';
        //display
        return parent::render();
    }
}