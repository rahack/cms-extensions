<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_upage
 */

defined('_JEXEC') or die;

require_once JPATH_ADMINISTRATOR . '/components/com_upage/helpers/mappers.php';

/**
 * Class UpageViewsDisplayHtml
 */
class UpageViewsDisplayHtml extends JViewHtml
{
    /**
     * Render display html page
     *
     * @return string|void
     */
    function render()
    {
        $app = JFactory::getApplication();
        $domain = UpageHelpersUpage::getDomainParam();
        $startFromDashboard = $app->input->getVar('dashboard', '');
        $autoStart = $app->input->getVar('autostart', '');
        $postId = $app->input->getVar('postid', '');

        if ($autoStart) {
            $session = JFactory::getSession();
            $registry = $session->get('registry');
            $registry->set('com_content.edit.article.id', $postId);
            $url = 'index.php?option=com_content&view=article&layout=edit&autostart=1&id=' . $postId . ($domain ? '&domain=' . $domain : '');
            $app->redirect($url);
        }

        if ($startFromDashboard) {
            // delete draft pages
            $db = JFactory::getDBO();
            $query = $db->getQuery(true);
            $query->select('*');
            $query->from('#__content');
            $query->where('state = 2');
            $query->where('title like \'%Page%\'');
            $db->setQuery($query);
            $list = $db->loadObjectList();
            if (count($list) > 0) {
                $contentMapper = Upage_Data_Mappers::get('content');
                foreach ($list as $item) {
                    $contentMapper->delete($item->id);
                }
            }
            // create draft page
            $actionsObject = new UpageModelsActions();
            $article = $actionsObject->createPost(array('state' => 2 /*to draft*/));
            if ($article) {
                $session = JFactory::getSession();
                $registry = $session->get('registry');
                $registry->set('com_content.edit.article.id', $article->id);
                $url = 'index.php?option=com_content&view=article&layout=edit&id=' . $article->id .'&start=1';
                if ($domain) {
                    $url .= '&domain=' . $domain;
                }
                $app->redirect($url);
            }
            // new post don't create, go to admin dashboard
            $app->redirect('index.php');
        }

        $editorSettings = UpageHelpersUpage::getEditorSettings();
        $manifest = UpageModelsManifests::getManifest();

        if (!$manifest && strpos($domain, '//localhost') === false) {
            include dirname(__FILE__) . '/first-start.php';
            die;
        }

        $layoutName = $app->input->getWord('layout', 'default');

        if ($layoutName == 'default' && $postId) {
            // start upage from edit article page
            $sectionsObject = UpageModelsSections::getSectionsObject($postId);
            if ($sectionsObject) {
                UpageModelsSections::clearPreview($sectionsObject);
                $parts = '/#/builder/1/page/' . $postId;
            } else {
                $parts = '/#/landing';
            }
            $app->redirect('index.php?option=com_upage' . ($domain ? '&domain=' . $domain : '') . $parts);
            return;
        }

        // start upage
        $this->startFiles = UpageHelpersUpage::getStartFiles();
        $this->manifestAttr = $manifest && !$domain ? ' manifest="' . $editorSettings['actions']['getManifest'] . '"' : '';

        //display
        return parent::render();
    }

}