<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.upage
 */

defined('_JEXEC') or die;

/**
 * Class PlgContentUpage
 */
class PlgContentUpage extends JPlugin
{

    private $_pageView = 'default';
    private $_sectionsHtml = '';
    private $_backlink = '';

    /**
     * @param object $context Context
     * @param object $row     Row
     * @param array  $params  Parameters
     * @param int    $page    Page number
     */
    public function onContentPrepare($context, &$row, &$params, $page = 0)
    {
        $app = JFactory::getApplication();

        if ($app->isAdmin()) {
            return;
        }

        if (!$this->_upageComponentInstalled()) {
            return;
        }

        if (isset($row->id)) {
            $secfp = dirname(JPATH_PLUGINS) . '/administrator/components/com_upage/models/sections.php';
            if (!file_exists($secfp)) {
                return;
            }
            include_once $secfp;
            $helper = dirname(JPATH_PLUGINS) . '/administrator/components/com_upage/helpers/upage.php';
            if (!file_exists($helper)) {
                return;
            }
            include_once $helper;
            $sectionsObj = UpageModelsSections::getSectionsObject($row->id);
            if ($sectionsObj) {
                $row->upage = true;
                $isPreview = JRequest::getBool('isPreview', false);

                $props = unserialize(base64_decode($sectionsObj->props));
                $this->_pageView = isset($props['pageView']) ? $props['pageView'] : '';

                $preview = isset($props['preview']) ? $props['preview'] : '';
                if ($isPreview && $preview !== '') {
                    $props = $props['preview'];
                }

                $config = UpageHelpersUpage::getUpageConfig();
                $hideBacklink = isset($config['hideBacklink']) ? (bool)$config['hideBacklink'] : false;

                if ($hideBacklink) {
                    $props['backlink'] = str_replace('u-backlink', 'u-backlink u-hidden', $props['backlink']);
                }

                // start shortcodes processing
                $currentText = $row->text;
                $row->text = $props['publishHtml'];

                if (class_exists('PlgContentLoadmodule')) {
                    $dispatcher = JEventDispatcher::getInstance();
                    $pluginParams = JPluginHelper::getPlugin('content', 'loadmodule');
                    $pluginObject = new PlgContentLoadmodule($dispatcher, (array)$pluginParams);
                    $pluginObject->onContentPrepare('content.loadmodule', $row, $params);
                }

                $props['publishHtml'] = $row->text;
                $row->text = $currentText;
                // end shortcodes processing

                // view as landing page only
                if ($this->_pageView == 'landing') {
                    $app->set('theme', 'landing');
                    $app->set('themes.base', JPATH_ADMINISTRATOR . '/components/com_upage/views');
                    $app->set('themeFile', 'landing.php');
                    $app->set('props', $props);
                    return;
                }

                $theme = JFactory::getApplication()->getTemplate(true);
                $isUpageTheme = $theme->params->get('upagetheme', '0');
                $backlink = isset($props['backlink']) && $props['backlink'] ?  $props['backlink'] : '';
                if ($backlink && $isUpageTheme !== '1') {
                    $backlink = str_replace("\n", ' ', $backlink);
                } else {
                    $backlink = '';
                }
                $this->_backlink = $backlink;

                $document = JFactory::getDocument();
                // styles sections
                $sectionsHead = $props['head'];
                $document->addStyleDeclaration($sectionsHead);

                // fonts sections
                $fonts = $props['fonts'];
                $document->addCustomTag($fonts);

                if (isset($props['titleInBrowser']) && $props['titleInBrowser'] != '') {
                    $document->setTitle($props['titleInBrowser']);
                }

                if (isset($props['description']) && $props['description'] != '') {
                    $document->setDescription($props['description']);
                }

                if (isset($props['keywords']) && $props['keywords'] != '') {
                    $document->setMetadata('keywords', $props['keywords']);
                }

                if (isset($props['metaTags']) && $props['metaTags'] != '') {
                    $document->addCustomTag($props['metaTags']);
                }

                if (isset($props['customHeadHtml']) && $props['customHeadHtml'] != '') {
                    $document->addCustomTag($props['customHeadHtml']);
                }

                // include css and js sections
                $assets = '/administrator/components/com_upage/assets';
                if (file_exists(dirname(JPATH_PLUGINS) . $assets . '/css/upage.css')) {
                    $document->addStyleSheet(JURI::root(true) . $assets. '/css/upage.css');
                }

                JHtml::_('jquery.framework');
                include_once dirname(JPATH_PLUGINS) . '/administrator/components/com_upage/helpers/upage.php';
                $upConfig = UpageHelpersUpage::getUpageConfig();
                if (isset($upConfig['jquery']) && $upConfig['jquery'] == '1') {
                    $document->addScript(JURI::root(true) . $assets . '/js/jquery.js');
                }

                if (file_exists(dirname(JPATH_PLUGINS) . $assets .'/js/upage.js')) {
                    $document->addScript(JURI::root(true) . $assets . '/js/upage.js');
                }

                // html sections
                UpageModelsSections::$liveSite = true;

                $responsiveScript = <<<SCRIPT
<script>
    var body = document.body;
        body.className += ' {$props['bodyClass']}';
        
    (function ($) {
        var ResponsiveCms = window.ResponsiveCms;
        if (!ResponsiveCms) {
            return;
        }
        ResponsiveCms.contentDom = $('script:last').parent();
    
        if (typeof ResponsiveCms.recalcClasses === 'function') {
            ResponsiveCms.recalcClasses();
        }
    })(jQuery);
</script>
SCRIPT;
                $this->_sectionsHtml = $responsiveScript . UpageModelsSections::processSectionsHtml($props['publishHtml']);

                // view as landing page with header and footer from current theme
                if ($this->_pageView == 'landing_with_header_footer') {
                    // content changes onAfterRender event
                    return;
                }

                // view as default page
                if (isset($row->text)) {
                    $row->text = $this->_sectionsHtml . $row->text;
                }
                if (isset($row->introtext)) {
                    $row->introtext = $this->_sectionsHtml . $row->introtext;
                }
            }


        }
        return;
    }

