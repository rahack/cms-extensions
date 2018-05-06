<?php
defined('_JEXEC') or die;
?>

<style type="text/css">
    .config-label {
        width: auto;
        float:left;
        text-align: left;
        padding-right:35px;
        padding-top: 3px;
    }
    .config-control {
        margin-left: 180px;
    }
    .config-group {
        margin-bottom:20px;
    }
</style>
<div style="margin: 10px 0 0 0; border:1px solid #e3e3e3;padding: 20px 0 50px 20px">
    <div style="margin-bottom: 20px;">
        <?php echo JText::_(''); ?>
    </div>
    <div class="config-group">
        <div class="config-label">
            <label for="checkbox-field"><?php echo JText::_('Use Jquery from Component'); ?></label>
        </div>
        <div class="config-control">
            <input type="checkbox" name="enable_jquery" id="enable_jquery" <?php echo $this->checked; ?>>
        </div>
    </div>
</div>
<script>
    jQuery(function($) {

        var enableJQ = $('input[id="enable_jquery"]');
        enableJQ.click(function() {
            var status = this.checked ? '1' : '0';
            $.ajax({
                url: '<?php echo $this->adminUrl . '/index.php?option=com_upage&controller=actions&action=saveConfig'; ?>',
                data: { jquery : status},
                type: 'POST',
                success: function (response) {
                    console.log(response);
                },
                error: function (xhr, status) {
                    alert('Failed  chunk');
                }
            });
        });

    });
</script>
