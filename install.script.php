<?php

defined('_JEXEC') or die;

if (!class_exists('PlgSysteminstallerInstallerScript')) {
    /**
     * Class PlgSysteminstallerInstallerScript
     */
    class PlgSysteminstallerInstallerScript
    {
        /**
         * @var array
         */
        protected $versions = array(
            'PHP' => array (
                '5.2' => '5.2.1',
                '0' => '5.4.30' // Preferred version
            ),
            'Joomla!' => array (
                '3.3' => '3.3.0',
                '0' => '3.3.6' // Preferred version
            )
        );

        /**
         * @var array
         */
        protected $packages = array();

        /**
         * @var
         */
        protected $sourcedir;

        /**
         * @var
         */
        protected $installerdir;

        /**
         * @var
         */
        protected $manifest;

        /**
         * @var
         */
        protected $parent;

        /**
         * @param object $parent Prent object
         *
         * @return bool
         */
        public function install($parent)
        {
            $this->cleanBogusError();

            jimport('joomla.filesystem.file');
            jimport('joomla.filesystem.folder');
            jimport('joomla.installer.helper');

            if (!class_exists('UpageInstaller')) {
                include_once $this->installerdir . '/UpageInstaller.php';
            }

            $retval = true;
            ob_get_clean();

            // Cycle through items and install each
            if (count($this->manifest->items->children())) {
                foreach ($this->manifest->items->children() as $item) {
                    $folder = $this->sourcedir . '/' . $item->dirname;

                    if (is_dir($folder)) {
                        // if its actually a directory then fill it up
                        $package                = Array();
                        $package['dir']         = $folder;
                        $package['type']        = JInstallerHelper::detectType($folder);
                        $package['installer']   = new UpageInstaller();
                        $package['name']        = (string) $item->name;
                        $package['state']       = 'Success';
                        $package['description'] = (string) $item->description;
                        $package['msg']         = '';
                        $package['type']        = ucfirst((string) $item['type']);

                        $package['installer']->setItemInfo($item);

                        // add installer to static for possible rollback.
                        $this->packages[] = $package;

                        if (!@$package['installer']->install($package['dir'])) {
                            while ($error = JError::getError(true)) {
                                $package['msg'] .= $error;
                            }

                            UpageInstallerEvents::addMessage($package, UpageInstallerEvents::STATUS_ERROR, $package['msg']);
                            break;
                        }

                        if ($package['installer']->getInstallType() == 'install') {
                            UpageInstallerEvents::addMessage($package, UpageInstallerEvents::STATUS_INSTALLED);
                        } else {
                            UpageInstallerEvents::addMessage($package, UpageInstallerEvents::STATUS_UPDATED);
                        }
                    } else {
                        $package                = Array();
                        $package['dir']         = $folder;
                        $package['name']        = (string) $item->name;
                        $package['state']       = 'Failed';
                        $package['description'] = (string) $item->description;
                        $package['msg']         = '';
                        $package['type']        = ucfirst((string) $item['type']);

                        UpageInstallerEvents::addMessage(
                            $package,
                            UpageInstallerEvents::STATUS_ERROR, JText::_('JLIB_INSTALLER_ABORT_NOINSTALLPATH')
                        );
                        break;
                    }
                }
            } else {
                $parent->getParent()->abort(
                    JText::sprintf(
                        'JLIB_INSTALLER_ABORT_PACK_INSTALL_NO_FILES',
                        JText::_('JLIB_INSTALLER_' . strtoupper($this->route))
                    )
                );
            }
            return $retval;
        }

        /**
         * @param object $parent Parent object
         *
         * @return bool
         */
        public function update($parent)
        {
            return $this->install($parent);
        }

        /**
         * @param string $type   Type extension
         * @param object $parent Parent object
         *
         * @return bool
         */
        public function preflight($type, $parent)
        {
            $this->setup($parent);

            //Load Event Handler.
            if (!class_exists('UpageInstallerEvents')) {
                include_once $this->installerdir . '/UpageInstallerEvents.php';

                $dispatcher = JDispatcher::getInstance();
                $plugin = new UpageInstallerEvents($dispatcher);
                $plugin->setTopInstaller($this->parent->getParent());
            }

            // Check installer requirements.
            if (($requirements = $this->checkRequirements()) !== true) {
                UpageInstallerEvents::addMessage(
                    array('name' => ''),
                    UpageInstallerEvents::STATUS_ERROR,
                    implode('<br />', $requirements)
                );
                return false;
            }
        }

        /**
         * @param string $type   Type extension
         * @param object $parent Parent object
         */
        public function postflight($type, $parent)
        {
            $conf = JFactory::getConfig();
            $conf->set('debug', false);
            $parent->getParent()->abort();
        }

        /**
         * @param null $msg  Text message
         * @param null $type Type extension
         */
        public function abort($msg = null, $type = null)
        {
            if ($msg) {
                JError::raiseWarning(100, $msg);
            }
            foreach ($this->packages as $package) {
                $package['installer']->abort(null, $type);
            }
        }

        /**
         * @param object $parent Parent object
         */
        protected function setup($parent)
        {
            $this->parent       = $parent;
            $this->sourcedir    = $parent->getParent()->getPath('source');
            $this->manifest     = $parent->getParent()->getManifest();
            $this->installerdir = $this->sourcedir . '/installer';
        }

        /**
         * @return array|bool
         */
        protected function checkRequirements()
        {
            $errors = array();

            if (($error = $this->checkVersion('PHP', phpversion())) !== true) {
                $errors[] = $error;
            }

            if (($error = $this->checkVersion('Joomla!', JVERSION)) !== true) {
                $errors[] = $error;
            }

            return $errors ? $errors : true;
        }

        /**
         * @param string $name    Extension name
         * @param string $version Extension version
         *
         * @return bool|string
         */
        protected function checkVersion($name, $version)
        {
            $major = $minor = 0;
            foreach ($this->versions[$name] as $major => $minor) {
                if (!$major || version_compare($version, $major, '<')) {
                    continue;
                }

                if (version_compare($version, $minor, '>=')) {
                    return true;
                }
                break;
            }

            if (!$major) {
                $minor = reset($this->versions[$name]);
            }

            $recommended = end($this->versions[$name]);

            if (version_compare($recommended, $minor, '>')) {
                return sprintf(
                    '%s %s is not supported. Minimum required version is %s %s, but it is highly recommended to use %s %s or later version.',
                    $name,
                    $version,
                    $name,
                    $minor,
                    $name,
                    $recommended
                );
            } else {
                return sprintf(
                    '%s %s is not supported. Please update to %s %s or later version.',
                    $name,
                    $version,
                    $name,
                    $minor
                );
            }
        }

        /**
         * Clean bogus error
         */
        protected function cleanBogusError()
        {
            $errors = array();

            while (($error = JError::getError(true)) !== false) {
                if (!($error->get('code') == 1 && $error->get('level') == 2 && $error->get('message') == JText::_('JLIB_INSTALLER_ERROR_NOTFINDXMLSETUPFILE'))) {
                    $errors[] = $error;
                }
            }

            foreach ($errors as $error) {
                JError::addToStack($error);
            }

            $app               = new UpageInstallerJAdministratorWrapper(JFactory::getApplication());
            $enqueued_messages = $app->getMessageQueue();
            $other_messages    = array();

            if (!empty($enqueued_messages) && is_array($enqueued_messages)) {
                foreach ($enqueued_messages as $enqueued_message) {
                    if (!($enqueued_message['message'] == JText::_('JLIB_INSTALLER_ERROR_NOTFINDXMLSETUPFILE') && $enqueued_message['type']) == 'error') {
                        $other_messages[] = $enqueued_message;
                    }
                }
            }

            $app->setMessageQueue($other_messages);
        }
    }

    if (!class_exists('UpageInstallerJAdministratorWrapper')) {
        /**
         * Class UpageInstallerJAdministratorWrapper
         */
        class UpageInstallerJAdministratorWrapper extends JApplicationCms
        {
            /**
             * @var JApplicationCms
             */
            protected $app;

            /**
             * UpageInstallerJAdministratorWrapper constructor.
             *
             * @param JApplicationCms $app Application object
             */
            public function __construct(JApplicationCms $app)
            {
                $this->app = $app;
            }

            /**
             * @return mixed
             */
            public function getMessageQueue()
            {
                return $this->app->getMessageQueue();
            }

            /**
             * @param array $messages Messages list
             */
            public function setMessageQueue($messages)
            {
                $this->app->_messageQueue = $messages;
            }
        }
    }
}
