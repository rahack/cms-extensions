<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_upage
 */

defined('_JEXEC') or die;

/**
 * Class UpageModelsSections
 */
class UpageModelsSections
{
    /**
     * @var array
     */
    public static $imagesData = array();

    /**
     * @var string
     */
    public static $imgRe = '\<img[^\>]+src\s*=\s*(["\']([^"\']+)["\'][^\>]*?)\>';

    /**
     * @var array
     */
    public static $typePlaceholders = array('id', 'author', 'date', 'category', 'comments', 'link', 'image', 'h1', 'text');

    /**
     * @var string
     */
    public static $pageContent = '';

    /**
     * @var bool
     */
    public static $liveSite = false;

    /**
     * @param string $content Page content
     *
     * @return mixed
     */
    public static function processSectionsHtml($content) {
        UpageModelsSections::$pageContent = $content;
        $content = preg_replace_callback('/\{(' . implode('|', UpageModelsSections::$typePlaceholders) . ')_(\d+)(\surl=\'([^\']*)\')?(\slink=\'([^\']*)\')?\}/', array('UpageModelsSections', 'parsePlaceholders'), $content);

        $content = UpageModelsSections::processDefaultImage($content);
        $content = UpageModelsSections::processForm($content);

        return $content;
    }

    /**
     * Processing of default image path
     *
     * @param string $content Page content
     *
     * @return mixed
     */
    public static function processDefaultImage($content)
    {
        // replace default image placeholder
        $url = JURI::current() . 'administrator/components/com_upage/assets/images/upage-images/default-image.jpg';
        return str_replace('[image_default]', $url, $content);
    }

    /**
     * Processing of forms
     *
     * @param string $content Page content
     *
     * @return mixed
     */
    public static function processForm($content)
    {
        return preg_replace('/(<form[^>]*action=[\'\"]+).*?([\'\"]+)/', '$1index.php?option=com_upage&task=sendmail$2', $content);

    }

    /**
     * @param array $matches Match value
     *
     * @return mixed|string
     */
    public static function parsePlaceholders($matches)
    {
        $type = $matches[1];
        $postId = $matches[2];
        $isLink = isset($matches[5]) && $matches[6] == "true";

        $db = JFactory::getDBO();
        $query = $db->getQuery(true);
        $query->select('*');
        $query->from('#__content');
        $query->where('state = 1');
        $query->where('id = ' . $postId);
        $db->setQuery($query);
        $list = $db->loadObjectList();

        if (count($list) < 1) {
            return '';
        }

        $article = $list[0];

        $registry = new JRegistry;
        $registry->loadString($article->attribs);
        $article->params = $registry;

        $link = JURI::current() . 'index.php?option=com_content&view=article&id=' . $article->id;

        switch($type) {
        case 'id':
            return 'cms_' . $article->id;
            break;
        case 'author':
            return UpageModelsSections::getAuthor($article);
            break;
        case 'date':
            return UpageModelsSections::getDate($article);
            break;
        case 'category':
            return UpageModelsSections::getCategory($article);
            break;
        case 'comments':
            return 'Comments not supported';
            break;
        case 'link':
            return $link;
            break;
        case 'image':
            $image = UpageModelsSections::getImageUrl($article);
            return $isLink ? ($image . '" data-post-link="' . $link) : $image;
            break;
        case 'h1':
            return $article->title;
            break;
        case 'text':
            return preg_replace('/' . UpageModelsSections::$imgRe . '/', '', $text =  $article->introtext . $article->fulltext);
            break;
        }
    }

