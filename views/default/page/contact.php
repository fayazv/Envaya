<div class='section_content padded'>

<?php echo $vars['widget']->renderContent(); ?>

<form method="POST" action='pg/send_feedback'>
<div class='input'>
<label><?php echo __('feedback:message') ?>:</label>
<?php
    echo elgg_view('input/longtext', array(
        'internalname' => 'message'
    ));
?>
</div>
<div class='input'>
<label><?php echo __('feedback:name') ?>:</label>
<?php
    echo elgg_view('input/text', array(
        'internalname' => 'name'
    ));
?>
</div>
<div class='input'>
<label><?php echo __('feedback:email') ?>:</label>
<?php
    echo elgg_view('input/text', array(
        'internalname' => 'email'
    ));
?>
</div>

<?php
    echo elgg_view('input/submit', array(
        'internalname' => 'submit',
        'value' => __('feedback:send'),
    ));
?>

</form>
</div>