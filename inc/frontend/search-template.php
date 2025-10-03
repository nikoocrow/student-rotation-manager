<?php
/**
 * Student Rotation Search Template
 * Actualizado con integración de formulario
 * 
 * @package StudentRotationManager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode para la página de búsqueda
 */
function sr_search_page_shortcode($atts) {
    wp_enqueue_style('sr-search-styles');
    wp_enqueue_script('sr-search-scripts');
    
    ob_start();
    ?>
    <div class="sr-search-container">
        <!-- Header -->
        <div class="sr-search-header">
            <h1>Student Rotation Search</h1>
        </div>
        
        <!-- Filters -->
        <div class="sr-filters">
            <div class="sr-filter-group">
                <label for="sr-filter-location">Filter by Location:</label>
                <select id="sr-filter-location" class="sr-filter-select">
                    <option value="">All Locations</option>
                    <?php sr_get_location_options(); ?>
                </select>
            </div>
            
            <div class="sr-filter-group">
                <label for="sr-filter-brand">Filter by Brand:</label>
                <select id="sr-filter-brand" class="sr-filter-select">
                    <option value="">All Brands</option>
                    <?php sr_get_brand_options(); ?>
                </select>
            </div>
            
            <button id="sr-clear-filters" class="sr-btn-secondary">Clear Filters</button>
        </div>
        
        <!-- Map -->
        <div id="sr-map" class="sr-map"></div>
        
        <!-- Results -->
        <div id="sr-results" class="sr-results">
            <div class="sr-results-count">
                <span id="sr-count">Loading...</span>
            </div>
            
            <div id="sr-cards-container" class="sr-cards-grid">
                <!-- Cards se cargan via JavaScript -->
            </div>
        </div>
        
        <!-- Form Section -->
        <div class="sr-form-section" id="sr-contact-form">
            <?php sr_render_contact_form(); ?>
        </div>
    </div>
    
    <!-- Modal Template -->
    <div id="sr-modal" class="sr-modal" style="display: none;">
        <div class="sr-modal-overlay"></div>
        <div class="sr-modal-content">
            <button class="sr-modal-close">&times;</button>
            <div id="sr-modal-body">
                <!-- Content loaded via JS -->
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('student_rotation_search', 'sr_search_page_shortcode');

/**
 * Get location options for filter
 */
function sr_get_location_options() {
    $locations = get_posts(array(
        'post_type' => 'location',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'post_status' => 'publish'
    ));
    
    foreach ($locations as $location) {
        echo '<option value="' . esc_attr($location->ID) . '">' . esc_html($location->post_title) . '</option>';
    }
}

/**
 * Get brand options for filter
 */
function sr_get_brand_options() {
    $brands = get_terms(array(
        'taxonomy' => 'brand',
        'hide_empty' => true,
    ));
    
    if (!is_wp_error($brands)) {
        foreach ($brands as $brand) {
            echo '<option value="' . esc_attr($brand->term_id) . '">' . esc_html($brand->name) . '</option>';
        }
    }
}

/**
 * AJAX: Get rotations data with coordinates
 */
function sr_ajax_get_rotations() {
    $location_id = isset($_GET['location']) ? intval($_GET['location']) : 0;
    $brand_id = isset($_GET['brand']) ? intval($_GET['brand']) : 0;
    
    $args = array(
        'post_type' => 'student_rotation',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC',
    );
    
    $meta_query = array('relation' => 'AND');
    
    if ($location_id > 0) {
        $meta_query[] = array(
            'key' => 'rotation_location',
            'value' => $location_id,
            'compare' => '='
        );
    }
    
    if (!empty($meta_query)) {
        $args['meta_query'] = $meta_query;
    }
    
    $rotations = get_posts($args);
    $data = array();
    
    foreach ($rotations as $rotation) {
        $location_id = get_field('rotation_location', $rotation->ID);
        $location_brand_terms = get_the_terms($location_id, 'brand');
        
        // Filter by brand
        if ($brand_id > 0) {
            $has_brand = false;
            if ($location_brand_terms) {
                foreach ($location_brand_terms as $term) {
                    if ($term->term_id == $brand_id) {
                        $has_brand = true;
                        break;
                    }
                }
            }
            if (!$has_brand) continue;
        }
        
        $location_post = get_post($location_id);
        
        // Obtener coordenadas de la location
        $latitude = get_post_meta($location_id, 'latitude', true);
        $longitude = get_post_meta($location_id, 'longitude', true);
        
        $data[] = array(
            'id' => $rotation->ID,
            'title' => $rotation->post_title,
            'location_name' => $location_post ? $location_post->post_title : '',
            'location_id' => $location_id,
            'brand' => get_field('rotation_brand', $rotation->ID),
            'availability' => get_field('rotation_availability', $rotation->ID),
            'address' => get_post_meta($location_id, 'address', true),
            'city' => get_post_meta($location_id, 'city', true),
            'state' => get_post_meta($location_id, 'state', true),
            'lat' => $latitude ? floatval($latitude) : 0,
            'lng' => $longitude ? floatval($longitude) : 0,
            'description' => get_field('rotation_description', $rotation->ID),
            'eligibility' => get_field('rotation_eligibility', $rotation->ID),
            'onboarding' => get_field('rotation_onboarding', $rotation->ID),
        );
    }
    
    wp_send_json_success($data);
}
add_action('wp_ajax_sr_get_rotations', 'sr_ajax_get_rotations');
add_action('wp_ajax_nopriv_sr_get_rotations', 'sr_ajax_get_rotations');

/**
 * Render contact form
 */
function sr_render_contact_form() {
    // Obtener configuración del formulario
    $headline = get_field('sr_form_headline', 'option');
    $subheadline = get_field('sr_form_subheadline', 'option');
    $form_shortcode = get_field('sr_form_shortcode', 'option');
    $field_name = get_field('sr_position_field_name', 'option');
    
    // Valores por defecto
    if (empty($headline)) {
        $headline = 'Interested in a Student Rotation?';
    }
    if (empty($subheadline)) {
        $subheadline = 'Fill out the form below and we\'ll get back to you soon.';
    }
    if (empty($field_name)) {
        $field_name = 'rotation-position';
    }
    
    ?>
    <div class="sr-contact-form">
        <h2><?php echo esc_html($headline); ?></h2>
        <p><?php echo esc_html($subheadline); ?></p>
        
        <?php if (!empty($form_shortcode)): ?>
            <div class="sr-form-wrapper">
                <?php echo do_shortcode($form_shortcode); ?>
                
                <!-- Campo oculto para la posición -->
                <input type="hidden" 
                       id="sr-rotation-position" 
                       name="<?php echo esc_attr($field_name); ?>" 
                       value=""
                       data-field-name="<?php echo esc_attr($field_name); ?>">
            </div>
        <?php else: ?>
            <div class="sr-form-notice">
                <p><strong>Form not configured.</strong></p>
                <p>Please go to <strong>Student Rotations → Form Settings</strong> to configure the contact form.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
        .sr-form-notice {
            padding: 20px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            margin-top: 20px;
        }
        
        .sr-form-notice p {
            margin: 5px 0;
        }
    </style>
    <?php
}

/**
 * Script para poblar el campo oculto en Contact Form 7
 */
function sr_populate_cf7_hidden_field() {
    if (!is_admin()) {
        ?>
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            // Función para actualizar campos ocultos en Contact Form 7
            function updateCF7HiddenField() {
                var positionValue = document.getElementById('sr-rotation-position')?.value;
                var fieldName = document.getElementById('sr-rotation-position')?.dataset.fieldName;
                
                if (positionValue && fieldName) {
                    // Buscar el campo oculto en el formulario CF7
                    var cf7HiddenFields = document.querySelectorAll('input[name="' + fieldName + '"]');
                    cf7HiddenFields.forEach(function(field) {
                        if (field.type === 'hidden' && field.id !== 'sr-rotation-position') {
                            field.value = positionValue;
                        }
                    });
                }
            }
            
            // Actualizar cuando cambia el valor
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                        updateCF7HiddenField();
                    }
                });
            });
            
            var targetNode = document.getElementById('sr-rotation-position');
            if (targetNode) {
                observer.observe(targetNode, { attributes: true });
                
                // También actualizar cuando se hace scroll al formulario
                setTimeout(updateCF7HiddenField, 500);
            }
        });
        </script>
        <?php
    }
}
add_action('wp_footer', 'sr_populate_cf7_hidden_field');