    /**
     * @param object $article Article object
     *
     * @return mixed|string
     */
    public static function getAuthor($article)
    {
        $db = JFactory::getDBO();
        $query = $db->getQuery(true);
        $query->select('name');
        $query->from('#__users');
        $query->where('id = ' . $article->created_by);
        $db->setQuery($query);

        if (!($author = $db->loadResult())) {
            return '';
        }

        if (UpageModelsSections::$liveSite) {
            ob_start();
            ?>
            <dd class="createdby" itemprop="author" itemscope itemtype="https://schema.org/Person">
                <?php $author = ($article->created_by_alias ? $article->created_by_alias : $author); ?>
                <?php $author = '<span itemprop="name">' . $author . '</span>'; ?>
                <?php echo JText::sprintf('COM_CONTENT_WRITTEN_BY', $author); ?>
            </dd>
            <?php
            return ob_get_clean();
        } else {
            return $author;
        }
    }

    /**
     * @param object $article Article object
     *
     * @return string
     */
    public static function getDate($article)
    {
        $formatedDate = $article->publish_up; //JText::sprintf('COM_CONTENT_PUBLISHED_DATE_ON', JHtml::_('date', $article->publish_up, JText::_('DATE_FORMAT_LC3')));
        if (UpageModelsSections::$liveSite) {
            ob_start();
            ?>
            <dd class="published">
                <span class="icon-calendar"></span>
                <time datetime="<?php echo JHtml::_('date', $article->publish_up, 'c'); ?>" itemprop="datePublished">
                    <?php echo $formatedDate; ?>
                </time>
            </dd>
            <?php
            return ob_get_clean();
        } else {
            return $formatedDate;
        }
    }

    /**
     * @param object $article Article object
     *
     * @return string
     */
    public static function getCategory($article)
    {
        $db = JFactory::getDBO();
        $query = $db->getQuery(true);
        $query->select('title AS category_title, alias AS category_alias, access AS category_access');
        $query->from('#__categories');
        $query->where('id = ' . $article->catid);
        $db->setQuery($query);

        if (!($category = $db->loadObject())) {
            return '';
        }
        $category->catslug = $category->category_alias ? ($article->catid . ':' . $category->category_alias) : $article->catid;

        if (UpageModelsSections::$liveSite) {
            ob_start();
            ?>
            <dd class="category-name">
                <?php $title = htmlspecialchars($category->category_title, ENT_COMPAT, 'UTF-8'); ?>
                <?php if ($article->params->get('link_category') && $category->catslug) : ?>
                    <?php $url = '<a href="' . JRoute::_(ContentHelperRoute::getCategoryRoute($category->catslug)) . '" itemprop="genre">' . $title . '</a>'; ?>
                    <?php echo JText::sprintf('COM_CONTENT_CATEGORY', $url); ?>
                <?php else : ?>
                    <?php echo JText::sprintf('COM_CONTENT_CATEGORY', '<span itemprop="genre">' . $title . '</span>'); ?>
                <?php endif; ?>
            </dd>
            <?php
            return ob_get_clean();
        } else {
            return $category->category_title;
        }
    }

    /**
     * @param object $article Article object
     *
     * @return mixed|string
     */
    public static function getImageUrl($article)
    {
        $postId = $article->id;

        if (!array_key_exists($postId, UpageModelsSections::$imagesData)) {
            // cms content
            $res = array();
            if ($article->images) {
                $imagesObj = is_array($article->images) ? $article->images : json_decode($article->images, true);
                if (isset($imagesObj['image_intro']) && $imagesObj['image_intro']) {
                    $res[] = $imagesObj['image_intro'];
                }
            }
            if (preg_match_all('/' . UpageModelsSections::$imgRe . '/', $article->introtext, $matches)) {
                $res = array_merge($res, $matches[2]);
            }
            UpageModelsSections::$imagesData[$postId]['cms'] = $res;
            // upage content
            $postfix = $postId;
            if (preg_match_all('/\{image_' . $postfix . '(\surl=\'([^\']*)\')?(\slink=\'([^\']*)\')?\}/', UpageModelsSections::$pageContent, $matches)) {
                UpageModelsSections::$imagesData[$postId]['upage'] = $matches[2];
            }
            UpageModelsSections::$imagesData[$postId]['diff'] = array_diff($matches[2], $res);
        }

        $defaultImageUrl = (UpageModelsSections::$liveSite ? JURI::current() : dirname(dirname(JURI::current())) . '/')  .
            'administrator/components/com_upage/assets/images/upage-images/default-image.jpg';
        if (array_key_exists($postId, UpageModelsSections::$imagesData)
            && array_key_exists('upage', UpageModelsSections::$imagesData[$postId])
            && count(UpageModelsSections::$imagesData[$postId]['upage']) > 0
        ) {
            $img = array_shift(UpageModelsSections::$imagesData[$postId]['upage']);
            if ($img == '[image_default]') {
                return  $defaultImageUrl;
            } else if (array_key_exists($img, array_flip(UpageModelsSections::$imagesData[$postId]['cms']))) {
                return $img;
            } else if (count(UpageModelsSections::$imagesData[$postId]['diff']) > 0) {
                return array_shift(UpageModelsSections::$imagesData[$postId]['diff']);
            } else {
                return  $defaultImageUrl;
            }
        } else {
            return '';
        }
    }

