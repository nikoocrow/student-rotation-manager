<?php
/**
 * Settings Page for Student Rotation Frontend
 * Actualizado con Configuración de Formulario
 * 
 * @package StudentRotationManager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register ACF Options Page for CTA Module Settings
 */
function sr_register_options_page() {
    if (function_exists('acf_add_options_sub_page')) {
        acf_add_options_sub_page(array(
            'page_title'  => 'CTA Module Settings',
            'menu_title'  => 'CTA Module',
            'parent_slug' => 'edit.php?post_type=student_rotation',
            'capability'  => 'manage_options',
            'menu_slug'   => 'sr-cta-settings',
        ));
        
        // Nueva página para configuración del formulario
        acf_add_options_sub_page(array(
            'page_title'  => 'Form Settings',
            'menu_title'  => 'Form Settings',
            'parent_slug' => 'edit.php?post_type=student_rotation',
            'capability'  => 'manage_options',
            'menu_slug'   => 'sr-form-settings',
        ));
    }
}
add_action('acf/init', 'sr_register_options_page');

/**
 * Register ACF Fields for Form Settings
 */
add_action('acf/include_fields', function() {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group(array(
        'key' => 'group_sr_form_settings',
        'title' => 'Contact Form Configuration',
        'fields' => array(
            array(
                'key' => 'field_sr_form_headline',
                'label' => 'Form Headline',
                'name' => 'sr_form_headline',
                'type' => 'text',
                'instructions' => 'Headline that appears above the contact form',
                'default_value' => 'Interested in a Student Rotation?',
                'maxlength' => 100,
                'required' => 1,
            ),
            array(
                'key' => 'field_sr_form_subheadline',
                'label' => 'Form Subheadline',
                'name' => 'sr_form_subheadline',
                'type' => 'textarea',
                'instructions' => 'Description text below the headline',
                'default_value' => 'Fill out the form below and we\'ll get back to you soon.',
                'rows' => 3,
                'required' => 1,
            ),
            array(
                'key' => 'field_sr_form_shortcode',
                'label' => 'Form Shortcode',
                'name' => 'sr_form_shortcode',
                'type' => 'text',
                'instructions' => 'Enter your form shortcode (e.g., [contact-form-7 id="123"] or [gravityform id="1"])',
                'placeholder' => '[contact-form-7 id="123"]',
                'required' => 1,
            ),
            array(
                'key' => 'field_sr_position_field_name',
                'label' => 'Position Field Name',
                'name' => 'sr_position_field_name',
                'type' => 'text',
                'instructions' => 'Name of the hidden field in your form that will receive the position data. For Contact Form 7, this should match your hidden field name.',
                'default_value' => 'rotation-position',
                'placeholder' => 'rotation-position',
                'required' => 1,
            ),
            array(
                'key' => 'field_sr_form_instructions',
                'label' => 'Setup Instructions',
                'name' => 'sr_form_instructions',
                'type' => 'message',
                'message' => '<strong>How to setup your form:</strong><br><br>
                    <strong>For Contact Form 7:</strong><br>
                    1. Create or edit your contact form<br>
                    2. Add this hidden field: <code>[hidden rotation-position]</code><br>
                    3. Copy the shortcode and paste it in the "Form Shortcode" field above<br><br>
                    
                    <strong>For Gravity Forms:</strong><br>
                    1. Create or edit your form<br>
                    2. Add a "Hidden" field<br>
                    3. Set the field label to "Rotation Position"<br>
                    4. Enable "Allow field to be populated dynamically"<br>
                    5. Set the parameter name to "rotation_position"<br>
                    6. Copy the shortcode and paste it above<br><br>
                    
                    The position will be automatically filled when users click "Learn More" on a rotation.',
                'new_lines' => '',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'options_page',
                    'operator' => '==',
                    'value' => 'sr-form-settings',
                ),
            ),
        ),
        'style' => 'default',
    ));
});

/**
 * Register ACF Fields for CTA Module
 */
