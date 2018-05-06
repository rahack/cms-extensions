<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Editors-xtd.upage
 */

defined('_JEXEC') or die;

class PlgButtonUpage extends JPlugin
{
    /**
     * PlgButtonUpage constructor.
     *
     * @param object $subject Subject object
     * @param object $config  Config object
     */
    public function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);
        $this->loadLanguage();
    }

    /**
     * @param string $name Name
     */
    public function onDisplay($name)
    {
        $app = JFactory::getApplication('administrator');
        $domain = JRequest::getVar('domain', '');

        JHtml::_('behavior.modal'); // for SqueezeBox

        if (!$this->_upageComponentInstalled()) {
            return;
        }

        $input = JFactory::getApplication()->input;
        $option = $input->get('option');
        $aid = $input->get('id', '');
        $start = $input->get('start', '0');
        $autostart = $input->get('autostart', '0');
        $cssDisplay = ($start == '1' || $autostart == '1') ? 'none' : 'block';

        if (!in_array($option, array('com_content')) || '' == $aid || !$app->isAdmin()) {
            return;
        }

        $upageComponentPath = dirname(JPATH_PLUGINS) . '/administrator/components/com_upage';
        $secfp      = $upageComponentPath . '/models/sections.php';
        $actionsfp  = $upageComponentPath . '/models/actions.php';
        $manifestfp = $upageComponentPath . '/models/manifests.php';
        $helperfp   = $upageComponentPath . '/helpers/upage.php';
        if (!file_exists($secfp) || !file_exists($actionsfp) || !file_exists($helperfp) || !file_exists($manifestfp)) {
            return;
        }

        include_once $secfp;
        include_once $actionsfp;
        include_once $helperfp;
        include_once $manifestfp;

        $current = dirname(dirname((JURI::current())));
        $adminPanelUrl = $current . '/administrator';

        $sectionsObj = UpageModelsSections::getSectionsObject($aid);

        $article = JTable::getInstance("content");
        $article->load($aid);
        $isConvertRequired = !$sectionsObj && ($article->introtext . $article->fulltext);

        $currentUrl = $adminPanelUrl . '/index.php?option=com_upage&postid=' . $aid . ($domain ? '&domain=' . $domain : '');
        $editLinkUrl = $adminPanelUrl . '/index.php?option=com_content&view=articles&layout=modal&tmpl=component';
        $savePageTypeUrl = $adminPanelUrl . '/index.php?option=com_upage&controller=actions&action=savePageType';

        $pageView = 'landing';
        if ($sectionsObj) {
            $props = unserialize(base64_decode($sectionsObj->props));
            $pageView = isset($props['pageView']) ? $props['pageView'] : $pageView;
        }

        switch($pageView) {
        case 'landing':
            $templateOptions = JText::sprintf('PLG_EDITORS-XTD_TEMPLATE_OPTIONS', '', 'selected', '');
            break;
        case 'landing_with_header_footer':
            $templateOptions = JText::sprintf('PLG_EDITORS-XTD_TEMPLATE_OPTIONS', '', '', 'selected');
            break;
        default:
            $templateOptions = JText::sprintf('PLG_EDITORS-XTD_TEMPLATE_OPTIONS', 'selected', '', '');
        }

        $editorSettings = UpageHelpersUpage::getEditorSettings();
        $editorSettings['pageId'] = $isConvertRequired ? '' : $aid;
        $editorSettings['startPageId'] = $aid;

        $cmsSettings = UpageHelpersUpage::getCmsSettings();
        $cmsSettings['isFirstStart'] = $start == '1' ? true : false;

        $editorSettingsJson = json_encode($editorSettings, JSON_PRETTY_PRINT);
        $cmsSettingsJson = json_encode($cmsSettings, JSON_PRETTY_PRINT);

        $modelActions = new UpageModelsActions();
        $site = $modelActions->getSite();
        if ($article->state == '2' && ($start == '1' || $autostart == '1')) {
            $site['items'][] = array(
                'siteId' => '1',
                'title' => '',
                'id' => (string) $article->id,
                'order' => 0,
                'status' => 2,
                'editorUrl' => $adminPanelUrl . '/index.php?option=com_upage&autostart=1&postid=' . $article->id . ($domain ? '&domain=' . $domain : ''),
                'htmlUrl' => $adminPanelUrl . '/index.php?option=com_upage&controller=actions&action=getPageHtml&pageId=' . $article->id
            );
        }
        $data = json_encode(
            array (
                'site' => $site,
                'pagePosts' => $modelActions->getSitePostsResult(array('options' => array('pageId' => $aid))),
                'pageHtml' => $modelActions->getPageHtml($aid),
                'startTerm' => $isConvertRequired ? 'site:joomla:' . $aid : '',
                'info' => array(
                    'productsExists' => $this->_vmEnabled(),
                    'newPageUrl' => $adminPanelUrl . '/index.php?option=com_upage&dashboard=1' . ($domain ? '&domain=' . $domain : '')
                )
            ),
            JSON_PRETTY_PRINT
        );
        $buttonText = $isConvertRequired ? JText::_('PLG_EDITORS-XTD_TURN_TO_UPAGE_BUTTON_TEXT') : JText::_('PLG_EDITORS-XTD_EDIT_WITH_UPAGE_BUTTON_TEXT');
        $buttonAreaClass = $isConvertRequired ? '' : 'upage-select-template-area';

        $doc = JFactory::getDocument();

        $js = <<<EOF
function runUpage()
{
    (function($){
        var iframe = $('<iframe>', {
            src: '$currentUrl',
            id: 'editor-frame'
        });
        $('nav').before(iframe);
        iframe.css('height', 'calc(100vh - ' + $('nav').height() + 'px)');
        iframe.css('width', '100%');

        $(document).scroll(function() {
            $(this).scrollTop(0);
        });

        $('body').addClass('editor');
    })(jQuery);
}

function postMessageListener(event) {
    if (event.origin !== location.origin) {
        return;
    }
    var data = JSON.parse(event.data);
    if (!data) {
        return;
    }
    if (data.action === 'close') {
        window.location.href = data.closeUrl;
    } else if (data.action === 'editLinkDialogOpen') {
        openEditLinkDialog();
    }
}

if (window.addEventListener) {
    window.addEventListener("message", postMessageListener);
} else {
    window.attachEvent("onmessage", postMessageListener); // IE8
}

SqueezeBox.extend({
    applyContent: function(content, size) {
        if (!this.isOpen && !this.applyTimer) return;
        this.applyTimer = clearTimeout(this.applyTimer);
        this.hideContent();
        if (!content) {
            this.toggleLoading(true);
        } else {
            if (this.isLoading) this.toggleLoading(false);
            this.fireEvent('onUpdate', [this.content], 20);
        }
        if (content) {
            if (['string', 'array'].contains(typeOf(content))) {
                this.content.set('html', content);
            } else if (!(content !== this.content && this.content.contains(content))) {
                this.content.adopt(content);
            }
        }
        this.callChain();
        if (!this.isOpen) {
            this.toggleListeners(true);
            this.resize(size, true);
            this.isOpen = true;
            this.win.setProperty('aria-hidden', 'false');
            this.fireEvent('onOpen', [this.content]);
        } else {
            this.resize(size);
        }
    }
});

function openEditLinkDialog() {
    //override joomla action for modal close
    window.jModalClose = function () {};
    
    (function($){
        var editorFrame = $('#editor-frame')[0].contentWindow;
        SqueezeBox.fromElement('$editLinkUrl', {
            size : {x : 800, y : 500},
            iframePreload: true,
            handler : 'iframe',
            onOpen : function (container, showContent) {
                var ifrDoc = container.firstChild.contentDocument;
                $('.select-link', ifrDoc).on('click', function() {
                    var uriAttr = $(this).attr('data-uri'),
                        url = uriAttr.match(/index.php[^,'"]+/),
                        text = $(this).text().trim();
                    $(ifrDoc).data('close-after-save', true);
                    editorFrame.postMessage(JSON.stringify({
                        action: 'editLinkDialogClose',
                        data: {
                            url: url ? url[0] : '',
                            blank: false
                        }
                    }), window.location.origin);
                    SqueezeBox.close();
                });
                container.setStyle('display', showContent ? '' : 'none');
            },
            onClose : function (container) {
                if (!$(container.firstChild.contentDocument).data('close-after-save'))
                    editorFrame.postMessage(JSON.stringify({action: 'editLinkDialogClose'}), window.location.origin);
            }
        });
        window.setTimeout(function () {
            SqueezeBox.fireEvent('onOpen', [SqueezeBox.content, true]);
        }, 1000);
    })(jQuery);
}

var dataBridgeData = $data;
window.dataBridge = {
    getSite: function () {
        return dataBridgeData.site;
    },
    getSitePosts: function () {
        return dataBridgeData.pagePosts;
    },
    getPageHtml: function () {
        return dataBridgeData.pageHtml;
    },
    getStartTerm: function () {
        return dataBridgeData.startTerm;
    },
    getInfo: function getInfo() {
        return dataBridgeData.info;
    },
    settings: $editorSettingsJson,
    cmsSettings: $cmsSettingsJson
};

function sendRequest(data, callback) {
    var xhr = new XMLHttpRequest();

    function onError() {
        callback(new Error('Failed to send a request to ' + data.url + ' ' + JSON.stringify({
            responseText: xhr.responseText,
            readyState: xhr.readyState,
            status: xhr.status
        }, null, 4)));
    }

    xhr.onerror = onError;
    xhr.onload = function () {
        if (this.readyState === 4 && this.status === 200) {
            callback(null, this.response);
        } else {
            onError();
        }
    };
    xhr.open(data.method || 'GET', data.url);

    if (data.data) {
        var formData = new FormData();
        formData.append("pageType", data.data.pageType);
        formData.append("pageId", data.data.pageId);
        xhr.send(formData);
    } else {
        xhr.send();
    }
}

jQuery(function($) {
    // autostart upage from cms admin main menu
    if ('$start' == '1' || '$autostart' == '1') {
        runUpage();
    }
    var upageButton = $('<a href="#" class="btn upage-button">$buttonText</a>'),
        upageArea = $('<div class="$buttonAreaClass"></div>');

    upageArea.append(upageButton);
    $('form').before(upageArea);
    upageButton.click(function(e) {
        e.preventDefault();
        runUpage();
    });
    if ('$buttonAreaClass' !== '') {
        var selectObj = $('$templateOptions');
        upageButton.after(selectObj);
        $('#toolbar-apply button, #toolbar-save button').click(function() {
            var pageType = $('.upage-select-template').val();
            sendRequest({
                url: '$savePageTypeUrl',
                method: 'POST',
                data: {
                    pageType: pageType,
                    pageId: $aid
                }
            }, function (error, response) {
                if (error) {
                    console.error(e);
                    alert('Save page type error.');
                }
            });
        });
    }
});
EOF;

        $favicon = $adminPanelUrl . '/components/com_upage/assets/images/button-icon.png';
        $css = <<<EOF
body.editor>*:not(nav) {
    display:none;
}

body>*:not(nav) {
    display:$cssDisplay;
}

#editor-frame{
    display: block !important;
}
#sbox-overlay, #sbox-window  {
    display: block;
}
.btn.upage-button {
    color: #4184F4;
    background: url('$favicon') no-repeat 4px 4px;
    font-weight: bold;
    font-family: Arial;
    padding-right: 5px;
    padding-left: 25px;
    margin: 10px 10px 20px 0px;
    background-size: 16px;
}
.upage-select-template {
    margin-left: 10px
}
.upage-select-template-area {
    border: 1px solid #ddd;
    margin: 10px 0 20px 0;
    padding: 15px;
}
EOF;
        $screenshotsContent = UpageModelsSections::getSectionsScreenshots($sectionsObj);
        if ($screenshotsContent) {
            $css .= <<<EOF
fieldset.adminform {
    display:none;
}
.upage-button {
    margin: 20px;
}
#preview-container {
    overflow: hidden;
}
#preview-frame {
    transform: scale(0.3);
    transform-origin: 0 0;
    height: 333.333%;
}
EOF;
            $js .= <<<EOF
