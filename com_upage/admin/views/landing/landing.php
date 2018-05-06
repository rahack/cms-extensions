<?php

defined('_JEXEC') or die;


$app = JFactory::getApplication();
$doc = JFactory::getDocument();
$props = $app->get('props');
$bodyClass = isset($props['bodyClass']) ? $props['bodyClass'] : '';
$head = isset($props['head']) ? $props['head'] : '';
$fonts = isset($props['fonts']) ? $props['fonts'] : '';
$publishHtml = isset($props['publishHtml']) ? $props['publishHtml'] : '';
$publishHtml = UpageModelsSections::processSectionsHtml($publishHtml);
$backlink = isset($props['backlink']) ? $props['backlink'] : '';

if (isset($props['titleInBrowser']) && $props['titleInBrowser'] != '') {
    $doc->setTitle($props['titleInBrowser']);
}

if (isset($props['description']) && $props['description'] != '') {
    $doc->setDescription($props['description']);
}

if (isset($props['keywords']) && $props['keywords'] != '') {
    $doc->setMetadata('keywords', $props['keywords']);
}

if (isset($props['metaTags']) && $props['metaTags'] != '') {
    $doc->addCustomTag($props['metaTags']);
}

if (isset($props['customHeadHtml']) && $props['customHeadHtml'] != '') {
    $doc->addCustomTag($props['customHeadHtml']);
}

JHtml::_('bootstrap.framework');

require_once  dirname(JPATH_PLUGINS) . '/administrator/components/com_upage/helpers/upage.php';
$params = UpageHelpersUpage::getUpageConfig();

?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" type="text/css" media="all" href="<?php echo $doc->baseurl; ?>/administrator/components/com_upage/assets/css/upage.css">
    <jdoc:include type="head" />
    <?php if (isset($params['jquery']) && $params['jquery'] == '1') : ?>
        <script type="text/javascript" src="<?php echo $doc->baseurl; ?>/administrator/components/com_upage/assets/js/jquery.js"></script>
    <?php endif; ?>
    <script type="text/javascript" src="<?php echo $doc->baseurl; ?>/administrator/components/com_upage/assets/js/upage.js"></script>
    <?php echo $fonts; ?>
    <style>
        <?php echo $head; ?>
    </style>
</head>
<body class="<?php echo $bodyClass; ?>">
<?php echo $publishHtml; ?>
<?php echo $backlink; ?>
</body>
</html>