/**
 * Filtro para Gravity Forms - Popular campo dinámicamente
 */
/**
 * Filtro para Gravity Forms - Popular campo dinámicamente
 */
function sr_populate_gravity_forms_field($value, $field, $name) {
    // Verificar si es el campo de rotation_position
    if ($name === 'rotation_position') {
        // Primero intentar obtener de $_GET
        if (isset($_GET['rotation_position'])) {
            return urldecode($_GET['rotation_position']);
        }
        
        // Si no está en GET, intentar obtener del campo oculto JavaScript
        // Esto se llenará vía JavaScript cuando el usuario haga clic
        return $value;
    }
    return $value;
}
add_filter('gform_field_value_rotation_position', 'sr_populate_gravity_forms_field', 10, 3);

/**
 * Enqueue styles and scripts
 */
function sr_enqueue_search_assets() {
    // Styles
    wp_register_style(
        'sr-search-styles',
        SR_PLUGIN_URL . 'assets/css/search.css',
        array(),
        SR_VERSION
    );
    
    // Scripts
    wp_register_script(
        'sr-search-scripts',
        SR_PLUGIN_URL . 'assets/js/search.js',
        array('jquery'),
        SR_VERSION,
        true
    );
    
    // Obtener el shortcode y extraer el ID
    $form_shortcode = get_field('sr_form_shortcode', 'option');
    $form_id = 0;
    $form_type = 'gravity'; // Por defecto
    
    // Detectar tipo de formulario
    if (strpos($form_shortcode, 'contact-form-7') !== false) {
        $form_type = 'cf7';
        // Para CF7 con hash: [contact-form-7 id="e7e1fa5"]
        if (preg_match('/\[contact-form-7\s+id=["\']?([a-zA-Z0-9]+)["\']?/i', $form_shortcode, $matches)) {
            $form_id = $matches[1]; // Puede ser hash o número
        }
    } elseif (strpos($form_shortcode, 'gravityform') !== false) {
        $form_type = 'gravity';
        // Para GF: [gravityform id="18"]
        if (preg_match('/\[gravityform\s+id=["\']?(\d+)["\']?/i', $form_shortcode, $matches)) {
            $form_id = intval($matches[1]);
        }
    }
    
    $field_name = get_field('sr_position_field_name', 'option');
    if (empty($field_name)) {
        $field_name = 'rotation_position';
    }
    
    wp_localize_script('sr-search-scripts', 'srData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sr_search_nonce'),
        'google_maps_key' => get_field('sr_google_maps_key', 'option'),
        'map_pin_icon' => SR_PLUGIN_URL . 'assets/images/map-pin.svg',
        'position_field_name' => $field_name,
        'gravity_form_id' => $form_id,
        'form_type' => $form_type, // NUEVO: tipo de formulario
    ));
}
add_action('wp_enqueue_scripts', 'sr_enqueue_search_assets');