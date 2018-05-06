<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_upage
 */

defined('_JEXEC') or die;

require_once JPATH_ADMINISTRATOR . '/components/com_upage/helpers/mappers.php';
require_once JPATH_ADMINISTRATOR . '/components/com_upage/helpers/import.php';

/**
 * Class UpageModelsActions
 */
class UpageModelsActions extends JModelBase
{
    /**
     * @var string Default category name
     */
    private $_defaultCategory = 'Uncategorised';

    /**
     * UpageModelsActions constructor.
     */
    public function __construct()
    {
        jimport('joomla.filesystem.file');
        jimport('joomla.filesystem.folder');
    }

    /**
     * @param array $data Data parameters
     *
     * @return mixed|string
     */
    public function getPage($data)
    {
        $postId = $data['pageId'];
        $postTitle = $data['pageTitle'];
        $db = JFactory::getDBO();
        $query = $db->getQuery(true);
        $query->select('*');
        $query->from('#__content');
        $query->where('id = ' . $postId);
        $db->setQuery($query);
        $list = $db->loadObjectList();
        $result = array();
        if (count($list) > 0) {
            $item = $list[0];
            if ($item->state == 2) {
                // change article status to publish from draft
                list($title, $alias) = $this->_generateNewTitle($item->catid, array('title' => $postTitle));
                $query = $db->getQuery(true);
                $query->update('#__content');
                $query->set('state=1');
                $query->set('title=' . $db->quote($title));
                $query->set('alias=' . $db->quote($alias));
                $query->where('id=' . $item->id);
                $db->setQuery($query);
                $db->query();
            }
            $result = $this->_getPage($item);
        }
        return $this->_response(
            array(
                'result' => 'done',
                'data' => $result,
            )
        );
    }

    /**
     * @param array $data Data parameters
     *
     * @return mixed|string
     */
    public function getSitePosts($data) {
        return $this->_response(
            array(
                'result' => 'done',
                'data' => $this->getSitePostsResult($data)
            )
        );
    }

    /**
     * @param array $data Data parameters
     *
     * @return array
     */
    public function getSitePostsResult($data)
    {
        $result = array();

        $options = isset($data['options']) ? $data['options'] : array();
        $pageId = isset($options['pageId']) ? $options['pageId'] : null;

        if (isset($options['page'])) {

            $db = JFactory::getDBO();
            $query = $db->getQuery(true);
            $query->select('*');
            $query->from('#__content');
            $query->where('state = 1');
            $query->order('created', 'desc');
            $query->where('id in (' . $options['page'] . ')');
            $db->setQuery($query);
            $list = $db->loadObjectList();
            $posts = $this->getPosts($list);

            return array(
                'posts' => array(
                    'text' => $posts[0]['text'],
                    'url' => $this->getArticleUrlById($list[0]->id),
                )
            );
        }

        $posts = array();
        if (isset($options['pageNumber'])) {
            $pageSize = 20;
            $pageNumber = isset($options['pageNumber']) ? (int)$options['pageNumber'] : 1;

            $db = JFactory::getDBO();
            $query = $db->getQuery(true);
            $query->select('*');
            $query->from('#__content');
            $query->where('state = 1');
            $query->order('created', 'desc');
            $sectionsPageIds = UpageModelsSections::getAllPageIds();
            if (count($sectionsPageIds) > 0) {
                $query->where('id not in (' . implode(',', $sectionsPageIds) . ')');
            }
            $db->setQuery($query, ($pageNumber - 1) * $pageSize, $pageSize);
            $list = $db->loadObjectList();


            $posts = $this->getPosts($list);
            if (count($posts) < $pageSize) {
                $result['nextPage'] = 0;
                $result['isMultiplePages'] = false;
            } else {
                $result['nextPage'] = $pageNumber + 1;
                $result['isMultiplePages'] = true;
            }
        }

        $products = array();
        if (isset($options['productsPageNumber'])) {
            $productsSize = 20;
            $productsPageNumber = (int)$options['productsPageNumber'];
            $products = $this->getProducts(null, $productsSize, $productsPageNumber);
            if (count($products) < $productsSize) {
                $result['nextProductsPage'] = 0;
                $result['isMultipleProducts'] = false;
            } else {
                $result['nextProductsPage'] = $productsPageNumber + 1;
                $result['isMultipleProducts'] = true;
            }
        }

        $items = array_merge($posts, $products);

        $result['posts'] = $items;

        $images = array();
        if (isset($options['imagesPageNumber'])) {
            $imagesSize = 20;
            $imagesPageNumber = (int)$options['imagesPageNumber'];
            $images = $this->getImagesFromMedia($imagesSize, $imagesPageNumber);
            if (count($images) < $imagesSize) {
                $result['nextImagesPage'] = 0;
                $result['isMultipleImages'] = false;
            } else {
                $result['nextImagesPage'] = $imagesPageNumber + 1;
                $result['isMultipleImages'] = true;
            }
        }
        $result['images'] = $images;

        return $result;
    }

