<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_upage
 */

defined('_JEXEC') or die;

require_once dirname(__FILE__) . '/mappers.php';

/**
 * Class Upage_Data_Loader
 */
class Upage_Data_Loader
{
    /**
     * @var null Sample data object
     */
    private $_data = null;

    /**
     * Numeric identificator of the currently selected template style in Joomla
     * administrator.
     */
    private $_style;

    /**
     * @var string Sample images path
     */
    private $_images = '';

    /**
     * Name of the template.
     */
    private $_template = '';

    /**
     * @var string Cms root url
     */
    private $_rootUrl = '';

    /**
     * @var string Sample data ids string
     */
    private $_dataIds = array();

    /**
     * @var bool Replace sample data flag
     */
    private $_replace = false;

    /**
     * Method to load sample data.
     *
     * @param string $file           File path to sample data
     * @param bool   $isThemeContent Flag for theme
     *
     * @return null|string|void
     */
    public function load($file, $isThemeContent = false)
    {
        $config = JFactory::getConfig();
        $live_site = $config->get('live_site');
        if ($isThemeContent) {

        }
        $p = dirname(dirname(JURI::current()));
        $root = trim($live_site) != '' ? JURI::root(true) : ($isThemeContent ? dirname(dirname($p)) : $p);
        if ('/' === substr($root, -1)) {
            $this->_rootUrl  = substr($root, 0, -1);
        } else {
            $this->_rootUrl  = $root;
        }

        $path = realpath($file);
        if (false === $path) {
            return;
        }
        $images = dirname($path) . DIRECTORY_SEPARATOR . 'images';
        if (file_exists($images) && is_dir($images)) {
            $this->_images = $images;
        }
        if ($isThemeContent) {
            $this->_template = basename(dirname(dirname($path)));
        }
        return $this->_parse($path);
    }

    /**
     * Method to execute installing sample data.
     *
     * @param array $params Sample data installing parameters
     */
    public function execute($params)
    {
        $callback = array();
        $callback[] = $this;
        $callback[] = '_error';
        Upage_Data_Mappers::errorCallback($callback);

        if ($this->_template) {
            $action = isset($params['action']) && is_string($params['action']) ? $params['action'] : '';
            if (0 == strlen($action) || !in_array($action, array('check', 'run', 'upage'))) {
                return 'Invalid action.';
            }
            $this->_style = isset($params['id']) && is_string($params['id'])
            && ctype_digit($params['id']) ? intval($params['id'], 10) : -1;
            if (-1 === $this->_style) {
                return 'Invalid style id.';
            }
            $this->_replace = isset($params['replace']) && $params['replace'] == '1' ? true : false;
            switch ($action) {
            case 'check':
                echo 'result:' . ($this->_contentIsInstalled() ? '1' : '0');
                break;
            case 'run':
                $this->_load();
                echo 'result:ok';
                break;
            }
        } else {
            $this->_replace = isset($params['replaceStatus']) && $params['replaceStatus'] == '1' ? true : false;
            $this->_load();
        }
    }

    /**
     * Method to throw errors.
     *
     * @param string $msg  Text message
     * @param int    $code Number error
     *
     * @throws Exception
     */
    public function _error($msg, $code)
    {
        throw new Exception($msg);
    }