add_action('acf/include_fields', function() {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group(array(
        'key' => 'group_sr_cta_module',
        'title' => 'Student Rotation CTA Module',
        'fields' => array(
            array(
                'key' => 'field_sr_cta_enabled',
                'label' => 'Enable CTA Module',
                'name' => 'sr_cta_enabled',
                'type' => 'true_false',
                'instructions' => 'Show the CTA module on Student Resource Center page',
                'default_value' => 1,
                'ui' => 1,
            ),
            array(
                'key' => 'field_sr_cta_image',
                'label' => 'CTA Image',
                'name' => 'sr_cta_image',
                'type' => 'image',
                'instructions' => 'Upload a screenshot or image for the CTA module',
                'return_format' => 'array',
                'preview_size' => 'medium',
                'library' => 'all',
                'conditional_logic' => array(
                    array(
                        array(
                            'field' => 'field_sr_cta_enabled',
                            'operator' => '==',
                            'value' => '1',
                        ),
                    ),
                ),
            ),
            array(
                'key' => 'field_sr_cta_headline',
                'label' => 'Headline',
                'name' => 'sr_cta_headline',
                'type' => 'text',
                'instructions' => 'Main headline for the CTA module',
                'default_value' => 'Find Student Rotation Opportunities',
                'maxlength' => 100,
                'conditional_logic' => array(
                    array(
                        array(
                            'field' => 'field_sr_cta_enabled',
                            'operator' => '==',
                            'value' => '1',
                        ),
                    ),
                ),
            ),
            array(
                'key' => 'field_sr_cta_subheadline',
                'label' => 'Subheadline',
                'name' => 'sr_cta_subheadline',
                'type' => 'textarea',
                'instructions' => 'Description text below the headline',
                'default_value' => 'Search available clinical rotation positions at our locations across the country.',
                'rows' => 3,
                'conditional_logic' => array(
                    array(
                        array(
                            'field' => 'field_sr_cta_enabled',
                            'operator' => '==',
                            'value' => '1',
                        ),
                    ),
                ),
            ),
            array(
                'key' => 'field_sr_cta_button_text',
                'label' => 'Button Text',
                'name' => 'sr_cta_button_text',
                'type' => 'text',
                'instructions' => 'Text for the CTA button',
                'default_value' => 'Search Rotations',
                'maxlength' => 50,
                'conditional_logic' => array(
                    array(
                        array(
                            'field' => 'field_sr_cta_enabled',
                            'operator' => '==',
                            'value' => '1',
                        ),
                    ),
                ),
            ),
            array(
                'key' => 'field_sr_cta_button_url',
                'label' => 'Button URL',
                'name' => 'sr_cta_button_url',
                'type' => 'text',
                'instructions' => 'Leave empty to use default search page',
                'default_value' => '',
                'placeholder' => '/student-resource-center/locations',
                'conditional_logic' => array(
                    array(
                        array(
                            'field' => 'field_sr_cta_enabled',
                            'operator' => '==',
                            'value' => '1',
                        ),
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'options_page',
                    'operator' => '==',
                    'value' => 'sr-cta-settings',
                ),
            ),
        ),
        'style' => 'default',
    ));
});

/**
 * Shortcode to display CTA module
 * Usage: [student_rotation_cta]
 */
function sr_cta_module_shortcode($atts) {
    if (!get_field('sr_cta_enabled', 'option')) {
        return '';
    }
    
    $image = get_field('sr_cta_image', 'option');
    $headline = get_field('sr_cta_headline', 'option');
    $subheadline = get_field('sr_cta_subheadline', 'option');
    $button_text = get_field('sr_cta_button_text', 'option');
    $button_url = get_field('sr_cta_button_url', 'option');
    
    if (empty($button_url)) {
        $button_url = home_url('/student-resource-center/locations');
    }
    
    ob_start();
    ?>
    <div class="sr-cta-module" style="display: flex; gap: 30px; padding: 40px; background: #f5f5f5; border-radius: 8px; margin: 40px 0;">
        <div class="sr-cta-content" style="flex: 1; display: flex; flex-direction: column; justify-content: center;">
            <?php if ($headline): ?>
            <h2 style="margin: 0 0 15px 0; font-size: 32px; color: #333;">
                <?php echo esc_html($headline); ?>
            </h2>
            <?php endif; ?>
            
            <?php if ($subheadline): ?>
            <p style="margin: 0 0 25px 0; font-size: 18px; color: #666; line-height: 1.6;">
                <?php echo esc_html($subheadline); ?>
            </p>
            <?php endif; ?>
            
            <?php if ($button_text && $button_url): ?>
            <div>
                <a href="<?php echo esc_url($button_url); ?>" class="sr-cta-button">
                    <?php echo esc_html($button_text); ?>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($image): ?>
        <div class="sr-cta-image" style="flex: 1; max-width: 50%;">
            <img src="<?php echo esc_url($image['url']); ?>" 
                 alt="<?php echo esc_attr($image['alt']); ?>"
                 style="width: 100%; height: auto; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        </div>
        <?php endif; ?>
    </div>
    
    <style>
        .sr-cta-button {
            background: #2860cc;
            border-radius: 50px;
            color: #fff !important;
            display: inline-block;
            text-decoration: none;
            font-size: 22px;
            font-weight: 600;
            margin: 10px 0;
            padding: 15px 60px;
            transition: 0.4s;
        }
        .sr-cta-button:hover {
            background: #ffd52f !important;
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .sr-cta-module {
                flex-direction: column !important;
            }
            .sr-cta-image {
                max-width: 100% !important;
            }
        }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('student_rotation_cta', 'sr_cta_module_shortcode');

/**
 * Register ACF Options Page for API Settings (Admin only)
 */
add_action('acf/init', function() {
    if (function_exists('acf_add_options_sub_page')) {
        acf_add_options_sub_page(array(
            'page_title'  => 'API Settings',
            'menu_title'  => 'API Settings',
            'parent_slug' => 'edit.php?post_type=student_rotation',
            'capability'  => 'manage_options',
            'menu_slug'   => 'sr-api-settings',
        ));
    }
});

/**
 * Register ACF Fields for API Settings
 */
add_action('acf/include_fields', function() {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group(array(
        'key' => 'group_sr_api_settings',
        'title' => 'Google Maps Configuration',
        'fields' => array(
            array(
                'key' => 'field_sr_google_maps_key',
                'label' => 'Google Maps API Key',
                'name' => 'sr_google_maps_key',
                'type' => 'text',
                'instructions' => 'Enter your Google Maps JavaScript API Key. <a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank">Get your API Key here</a>',
                'required' => 1,
                'placeholder' => 'AIzaSyDxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'options_page',
                    'operator' => '==',
                    'value' => 'sr-api-settings',
                ),
            ),
        ),
        'style' => 'default',
    ));
});