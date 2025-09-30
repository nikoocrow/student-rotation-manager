<?php
/**
 * CSV Upload Admin Page
 * 
 * @package StudentRotationManager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add CSV Upload submenu to Student Rotations
 */
function sr_add_csv_upload_page() {
    add_submenu_page(
        'edit.php?post_type=student_rotation',
        'Import CSV',
        'Import CSV',
        'publish_student_rotations', // Solo quien puede crear rotations
        'sr-csv-upload',
        'sr_csv_upload_page_content'
    );
}
add_action('admin_menu', 'sr_add_csv_upload_page');

/**
 * Render CSV Upload page content
 */
function sr_csv_upload_page_content() {
    // Verificar permisos
    if (!current_user_can('publish_student_rotations')) {
        wp_die('You do not have permission to access this page.');
    }
    
    // Procesar formularios
    sr_handle_csv_upload();
    
    ?>
    <div class="wrap">
        <h1>Import Student Rotations from CSV</h1>
        
        <?php sr_render_upload_instructions(); ?>
        
        <?php 
        // Mostrar diferentes vistas según el estado
        if (isset($_POST['sr_preview_csv']) && isset($_SESSION['sr_csv_data'])) {
            sr_render_preview();
        } elseif (isset($_POST['sr_confirm_import']) && isset($_SESSION['sr_import_results'])) {
            sr_render_results();
        } else {
            sr_render_upload_form();
        }
        ?>
    </div>
    <?php
}

/**
 * Handle CSV upload and processing
 */


function sr_handle_csv_upload() {
    // Iniciar sesión
    if (!session_id()) {
        session_start();
    }
    
    // PASO 1: Upload CSV
    if (isset($_POST['sr_preview_csv'])) {
        
        // Verificar nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'sr_csv_upload')) {
            add_settings_error('sr_csv', 'nonce_error', 'Security check failed.');
            error_log('Nonce failed');
            return;
        }
        
        // Verificar que se subió un archivo
        if (empty($_FILES['csv_file']['tmp_name'])) {
            add_settings_error('sr_csv', 'no_file', 'Please select a CSV file to upload.');
            return;
        }
        
        // Verificar errores de subida
        if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            add_settings_error('sr_csv', 'upload_error', 'File upload error: ' . $_FILES['csv_file']['error']);
            return;
        }
        
        // Parsear CSV
        $csv_data = SR_CSV_Processor::parse_csv($_FILES['csv_file']['tmp_name']);
        
        if (is_wp_error($csv_data)) {
            add_settings_error('sr_csv', 'parse_error', $csv_data->get_error_message());
            return;
        }
        
        // Validar datos
        $validation = SR_CSV_Processor::validate_csv_data($csv_data['rows']);
        
        // Guardar en sesión para el preview
        $_SESSION['sr_csv_data'] = array(
            'validation' => $validation,
            'is_master_sheet' => isset($_POST['is_master_sheet']) && $_POST['is_master_sheet'] === '1',
            'total_rows' => $csv_data['total']
        );
        
        // Success message
        add_settings_error('sr_csv', 'preview_ready', 'CSV uploaded successfully. Review the preview below.', 'success');
        
        return;
    }
    
    // PASO 2: Usuario confirma import
    if (isset($_POST['sr_confirm_import']) && isset($_POST['_wpnonce'])) {
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'sr_csv_confirm')) {
            add_settings_error('sr_csv', 'nonce_error', 'Security check failed.');
            return;
        }
        
        if (!isset($_SESSION['sr_csv_data'])) {
            add_settings_error('sr_csv', 'no_data', 'Session expired. Please upload the CSV again.');
            return;
        }
        
        $csv_data = $_SESSION['sr_csv_data'];
        $validation = $csv_data['validation'];
        
        $rows_to_import = $validation['valid'];
        
        if (isset($_POST['include_warnings']) && $_POST['include_warnings'] === '1') {
            $rows_to_import = array_merge($rows_to_import, $validation['warnings']);
        }
        
        $results = SR_CSV_Processor::process_import(
            $rows_to_import, 
            $csv_data['is_master_sheet']
        );
        
        $_SESSION['sr_import_results'] = $results;
        unset($_SESSION['sr_csv_data']);
        
        return;
    }
    
    // PASO 3: Finalizar
    if (isset($_POST['sr_finish'])) {
        unset($_SESSION['sr_import_results']);
        unset($_SESSION['sr_csv_data']);
        wp_redirect(admin_url('edit.php?post_type=student_rotation'));
        exit;
    }
}

/**
 * Render upload instructions
 */