    /**
     * Method check content installing
     *
     * @return bool
     */
    private function _contentIsInstalled()
    {
        $content = Upage_Data_Mappers::get('content');

        if (($ids = $this->_getDataIds()) !== '') {
            foreach ($ids as $id) {
                $contentList = $content->find(array('id' => $id));
                if (0 != count($contentList)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Installing sample data.
     */
    private function _load()
    {
        if ($this->_replace) {
            $this->_deletePreviousContent();
        }
        $this->_loadPages();
        $this->_saveDataIds();
        if ($this->_template) {
            $this->_createModules();
            $this->_configureModulesVisibility();
            $this->_configureEditor();
        }
        $this->_copyImages();
    }

    /**
     * Delete previous content
     */
    private function _deletePreviousContent()
    {
        $content = Upage_Data_Mappers::get('content');

        if (($ids = $this->_getDataIds()) !== '') {
            foreach ($ids as $id) {
                $contentList = $content->find(array('id' => $id));
                if (0 != count($contentList)) {
                    $content->delete($contentList[0]->id);
                    // delete sections
                    $db = JFactory::getDBO();
                    $query = $db->getQuery(true);
                    $query->delete('#__upage_sections')
                        ->where($db->qn('page_id') . ' = ' . $db->q($contentList[0]->id));
                    $db->setQuery($query);
                    try {
                        $db->execute();
                    }
                    catch (Exception $exc) {
                        // Nothing
                    }
                }
            }
        }
    }

    /**
     * Method to save sample data ids
     */
    private function _saveDataIds()
    {
        if (count($this->_dataIds) < 1) {
            return;
        }

        $parameters = $this->_getExtOptions();
        $parameters['dataIds'] = implode(',', $this->_dataIds);
        $this->_setExtOptions($parameters);
    }

    /**
     * Method to get sample data ids
     *
     * @return array|string
     */
    private function _getDataIds()
    {
        $parameters = $this->_getExtOptions();
        if (isset($parameters['dataIds']) && $parameters['dataIds']) {
            return explode(',', $parameters['dataIds']);
        } else {
            return '';
        }
    }

    /**
     * Method to get or create default category id
     *
     * @throws Exception
     */
    private function _getDefaultCategory()
    {
        $categories = Upage_Data_Mappers::get('category');

        $categoryList = $categories->find(array('title' => 'Uncategorised'));
        foreach ($categoryList as & $categoryListItem) {
            return  $categoryListItem->id;
        }

        $category = $categories->create();
        $category->title = 'Upage Category';
        $category->extension = 'com_content';
        $category->metadata = $this->_paramsToString(array('robots' => '', 'author' => '', 'tags' => ''));
        $status = $categories->save($category);
        if (is_string($status)) {
            return $this->_error($status, 1);
        }
        return $category->id;
    }

    /**
     * Method load sample pages to cms
     *
     * @throws Exception
     */
    private function _loadPages()
    {
        $content = Upage_Data_Mappers::get('content');
        $defaultCategoryId = $this->_getDefaultCategory();
        $key = 0;
        foreach ($this->_data['Pages'] as & $articleData) {
            $key++;
            $article = $content->create();
            $article->catid = $defaultCategoryId;
            list($title, $alias) = $this->_generateNewTitle($defaultCategoryId, $articleData['caption'], $key);
            $article->title = $title;
            $article->alias = $alias;
            $article->introtext = $this->_processingContent(isset($articleData['content']) ? $articleData['content'] : '');
            $article->attribs = $this->_paramsToString(
                array (
                    'show_title' => '',
                    'link_titles' => '',
                    'show_intro' => '',
                    'show_category' => '',
                    'link_category' => '',
                    'show_parent_category' => '',
                    'link_parent_category' => '',
                    'show_author' => '',
                    'link_author' => '',
                    'show_create_date' => '',
                    'show_modify_date' => '',
                    'show_publish_date' => '',
                    'show_item_navigation' => '',
                    'show_icons' => '',
                    'show_print_icon' => '',
                    'show_email_icon' => '',
                    'show_vote' => '',
                    'show_hits' => '',
                    'show_noauth' => '',
                    'alternative_readmore' => '',
                    'article_layout' => ''
                )
            );
            $article->metadata = $this->_paramsToString(array('robots' => '', 'author' => '', 'rights' => '', 'xreference' => '', 'tags' => ''));
            $status = $content->save($article);
            if (is_string($status)) {
                return $this->_error($status, 1);
            }
            $articleData['joomla_id'] = $article->id;
            $this->_dataIds[] = $article->id;
            if (isset($articleData['properties'])) {
                $properties = $articleData['properties'];
                $properties['head'] = $this->_processingContent($properties['head']);
                $properties['html'] = $this->_processingContent($properties['html']);
                $properties['publishHtml'] = $this->_processingContent($properties['publishHtml']);
                $properties['pageView'] = 'landing_with_header_footer';

                $properties['keywords']         = isset($articleData['keywords']) ? $articleData['keywords'] : '';
                $properties['description']      = isset($articleData['description']) ? $articleData['description'] : '';
                $properties['metaTags']         = isset($articleData['metaTags']) ? $articleData['metaTags'] : '';
                $properties['customHeadHtml']   = isset($articleData['customHeadHtml']) ? $articleData['customHeadHtml'] : '';
                $properties['titleInBrowser']   = isset($articleData['titleInBrowser']) ? $articleData['titleInBrowser'] : '';

                $db = JFactory::getDBO();
                $query = $db->getQuery(true);
                $query->insert('#__upage_sections');
                $query->set($db->quoteName('props') . '=' .  $db->quote(base64_encode(serialize($properties))));
                $query->set($db->quoteName('page_id') . '=' . $db->quote($articleData['joomla_id']));
                $db->setQuery($query);
                $db->query();
            }
        }
    }

    /**
     * Create modules from import data
     */
    private function _createModules()
    {
        if (!isset($this->_data['Modules'])) {
            return;
        }

        $modulesMapper = Upage_Data_Mappers::get('module');

        foreach ($this->_data['Modules'] as $moduleData) {
            $modulesList = $modulesMapper->find(array('title' => $moduleData['title']));
            foreach ($modulesList as $modulesListItem) {
                $status = $modulesMapper->delete($modulesListItem->id);
            }
        }

        $order = array();

        foreach ($this->_data['Modules'] as $key => $moduleData) {
            $module = $modulesMapper->create();
            $module->title = $moduleData['title'];
            $module->position = $moduleData['position'];

            $params = array();
            switch ($moduleData['type']) {
            case 'menu':
                $module->module = 'mod_menu';
                $params = array
                (
                    'menutype' => $moduleData['menu'],
                    'startLevel' => '1',
                    'endLevel' => '0',
                    'showAllChildren' => '1',
                    'tag_id' => '',
                    'class_sfx' => '',
                    'window_open' => '',
                    'layout' => '_:default',
                    'moduleclass_sfx' => '',
                    'cache' => '1',
                    'cache_time' => '900',
                    'cachemode' => 'itemid'
                );
                break;
            case 'login':
                $module->module = 'mod_login';
                $params = array
                (
                    'pretext' => '',
                    'posttext' => '',
                    'login' => '',
                    'logout' => '',
                    'greeting' => '1',
                    'name' => '0',
                    'usesecure' => '0',
                    'layout' => '_:default',
                    'moduleclass_sfx' => '',
                    'cache' => '0'
                );
                break;
            case 'search':
                $module->module = 'mod_search';
                $params = array
                (
                    'layout' => '_:default',
                    'moduleclass_sfx' => '',
                    'cache' => '0'
                );
                break;
            case 'text':
                $module->module = 'mod_custom';
                $module->content = $this->_processingContent($moduleData['content']);
                $params = array
                (
                    'prepare_content' => '1',
                    'layout' => '_:default',
                    'moduleclass_sfx' => '',
                    'cache' => '1',
                    'cache_time' => '900',
                    'cachemode' => 'static'
                );
                break;
            }
            $module->showtitle = 'true' == $moduleData['showTitle'] ? '1' : '0';
            // style:
            if (isset($moduleData['style']) && isset($params['moduleclass_sfx'])) {
                $params['moduleclass_sfx'] = $moduleData['style'];
            }
            // parameters:
            $module->params = $this->_paramsToString($params);

            // ordering:
            if (!isset($order[$moduleData['position']])) {
                $order[$moduleData['position']] = 1;
            }
            $module->ordering = $order[$moduleData['position']];
            $order[$moduleData['position']]++;

            $status = $modulesMapper->save($module);
            if (is_string($status)) {
                trigger_error($status, E_USER_ERROR);
            }
            $this->_data['Modules'][$key]['joomla_id'] = $module->id;
        }
    }

    /**
     * To configure visibility of modules
     */
    private function _configureModulesVisibility()
    {
        if (!isset($this->_data['Modules'])) {
            return;
        }
        // to do - menu implementing
        /*if (!isset($this->_data['Menus']))
            return;*/

        $contentMenuItems = array();

        // to do - menu implementing
        /*foreach ($this->_data['Menus'] as $menuData)
            foreach ($menuData['items'] as $itemData)
                $contentMenuItems[] = $itemData['joomla_id'];*/

        $contentMenuItems[] = 0; // on all pages

        $contentModules = array();
        foreach ($this->_data['Modules'] as $moduleData) {
            $contentModules[] = $moduleData['joomla_id'];
        }

        $modules = Upage_Data_Mappers::get('module');

        // to do - menu implementing
        /*$menuItems = Upage_Data_Mappers::get('menuItem');

        $userMenuItems = array();
        $menuItemList = $menuItems->find(array('scope' => 'site'));
        foreach ($menuItemList as $menuItem) {
            if (in_array($menuItem->id, $contentMenuItems))
                continue;
            $userMenuItems[] = $menuItem->id;
        }*/

        $moduleList = $modules->find(array('scope' => 'site'));
        foreach ($moduleList as $moduleListItem) {
            if (in_array($moduleListItem->id, $contentModules)) {
                $modules->enableOn($moduleListItem->id, $contentMenuItems);
            } else {
                $pages = $modules->getAssignment($moduleListItem->id);
                if (1 == count($pages) && '0' == $pages[0]) {
                    $modules->disableOn($moduleListItem->id, $contentMenuItems);
                }
                if (0 < count($pages) && 0 > $pages[0]) {
                    $disableOnPages = array_unique(array_merge(array_map('abs', $pages), $contentMenuItems));
                    $modules->disableOn($moduleListItem->id, $disableOnPages);
                }
            }
        }
    }

    /**
     * Generate new title for page
     *
     * @param int    $catId Category Id
     * @param string $title Start title
     * @param int    $key   Custom key for alias
     *
     * @return array
     */
    private function _generateNewTitle($catId, $title, $key = 0)
    {
        $title = $title ? strip_tags($title) : 'Post';
        if (JFactory::getConfig()->get('unicodeslugs') == 1) {
            $alias = JFilterOutput::stringURLUnicodeSlug($title);
        } else {
            $alias = JFilterOutput::stringURLSafe($title);
        }
        $table = JTable::getInstance('Content');
        while ($table->load(array('alias' => $alias, 'catid' => $catId))) {
            $title = JString::increment($title);
            $alias = JString::increment($alias, 'dash');
        }
        if (!$alias) {
            $date = new JDate();
            $alias = $date->format('Y-m-d-H-i-s') . '-' . $key;
        }
        return array($title, $alias);
    }

    /**
     * Method to proccess page content
     *
     * @param string $content  Page sample content
     * @param bool   $relative Path relative flag
     *
     * @return mixed
     */
    private function _processingContent($content, $relative = false)
    {
        if ($content == '') {
            return $content;
        }

        $old = $this->_rootUrl;
        if ($relative) {
            $this->_rootUrl = '';
        } else {
            $this->_rootUrl .= '/';
        }
        $content = $this->_replacePlaceholdersForImages($content);
        $this->_rootUrl =  $old;
        return $content;
    }

    /**
     * Replace image placeholders in page content
     *
     * @param string $content Page sample content
     *
     * @return mixed
     */
    private function _replacePlaceholdersForImages($content)
    {
        //change default image
        $content = str_replace('[image_default]', $this->_rootUrl . 'administrator/components/com_upage/assets/images/upage-images/default-image.jpg', $content);
        $content = preg_replace_callback('/\[image_(\d+)\]/', array(&$this, '_replacerImages'), $content);
        return $content;
    }

    /**
     * Callback function for replacement image placeholders
     *
     * @param array $match
     *
     * @return string
     */
    private function _replacerImages($match)
    {
        $full = $match[0];
        $n = $match[1];
        if (isset($this->_data['Images'][$n])) {
            $imageName = $this->_data['Images'][$n]['fileName'];
            return $this->_rootUrl . 'images/upage-images/' . $imageName;
        }
        return $full;
    }

    /**
     * To configure editor
     *
     * @return null|void
     * @throws Exception
     */
    private function _configureEditor()
    {
        $extensions = Upage_Data_Mappers::get('extension');
        $tinyMce = $extensions->findOne(array('element' => 'tinymce'));
        if (is_string($tinyMce)) {
            return $this->_error($tinyMce, 1);
        }
        if (!is_null($tinyMce)) {
            $params = $this->_stringToParams($tinyMce->params);
            $elements = isset($params['extended_elements']) && strlen($params['extended_elements']) ? explode(',', $params['extended_elements']) : array();
            $invalidElements = isset($params['invalid_elements']) && strlen($params['invalid_elements']) ? explode(',', $params['invalid_elements']) : array();
            if (in_array('script', $invalidElements)) {
                array_splice($invalidElements, array_search('script', $invalidElements), 1);
            }
            if (!in_array('style', $elements)) {
                $elements[] = 'style';
            }
            if (!in_array('script', $elements)) {
                $elements[] = 'script';
            }
            if (!in_array('div[*]', $elements)) {
                $elements[] = 'div[*]';
            }
            $params['extended_elements'] = implode(',', $elements);
            $params['invalid_elements'] = implode(',', $invalidElements);
            $tinyMce->params = $this->_paramsToString($params);
            $status = $extensions->save($tinyMce);
            if (is_string($status)) {
                return $this->_error($status, 1);
            }
        }
        return null;
    }

    /**
     * Method to copy sample images to cms
     */
    private function _copyImages()
    {
        if (!$this->_images) {
            return;
        }
        $imgDir = dirname(JPATH_BASE) . DIRECTORY_SEPARATOR . 'images';
        $contentDir = $imgDir . DIRECTORY_SEPARATOR . 'upage-images';
        if (!file_exists($contentDir)) {
            mkdir($contentDir);
        }
        if ($handle = opendir($this->_images)) {
            while (false !== ($file = readdir($handle))) {
                if ('.' == $file || '..' == $file || is_dir($file)) {
                    continue;
                }
                if (!preg_match('~\.(?:bmp|jpg|jpeg|png|ico|gif)$~i', $file)) {
                    continue;
                }
                copy($this->_images . DIRECTORY_SEPARATOR . $file, $contentDir . DIRECTORY_SEPARATOR . $file);
            }
            closedir($handle);
        }
    }

    /**
     * Method to get Upage Component options
     *
     * @return mixed
     */
    private function _getExtOptions()
    {
        $db = JFactory::getDBO();
        $query = $db->getQuery(true);
        if ($this->_template) {
            $query->select('params')->from('#__template_styles')->where('id=' . $query->escape($this->_style));
        } else {
            $query->select('params')->from('#__upage_params')->where('name=' . $query->quote('com_upage'));
        }
        $db->setQuery($query);
        return $this->_stringToParams($db->loadResult());
    }

    /**
     * Method to save Upage Component options
     *
     * @param array $parameters
     */
    private function _setExtOptions($parameters)
    {
        $db = JFactory::getDBO();
        $query = $db->getQuery(true);
        if ($this->_template) {
            $query->update('#__template_styles')->set(
                $db->quoteName('params') . '=' .
                $db->quote($this->_paramsToString($parameters))
            )->where('id=' . $query->escape($this->_style));
        } else {
            $query->update('#__upage_params')->set(
                $db->quoteName('params') . '=' .
                $db->quote($this->_paramsToString($parameters))
            )->where('name=' . $query->quote('com_upage'));
        }
        $db->setQuery($query);
        $db->query();
    }

    /**
     * Convert parameters array to string
     *
     * @param array $params
     *
     * @return mixed
     */
    private function _paramsToString($params)
    {
        $registry = new JRegistry();
        $registry->loadArray($params);
        return $registry->toString();
    }

    /**
     * Convert parameters string to array
     *
     * @param string $string
     *
     * @return mixed
     */
    private function _stringToParams($string)
    {
        $registry = new JRegistry();
        $registry->loadString($string);
        return $registry->toArray();
    }

    /**
     * Parsing of sample data file
     *
     * @param string $file
     *
     * @return null|string
     */
    private function _parse($file)
    {
        $error = null;
        if (!($fp = fopen($file, 'r'))) {
            $error = 'Could not open json input';
        }
        $contents = '';
        if (is_null($error)) {
            while (!feof($fp)) {
                $contents .= fread($fp, 4096);
            }
            fclose($fp);
        }

        $this->_data = json_decode($contents, true);

        return $error;
    }
}