    /**
     * @param null $cids               Ids array
     * @param int  $productsSize       Count products
     * @param int  $productsPageNumber Limit parameter
     *
     * @return array
     */
    public function getProducts($cids = null, $productsSize = 0, $productsPageNumber = 0)
    {
        $result = array();

        if (!JComponentHelper::isInstalled('com_virtuemart')) {
            return $result;
        }

        if (!JComponentHelper::getComponent('com_virtuemart', true)->enabled) {
            return $result;
        }

        $categoryId = 0;
        $imgAmount = 5;

        if (!class_exists('VmConfig')) {
            include_once JPATH_ROOT . '/administrator/components/com_virtuemart/helpers/config.php';
        }
        if (!class_exists('vmLanguage')) {
            include_once VMPATH_ADMIN . '/helpers/vmlanguage.php';
        }

        VmConfig::loadConfig();
        vmLanguage::loadJLang('com_virtuemart');

        if (!class_exists('VmModel')) {
            include_once VMPATH_ADMIN . '/helpers/vmmodel.php';
        }

        $productModel = VmModel::getModel('product');
        $ids = $productModel->sortSearchListQuery(true, $categoryId);
        if ($productsSize) {
            $ids = array_slice($ids, ($productsPageNumber - 1) * $productsSize, $productsSize);
        }
        $products = $productModel->getProducts($ids);
        $productModel->addImages($products, $imgAmount);
        $currency = CurrencyDisplay::getInstance();

        foreach ($products as $product) {
            if ($cids && !in_array($product->id, $cids)) {
                continue;
            }
            $item = array(
                'postType' => 'product',
                'id' => 'cms_p_' . $product->id,
                'h1' => array(array('content' => $product->product_name, 'type' => 'h1')),
                'images' => array(),
                'text' => array(array('content' => $product->product_desc))
            );

            foreach ($product->images as $image) {
                $filePath = JPATH_ROOT . '/' . $image->file_url;
                $info = @getimagesize($filePath);
                $item['images'][] = array('sizes' => array(array(
                    'height' => @$info[1],
                    'url' => str_replace(JPATH_SITE, $this->getHomeUrl(), $filePath),
                    'width' => @$info[0],
                )), 'type' => 'image');
            }
            $priceText = $currency->createPriceDiv('salesPrice', 'COM_VIRTUEMART_PRODUCT_SALESPRICE', $product->prices, true);
            $item['h2'] = array(array('content' => $priceText, 'type' => 'h2'));

            $result[] = $item;
        }
        return $result;
    }

    /**
     * @param array $posts Cms posts
     *
     * @return array
     */
    public function getPosts($posts)
    {
        $result = array();

        if (count($posts) < 1) {
            return $result;
        }

        foreach ($posts as $key => $item) {
            $post = array(
                'url' => $this->getArticleUrlById($item->id),
                'date' => $item->created,
                'h1' => array(array('content' => $item->title, 'type' => 'h1')),
                'images' => array(),
            );
            $post['id'] = 'cms_' . $item->id;
            $content = $item->introtext . $item->fulltext;
            // third-party plugins
            $content = JHtml::_('content.prepare', $content);
            // themler shortcodes plugin
            $scpath = JPATH_PLUGINS . '/content/themlercontent/lib/Shortcodes.php';
            if (file_exists($scpath) && $content) {
                include_once $scpath;
                $content = DesignerShortcodes::process($content);
            }

            UpageModelsActions::$postImages = array();

            $images = json_decode($item->images, true);
            $imgsContent = isset($images['image_intro']) ? ('<img src="' . $images['image_intro'] . '" />') : '';
            $imgsContent .= isset($images['image_fulltext']) ? ('<img src="' . $images['image_fulltext'] . '" />') : '';
            $this->prepareImages($imgsContent);

            $content = $this->prepareImages($content, true);
            $post['text'] = array(array('content' => $content));

            foreach (UpageModelsActions::$postImages as $image) {
                $info = @getimagesize($image);
                $post['images'][] = array('sizes' => array(array(
                    'height' => @$info[1],
                    'url' => str_replace(JPATH_SITE, $this->getHomeUrl(), $image),
                    'width' => @$info[0],
                )), 'type' => 'image');
            }
            $result[] = $post;
        }
        return $result;
    }

