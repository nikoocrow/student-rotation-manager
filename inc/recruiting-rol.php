<?php
/**
 * Recruiter Role - Maximum Restricted Access
 * Solo puede acceder al CPT Student Rotation
 * 
 * @package StudentRotationManager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create Recruiter Role
 */
function upr_create_recruiter_role() {
    if (get_role('recruiter')) {
        return;
    }
    
    add_role(
        'recruiter',
        'Recruiter',
        array(
            'read' => true,
        )
    );
}
add_action('init', 'upr_create_recruiter_role');

/**
 * Add Student Rotation capabilities to Recruiter role
 */
function upr_add_recruiter_capabilities() {
    $role = get_role('recruiter');
    
    if (!$role) {
        return;
    }
    
    // Remover capacidades que no necesitan
    $remove_caps = array(
        'edit_posts',
        'delete_posts',
        'publish_posts',
        'edit_pages',
        'delete_pages',
        'publish_pages',
        'edit_users',
        'list_users',
        'manage_options',
        'upload_files',
        'edit_theme_options',
        'customize',
        'switch_themes',
        'activate_plugins',
        'edit_plugins',
        'delete_plugins',
    );
    
    foreach ($remove_caps as $cap) {
        $role->remove_cap($cap);
    }
    
    // Agregar SOLO capacidades para Student Rotations
    $caps = array(
        'read_student_rotation',
        'read_student_rotations',
        'read_private_student_rotations',
        'edit_student_rotation',
        'edit_student_rotations',
        'edit_others_student_rotations',
        'edit_published_student_rotations',
        'edit_private_student_rotations',
        'publish_student_rotations',
        'delete_student_rotation',
        'delete_student_rotations',
        'delete_published_student_rotations',
        'delete_private_student_rotations',
        'delete_others_student_rotations',
    );
    
    foreach ($caps as $cap) {
        $role->add_cap($cap);
    }
}
add_action('admin_init', 'upr_add_recruiter_capabilities');

/**
 * Remove ALL menu items except Student Rotations
 */
function upr_remove_recruiter_menus() {
    if (!current_user_can('recruiter') || current_user_can('administrator')) {
        return;
    }
    
    // Menús principales de WordPress
    remove_menu_page('index.php');
    remove_menu_page('edit.php');
    remove_menu_page('upload.php');
    remove_menu_page('edit.php?post_type=page');
    remove_menu_page('edit-comments.php');
    remove_menu_page('themes.php');
    remove_menu_page('plugins.php');
    remove_menu_page('users.php');
    remove_menu_page('tools.php');
    remove_menu_page('options-general.php');
    
    // CPTs de Upstream
    remove_menu_page('edit.php?post_type=location');
    remove_menu_page('edit.php?post_type=uplifted');
    remove_menu_page('edit.php?post_type=testimonials');
    
    // Plugins
    remove_menu_page('edit.php?post_type=acf-field-group');
    remove_menu_page('amp_options');
    remove_menu_page('theme-general-settings');
    
    // Separadores
    remove_menu_page('separator1');
    remove_menu_page('separator2');
    remove_menu_page('separator-last');
}
add_action('admin_menu', 'upr_remove_recruiter_menus', 999);

/**
 * Remove submenu items
 */
function upr_remove_recruiter_submenus() {
    if (!current_user_can('recruiter') || current_user_can('administrator')) {
        return;
    }
    
    global $submenu;
    unset($submenu['themes.php']);
    unset($submenu['options-general.php']);
    unset($submenu['tools.php']);
}
add_action('admin_menu', 'upr_remove_recruiter_submenus', 9999);

/**
 * Hide admin bar items for recruiters
 */
function upr_remove_recruiter_admin_bar_items($wp_admin_bar) {
    if (!current_user_can('recruiter') || current_user_can('administrator')) {
        return;
    }
    
    $wp_admin_bar->remove_node('wp-logo');
    $wp_admin_bar->remove_node('site-name');
    $wp_admin_bar->remove_node('updates');
    $wp_admin_bar->remove_node('new-content');
    $wp_admin_bar->remove_node('comments');
    $wp_admin_bar->remove_node('search');
    $wp_admin_bar->remove_node('customize');
    $wp_admin_bar->remove_node('themes');
}
add_action('admin_bar_menu', 'upr_remove_recruiter_admin_bar_items', 999);

/**
 * Redirect recruiters to Student Rotations on login
 */
function upr_recruiter_login_redirect($redirect_to, $request, $user) {
    if (isset($user->roles) && is_array($user->roles)) {
        if (in_array('recruiter', $user->roles)) {
            return admin_url('edit.php?post_type=student_rotation');
        }
    }
    return $redirect_to;
}
add_filter('login_redirect', 'upr_recruiter_login_redirect', 10, 3);

