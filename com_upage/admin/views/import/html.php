<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_upage
 */

defined('_JEXEC') or die;

/**
 * Class UpageViewsImportHtml
 */
class UpageViewsImportHtml extends JViewHtml
{
    /**
     * Render import html page
     *
     * @return string
     */
    function render()
    {
        $this->adminUrl = dirname(dirname((JURI::current()))) . '/administrator';
        JToolbarHelper::title(JText::_('COM_UPAGE_IMPORT_HEADER'));
        //display
        return parent::render();
    }
}