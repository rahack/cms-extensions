<?php

defined('_JEXEC') or die;

ob_start();
echo $this->result;
echo ob_get_clean();
exit();