    /**
     * @param int $pageId Page id
     *
     * @return null
     */
    public static function getSectionsObject($pageId)
    {
        $db = JFactory::getDBO();
        $query = $db->getQuery(true);
        $query->select('*');
        $query->from('#__upage_sections');
        $query->where('page_id = ' . $pageId);
        $db->setQuery($query);
        $list = $db->loadObjectList();
        if (count($list) > 0) {
            return $list[0];
        } else {
            return null;
        }
    }

    /**
     * Clear preview page
     *
     * @param object $sectionsObject sections page object
     *
     * @return null
     */
    public static function clearPreview($sectionsObject) {
        $props = unserialize(base64_decode($sectionsObject->props));
        $props['preview'] = '';

        $db = JFactory::getDBO();
        $query = $db->getQuery(true);
        $query->update('#__upage_sections');
        $query->set($db->quoteName('props') . '=' . $db->quote(base64_encode(serialize($props))));
        $query->where('page_id=' . $query->escape($sectionsObject->page_id));
        $db->setQuery($query);
        $db->query();
    }

    /**
     * @return mixed
     */
    public static function getAllPageIds()
    {
        $db = JFactory::getDBO();
        $query = $db->getQuery(true);
        $query->select('page_id');
        $query->from('#__upage_sections');
        $db->setQuery($query);
        return $db->loadAssocList(null, 'page_id');
    }

    /**
     * @param object $sectionsObj sections page object
     *
     * @return mixed|string
     */
    public static function getSectionsScreenshots($sectionsObj)
    {
        if (!$sectionsObj) {
            return '';
        }

        $props = unserialize(base64_decode($sectionsObj->props));
        $publishHtml = isset($props['publishHtml']) ? $props['publishHtml'] : '';
        $bodyClass = isset($props['bodyClass']) ? $props['bodyClass'] : '';
        $head = isset($props['head']) ? $props['head'] : '';
        $fonts = isset($props['fonts']) ? $props['fonts'] : '';
        $publishHtml = UpageModelsSections::processSectionsHtml($publishHtml);
        preg_match_all('/<section[\s\S]+?<\/section>/', $publishHtml, $matches, PREG_SET_ORDER);
        $count = count($matches);
        if ($count > 4) {
            for ($i = 4; $i < $count; $i++) {
                $publishHtml = str_replace($matches[$i], '', $publishHtml);
            }
        }
        $assets = '/administrator/components/com_upage/assets';
        $upageCss = JURI::root(true) . $assets. '/css/upage.css';
        $ret = <<<EOF
<!DOCTYPE html>
<html>        
    <head>
    <style>
        body {
            cursor: pointer;
        }
    </style>
    <link rel="stylesheet" href="$upageCss">
    </head>
    <body class="$bodyClass">
        $fonts
        <style>
        $head
        </style>
        $publishHtml
    </body>
</html>
EOF;
        return json_encode($ret);
    }
}