function sr_render_upload_instructions() {
    ?>
    <div class="notice notice-info">
        <h3>CSV Format Requirements</h3>
        <p>Your CSV file must include these columns (in any order):</p>
        <ul style="list-style: disc; margin-left: 20px;">
            <li><strong>Location Title</strong> - Must match existing location name exactly</li>
            <li><strong>Brand</strong> - Brand name (will be validated against location)</li>
            <li><strong>Rotation Start Date</strong> - Format: MM/DD/YYYY</li>
            <li><strong>Rotation End Date</strong> - Format: MM/DD/YYYY</li>
            <li><strong>Description</strong> - Clinical rotation description</li>
            <li><strong>Eligibility Criteria</strong> - Optional</li>
            <li><strong>Onboarding Requirements</strong> - Optional</li>
        </ul>
        <p><strong>Master Sheet Mode:</strong> If checked, ALL existing student rotations will be deleted before importing.</p>
    </div>
    <?php
}

/**
 * Render upload form
 */
function sr_render_upload_form() {
    ?>
    <div class="card_admin" style="margin-top: 20px; background-color:#fff; padding:15px">
        <h2>Upload CSV File</h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('sr_csv_upload'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="csv_file">CSV File</label>
                    </th>
                    <td>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                        <p class="description">Select a CSV file to upload</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="is_master_sheet">Import Mode</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="is_master_sheet" id="is_master_sheet" value="1">
                            <strong style="color: #d63638;">Master Sheet Mode</strong> - Delete all existing rotations
                        </label>
                        <p class="description">
                            Check this box to replace ALL existing student rotations with the data from this CSV.
                            <strong>This action cannot be undone!</strong>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('Upload and Preview', 'primary', 'sr_preview_csv'); ?>
        </form>
    </div>
    <?php
}

/**
 * Render preview of CSV data
 */
function sr_render_preview() {
    $csv_data = $_SESSION['sr_csv_data'];
    $validation = $csv_data['validation'];
    $is_master = $csv_data['is_master_sheet'];
    
    $valid_count = count($validation['valid']);
    $warning_count = count($validation['warnings']);
    $error_count = count($validation['errors']);
    
    ?>
    <div class="card_admin" style="margin-top: 20px; background-color:#fff; padding:15px">
        <h2>Preview Import</h2>
        
        <?php if ($is_master): ?>
        <div class="notice notice-error inline">
            <p><strong>WARNING: Master Sheet Mode</strong></p>
            <p>All existing student rotations will be deleted before importing this CSV.</p>
        </div>
        <?php endif; ?>
        
        <div style="display: flex; gap: 20px; margin: 20px 0;">
            <div style="padding: 15px; background: #e7f5e7; border-left: 4px solid #46b450;">
                <strong style="font-size: 24px; color: #46b450;"><?php echo $valid_count; ?></strong>
                <div>Valid Rows</div>
            </div>
            
            <?php if ($warning_count > 0): ?>
            <div style="padding: 15px; background: #fff8e5; border-left: 4px solid #f0b849;">
                <strong style="font-size: 24px; color: #f0b849;"><?php echo $warning_count; ?></strong>
                <div>Warnings</div>
            </div>
            <?php endif; ?>
            
            <?php if ($error_count > 0): ?>
            <div style="padding: 15px; background: #fce8e8; border-left: 4px solid #d63638;">
                <strong style="font-size: 24px; color: #d63638;"><?php echo $error_count; ?></strong>
                <div>Errors</div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Valid Rows -->
        <?php if ($valid_count > 0): ?>
        <h3 style="color: #46b450;">✓ Valid Rows (<?php echo $valid_count; ?>)</h3>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>Row</th>
                    <th>Location</th>
                    <th>Brand</th>
                    <th>Dates</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($validation['valid'] as $row): ?>
                <tr>
                    <td><?php echo $row['row_number']; ?></td>
                    <td><?php echo esc_html($row['data']['Location Title']); ?></td>
                    <td><?php echo esc_html($row['data']['Brand']); ?></td>
                    <td><?php echo esc_html($row['data']['Rotation Start Date'] . ' - ' . $row['data']['Rotation End Date']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <!-- Warning Rows -->
        <?php if ($warning_count > 0): ?>
        <h3 style="color: #f0b849; margin-top: 30px;">⚠ Warnings (<?php echo $warning_count; ?>)</h3>
        <p>These rows have warnings but can still be imported:</p>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>Row</th>
                    <th>Location</th>
                    <th>Brand</th>
                    <th>Warning</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($validation['warnings'] as $row): ?>
                <tr>
                    <td><?php echo $row['row_number']; ?></td>
                    <td><?php echo esc_html($row['data']['Location Title']); ?></td>
                    <td><?php echo esc_html($row['data']['Brand']); ?></td>
                    <td style="color: #f0b849;"><?php echo esc_html(implode(', ', $row['warnings'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <!-- Error Rows -->
        <?php if ($error_count > 0): ?>
        <h3 style="color: #d63638; margin-top: 30px;">✗ Errors (<?php echo $error_count; ?>)</h3>
        <p>These rows cannot be imported and will be skipped:</p>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>Row</th>
                    <th>Location</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($validation['errors'] as $row): ?>
                <tr>
                    <td><?php echo $row['row_number']; ?></td>
                    <td><?php echo esc_html($row['data']['Location Title'] ?? 'N/A'); ?></td>
                    <td style="color: #d63638;"><?php echo esc_html(implode(', ', $row['errors'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <!-- Confirmation Form -->
        <form method="post" style="margin-top: 30px;">
            <?php wp_nonce_field('sr_csv_confirm'); ?>
            
            <?php if ($warning_count > 0): ?>
            <p>
                <label>
                    <input type="checkbox" name="include_warnings" value="1" checked>
                    <strong>Import rows with warnings</strong>
                </label>
            </p>
            <?php endif; ?>
            
            <?php if ($valid_count > 0 || $warning_count > 0): ?>
                <p>
                    <input type="submit" name="sr_confirm_import" class="button button-primary button-large" 
                           value="<?php echo $is_master ? 'Delete All & Import' : 'Confirm Import'; ?>">
                    <a href="<?php echo admin_url('edit.php?post_type=student_rotation&page=sr-csv-upload'); ?>" 
                       class="button button-secondary button-large">Cancel</a>
                </p>
            <?php else: ?>
                <div class="notice notice-error inline">
                    <p>No valid rows to import. Please fix the errors and try again.</p>
                </div>
                <p>
                    <a href="<?php echo admin_url('edit.php?post_type=student_rotation&page=sr-csv-upload'); ?>" 
                       class="button button-primary">Upload Another File</a>
                </p>
            <?php endif; ?>
        </form>
    </div>
    <?php
}

/**
 * Render import results
 */
function sr_render_results() {
    $results = $_SESSION['sr_import_results'];
    
    $created_count = count($results['created']);
    $failed_count = count($results['failed']);
    $deleted_count = $results['deleted'];
    
    ?>
    <div class="card_admin" style="margin-top: 20px; background-color:#fff; padding:15px">
        <h2>Import Complete!</h2>
        
        <div style="display: flex; gap: 20px; margin: 20px 0;">
            <?php if ($deleted_count > 0): ?>
            <div style="padding: 15px; background: #fce8e8; border-left: 4px solid #d63638;">
                <strong style="font-size: 24px; color: #d63638;"><?php echo $deleted_count; ?></strong>
                <div>Rotations Deleted</div>
            </div>
            <?php endif; ?>
            
            <div style="padding: 15px; background: #e7f5e7; border-left: 4px solid #46b450;">
                <strong style="font-size: 24px; color: #46b450;"><?php echo $created_count; ?></strong>
                <div>Rotations Created</div>
            </div>
            
            <?php if ($failed_count > 0): ?>
            <div style="padding: 15px; background: #fce8e8; border-left: 4px solid #d63638;">
                <strong style="font-size: 24px; color: #d63638;"><?php echo $failed_count; ?></strong>
                <div>Failed</div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($created_count > 0): ?>
        <h3>Successfully Created:</h3>
        <ul style="list-style: disc; margin-left: 20px;">
            <?php foreach ($results['created'] as $created): ?>
            <li>
                Row <?php echo $created['row']; ?>: 
                <a href="<?php echo get_edit_post_link($created['post_id']); ?>">
                    <?php echo esc_html($created['title']); ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        
        <?php if ($failed_count > 0): ?>
        <h3 style="color: #d63638;">Failed:</h3>
        <ul style="list-style: disc; margin-left: 20px;">
            <?php foreach ($results['failed'] as $failed): ?>
            <li style="color: #d63638;">
                Row <?php echo $failed['row']; ?>: <?php echo esc_html($failed['error']); ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        
        <form method="post" style="margin-top: 30px;">
            <p>
                <a href="<?php echo admin_url('edit.php?post_type=student_rotation'); ?>" 
                   class="button button-primary button-large">View All Rotations</a>
                <a href="<?php echo admin_url('edit.php?post_type=student_rotation&page=sr-csv-upload'); ?>" 
                   class="button button-secondary button-large">Import Another File</a>
            </p>
        </form>
    </div>
    <?php
}