    /**
     * @param string $content   Page content
     * @param bool   $appendNew Append new images
     *
     * @return mixed
     */
    public function prepareImages($content, $appendNew = false)
    {
        if ('' == $content) {
            return $content;
        }
        if ($appendNew == false) {
            UpageModelsActions::$postImages = array();
        }
        $regexs = array('/src=["\']?([^\'"]+)["\']/', '/url\((["\']?([^\'"]*?)["\']?)\)/', '/image=["\']?([^\'"]*)["\']/');
        foreach ($regexs as $regex) {
            $content = preg_replace_callback($regex, array(&$this, '_proccessImages'), $content);
        }
        return $content;
    }

    /**
     * @param array $match Match for images
     *
     * @return mixed
     */
    private function _proccessImages($match)
    {
        $full = $match[0];
        $path = $match[1];

        if (preg_match('/^' . htmlentities('"') . '(.+)' . htmlentities('"') . '$/', $path, $newmatch)) {
            $path = $newmatch[1];
        }

        return $this->_proccessImage($path, $full);
    }

    /**
     * @var array
     */
    public static $postImages = array();

    /**
     * @param string $path Image path
     * @param string $full Full path
     *
     * @return mixed
     */
    private function _proccessImage($path, $full)
    {
        $root = $this->getHomeUrl();
        if (preg_match('/^http/', $path) && strpos($full, $root) == false) {
            return $full;
        }

        if ('' !== $path) {
            $firstSymbol = '';
            if ($path[0] == '/' || $path[0] == '\\') {
                $path = substr($path, 1);
                $firstSymbol = $path[0];
            }

            if (file_exists(JPATH_SITE . '/' . $path) && !in_array(JPATH_SITE . '/' . $path, UpageModelsActions::$postImages)) {
                UpageModelsActions::$postImages[] = JPATH_SITE . '/' . $path;
                return str_replace($firstSymbol . $path, $root . '/' . $path, $full);
            }

            if (strpos($path, $root) !== -1) {
                $file = str_replace($root, JPATH_SITE, $path);
                if (file_exists($file) && !in_array($file, UpageModelsActions::$postImages)) {
                    UpageModelsActions::$postImages[] = $file;
                }
                return $full;
            }
        }
        return $full;
    }

    /**
     * @param int $imagesSize       Count images
     * @param int $imagesPageNumber Limit parameter
     *
     * @return array
     */
    public function getImagesFromMedia($imagesSize, $imagesPageNumber)
    {
        $mediaPosts = array();
        $params = JComponentHelper::getParams('com_media');
        $root = str_replace(DIRECTORY_SEPARATOR, '/', JPATH_ROOT);
        $imagesPath = $root . '/' . $params->get('image_path', 'images');
        if (file_exists($imagesPath)) {
            $fileList = JFolder::files($imagesPath, '\.jpg|\.png|\.gif|\.bmp|\.jpeg|\.ico', true, true);
            $fileList = array_slice($fileList, ($imagesPageNumber - 1) * $imagesSize, $imagesSize);
            foreach ($fileList as $key => $file) {
                $mediaPost = array(
                    'h1' => array(array('content' => 'Image' . ++$key)),
                    'images' => array(),
                    'id' => basename($file)
                );
                $path = str_replace(DIRECTORY_SEPARATOR, '/', JPath::clean($file));
                $info = @getimagesize($path);
                $mediaPost['images'][] = array('sizes' => array(array(
                    'height' => @$info[1],
                    'url' => str_replace($root, $this->getHomeUrl(), $path),
                    'width' => @$info[0],
                )));
                $mediaPost['postType'] = 'image';
                $mediaPosts[] = $mediaPost;
            }
        }
        return $mediaPosts;
    }

