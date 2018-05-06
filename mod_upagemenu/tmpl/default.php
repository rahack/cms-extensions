<?php

defined('_JEXEC') or die;

$upageMenu = "";
$upageIcon = dirname(dirname(JURI::current())) . '/administrator/components/com_upage/assets/images/button-icon.png';
?>
    <style>
        .upage-icon {
            background: url('<?php echo $upageIcon; ?>') no-repeat;
            float: left;
            width: 16px;
            height: 16px;
            margin-right: 6px;
            background-size: 16px;
        }
    </style>
<?php
if ($upageComponentItems) {
    $upageMenu = '<ul id="upage-menu" class="nav" >';
    $upageMenu .= '<li class="dropdown" ><a class="dropdown-toggle" data-toggle="dropdown" href="#"><span class="upage-icon">&nbsp;</span>' . $upageComponentItems->text . '<span class="caret"></span></a>';

    if (!empty($upageComponentItems->submenu)) {
        $upageMenu .= '<ul class="dropdown-menu">';
        foreach ($upageComponentItems->submenu as $sub) {
            $upageMenu .= '<li><a class="' . $sub->class . '" href="' . $sub->link . '">' . $sub->text . '</a></li>';
        }
        $upageMenu .= '</ul>';
    }
    $upageMenu .= '</li></ul>';
}
echo $upageMenu;