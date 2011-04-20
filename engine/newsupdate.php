<?php

/*
 * A news update posted by an organization. 
 * Basically a blog post by another name.
 */
class NewsUpdate extends Entity
{
    static $table_name = 'news_updates';
    static $table_attributes = array(
		'num_comments' => 0
    );
    static $mixin_classes = array(
        'Mixin_Content'
    );

    public function query_comments()
    {
        return Comment::query()->where('container_guid = ?', $this->guid)->order_by('guid');
    }
    
    public function get_title()
    {
        return __("widget:news:item");
    }

    public function js_properties()
    {
        return array(
            'guid' => $this->guid,
            'container_guid' => $this->container_guid,
            'dateText' => $this->get_date_text(),
            'imageURL' => $this->thumbnail_url,
            'snippetHTML' => $this->get_snippet()
        );
    }

    public function get_url()
    {
        $org = $this->get_container_entity();
        if ($org)
        {
            return $org->get_url() . "/post/" . $this->guid;
        }
        return '';
    }

    function post_feed_items()
    {
        $org = $this->get_container_entity();
        $recent = time() - 60*60*6;
        
        $recent_update = $org->query_feed_items()
            ->where("action_name in ('news','newsmulti')")
            ->where('time_posted > ?', $recent)
            ->order_by('id desc')
            ->get();
        
        if ($recent_update)
        {
            $time = time();
        
            foreach ($recent_update->query_items_in_group()->filter() as $r)
            {
                $r->action_name = 'newsmulti';
                $r->subject_guid = $this->guid;
                $r->time_posted = $time;
                $prev_count = @$r->args['count'] ?: 1;
                $r->args = array('count' => $prev_count + 1);
                $r->save();
            }
        }
        else
        {                
            FeedItem::post($org, 'news', $this);
        }
    }                
}
