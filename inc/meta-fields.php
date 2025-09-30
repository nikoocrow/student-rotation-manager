<?php
/**
 * Student Rotation Meta Fields (ACF)
 * 
 * @package StudentRotationManager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register ACF Fields for Student Rotation
 */
add_action('acf/include_fields', function() {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group(array(
        'key' => 'group_68dadb64706cf',
        'title' => 'Student Rotation Details',
        'fields' => array(
            array(
                'key' => 'field_68dadb651f5a8',
                'label' => 'Location',
                'name' => 'rotation_location',
                'type' => 'post_object',
                'instructions' => 'Select the clinic location for this rotation',
                'required' => 1,
                'post_type' => array('location'),
                'return_format' => 'id',
                'ui' => 1,
            ),
            array(
                'key' => 'field_68dadc9fba76b',
                'label' => 'Brand',
                'name' => 'rotation_brand',
                'type' => 'text',
                'instructions' => 'Brand will be automatically filled when you save',
                'required' => 0,
                'wrapper' => array(
                    'class' => 'acf-disabled-field',
                ),
                'placeholder' => 'Auto-filled from location',
                'readonly' => 1,
            ),
            array(
                'key' => 'field_68dadd687d49d',
                'label' => 'Rotation Start Date',
                'name' => 'rotation_start_date',
                'type' => 'date_picker',
                'instructions' => 'Position will be automatically deleted after this date',
                'required' => 1,
                'display_format' => 'm/d/Y',
                'return_format' => 'Ymd',
                'first_day' => 1,
            ),
            array(
                'key' => 'field_68dade7c573e6',
                'label' => 'Rotation End Date',
                'name' => 'rotation_end_date',
                'type' => 'date_picker',
                'required' => 1,
                'display_format' => 'm/d/Y',
                'return_format' => 'Ymd',
                'first_day' => 1,
            ),
            array(
                'key' => 'field_68dadf0ec4774',
                'label' => 'Clinical Rotation Availability',
                'name' => 'rotation_availability',
                'type' => 'text',
                'instructions' => 'Date range display (auto-generated when you save)',
                'required' => 0,
                'wrapper' => array(
                    'class' => 'acf-disabled-field',
                ),
                'placeholder' => 'Auto-generated from dates',
                'readonly' => 1,
            ),
            array(
                'key' => 'field_68dadf8e65283',
                'label' => 'Clinical Rotation Description',
                'name' => 'rotation_description',
                'type' => 'wysiwyg',
                'instructions' => 'Describe the clinical rotation',
                'required' => 1,
                'tabs' => 'all',
                'toolbar' => 'basic',
                'media_upload' => 0,
            ),
            array(
                'key' => 'field_68dadf6212e6b',
                'label' => 'Eligibility Criteria',
                'name' => 'rotation_eligibility',
                'type' => 'textarea',
                'instructions' => 'Optional. If empty, will display "None"',
                'required' => 0,
                'rows' => 4,
            ),
            array(
                'key' => 'field_68dae0aecc4c4',
                'label' => 'Onboarding Requirements',
                'name' => 'rotation_onboarding',
                'type' => 'textarea',
                'instructions' => 'Optional. If empty, will display "None"',
                'required' => 0,
                'rows' => 4,
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'student_rotation',
                ),
            ),
        ),
        'position' => 'normal',
        'style' => 'default',
    ));
});

/**
 * Make readonly fields visually disabled
 */
add_action('acf/input/admin_head', function() {
    global $post;
    if (!$post || get_post_type($post->ID) !== 'student_rotation') {
        return;
    }
    ?>
    <style>
        .acf-disabled-field input[type="text"] {
            background-color: #f0f0f0 !important;
            cursor: not-allowed !important;
        }
    </style>
    <script type="text/javascript">
        (function($) {
            $(document).ready(function() {
                $('input[name="acf[field_68dadc9fba76b]"]').prop('readonly', true);
                $('input[name="acf[field_68dadf0ec4774]"]').prop('readonly', true);
            });
        })(jQuery);
    </script>
    <?php
});

/**
 * Auto-generate Brand and Availability when post is saved
 */
add_action('acf/save_post', function($post_id) {
    if (get_post_type($post_id) !== 'student_rotation') {
        return;
    }
    
    // AUTO-LLENAR BRAND desde Location
    $location_id = get_field('rotation_location', $post_id);
    if ($location_id) {
        $brand_terms = get_the_terms($location_id, 'brand');
        if ($brand_terms && !is_wp_error($brand_terms)) {
            update_field('rotation_brand', $brand_terms[0]->name, $post_id);
        }
    }
    
    // AUTO-GENERAR AVAILABILITY desde fechas
    $start_date = get_field('rotation_start_date', $post_id);
    $end_date = get_field('rotation_end_date', $post_id);
    
    if ($start_date && $end_date) {
        $start = DateTime::createFromFormat('Ymd', $start_date);
        $end = DateTime::createFromFormat('Ymd', $end_date);
        
        if ($start && $end) {
            $availability = $start->format('F j, Y') . ' - ' . $end->format('F j, Y');
            update_field('rotation_availability', $availability, $post_id);
        }
    }
}, 20);

/**
 * Validate that end date is after start date
 */
add_filter('acf/validate_value/name=rotation_end_date', function($valid, $value, $field, $input) {
    if (!$valid) {
        return $valid;
    }
    
    if (isset($_POST['acf']['field_68dadd687d49d'])) {
        $start_date = $_POST['acf']['field_68dadd687d49d'];
        
        if ($start_date && $value) {
            $start = DateTime::createFromFormat('Ymd', $start_date);
            $end = DateTime::createFromFormat('Ymd', $value);
            
            if ($start && $end && $end <= $start) {
                $valid = 'End date must be after start date';
            }
        }
    }
    
    return $valid;
}, 10, 4);

/**
 * Load Brand value when editing existing post
 */
add_filter('acf/load_value/name=rotation_brand', function($value, $post_id, $field) {
    if ($value) {
        return $value;
    }
    
    $location_id = get_field('rotation_location', $post_id);
    if ($location_id) {
        $brand_terms = get_the_terms($location_id, 'brand');
        if ($brand_terms && !is_wp_error($brand_terms)) {
            return $brand_terms[0]->name;
        }
    }
    
    return $value;
}, 10, 3);

/**
 * Load Availability value when editing existing post
 */
add_filter('acf/load_value/name=rotation_availability', function($value, $post_id, $field) {
    if ($value) {
        return $value;
    }
    
    $start_date = get_field('rotation_start_date', $post_id);
    $end_date = get_field('rotation_end_date', $post_id);
    
    if ($start_date && $end_date) {
        $start = DateTime::createFromFormat('Ymd', $start_date);
        $end = DateTime::createFromFormat('Ymd', $end_date);
        
        if ($start && $end) {
            return $start->format('F j, Y') . ' - ' . $end->format('F j, Y');
        }
    }
    
    return $value;
}, 10, 3);