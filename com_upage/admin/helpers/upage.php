<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_upage
 */

defined('_JEXEC') or die;

/**
 * Class UpageHelpersUpage
 */
class UpageHelpersUpage
{
    /**
     * Extension type name
     *
     * @var string
     */
    public static $extension = 'com_upage';

    /**
     * Get actions for upage component
     *
     * @return JObject
     */
    public static function getActions()
    {
        $user = JFactory::getUser();
        $result = new JObject;

        $assetName = 'com_upage';
        $level = 'component';

        $actions = JAccess::getActions('com_upage', $level);

        foreach ($actions as $action) {
            $result->set($action->name, $user->authorise($action->name, $assetName));
        }

        return $result;
    }

    /**
     * Check site ssl or not
     *
     * @return bool
     */
    public static function isSSL()
    {
        $isSSL = false;

        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
            $_SERVER['HTTPS'] = 'on';
        }

        if (isset($_SERVER['HTTPS'])) {
            if ('on' == strtolower($_SERVER['HTTPS'])) {
                $isSSL = true;
            }
            if ('1' == $_SERVER['HTTPS']) {
                $isSSL = true;
            }
        } elseif (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) {
            $isSSL = true;
        }
        return $isSSL;
    }

    /**
     * Get domain get parameter
     *
     * @return mixed|string
     */
    public static function getDomainParam()
    {
        if (!isset($_GET['domain'])) {
            return '';
        }

        $domain = preg_replace('#(.*)\/$#', '$1', urldecode($_GET['domain']));
        if (self::isSSL()) {
            $domain = str_replace('http://', 'https://', $domain);
        } else {
            $domain = str_replace('https://', 'http://', $domain);
        }
        return $domain;
    }

    /**
     * Get files for upage starting
     *
     * @return array
     */
    public static function getStartFiles()
    {
        $hash = md5(round(microtime(true)));
        $assets = dirname(dirname((JURI::current()))) . '/administrator/components/com_upage/assets';

        $domain = self::getDomainParam();

        return array(
            'editor' => $assets . '/js/editor.js?version=' . $hash,
            'loader' => $domain ? $domain . '/Editor/loader.js' : self::getLoader()
        );
    }

    /**
     * Get custom loader file
     *
     * @return string
     */
    public static function getLoader() {
        $manifest = UpageModelsManifests::getManifest();
        if ($manifest == '') {
            return '';
        }
        if (!preg_match('#(.*)/loader\.js#', $manifest->manifest, $match)) {
            return '';
        }
        return $match[0];
    }

    /**
     * Get actions list for upage app
     *
     * @return array
     */
    public static function getEditorSettings()
    {
        $current = dirname(dirname((JURI::current())));
        $index = $current . '/administrator/index.php?option=com_upage&controller=actions';
        return array(
            'actions' => array(
                'uploadImage' => $index . '&action=uploadImage',
                'savePage' => $index . '&action=savePage',
                'getSite' => $index . '&action=getSite',
                'getSitePosts' => $index . '&action=getSitePosts',
                'getPage' => $index . '&action=getPage',
                'updateManifest' => $index . '&action=updateManifest',
                'getManifest' => $index . '&action=getManifest',
                'saveSiteSettings' => $index . '&action=saveSiteSettings',
            ),
            'uploadImageOptions' => array(
                'formFileName' => 'async-upload'
            ),
            'dashboardUrl' => $current . '/administrator/',
            'editPostUrl' => $current . '/administrator/index.php?option=com_content&view=article&layout=edit&id={id}'
        );
    }

    /**
     * Get cms custom settings
     *
     * @return array
     */
    public static function getCmsSettings()
    {
        $manifest = UpageModelsManifests::getManifest();
        return array(
            'defaultImageUrl' => dirname(dirname((JURI::current()))) . '/administrator/components/com_upage/assets/images/upage-images/default-image.jpg',
            'manifestVersion' => $manifest ? $manifest->version : '',
            'isFirstStart' => false
        );
    }

    /**
     * Get upage properties
     *
     * @return mixed
     */
    public static function getUpageConfig()
    {
        $db = JFactory::getDBO();
        $query = $db->getQuery(true);
        $query->select('params')->from('#__upage_params')->where('name=' . $query->quote('com_upage'));
        $db->setQuery($query);

        $registry = new JRegistry();
        $registry->loadString($db->loadResult());
        return $registry->toArray();
    }

}