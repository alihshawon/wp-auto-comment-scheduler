<?php
add_action('acs_daily_maintenance','acs_daily_maintenance');
function acs_daily_maintenance() {
    $posts = get_posts(['numberposts'=>-1,'post_status'=>'publish,future','fields'=>'ids']);
    foreach($posts as $pid) {
        $ts = wp_next_scheduled('acs_post_comment_event',[$pid]);
        if ($ts) wp_unschedule_event($ts,'acs_post_comment_event',[$pid]);
    }
}