<?php

class Action_EditDesign extends Action
{
    function before()
    {
        $this->require_editor();
        $this->require_org();        
    }
     
    function process_input()
    {
        $org = $this->get_org();

        $theme = get_input('theme');

        if ($theme != $org->theme)
        {
            SessionMessages::add(__("theme:changed"));
            $org->theme = $theme;
            $org->save();
        }

        $iconFiles = UploadedFile::json_decode_array($_POST['icon']);

        if (get_input('deleteicon'))
        {
            $org->set_icon(null);
            SessionMessages::add(__("icon:reset"));
        }
        else if ($iconFiles)
        {
            $org->set_icon($iconFiles);
            SessionMessages::add(__("icon:saved"));
        }

        $headerFiles = UploadedFile::json_decode_array($_POST['header']);

        $customHeader = (int)get_input('custom_header');

        if (!$customHeader)
        {
            if ($org->has_custom_header())
            {
                $org->set_header(null);
                SessionMessages::add(__("header:reset"));
            }
        }
        else if ($headerFiles)
        {
            $org->set_header($headerFiles);
            SessionMessages::add(__("header:saved"));
        }
        
        forward($org->get_url());
    }

    function render()
    {
        $org = $this->get_org();

        $cancelUrl = get_input('from') ?: $org->get_url();
        PageContext::get_submenu('edit')->add_item(__("canceledit"), $cancelUrl);

        $this->page_draw(array(
            'title' => __("design:edit"),
            'content' => view("org/design", array('org' => $org))
        ));        
    }
    
}    