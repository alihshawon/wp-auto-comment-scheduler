<?php
function acs_admin_menu() {
    add_menu_page(
        __('Comment Scheduler', 'auto-comment-scheduler'),
        __('Comment Scheduler', 'auto-comment-scheduler'),
        'manage_comment_scheduler',
        'comment-scheduler',
        'acs_admin_page',
        'dashicons-admin-comments',
        80
    );
}
add_action('admin_menu', 'acs_admin_menu');

function acs_admin_assets($hook) {
    if (strpos($hook, 'comment-scheduler') === false) return;
    wp_enqueue_style('acs-admin-css', plugins_url('../assets/css/admin.css', __FILE__));
    wp_enqueue_script('acs-admin-js', plugins_url('../assets/js/admin.js', __FILE__), ['jquery'], '1.1', true);
    wp_localize_script('acs-admin-js', 'acsAdmin', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('acs_toggle_active'),
        'referer' => wp_get_referer()
    ]);
}
add_action('admin_enqueue_scripts', 'acs_admin_assets');

function acs_admin_page() {
    if (!current_user_can('manage_comment_scheduler')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'auto-comment-scheduler'));
    }
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'users';
    $is_active = get_option('acs_plugin_active', 1);
    ?>
    <div class="wrap">
        <h1><?php _e('Comment Scheduler', 'auto-comment-scheduler'); ?></h1>
        <div class="acs-tabs">
            <a href="?page=comment-scheduler&tab=users" class="<?php echo $current_tab==='users'?'active':''; ?>"><?php _e('Select Users', 'auto-comment-scheduler'); ?></a>
            <a href="?page=comment-scheduler&tab=comments" class="<?php echo $current_tab==='comments'?'active':''; ?>"><?php _e('Comment Settings', 'auto-comment-scheduler'); ?></a>
            <a href="?page=comment-scheduler&tab=posts" class="<?php echo $current_tab==='posts'?'active':''; ?>"><?php _e('Post Settings', 'auto-comment-scheduler'); ?></a>
        </div>
        <div class="acs-status">
            <label class="acs-toggle-switch">
                <input type="checkbox" id="acs-active-toggle" <?php checked($is_active,1); ?>>
                <span class="acs-toggle-slider"></span>
                <span class="acs-toggle-label"><?php _e('Plugin Active', 'auto-comment-scheduler'); ?></span>
            </label>
        </div>
        <?php
        switch($current_tab):
            case 'comments': acs_render_comment_settings(); break;
            case 'posts': acs_render_post_settings(); break;
            default: acs_render_user_selection();
        endswitch;
        ?>
    </div>
    <?php
}

function acs_render_user_selection() {
    $users = get_users(['fields'=>['ID','display_name']]);
    $saved = get_option('acs_selected_users', []);
    if (isset($_POST['acs_save_users'])) {
        check_admin_referer('acs_save_users');
        $saved = isset($_POST['acs_selected_users'])?array_map('intval', $_POST['acs_selected_users']):[];
        update_option('acs_selected_users', $saved);
        echo '<div class="notice notice-success"><p>'.__('Users saved!','auto-comment-scheduler').'</p></div>';
    }
    ?>
    <form method="post">
        <?php wp_nonce_field('acs_save_users'); ?>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th><?php _e('Select','auto-comment-scheduler'); ?></th><th><?php _e('Username','auto-comment-scheduler'); ?></th></tr></thead><tbody>
            <?php foreach($users as $u): ?>
            <tr>
                <td><input type="checkbox" name="acs_selected_users[]" value="<?php echo $u->ID;?>" <?php checked(in_array($u->ID,$saved)); ?> /></td>
                <td><?php echo esc_html($u->display_name); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php submit_button(__('Save Users','auto-comment-scheduler'), 'primary', 'acs_save_users'); ?>
    </form>
    <?php
}

