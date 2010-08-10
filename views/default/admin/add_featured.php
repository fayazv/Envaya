<?php
    $org = $vars['entity'];
?>
<form method='POST' action='admin/new_featured'>
<?php echo view('input/securitytoken') ?>

<strong><a href='<?php echo $org->getURL() ?>'><?php echo escape($org->name) ?></a></strong>

<br /><br />
<div class='input'>
<label><?php echo __('featured:image'); ?></label>
<?php echo view('admin/featured_image', array(
    'internalname' => 'image_url',
    'org' => $org, 
    'value' => $org->getIcon('medium')
)); 
?>
</div>
<div class='input' style='clear:both'>
<label><?php echo __('featured:text'); ?></label>
<?php
    $homeWidget = $org->getWidgetByName('home');
    
    echo view('input/tinymce',
        array(
            'internalname' => 'content',
            'valueIsHTML' => true,
            'allowCustomHTML' => true,
            'value' => $homeWidget->content,
            'trackDirty' => true
        )
    );
?>
</div>
<?php

    echo view('input/submit',
        array('internalname' => 'submit',
            'class' => "submit_button",
            'trackDirty' => true,
            'value' => __('publish')));

    echo view('input/hidden', array(
        'internalname' => 'username',
        'value' => $org->username
    ));
    
    
?>

</form>