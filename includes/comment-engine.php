<?php
function acs_post_comment($post_id) {
    if (!get_option('acs_plugin_active',1)) return;
    $post = get_post($post_id);
    if (!$post || !in_array($post->post_status, ['publish','future'])) return;

    $ps = get_option('acs_post_settings',[]);
    if ($post->post_status==='future' && empty($ps['enable_future_posts'])) return;

    $uids = get_option('acs_selected_users',[]);
    $cs = get_option('acs_comment_settings',[]);
    $ul = get_option('acs_user_limits',[]);
    $pl = $ps['post_limits'] ?? [];

    if (isset($pl[$post_id]) && $pl[$post_id]===0) return;

    $avail=[];
    foreach($uids as $uid) {
        $limit = $ul[$uid] ?? 1;
        $cnt = get_comments(['post_id'=>$post_id,'user_id'=>$uid,'count'=>true]);
        if ($cnt < $limit && !empty($cs[$uid]['comments'])) $avail[]=$uid;
    }
    if (empty($avail)) return;

    $uid = $avail[array_rand($avail)];
    $comment = $cs[$uid]['comments'][array_rand($cs[$uid]['comments'])];
    wp_insert_comment(['comment_post_ID'=>$post_id,'comment_content'=>$comment,'user_id'=>$uid,'comment_approved'=>1,'comment_date'=>current_time('mysql')]);

    $interval = ($ps['global_interval'] ?? 60);
    $tol = ($ps['interval_tolerance'] ?? 10);
    $var = $interval * ($tol/100);
    $rand = rand(-$var, $var);
    $next = max(1, ($interval + $rand)) * 60;

    wp_schedule_single_event(time()+$next, 'acs_post_comment_event', [$post_id]);
}

add_action('publish_post','acs_post_comment');
add_action('publish_future_post','acs_post_comment');
add_action('acs_post_comment_event','acs_post_comment');