function renderScreenshots()
{
    (function($){
        var previewContainer = $('<div>', {
            id: 'preview-container'
        }),
        previewFrame = $('<iframe>', {
            id: 'preview-frame',
            frameborder: 0,
            scrolling: 'no',
            width: '9999px'
        });

        $('.adminform').before(previewContainer);
        previewContainer.append(previewFrame);
       
        var doc = previewFrame[0].contentDocument;
        doc.open();
        doc.write($screenshotsContent);
        doc.close();
        
        previewFrame.load(function() {
            var containerHeight = 0;
            $('section', previewFrame.contents()).each(function(i, el) {
                containerHeight += $(el).height();
            });
            previewContainer.height(containerHeight * 0.3);
        });

        $('body', previewFrame.contents()).click(function(e) {
            runUpage();
        });
    })(jQuery);
}

jQuery(function($) {
    renderScreenshots();
});
EOF;
        }

        $doc->addScriptDeclaration($js);
        $doc->addStyleDeclaration($css);
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

    /**
     * Check the existence of Virtuemart
     *
     * @return bool
     */
    private function _vmEnabled() {
        if (!JComponentHelper::isInstalled('com_virtuemart')) {
            return false;
        }

        if (!JComponentHelper::getComponent('com_virtuemart', true)->enabled) {
            return false;
        }
        return true;
    }
}