    /**
     * Get site object by page id
     *
     * @return array
     */
    public function getSite()
    {
        $config = UpageHelpersUpage::getUpageConfig();
        $hideBacklink = isset($config['hideBacklink']) ? $config['hideBacklink'] : false;

        $site = array(
            'id' => '1',
            'isFullLoaded' => true,
            'items' => array(),
            'order' => 0,
            'publicUrl' => $this->getHomeUrl(),
            'status' => 2,
            'title' => JFactory::getConfig()->get('sitename', 'My Site'),
            'settings' => json_encode(
                array(
                    'showBrand' => $hideBacklink ? 'false' : 'true',
                )
            ),
        );

        $db = JFactory::getDBO();
        $query = $db->getQuery(true);
        $query->select('*');
        $query->from('#__content');
        $sectionsPageIds = UpageModelsSections::getAllPageIds();
        if (count($sectionsPageIds) > 0) {
            $query->where('id in (' . implode(',', $sectionsPageIds) . ')');
        }
        $query->order('created', 'desc');
        $db->setQuery($query);
        $list = $db->loadObjectList();

        $pages = array();
        foreach ($list as $key => $item) {
            $pages[] = $this->_getPage($item);
        }

        $site['items'] = $pages;
        return $site;
    }

    /**
     * @param string $id Page id
     *
     * @return string
     */
    public function getPageHtml($id = '')
    {
        $pageId = $id ? $id : JRequest::getInt('pageId');
        $html = '';
        $sectionObject = UpageModelsSections::getSectionsObject($pageId);
        if ($sectionObject) {
            $props = unserialize(base64_decode($sectionObject->props));
            $html = isset($props['html']) ? $props['html'] : '';
            $html = UpageModelsSections::processSectionsHtml($html);
        }
        return $html;
    }

    /**
     * @param object $postObject Cms post object
     *
     * @return array
     */
    private function _getPage($postObject)
    {
        $head = null;
        $sectionObject = UpageModelsSections::getSectionsObject($postObject->id);
        if ($sectionObject) {
            $props = unserialize(base64_decode($sectionObject->props));
            $head = isset($props['head']) ? $props['head'] : '';
        }
        $domain = JRequest::getVar('domain', '');
        $current = dirname(dirname((JURI::current())));
        $adminPanelUrl = $current . '/administrator';
        return array(
            'siteId' => '1',
            'title' => $postObject->title,
            'publicUrl' => $this->getArticleUrlById($postObject->id),
            'canShare' => false,
            'html' => null,
            'head' => $head,
            'keywords' => null,
            'imagesUrl' => array(),
            'id' => (string) $postObject->id,
            'order' => 0,
            'status' => 2,
            'editorUrl' => $adminPanelUrl . '/index.php?option=com_upage&autostart=1&postid=' . $postObject->id . ($domain ? '&domain=' . $domain : ''),
            'htmlUrl' => $adminPanelUrl . '/index.php?option=com_upage&controller=actions&action=getPageHtml&pageId=' . $postObject->id
        );
    }

    /**
     * @param array $data Data parameters
     *
     * @return bool|mixed|string
     */
    public function uploadImage($data)
    {
        $imagesPaths = $this->getImagesPaths();
        $file = $_FILES['async-upload'];

        $type = @exif_imagetype($file['tmp_name']);

        if (!$type) {
            return $this->_response(
                array(
                    'status' => 'done',
                    'upload_id' => '',
                    'image_url' => ''
                )
            );
        }
        switch ($type) {
        case IMAGETYPE_GIF:
            $ext = 'gif';
            break;
        case IMAGETYPE_JPEG:
            $ext = 'jpg';
            break;
        case IMAGETYPE_PNG :
            $ext = 'png';
            break;
        case IMAGETYPE_BMP :
            $ext = 'bmp';
            break;
        default:
            $ext = 'jpg';
        }

        do {
            $name = md5(microtime() . rand(0, 9999));
            $file['filepath'] = $imagesPaths['realpath'] . '/' . $name . '.' . $ext;
        } while (file_exists($file['filepath']));

        $objectFile = new JObject($file);
        if (!JFile::upload($objectFile->tmp_name, $objectFile->filepath)) {
            // Error in upload
            JError::raiseWarning(100, JText::_('Unable to upload file'));
            return false;
        }
        $imagesUrl = str_replace(JPATH_ROOT, $this->getHomeUrl(), $file['filepath']);
        $imagesUrl = str_replace('\\', '/', $imagesUrl);
        return $this->_response(
            array(
                'status' => 'done',
                'upload_id' => '',
                'image_url' => $imagesUrl
            )
        );
    }