    /**
     *  Proccess content before rendering
     */
    public function onAfterRender()
    {
        $app = JFactory::getApplication();

        if ($app->isAdmin()) {
            return;
        }

        $pageContent = $app->getBody();

        if ($this->_sectionsHtml && $this->_pageView === 'landing_with_header_footer') {
            $bodyStartTag = '<body>';
            $bodyContent = '';
            $bodyEndTag = '</body>';
            if (preg_match('/(<body[^>]+>)([\s\S]*)(<\/body>)/', $pageContent, $matches)) {
                $bodyStartTag = $matches[1];
                $bodyContent = trim($matches[2]);
                $bodyEndTag = $matches[3];
            }

            if ($bodyContent == '') {
                $newPageContent = $bodyStartTag . $this->_sectionsHtml . $bodyEndTag;
            } else {
                $newPageContent = $bodyStartTag;
                if (preg_match('/<header[^>]+>[\s\S]*<\/header>/', $bodyContent, $matches2)) {
                    $newPageContent .= $matches2[0];
                }
                $newPageContent .= $this->_sectionsHtml;
                if (preg_match('/<footer[^>]+>[\s\S]*<\/footer>/', $bodyContent, $matches3)) {
                    $newPageContent .= $matches3[0];
                }
                $newPageContent .= $bodyEndTag;
            }
            $pageContent = preg_replace('/(<body[^>]+>)([\s\S]*)(<\/body>)/', $newPageContent, $pageContent);
        }
        // add backlink
        $pageContent = str_replace('</body>', $this->_backlink . '</body>', $pageContent);

        $app->setBody($pageContent);
    }

    /**
     * Check upage component
     *
     * @return bool
     */
    private function _upageComponentInstalled() {
        if (!JComponentHelper::isInstalled('com_upage')) {
            return false;
        }
        if (!JComponentHelper::getComponent('com_upage', true)->enabled) {
            return false;
        }
        return true;
    }
}