/**
 * Student Rotation Search - Map, Cards y Formulario
 * Versión Final con Debug
 */

(function($) {
    'use strict';
    
    let map;
    let markers = [];
    let rotations = [];
    let infoWindows = [];
    let currentPage = 1;
    const itemsPerPage = 9;
    
    /**
     * Initialize
     */
    $(document).ready(function() {
        console.log('Search JS loaded');
        console.log('API Key:', srData.google_maps_key);
        console.log('Position Field Name:', srData.position_field_name);
        
        loadGoogleMaps();
        setupFilters();
    });
    
    /**
     * Load Google Maps API
     */
    function loadGoogleMaps() {
        const apiKey = srData.google_maps_key;
        
        if (!apiKey) {
            $('#sr-map').html('<div style="padding: 40px; text-align: center; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px;"><strong>Google Maps API Key not configured.</strong><br>Please go to Student Rotations → API Settings to add your API key.</div>');
            loadRotations();
            return;
        }
        
        if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
            initMap();
            return;
        }
        
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&callback=initMap`;
        script.async = true;
        script.defer = true;
        script.onerror = function() {
            $('#sr-map').html('<div style="padding: 40px; text-align: center; background: #f8d7da; border: 1px solid #dc3545; border-radius: 8px;"><strong>Error loading Google Maps.</strong><br>Please check your API key.</div>');
            loadRotations();
        };
        document.head.appendChild(script);
    }
    
    /**
     * Initialize Google Map
     */
    window.initMap = function() {
        console.log('Initializing map...');
        
        const centerUS = { lat: 39.8283, lng: -98.5795 };
        
        map = new google.maps.Map(document.getElementById('sr-map'), {
            center: centerUS,
            zoom: 4,
            styles: [
                {
                    featureType: 'poi',
                    elementType: 'labels',
                    stylers: [{ visibility: 'off' }]
                }
            ]
        });
        
        console.log('Map initialized');
        loadRotations();
    };
    
    /**
     * Load rotations via AJAX
     */
    function loadRotations() {
        console.log('Loading rotations...');
        
        currentPage = 1;
        
        const locationFilter = $('#sr-filter-location').val();
        const brandFilter = $('#sr-filter-brand').val();
        
        console.log('Filters - Location:', locationFilter, 'Brand:', brandFilter);
        
        $.ajax({
            url: srData.ajaxurl,
            type: 'GET',
            data: {
                action: 'sr_get_rotations',
                location: locationFilter,
                brand: brandFilter,
                nonce: srData.nonce
            },
            success: function(response) {
                console.log('AJAX response:', response);
                
                if (response.success) {
                    rotations = response.data;
                    console.log('Rotations loaded:', rotations.length);
                    
                    if (rotations.length > 0) {
                        console.log('First rotation:', rotations[0]);
                    }
                    
                    updateMap();
                    updateCards();
                    updateCount();
                } else {
                    console.error('Error loading rotations');
                    $('#sr-count').text('Error loading rotations');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                $('#sr-count').text('Error loading rotations');
            }
        });
    }
    
    /**
     * Update map with markers
     */
    function updateMap() {
        if (!map) {
            console.log('No map available');
            return;
        }
        
        console.log('Updating map with', rotations.length, 'rotations');
        
        markers.forEach(marker => marker.setMap(null));
        markers = [];
        infoWindows.forEach(iw => iw.close());
        infoWindows = [];
        
        const locationGroups = {};
        
        rotations.forEach(rotation => {
            const locId = rotation.location_id;
            if (!locationGroups[locId]) {
                locationGroups[locId] = [];
            }
            locationGroups[locId].push(rotation);
        });
        
        console.log('Location groups:', Object.keys(locationGroups).length);
        
        const bounds = new google.maps.LatLngBounds();
        let hasValidCoords = false;
        
        Object.keys(locationGroups).forEach(locId => {
            const rotationsAtLocation = locationGroups[locId];
            const firstRotation = rotationsAtLocation[0];
            
            const lat = parseFloat(firstRotation.lat);
            const lng = parseFloat(firstRotation.lng);
            
            console.log('Location:', firstRotation.location_name, 'Lat:', lat, 'Lng:', lng);
            
            if (isNaN(lat) || isNaN(lng) || lat === 0 || lng === 0) {
                console.warn('Invalid coordinates for location:', firstRotation.location_name, lat, lng);
                return;
            }
            
            hasValidCoords = true;
            const position = { lat, lng };
            
            const marker = new google.maps.Marker({
                position: position,
                map: map,
                title: firstRotation.location_name,
                icon: {
                    url: srData.map_pin_icon || 'https://maps.google.com/mapfiles/ms/icons/red-dot.png',
                    scaledSize: new google.maps.Size(45, 45),
                    anchor: new google.maps.Point(16, 32)
                }
            });
            
            const content = `
                <div style="padding: 10px; max-width: 250px;">
                    <h3 style="margin: 0 0 10px 0; font-size: 16px;">${firstRotation.location_name}</h3>
                    <p style="margin: 0 0 5px 0;"><strong>${firstRotation.brand || 'N/A'}</strong></p>
                    <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">${firstRotation.address || ''}<br>${firstRotation.city || ''}, ${firstRotation.state || ''}</p>
                    <p style="margin: 0; font-size: 14px;"><strong>${rotationsAtLocation.length}</strong> rotation(s) available</p>
                </div>
            `;
            
            const infoWindow = new google.maps.InfoWindow({
                content: content
            });
            
            marker.addListener('click', function() {
                infoWindows.forEach(iw => iw.close());
                infoWindow.open(map, marker);
                
                const firstCard = $(`.sr-card[data-location="${locId}"]`).first();
                if (firstCard.length) {
                    $('html, body').animate({
                        scrollTop: firstCard.offset().top - 100
                    }, 500);
                }
            });
            
            markers.push(marker);
            infoWindows.push(infoWindow);
            bounds.extend(position);
        });
        
        if (hasValidCoords && markers.length > 0) {
            map.fitBounds(bounds);
            
            if (markers.length === 1) {
                google.maps.event.addListenerOnce(map, 'bounds_changed', function() {
                    map.setZoom(12);
                });
            }
        } else {
            console.warn('No valid coordinates found, map will not be updated');
        }
        
        console.log('Map updated with', markers.length, 'markers');
    }
    
    /**
     * Update cards display with pagination
     */
    function updateCards() {
        const container = $('#sr-cards-container');
        container.empty();
        
        if (rotations.length === 0) {
            container.html('<p style="text-align: center; padding: 40px; background: #f0f0f0; border-radius: 8px;">No rotations found matching your criteria.</p>');
            $('.sr-pagination').remove();
            return;
        }
        
        const totalPages = Math.ceil(rotations.length / itemsPerPage);
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const pageRotations = rotations.slice(startIndex, endIndex);
        
        console.log('Displaying rotations', startIndex, 'to', endIndex, 'of', rotations.length);
        
        pageRotations.forEach(rotation => {
            const card = createCard(rotation);
            container.append(card);
        });
        
        createPagination(totalPages);
    }
    
    /**
     * Create pagination controls
     */
    function createPagination(totalPages) {
        $('.sr-pagination').remove();
        
        if (totalPages <= 1) return;
        
        const pagination = $('<div class="sr-pagination"></div>');
        
        if (currentPage > 1) {
            pagination.append(`<button class="sr-page-btn" data-page="${currentPage - 1}">← Previous</button>`);
        }
        
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                const activeClass = i === currentPage ? 'active' : '';
                pagination.append(`<button class="sr-page-btn ${activeClass}" data-page="${i}">${i}</button>`);
            } else if (i === currentPage - 2 || i === currentPage + 2) {
                pagination.append(`<span class="sr-page-dots">...</span>`);
            }
        }
        
        if (currentPage < totalPages) {
            pagination.append(`<button class="sr-page-btn" data-page="${currentPage + 1}">Next →</button>`);
        }
        
        $('#sr-cards-container').after(pagination);
        
        $('.sr-page-btn').on('click', function() {
            currentPage = parseInt($(this).data('page'));
            updateCards();
            
            $('html, body').animate({
                scrollTop: $('#sr-results').offset().top - 100
            }, 300);
        });
    }
    
    /**
     * Create a card element
     */
    function createCard(rotation) {
        return $(`
            <div class="sr-card" data-id="${rotation.id}" data-location="${rotation.location_id}">
                <div class="sr-card-header">
                    <h3>${rotation.location_name}</h3>
                    <span class="sr-card-brand">${rotation.brand || 'N/A'}</span>
                </div>
                <div class="sr-card-body">
                    <p class="sr-card-address">${rotation.address || ''}<br>${rotation.city || ''}, ${rotation.state || ''}</p>
                    <p class="sr-card-dates"><strong>Available:</strong> ${rotation.availability || 'N/A'}</p>
                </div>
                <div class="sr-card-footer">
                    <button class="sr-btn-details" data-id="${rotation.id}">Rotation Details</button>
                    <button class="sr-btn-apply" data-id="${rotation.id}">Learn More</button>
                </div>
            </div>
        `);
    }
    
    /**
     * Update results count
     */
    function updateCount() {
        const count = rotations.length;
        const text = count === 1 ? '1 rotation found' : `${count} rotations found`;
        $('#sr-count').text(text);
    }
    
    /**
     * Setup filter event listeners
     */
    function setupFilters() {
        $('#sr-filter-location, #sr-filter-brand').on('change', function() {
            console.log('Filter changed');
            loadRotations();
        });
        
        $('#sr-clear-filters').on('click', function() {
            console.log('Clearing filters');
            $('#sr-filter-location, #sr-filter-brand').val('');
            loadRotations();
        });
    }
    
    /**
     * Populate hidden form field
     */
    /***/

    function populateFormField(rotationData) {
    const positionText = `${rotationData.location_name} - ${rotationData.availability}`;
    
    console.log('=== DEBUG INFO ===');
    console.log('Position text:', positionText);
    console.log('Form type:', srData.form_type);
    console.log('Form ID:', srData.gravity_form_id);
    console.log('Position field name:', srData.position_field_name);
    
    // Actualizar campo oculto principal
    $('#sr-rotation-position').val(positionText);
    
    // Contact Form 7
    if (srData.form_type === 'cf7') {
        const fieldName = srData.position_field_name || 'rotation-position';
        console.log('Using CF7, looking for field:', fieldName);
        
        // Múltiples selectores para CF7
        let cf7Updated = false;
        
        // Método 1: Por name directo
        $(`input[name="${fieldName}"]`).each(function() {
            if ($(this).attr('type') === 'hidden') {
                $(this).val(positionText);
                console.log('✓ CF7 field updated (method 1):', $(this).attr('id'));
                cf7Updated = true;
            }
        });
        
        // Método 2: Dentro del wrapper de CF7
        if (!cf7Updated) {
            $(`.wpcf7-form-control-wrap.${fieldName} input`).val(positionText);
            console.log('✓ CF7 field updated (method 2)');
            cf7Updated = true;
        }
        
        // Método 3: Buscar todos los hidden dentro del form CF7
        if (!cf7Updated) {
            $('.wpcf7 input[type="hidden"]').each(function() {
                const name = $(this).attr('name');
                if (name && name.indexOf('rotation') !== -1) {
                    $(this).val(positionText);
                    console.log('✓ CF7 field updated (method 3):', name);
                    cf7Updated = true;
                }
            });
        }
        
        if (!cf7Updated) {
            console.error('❌ CF7 field not found');
            console.log('Available CF7 fields:');
            $('.wpcf7 input').each(function() {
                console.log('  -', $(this).attr('type'), $(this).attr('name'));
            });
        }
    }
    
    // Gravity Forms
    if (srData.form_type === 'gravity') {
        const formId = parseInt(srData.gravity_form_id) || 0;
        if (formId > 0) {
            updateGravityFormsField(positionText, formId);
        }
    }
    
    $('#sr-rotation-position').trigger('change');
    console.log('Form field population complete');
}
    
    /**
     * Show rotation details modal
     */
    $(document).on('click', '.sr-btn-details', function() {
        const rotationId = $(this).data('id');
        const rotation = rotations.find(r => r.id == rotationId);
        
        console.log('Opening details for rotation:', rotationId);
        
        if (!rotation) {
            console.error('Rotation not found:', rotationId);
            return;
        }
        
        const modalContent = `
            <h2>${rotation.location_name}</h2>
            <p><strong>${rotation.brand || 'N/A'}</strong></p>
            <p>${rotation.address || ''}<br>${rotation.city || ''}, ${rotation.state || ''}</p>
            
            <h3>Clinical Rotation Availability</h3>
            <p>${rotation.availability || 'N/A'}</p>
            
            <h3>Description</h3>
            <div>${rotation.description || 'No description available'}</div>
            
            <h3>Eligibility Criteria</h3>
            <p>${rotation.eligibility || 'None'}</p>
            
            <h3>Onboarding Requirements</h3>
            <p>${rotation.onboarding || 'None'}</p>
            
            <div style="margin-top: 30px;">
                <button class="sr-btn-apply" data-id="${rotation.id}">Apply for This Rotation</button>
            </div>
        `;
        
        $('#sr-modal-body').html(modalContent);
        $('#sr-modal').fadeIn(300);
    });
    
    /**
     * Close modal
     */
    $(document).on('click', '.sr-modal-close, .sr-modal-overlay', function() {
        console.log('Closing modal');
        $('#sr-modal').fadeOut(300);
    });
    
    /**
     * Handle apply button
     */
    $(document).on('click', '.sr-btn-apply', function() {
        const rotationId = $(this).data('id');
        const rotation = rotations.find(r => r.id == rotationId);
        
        console.log('Apply clicked for rotation:', rotationId);
        
        $('#sr-modal').fadeOut(300);
        
        if (rotation) {
            populateFormField(rotation);
        } else {
            console.error('Rotation not found for apply:', rotationId);
        }
        
        setTimeout(function() {
            const formSection = $('#sr-contact-form');
            if (formSection.length) {
                console.log('Scrolling to form');
                $('html, body').animate({
                    scrollTop: formSection.offset().top - 100
                }, 500);
            } else {
                console.error('Form section not found');
            }
        }, 100);
    });


 /**
 * Targeting específico para Gravity Forms usando el Form ID
 */
function updateGravityFormsField(positionText, formId) {
    console.log('=== Updating Gravity Forms field ===');
    console.log('Form ID:', formId);
    console.log('Position text:', positionText);
    
    let fieldFound = false;
    const formSelector = '#gform_' + formId;
    
    // Verificar que el formulario existe
    if ($(formSelector).length === 0) {
        console.error('❌ Form not found:', formSelector);
        console.log('Available forms:', $('.gform_wrapper').length);
        return;
    }
    
    console.log('✓ Form found:', formSelector);
    
    // Método 1: Por parameter name en clase
    const paramName = srData.position_field_name || 'rotation_position';
    const byParamSelector = `${formSelector} .gfield_parameter_${paramName} input[type="hidden"]`;
    
    console.log('Trying selector:', byParamSelector);
    
    if ($(byParamSelector).length) {
        $(byParamSelector).val(positionText);
        console.log('✓ GF field updated by parameter class');
        fieldFound = true;
    }
    
    // Método 2: Buscar por clase que contenga "rotation"
    if (!fieldFound) {
        console.log('Trying method 2: search by class containing "rotation"');
        $(`${formSelector} .gfield`).each(function() {
            const classes = $(this).attr('class') || '';
            if (classes.toLowerCase().indexOf('rotation') !== -1) {
                const $input = $(this).find('input[type="hidden"]');
                if ($input.length) {
                    $input.val(positionText);
                    console.log('✓ GF field updated by class match:', $input.attr('id'));
                    fieldFound = true;
                }
            }
        });
    }
    
    // Método 3: Buscar todos los campos hidden y llenar los vacíos
    if (!fieldFound) {
        console.log('Trying method 3: fill empty hidden fields');
        $(`${formSelector} input[type="hidden"]`).each(function() {
            const id = $(this).attr('id') || '';
            const name = $(this).attr('name') || '';
            const currentValue = $(this).val();
            
            console.log('Found field:', id, 'Name:', name, 'Value:', currentValue);
            
            // Solo llenar campos vacíos que sean del formato input_X_Y
            if (!currentValue && id.indexOf('input_') === 0) {
                $(this).val(positionText);
                console.log('✓ GF field updated (empty field):', id);
                fieldFound = true;
            }
        });
    }
    
    if (!fieldFound) {
        console.error('❌ No GF field found');
        console.log('All hidden fields in form:');
        $(`${formSelector} input[type="hidden"]`).each(function() {
            console.log('  -', $(this).attr('id'), '=', $(this).val());
        });
    }
}
    
})(jQuery);