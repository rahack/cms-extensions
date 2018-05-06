<?php

defined('_JEXEC') or die;

/**
 * Class Com_UpageInstallerScript
 */
class Com_UpageInstallerScript
{
    /**
     * Custom install operations
     *
     * @param object $parent Parent object
     */
    public function install($parent) {
        jimport('joomla.filesystem.folder');
        jimport('joomla.filesystem.file');
        
        $src = JPATH_ROOT . '/administrator/components/com_upage/assets/images/upage-images';
        
        $this->createFolder(JPATH_ROOT . '/images/upage-images');
        
        JFile::copy($src . '/default-image.jpg', JPATH_ROOT . '/images/upage-images/default-image.jpg');
    }

    /**
     * Create folder by path
     *
     * @param string $path Path for creating
     *
     * @return bool
     */
    public function createFolder($path)
    {
        if (JFolder::create($path)) {
            if (!JFile::exists($path . '/index.html')) {
                JFile::copy(JPATH_ROOT . '/components/index.html', $path . '/index.html');
            }
            return true;
        }
        return false;
    }

    /**
     * Update action for installing
     *
     * @param object $parent Parent object
     */
    public function update($parent)
    {
        return $this->install($parent);
    }

    /**
     * Postflight method for joomla core
     *
     * @param string $type   Extension type
     * @param object $parent Parent object
     *
     * @return bool
     */
    public function postflight($type, $parent)
    {
        $db = JFactory::getDbo();
        $db->setQuery('UPDATE `#__menu` SET `link`="index.php?option=com_upage&dashboard=1" WHERE `alias` = "com-upage" ');
        $db->execute();

        return true;
    }
}