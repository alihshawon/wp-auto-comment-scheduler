<?php
/**
 * Plugin Name: Auto Comment Scheduler
 * Description: Automatically posts comments from selected users at scheduled intervals with tolerance
 * Version: 3.2
 * Author: Ali H Shawon
 * Text Domain: auto-comment-scheduler
 */

defined('ABSPATH') or die('No direct access!');

// Activation hook
register_activation_hook(__FILE__, 'acs_activate_plugin');
function acs_activate_plugin() {
    $role = get_role('administrator');
    if ($role && !$role->has_cap('manage_comment_scheduler')) {
        $role->add_cap('manage_comment_scheduler');
    }
    add_option('acs_plugin_active', 1);
    if (!wp_next_scheduled('acs_daily_maintenance')) {
        wp_schedule_event(time(), 'daily', 'acs_daily_maintenance');
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'acs_deactivate_plugin');
function acs_deactivate_plugin() {
    wp_clear_scheduled_hook('acs_daily_maintenance');
    // Clear all scheduled comment events
    $timestamp = wp_next_scheduled('acs_post_comment_event');
    while ($timestamp) {
        wp_unschedule_event($timestamp, 'acs_post_comment_event');
        $timestamp = wp_next_scheduled('acs_post_comment_event');
    }
}

add_action('plugins_loaded', function() {
    require_once plugin_dir_path(__FILE__) . 'includes/admin-interface.php';
    require_once plugin_dir_path(__FILE__) . 'includes/comment-engine.php';
    require_once plugin_dir_path(__FILE__) . 'includes/scheduler.php';
});

// AJAX handler for toggle
add_action('wp_ajax_acs_toggle_status', function() {
    check_ajax_referer('acs_toggle_active');
    if (!current_user_can('manage_comment_scheduler')) {
        wp_send_json_error('Unauthorized', 403);
    }
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;
    update_option('acs_plugin_active', $status);
    wp_send_json_success();
});

// Plugin settings link on plugin page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $link = '<a href="' . admin_url('admin.php?page=comment-scheduler') . '">' . __('Settings', 'auto-comment-scheduler') . '</a>';
    array_unshift($links, $link);
    return $links;
});