    /**
     * @param array $data Data parameters
     *
     * @return mixed|string
     */
    public function updateManifest($data) {
        $manifest = $data['manifest'];
        $version = $data['version'];
        $domain = $data['domain'];
        UpageModelsManifests::setManifest($version, $manifest);

        if ($domain) {
            $domain = '&domain=' . $domain;
        }
        if (($res = UpageModelsManifests::getManifest()) != '') {
            $version = '&ver=' . $res->version;
        }

        return $this->_response(
            array(
                'result' => 'done',
                'startUrl' => dirname(dirname(JURI::current())) . '/administrator/index.php?option=com_upage' . $domain . $version
            )
        );
    }

    /**
     * @return string
     */
    public function getManifest() {
        $manifest = UpageModelsManifests::getManifest();
        return $manifest ? $manifest->manifest : '';
    }

    /**
     * @param array $data Data parameters
     */
    public function savePageType($data) {
        $id = $data['pageId'];
        $type = $data['pageType'];

        $sectionsObject = UpageModelsSections::getSectionsObject($id);
        if ($sectionsObject) {
            $props = unserialize(base64_decode($sectionsObject->props));
            $props['pageView'] = $type;

            $db = JFactory::getDBO();
            $query = $db->getQuery(true);
            $query->update('#__upage_sections');
            $query->set($db->quoteName('props') . '=' . $db->quote(base64_encode(serialize($props))));
            $query->where('page_id=' . $query->escape($id));
            $db->setQuery($query);
            $db->query();
        }
    }

    /**
     * @param array $data Data parameters
     *
     * @return mixed|string
     */
    public function saveSiteSettings($data)
    {
        $settings = isset($data['settings']) && is_array($data['settings']) ? $data['settings'] : array();
        if (isset($settings['showBrand'])) {
            $this->saveConfig(array('hideBacklink' => $settings['showBrand'] === 'true' ? false : true));
        }
        return $this->_response(
            array(
                'result' => 'done'
            )
        );
    }

    /**
     * @param array $data Data parameters
     *
     * @return mixed|string
     */
    public function savePage($data)
    {
        if (!isset($data['id']) || !isset($data['data'])) {
            return $this->_response(
                array(
                    'status' => 'error',
                    'message' => 'post parameter missing',
                )
            );
        }

        $isPreview = isset($data['isPreview']) && $data['isPreview'] == 'true' ? true : false;

        $pageId = $data['id'];
        $pageTitle = isset($data['title']) ? $data['title'] : '';
        if ($pageId == '-1') {
            $article = $this->createPost(array('title' => $pageTitle));
            $pageId = $article->id;

            $session = JFactory::getSession();
            $registry = $session->get('registry');
            $registry->set('com_content.edit.article.id', $article->id);
        }

        // properties
        $opt = isset($data['data']) ? $data['data'] : '';
        $html           = isset($opt['html']) ? $opt['html'] : '';
        $publishHtml    = isset($opt['publishHtml']) ? $opt['publishHtml'] : '';
        $head           = isset($opt['head']) ? $opt['head'] : '';
        $bodyClass      = isset($opt['bodyClass']) ? $opt['bodyClass'] : '';
        $fonts          = isset($opt['fonts']) ? $opt['fonts'] : '';
        $backlink         = isset($opt['backlink']) ? $opt['backlink'] : '';

        $keywords       = isset($data['keywords']) ? $data['keywords'] : '';
        $description    = isset($data['description']) ? $data['description'] : '';
        $metaTags       = isset($data['metaTags']) ? $data['metaTags'] : '';
        $customHeadHtml = isset($data['customHeadHtml']) ? $data['customHeadHtml'] : '';
        $titleInBrowser = isset($data['titleInBrowser']) ? $data['titleInBrowser'] : '';

        $props = array(
            'html' => $html,
            'publishHtml' => $publishHtml,
            'backlink' => $backlink,
            'head' => $head,
            'bodyClass' => $bodyClass,
            'fonts' => $fonts,
            'keywords' => $keywords,
            'description' => $description,
            'metaTags' => $metaTags,
            'customHeadHtml' => $customHeadHtml,
            'titleInBrowser' => $titleInBrowser
        );

        $sectionsObject = UpageModelsSections::getSectionsObject($pageId);
        $db = JFactory::getDBO();
        $query = $db->getQuery(true);
        if ($sectionsObject) {
            $oldProps = unserialize(base64_decode($sectionsObject->props));
            if ($isPreview) {
                $oldProps['preview'] = $props;
                $props = $oldProps;
            } else {
                $props['preview'] = '';
            }
            $props['pageView'] = isset($oldProps['pageView']) ? $oldProps['pageView'] : 'landing_with_header_footer';
            $query->update('#__upage_sections');
            $query->set($db->quoteName('props') . '=' . $db->quote(base64_encode(serialize($props))));
            $query->where('page_id=' . $query->escape($pageId));
        } else {
            $props['preview'] = $isPreview ? $props : '';
            $props['pageView'] = 'landing_with_header_footer';
            $query->insert('#__upage_sections');
            $query->set($db->quoteName('props') . '=' .  $db->quote(base64_encode(serialize($props))));
            $query->set($db->quoteName('page_id') . '=' . $db->quote($pageId));
        }
        $db->setQuery($query);
        $db->query();

        return $this->getPage(array('pageId' => $pageId, 'pageTitle' => $pageTitle, 'isPreview' => $isPreview));
    }

