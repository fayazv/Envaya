<?php

    global $CONFIG;

    $group_guid = get_input('org_guid');
    $group = get_entity($group_guid);
    
    $size = strtolower(get_input('size'));
    if (!in_array($size,array('large','medium','small','tiny','master','topbar')))
        $size = "medium";
    
    $filehandler = $group->getIconFile($size);
    
    $success = false;
    if ($filehandler->open("read")) {
        if ($contents = $filehandler->read($filehandler->size())) {
            $success = true;
        } 
    }
    
    if (!$success) {
        $contents = file_get_contents($CONFIG->pluginspath."envaya/graphics/default{$size}.gif");        
    }
    
    header("Content-type: image/jpeg");
    header('Expires: ' . date('r',time() + 864000));
    header("Pragma: public");
    header("Cache-Control: public");
    header("Content-Length: " . strlen($contents));
    echo $contents;
?>