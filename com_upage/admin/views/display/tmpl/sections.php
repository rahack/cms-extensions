<?php

defined('_JEXEC') or die;

ob_start();
?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body><?php echo $this->result; ?></body>
    </html>
<?php
echo ob_get_clean();
exit();
?>