/**
 * Redirect recruiters away from unauthorized pages
 */
function upr_redirect_recruiter_from_unauthorized() {
    if (!current_user_can('recruiter') || current_user_can('administrator')) {
        return;
    }
    
    global $pagenow;
    
    $blocked_pages = array(
        'index.php',
        'upload.php',
        'themes.php',
        'plugins.php',
        'users.php',
        'tools.php',
        'options-general.php',
        'edit-comments.php',
        'theme-general-settings',
        'edit.php?post_type=acf-field-group',
        'amp_options',
    );
    
    if (in_array($pagenow, $blocked_pages)) {
        wp_redirect(admin_url('edit.php?post_type=student_rotation'));
        exit;
    }
    
    if (isset($_GET['page']) && $_GET['page'] === 'theme-general-settings') {
        wp_redirect(admin_url('edit.php?post_type=student_rotation'));
        exit;
    }
}
add_action('admin_init', 'upr_redirect_recruiter_from_unauthorized');

/**
 * Block access to other post types via direct URL
 */
function upr_prevent_recruiter_unauthorized_cpt_access() {
    if (!current_user_can('recruiter') || current_user_can('administrator')) {
        return;
    }
    
    global $pagenow, $typenow;
    
    $blocked_post_types = array(
        'post',
        'page',
        'location',
        'uplifted',
        'testimonials',
        'acf-field-group',
    );
    
    if (in_array($pagenow, array('edit.php', 'post.php', 'post-new.php'))) {
        
        if (in_array($typenow, $blocked_post_types)) {
            wp_die(
                '<h1>Access Denied</h1><p>You do not have permission to access this content.</p>',
                'Access Denied',
                array(
                    'response' => 403,
                    'back_link' => admin_url('edit.php?post_type=student_rotation')
                )
            );
        }
        
        if (isset($_GET['post_type']) && in_array($_GET['post_type'], $blocked_post_types)) {
            wp_redirect(admin_url('edit.php?post_type=student_rotation'));
            exit;
        }
        
        if ($pagenow === 'edit.php' && !isset($_GET['post_type'])) {
            wp_redirect(admin_url('edit.php?post_type=student_rotation'));
            exit;
        }
    }
}
add_action('admin_init', 'upr_prevent_recruiter_unauthorized_cpt_access');

/**
 * Hide screen options and help tabs
 */
function upr_hide_recruiter_screen_elements() {
    if (!current_user_can('recruiter') || current_user_can('administrator')) {
        return;
    }
    
    add_filter('screen_options_show_screen', '__return_false');
    
    $screen = get_current_screen();
    if ($screen) {
        $screen->remove_help_tabs();
    }
}
add_action('admin_head', 'upr_hide_recruiter_screen_elements');

/**
 * Simplify admin menu for recruiters
 */
function upr_recruiter_admin_styles() {
    if (!current_user_can('recruiter') || current_user_can('administrator')) {
        return;
    }
    ?>
    <style>
        #collapse-button { display: none !important; }
        #wpfooter { display: none !important; }
        #wpadminbar .ab-top-menu > li { display: none; }
        #wpadminbar #wp-admin-bar-my-account { display: block !important; }
        .notice { margin: 5px 0; }
    </style>
    <?php
}
add_action('admin_head', 'upr_recruiter_admin_styles');

/**
 * Customize admin footer for recruiters
 */
function upr_recruiter_admin_footer() {
    if (!current_user_can('recruiter') || current_user_can('administrator')) {
        return;
    }
    return 'Student Rotation Management | Upstream Rehabilitation';
}
add_filter('admin_footer_text', 'upr_recruiter_admin_footer');

/**
 * Remove dashboard widgets
 */
function upr_remove_recruiter_dashboard_widgets() {
    if (!current_user_can('recruiter') || current_user_can('administrator')) {
        return;
    }
    
    global $wp_meta_boxes;
    unset($wp_meta_boxes['dashboard']);
}
add_action('wp_dashboard_setup', 'upr_remove_recruiter_dashboard_widgets', 999);

/**
 * Block REST API access
 */
function upr_restrict_recruiter_rest_api($result) {
    if (!is_user_logged_in()) {
        return $result;
    }
    
    $user = wp_get_current_user();
    if (in_array('recruiter', $user->roles) && !in_array('administrator', $user->roles)) {
        $route = $_SERVER['REQUEST_URI'];
        if (strpos($route, 'student_rotation') === false && 
            strpos($route, 'users/me') === false) {
            return new WP_Error(
                'rest_forbidden',
                'Access denied.',
                array('status' => 403)
            );
        }
    }
    
    return $result;
}
add_filter('rest_pre_dispatch', 'upr_restrict_recruiter_rest_api', 10, 1);