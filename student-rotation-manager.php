<?php
/**
 * Plugin Name: Student Rotation Manager
 * Plugin URI: https://urpt.com
 * Description: Manage student clinical rotations for Upstream Rehabilitation. Only active on urpt.com site.
 * Version: 1.0.0
 * Author: Envisionit media -nikocrow
 * Author URI: https://envisionitmedia.com
 * Text Domain: student-rotation
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}


/**
 * Start session for CSV upload functionality
 */
function sr_start_session() {
    if (!session_id()) {
        session_start();
    }
}
add_action('init', 'sr_start_session', 1);


/**
 * Check if plugin should load on current site
 * Solo cargar en urpt.com en multisite
 */
function sr_should_load() {
    // Si NO es multisite, cargar siempre
    if (!is_multisite()) {
        return true;
    }
    
    // En multisite, solo cargar si la URL contiene urpt.com
    $current_site_url = get_site_url();
    if (strpos($current_site_url, 'urpt.com') !== false) {
        return true;
    }
    
    return false;
}

/**
 * Load plugin files only if we're on the correct site
 */
if (sr_should_load()) {
    
    // Define constants
    define('SR_VERSION', '1.0.0');
    define('SR_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('SR_PLUGIN_URL', plugin_dir_url(__FILE__));
    
    // Include core files
    require_once SR_PLUGIN_DIR . 'inc/recruiting-rol.php';
    require_once SR_PLUGIN_DIR . 'inc/post-type.php';
    
    // Include meta-fields only if ACF is active
    if (function_exists('acf_add_local_field_group')) {
        require_once SR_PLUGIN_DIR . 'inc/meta-fields.php';
    }
}

/**
 * Plugin Activation
 */
function sr_activate() {
    // Solo activar si estamos en el site correcto
    if (!sr_should_load()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            'This plugin can only be activated on urpt.com site.',
            'Plugin Activation Error',
            array('back_link' => true)
        );
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Set activation flag
    add_option('sr_activated', current_time('mysql'));
}
register_activation_hook(__FILE__, 'sr_activate');

/**
 * Plugin Deactivation
 */
function sr_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Clear scheduled cron jobs
    $timestamp = wp_next_scheduled('upr_delete_expired_rotations_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'upr_delete_expired_rotations_hook');
    }
    
    // Update deactivation time
    update_option('sr_deactivated', current_time('mysql'));
}
register_deactivation_hook(__FILE__, 'sr_deactivate');

/**
 * Add settings link on plugins page
 */
function sr_add_settings_link($links) {
    if (!sr_should_load()) {
        return $links;
    }
    
    $settings_link = '<a href="' . admin_url('edit.php?post_type=student_rotation') . '">Manage Rotations</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'sr_add_settings_link');

/**
 * Check dependencies (ACF)
 */
function sr_check_dependencies() {
    if (!sr_should_load()) {
        return;
    }
    
    if (!function_exists('acf_add_local_field_group')) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>Student Rotation Manager:</strong> 
                Advanced Custom Fields (ACF) is recommended for full functionality. 
                <a href="<?php echo admin_url('plugin-install.php?s=advanced+custom+fields&tab=search&type=term'); ?>">Install ACF</a>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'sr_check_dependencies');

/**
 * Display admin notice on wrong site
 */
function sr_wrong_site_notice() {
    if (is_multisite() && !sr_should_load()) {
        $current_site = get_site_url();
        ?>
        <div class="notice notice-error">
            <p>
                <strong>Student Rotation Manager:</strong> 
                This plugin is only meant to be activated on urpt.com. 
                Current site: <?php echo esc_html($current_site); ?>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'sr_wrong_site_notice');

// Include meta-fields only if ACF is active
if (function_exists('acf_add_local_field_group')) {
    require_once SR_PLUGIN_DIR . 'inc/meta-fields.php';
}

// Include CSV processor
require_once SR_PLUGIN_DIR . 'inc/csv-processor.php';
require_once SR_PLUGIN_DIR . 'inc/csv-upload-page.php';
// Include settings page
require_once SR_PLUGIN_DIR . 'inc/settings-page.php';
// Include frontend
require_once SR_PLUGIN_DIR . 'inc/frontend/search-template.php';