function acs_render_comment_settings() {
    $ids = get_option('acs_selected_users', []);
    if (empty($ids)) {
        echo '<div class="notice notice-error"><p>'.__('Select users first.','auto-comment-scheduler').'</p></div>';
        return;
    }
    $comments = get_option('acs_comment_settings', []);
    $limits = get_option('acs_user_limits', []);
    if (isset($_POST['acs_save_comments'])) {
        check_admin_referer('acs_save_comments');
        $newC=[]; $newL=[];
        foreach($ids as $uid) {
            if (isset($_POST['user_comments'][$uid])) {
                $newC[$uid] = ['comments'=>array_filter(array_map('sanitize_textarea_field',explode("\n",$_POST['user_comments'][$uid])))];
                $newL[$uid] = isset($_POST['user_limits'][$uid]) ? absint($_POST['user_limits'][$uid]) : 1;
            }
        }
        update_option('acs_comment_settings',$newC);
        update_option('acs_user_limits',$newL);
        $comments=$newC; $limits=$newL;
        echo '<div class="notice notice-success"><p>'.__('Comments saved!','auto-comment-scheduler').'</p></div>';
    }
    ?>
    <form method="post">
        <?php wp_nonce_field('acs_save_comments'); ?>
        <?php foreach($ids as $uid):
            $u=get_userdata($uid);
            if(!$u)continue;
        ?>
        <div class="acs-user-box">
            <h3><?php echo esc_html($u->display_name); ?></h3>
            <div class="acs-user-limit"><label><?php _e('Max comments/post','auto-comment-scheduler'); ?>&nbsp;
                <input type="number" name="user_limits[<?php echo $uid;?>]" value="<?php echo $limits[$uid]??1;?>" min="0">
            </label></div>
            <textarea name="user_comments[<?php echo $uid;?>]" rows="5" class="large-text"><?php
                echo esc_textarea(implode("\n",$comments[$uid]['comments'] ?? []));
            ?></textarea>
            <p class="description"><?php _e('One per line','auto-comment-scheduler'); ?></p>
        </div>
        <?php endforeach; ?>
        <?php submit_button(__('Save Comments','auto-comment-scheduler'), 'primary', 'acs_save_comments'); ?>
    </form>
    <?php
}

function acs_render_post_settings() {
    $settings = get_option('acs_post_settings', ['global_interval'=>60,'interval_tolerance'=>10,'enable_future_posts'=>1,'post_limits'=>[]]);
    if (isset($_POST['acs_save_post_settings'])) {
        check_admin_referer('acs_save_post_settings');
        $new=['global_interval'=>absint($_POST['global_interval']),'interval_tolerance'=>absint($_POST['interval_tolerance']),'enable_future_posts'=>isset($_POST['enable_future_posts'])?1:0,'post_limits'=>array_map('absint',$_POST['post_limits']??[])];
        update_option('acs_post_settings',$new);
        $settings=$new;
        echo '<div class="notice notice-success"><p>'.__('Post settings saved!','auto-comment-scheduler').'</p></div>';
    }
    $posts = get_posts(['numberposts'=>-1,'post_status'=>'publish,future']);
    ?>
    <form method="post">
        <?php wp_nonce_field('acs_save_post_settings'); ?>
        <h2><?php _e('Global Settings','auto-comment-scheduler'); ?></h2>
        <table class="form-table">
            <tr><th><label><?php _e('Interval (min)','auto-comment-scheduler'); ?></label></th><td><input type="number" name="global_interval" value="<?php echo $settings['global_interval'];?>" min="1"></td></tr>
            <tr><th><label><?php _e('Tolerance (%)','auto-comment-scheduler'); ?></label></th><td><input type="number" name="interval_tolerance" value="<?php echo $settings['interval_tolerance'];?>" min="0" max="100"></td></tr>
            <tr><th><?php _e('Future Posts','auto...'); ?></th><td><input type="checkbox" name="enable_future_posts" value="1" <?php checked($settings['enable_future_posts']); ?>> <?php _e('Allow future posts','auto-comment-scheduler'); ?></td></tr>
        </table>
        <h2><?php _e('Per-Post Limits','auto-comment-scheduler'); ?></h2>
        <table class="wp-list-table widefat fixed striped"><thead><tr><th><?php _e('Title','auto-comment-scheduler'); ?></th><th><?php _e('Status','auto-comment-scheduler'); ?></th><th><?php _e('Max comments','auto-comment-scheduler'); ?></th></tr></thead><tbody>
        <?php foreach($posts as $p): ?>
        <tr>
            <td><?php echo esc_html($p->post_title); ?></td>
            <td><?php echo $p->post_status==='future'?__('Scheduled') : __('Published'); ?></td>
            <td><input type="number" name="post_limits[<?php echo $p->ID;?>]" value="<?php echo $settings['post_limits'][$p->ID]??'';?>" min="0" placeholder="<?php _e('Default','auto-comment-scheduler'); ?>"></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table>
        <?php submit_button(__('Save Post Settings','auto-comment-scheduler'), 'primary', 'acs_save_post_settings'); ?>
    </form>
    <?php
}
