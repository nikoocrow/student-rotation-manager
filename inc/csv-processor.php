<?php
/**
 * CSV Processor for Student Rotations
 * 
 * @package StudentRotationManager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SR_CSV_Processor {
    
    /**
     * Parse CSV file and return array of rows
     * 
     * @param string $file_path Path to CSV file
     * @return array|WP_Error
     */
    public static function parse_csv($file_path) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'CSV file not found');
        }
        
        $rows = array();
        $headers = array();
        
        if (($handle = fopen($file_path, 'r')) !== false) {
            // Primera fila son los headers
            $headers = fgetcsv($handle);
            
            // Limpiar headers (remover espacios)
            $headers = array_map('trim', $headers);
            
            // Leer el resto de las filas
            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) === count($headers)) {
                    $row = array_combine($headers, $data);
                    $rows[] = $row;
                }
            }
            
            fclose($handle);
        }
        
        return array(
            'headers' => $headers,
            'rows' => $rows,
            'total' => count($rows)
        );
    }
    
    /**
     * Validate CSV data
     * 
     * @param array $rows Array of CSV rows
     * @return array Validation results
     */
    public static function validate_csv_data($rows) {
        $results = array(
            'valid' => array(),
            'warnings' => array(),
            'errors' => array()
        );
        
        $required_headers = array(
            'Location Title',
            'Brand',
            'Rotation Start Date',
            'Rotation End Date',
            'Description'
        );
        
        foreach ($rows as $index => $row) {
            $row_number = $index + 2; // +2 porque row 1 son headers
            $validation = self::validate_row($row, $row_number);
            
            if (!empty($validation['errors'])) {
                $results['errors'][] = $validation;
            } elseif (!empty($validation['warnings'])) {
                $results['warnings'][] = $validation;
            } else {
                $results['valid'][] = $validation;
            }
        }
        
        return $results;
    }
    
    /**
     * Validate single CSV row
     * 
     * @param array $row CSV row data
     * @param int $row_number Row number for error reporting
     * @return array Validation result
     */
    private static function validate_row($row, $row_number) {
        $result = array(
            'row_number' => $row_number,
            'data' => $row,
            'errors' => array(),
            'warnings' => array(),
            'location_id' => null
        );
        
        // 1. Validar que Location Title existe
        $location_title = trim($row['Location Title']);
        
        if (empty($location_title)) {
            $result['errors'][] = 'Location Title is required';
            return $result;
        }
        
        $location = get_page_by_title($location_title, OBJECT, 'location');
        
        if (!$location) {
            $result['errors'][] = "Location '{$location_title}' not found in WordPress";
            return $result;
        }
        
        $result['location_id'] = $location->ID;
        
        // 2. Validar Brand
        $csv_brand = trim($row['Brand']);
        $brand_terms = get_the_terms($location->ID, 'brand');
        
        if ($brand_terms && !is_wp_error($brand_terms)) {
            $location_brand = $brand_terms[0]->name;
            
            if ($location_brand !== $csv_brand) {
                $result['warnings'][] = "Brand mismatch: Location has '{$location_brand}' but CSV says '{$csv_brand}'";
            }
        } else {
            $result['warnings'][] = "No brand found for this location";
        }
        
        // 3. Validar Start Date
        $start_date = DateTime::createFromFormat('m/d/Y', trim($row['Rotation Start Date']));
        
        if (!$start_date) {
            $result['errors'][] = "Invalid start date format. Use MM/DD/YYYY";
            return $result;
        }
        
        $result['start_date'] = $start_date;
        
        // 4. Validar End Date
        $end_date = DateTime::createFromFormat('m/d/Y', trim($row['Rotation End Date']));
        
        if (!$end_date) {
            $result['errors'][] = "Invalid end date format. Use MM/DD/YYYY";
            return $result;
        }
        
        if ($end_date <= $start_date) {
            $result['errors'][] = "End date must be after start date";
            return $result;
        }
        
        $result['end_date'] = $end_date;
        
        // 5. Validar Description
        if (empty(trim($row['Description']))) {
            $result['errors'][] = "Description is required";
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Process import - Create student rotations
     * 
     * @param array $validated_rows Validated rows to import
     * @param bool $is_master_sheet Whether to delete existing rotations
     * @return array Import results
     */
    public static function process_import($validated_rows, $is_master_sheet = false) {
        $results = array(
            'created' => array(),
            'failed' => array(),
            'deleted' => 0
        );
        
        // Modo A: Master Sheet - Eliminar todas las rotations existentes
        if ($is_master_sheet) {
            $existing = get_posts(array(
                'post_type' => 'student_rotation',
                'posts_per_page' => -1,
                'post_status' => 'any'
            ));
            
            foreach ($existing as $post) {
                wp_delete_post($post->ID, true); // Force delete
                $results['deleted']++;
            }
        }
        
        // Crear nuevas rotations
        foreach ($validated_rows as $row_data) {
            $created = self::create_rotation_from_row($row_data);
            
            if (is_wp_error($created)) {
                $results['failed'][] = array(
                    'row' => $row_data['row_number'],
                    'error' => $created->get_error_message()
                );
            } else {
                $results['created'][] = array(
                    'row' => $row_data['row_number'],
                    'post_id' => $created,
                    'title' => get_the_title($created)
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Create single rotation from CSV row
     * 
     * @param array $row_data Validated row data
     * @return int|WP_Error Post ID or error
     */
    private static function create_rotation_from_row($row_data) {
        $row = $row_data['data'];
        $location_id = $row_data['location_id'];
        $start_date = $row_data['start_date'];
        $end_date = $row_data['end_date'];
        
        // Generar título del post
        $post_title = trim($row['Location Title']) . ' - ' . $start_date->format('m/d/Y');
        
        // Crear post
        $post_id = wp_insert_post(array(
            'post_type' => 'student_rotation',
            'post_title' => $post_title,
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        ));
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Guardar campos ACF
        update_field('rotation_location', $location_id, $post_id);
        update_field('rotation_start_date', $start_date->format('Ymd'), $post_id);
        update_field('rotation_end_date', $end_date->format('Ymd'), $post_id);
        update_field('rotation_description', trim($row['Description']), $post_id);
        
        // Campos opcionales
        if (!empty(trim($row['Eligibility Criteria']))) {
            update_field('rotation_eligibility', trim($row['Eligibility Criteria']), $post_id);
        }
        
        if (!empty(trim($row['Onboarding Requirements']))) {
            update_field('rotation_onboarding', trim($row['Onboarding Requirements']), $post_id);
        }
        
        // Brand y Availability se auto-generan via hooks en meta-fields.php
        
        return $post_id;
    }
}