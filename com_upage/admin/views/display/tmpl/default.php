<?php

defined('_JEXEC') or die;

header('Content-Type: text/html; charset=utf-8');
ob_start();
?>
    <!DOCTYPE html>
    <html<?php echo $this->manifestAttr; ?>>
    <head>
        <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <script type="text/javascript" src="<?php echo $this->startFiles['editor']; ?>"></script>
        <script type="text/javascript" src="<?php echo $this->startFiles['loader']; ?>" data-processor="joomla"></script>
    </head>
    <body></body>
    </html>
<?php
echo ob_get_clean();
exit();
?>