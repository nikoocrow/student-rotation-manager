<?php
/**
 * Student Rotation Custom Post Type
 * 
 * @package StudentRotationManager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Student Rotation Custom Post Type
 */
function upr_register_student_rotation_cpt() {
    $labels = array(
        'name'                  => 'Student Rotations',
        'singular_name'         => 'Student Rotation',
        'menu_name'             => 'Student Rotations',
        'add_new'               => 'Add New',
        'add_new_item'          => 'Add New Rotation',
        'edit_item'             => 'Edit Rotation',
        'new_item'              => 'New Rotation',
        'view_item'             => 'View Rotation',
        'search_items'          => 'Search Rotations',
        'not_found'             => 'No rotations found',
        'not_found_in_trash'    => 'No rotations found in trash',
        'all_items'             => 'All Rotations',
    );

    $args = array(
        'label'                 => 'Student Rotations',
        'labels'                => $labels,
        'description'           => 'Clinical rotation positions for students',
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 26,
        'menu_icon'             => 'dashicons-welcome-learn-more',
        'supports'              => array('title'),
        'capability_type'       => 'student_rotation',
        'map_meta_cap'          => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'show_in_rest'          => true,
        'rewrite'               => array('slug' => 'student-rotation'),
    );

    register_post_type('student_rotation', $args);
}
add_action('init', 'upr_register_student_rotation_cpt', 0);

/**
 * Add capabilities to Administrator
 */
function upr_add_admin_student_rotation_caps() {
    $admin = get_role('administrator');
    if ($admin) {
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
            $admin->add_cap($cap);
        }
    }
}
add_action('admin_init', 'upr_add_admin_student_rotation_caps');

/**
 * Customize columns in admin list
 */
function upr_student_rotation_columns($columns) {
    unset($columns['date']);
    
    $columns['location'] = 'Location';
    $columns['brand'] = 'Brand';
    $columns['dates'] = 'Rotation Dates';
    $columns['date'] = 'Created';
    
    return $columns;
}
add_filter('manage_student_rotation_posts_columns', 'upr_student_rotation_columns');

/**
 * Populate custom columns with data
 */
function upr_student_rotation_column_content($column, $post_id) {
    switch ($column) {
        case 'location':
            if (function_exists('get_field')) {
                $location_id = get_field('rotation_location', $post_id);
                if ($location_id) {
                    echo get_the_title($location_id);
                } else {
                    echo '—';
                }
            } else {
                echo '—';
            }
            break;
            
        case 'brand':
            if (function_exists('get_field')) {
                $brand = get_field('rotation_brand', $post_id);
                echo $brand ? esc_html($brand) : '—';
            } else {
                echo '—';
            }
            break;
            
        case 'dates':
            if (function_exists('get_field')) {
                $start = get_field('rotation_start_date', $post_id);
                $end = get_field('rotation_end_date', $post_id);
                
                if ($start && $end) {
                    $start_date = DateTime::createFromFormat('Ymd', $start);
                    $end_date = DateTime::createFromFormat('Ymd', $end);
                    
                    if ($start_date && $end_date) {
                        echo $start_date->format('M j, Y') . ' - ' . $end_date->format('M j, Y');
                    } else {
                        echo '—';
                    }
                } else {
                    echo '—';
                }
            } else {
                echo '—';
            }
            break;
    }
}
add_action('manage_student_rotation_posts_custom_column', 'upr_student_rotation_column_content', 10, 2);

/**
 * Make columns sortable
 */
function upr_student_rotation_sortable_columns($columns) {
    $columns['location'] = 'location';
    $columns['brand'] = 'brand';
    $columns['dates'] = 'start_date';
    return $columns;
}
add_filter('manage_edit-student_rotation_sortable_columns', 'upr_student_rotation_sortable_columns');

/**
 * Auto-delete expired rotations (past start date)
 */
function upr_delete_expired_rotations() {
    $today = date('Ymd');
    
    $args = array(
        'post_type'      => 'student_rotation',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => array(
            array(
                'key'     => 'rotation_start_date',
                'value'   => $today,
                'compare' => '<',
                'type'    => 'DATE'
            )
        )
    );
    
    $expired_rotations = get_posts($args);
    
    foreach ($expired_rotations as $rotation) {
        wp_delete_post($rotation->ID, true);
    }
}

// Schedule cron job
if (!wp_next_scheduled('upr_delete_expired_rotations_hook')) {
    wp_schedule_event(time(), 'daily', 'upr_delete_expired_rotations_hook');
}
add_action('upr_delete_expired_rotations_hook', 'upr_delete_expired_rotations');

/**
 * Delete rotation when associated location is deleted
 */
/**
 * Delete rotation when associated location is deleted
 * Using multiple hooks to ensure it works
 */
function upr_delete_rotation_on_location_delete($post_id) {
    // Verificar que no sea un auto-save o revision
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }
    
    // Verificar si es una location
    if (get_post_type($post_id) !== 'location') {
        return;
    }
    
    // Log para debug
    error_log('Location deleted: ' . $post_id . ', checking for associated rotations...');
    
    // Buscar rotations asociadas
    $args = array(
        'post_type'      => 'student_rotation',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'meta_query'     => array(
            array(
                'key'     => 'rotation_location',
                'value'   => $post_id,
                'compare' => '='
            )
        )
    );
    
    $rotations = get_posts($args);
    
    error_log('Found ' . count($rotations) . ' rotations to delete');
    
    foreach ($rotations as $rotation) {
        error_log('Deleting rotation: ' . $rotation->ID);
        wp_delete_post($rotation->ID, true); // Force delete, bypass trash
    }
}

// Usar múltiples hooks para asegurar que funcione
add_action('before_delete_post', 'upr_delete_rotation_on_location_delete', 10, 1);
add_action('wp_trash_post', 'upr_delete_rotation_on_location_delete', 10, 1);
add_action('trashed_post', 'upr_delete_rotation_on_location_delete', 10, 1);

/**
 * Admin notices for recruiters
 */
function upr_recruiter_admin_notices() {
    if (!current_user_can('recruiter') || current_user_can('administrator')) {
        return;
    }
    
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'student_rotation' && $screen->base === 'edit') {
        ?>
        <div class="notice notice-info">
            <p><strong>Welcome to Student Rotation Management!</strong> Click "Add New" to create a new rotation position.</p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'upr_recruiter_admin_notices');





// TEMPORAL - Para testing
add_action('admin_init', function() {
    if (isset($_GET['test_delete_expired']) && current_user_can('administrator')) {
        upr_delete_expired_rotations();
        wp_die('Expired rotations check completed. Check if old rotations were deleted.');
    }
});