    /**
     * @param string $name Category name
     *
     * @return mixed
     */
    private function _getCategoryByName($name)
    {
        $categoryMapper = Upage_Data_Mappers::get('category');
        $res = $categoryMapper->find(array('title' => $name));

        if (count($res) > 0) {
            return $res[0]->id;
        }

        $categoryObj = $categoryMapper->create();
        $categoryObj->title = $name;
        $categoryObj->extension = 'com_content';
        $categoryObj->metadata = $this->_paramsToString(array('robots' => '', 'author' => '', 'tags' => ''));
        $status = $categoryMapper->save($categoryObj);
        if (is_string($status)) {
            trigger_error($status, E_USER_ERROR);
        }
        return $categoryObj->id;
    }

    /**
     * @param array $data Data parameters
     *
     * @return mixed
     */
    public function createPost($data = array())
    {
        $content = isset($data['content']) ? $data['content'] : '';

        $images = '';
        if (isset($data['images'])) {
            foreach ($data['images'] as $img) {
                $images .= '<img src="' . $img .'">' . PHP_EOL;
            }
        }
        $content = $images . $content;

        $contentMapper = Upage_Data_Mappers::get('content');
        $article = $contentMapper->create();
        $article->catid = $this->_getCategoryByName($this->_defaultCategory);

        list($title, $alias) = $this->_generateNewTitle($article->catid, $data);

        $article->title = $title;
        $article->alias = $alias;
        $article->introtext = $content;
        if (isset($data['state'])) {
            $article->state = $data['state'];
        }
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
        $status = $contentMapper->save($article);
        if (is_string($status)) {
            trigger_error($status, E_USER_ERROR);
        }

        return $article;
    }

    /**
     * @param int   $catId Category id
     * @param array $data  Data
     *
     * @return array
     */
    private function _generateNewTitle($catId, $data) {
        $title = isset($data['title']) ? strip_tags($data['title']) : (isset($data['subpage']) ? 'SubPage' : 'Page');
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
        return array($title, $alias);
    }

    /**
     * @param string|array $result Result
     *
     * @return mixed|string
     */
    private function _response($result)
    {
        if (is_string($result)) {
            $result = array('result' => $result);
        }
        return json_encode($result);
    }

    /**
     * @param array $params Parameters
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
     * @param string $string Parameters string
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
     * @return array
     */
    public function getImagesPaths()
    {
        $imagesFolder = JPATH_ROOT . '/images';
        if (!file_exists($imagesFolder)) {
            JFolder::create($imagesFolder);
        }

        $upageContentFolder = JPath::clean(implode('/', array($imagesFolder, 'upage-images')));
        if (!file_exists($upageContentFolder)) {
            JFolder::create($upageContentFolder);
        }

        $upageContentFolderUrl = $this->getHomeUrl() . '/images/upage-images';

        return array('realpath' => $upageContentFolder, 'url' => $upageContentFolderUrl);
    }

    /**
     * @return string
     */
    public function getHomeUrl()
    {
        return dirname(dirname(JURI::current()));
    }

    /**
     * @param int $id Article id
     *
     * @return string
     */
    public function getArticleUrlById($id)
    {
        return $this->getHomeUrl() . '/index.php?option=com_content&view=article&id=' . $id;
    }

    /**
     * @param array $data Data parameters
     *
     * @return mixed|string
     * @throws Exception
     */
    public function importData($data)
    {
        $fileName = isset($data['filename']) ? $data['filename'] : '';
        $isLast = isset($data['last']) ? $data['last'] : '';

        if ('' === $fileName) {
            throw new Exception("Empty filename");
        } else {
            $unzipHere = false;
            $tmp = JPATH_SITE . '/tmp';
            $images = JPATH_SITE . '/images';
            if (file_exists($tmp) && is_writable($tmp)) {
                $unzipHere = $tmp . '/' . $fileName;
            }
            if (!$unzipHere && file_exists($images) && is_writable($images)) {
                $unzipHere = $images . '/' . $fileName;
            }
            if (!$unzipHere) {
                throw new Exception("Upload dir $unzipHere don't writable");
            }
            $result = $this->_uploadFileChunk($unzipHere, $isLast);
            if ($result['status'] == 'done') {
                $contentDir = $this->_contentUnZip($unzipHere);
            }
        }

        $loader = new Upage_Data_Loader();
        $loader->load($contentDir . '/content/content.json');
        $loader->execute($_POST);

        return $this->_response(
            array(
                'result' => 'done'
            )
        );
    }

    /**
     * @param array $data Data parameters
     *
     * @return mixed|string
     */
    public function saveConfig($data) {
        $db = JFactory::getDBO();
        $query = $db->getQuery(true);
        $query->select('params')->from('#__upage_params')->where('name=' . $query->quote('com_upage'));
        $db->setQuery($query);

        $registry = new JRegistry();


        $registry->loadString($db->loadResult());
        $params = $registry->toArray();

        $excludeParameters = array('option', 'action', 'controller');
        foreach ($data as $key => $value) {
            if (in_array($key, $excludeParameters)) {
                continue;
            }
            $params[$key] = $value;
        }

        $registry->loadArray($params);
        $str = $registry->toString();

        $query = $db->getQuery(true);
        $query->update('#__upage_params')->set(
            $db->quoteName('params') . '=' .
            $db->quote($str)
        )->where('name=' . $query->quote('com_upage'));
        $db->setQuery($query);
        $db->query();

        return $this->_response(
            array(
                'result' => 'done'
            )
        );
    }

    /**
     * @param string $uploadPath Upload path
     * @param bool   $isLast     Last chunk flag
     *
     * @return array
     */
    private function _uploadFileChunk($uploadPath, $isLast)
    {
        if (!isset($_FILES['chunk']) || !file_exists($_FILES['chunk']['tmp_name'])) {
            trigger_error('Empty chunk data', E_USER_ERROR);
        }
        $contentRange = $_SERVER['HTTP_CONTENT_RANGE'];
        if ('' === $contentRange && '' === $isLast) {
            trigger_error('Empty Content-Range header', E_USER_ERROR);
        }

        $rangeBegin = 0;

        if ($contentRange) {
            $contentRange = str_replace('bytes ', '', $contentRange);
            list($range, $total) = explode('/', $contentRange);
            list($rangeBegin, $rangeEnd) = explode('-', $range);
        }

        $tmpPath = dirname($uploadPath) . '/uptmp/' . basename($uploadPath);
        JFolder::create(dirname($tmpPath));

        $f = fopen($tmpPath, 'c');

        if (flock($f, LOCK_EX)) {
            fseek($f, (int) $rangeBegin);
            fwrite($f, file_get_contents($_FILES['chunk']['tmp_name']));

            flock($f, LOCK_UN);
            fclose($f);
        }

        if ($isLast) {
            if (file_exists($uploadPath)) {
                JFile::delete($uploadPath);
            }
            JFolder::create(dirname($uploadPath));
            JFile::move($tmpPath, $uploadPath);
            JFolder::delete(dirname($tmpPath));

            return array(
                'status' => 'done'
            );
        } else {
            return array(
                'status' => 'processed'
            );
        }
    }

    /**
     * @param string $zipPath Zip path
     *
     * @return string
     */
    private function _contentUnZip($zipPath)
    {
        $zip = new ZipArchive;
        $tmpdir = dirname($zipPath) . '/' . md5(round(microtime(true)));
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($tmpdir);
            $zip->close();
        }
        JFile::delete($zipPath);
        return $tmpdir;
    }

}