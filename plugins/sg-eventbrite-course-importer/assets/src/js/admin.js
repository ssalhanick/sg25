/**
 * Admin JavaScript for Eventbrite Course Importer
 *
 * @package SG\EventbriteCourseImporter
 * @since 1.0.0
 */

(function($) {
    'use strict';

    const EventbriteImport = {
        
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Test connection
            $(document).on('click', '#test-connection', this.testConnection);
            
            // Load events
            $(document).on('click', '#load-all-events', this.loadAllEvents);
            $(document).on('click', '#search-events', this.searchEvents);
            $(document).on('keypress', '#event-search', function(e) {
                if (e.which === 13) {
                    EventbriteImport.searchEvents();
                }
            });
            
            // Event selection
            $(document).on('change', '.event-checkbox', this.updateSelectionButtons);
            $(document).on('click', '#select-all', this.selectAllEvents);
            $(document).on('click', '#deselect-all', this.deselectAllEvents);
            
            // Import actions
            $(document).on('click', '#preview-selected', this.previewSelectedEvents);
            $(document).on('click', '#import-selected', this.importSelectedEvents);
            
            // Preview modal
            $(document).on('click', '.sg-close-modal', this.closePreviewModal);
            $(document).on('click', '#import-from-preview', this.importFromPreview);
            $(document).on('click', '.preview-event', this.previewSingleEvent);
            
            // Close modal on background click
            $(document).on('click', '.sg-preview-modal', function(e) {
                if (e.target === this) {
                    EventbriteImport.closePreviewModal();
                }
            });
        },

        testConnection: function() {
            const $button = $('#test-connection');
            const $status = $('#connection-status');
            
            $button.prop('disabled', true).text(sgEventbriteImport.strings.testing_connection);
            $status.removeClass('success error').html('');
            
            $.post(sgEventbriteImport.ajaxUrl, {
                action: 'sg_eventbrite_test_connection',
                nonce: sgEventbriteImport.nonce
            })
            .done(function(response) {
                if (response.success) {
                    $status.addClass('success').html(response.data.message);
                } else {
                    $status.addClass('error').html(response.data.message);
                }
            })
            .fail(function() {
                $status.addClass('error').html(sgEventbriteImport.strings.connection_failed);
            })
            .always(function() {
                $button.prop('disabled', false).text('Test Connection');
            });
        },

        loadAllEvents: function() {
            EventbriteImport.fetchEvents();
        },

        searchEvents: function() {
            console.log('Search events button clicked');
            const searchQuery = $('#event-search').val().trim();
            console.log('Search query:', searchQuery);
            if (searchQuery) {
                EventbriteImport.fetchEvents(searchQuery);
            } else {
                alert('Please enter search keywords');
            }
        },

        fetchEvents: function(searchQuery = '') {
            console.log('fetchEvents called with query:', searchQuery);
            const $container = $('#events-container');
            const $loadButton = $('#load-all-events');
            const $searchButton = $('#search-events');
            
            $container.html('<div class="loading-spinner">' + sgEventbriteImport.strings.fetching_events + '</div>');
            $loadButton.prop('disabled', true);
            $searchButton.prop('disabled', true);
            
            console.log('Making AJAX request to:', sgEventbriteImport.ajaxUrl);
            console.log('Request data:', {
                action: 'sg_eventbrite_fetch_events',
                nonce: sgEventbriteImport.nonce,
                search: searchQuery
            });
            
            $.post(sgEventbriteImport.ajaxUrl, {
                action: 'sg_eventbrite_fetch_events',
                nonce: sgEventbriteImport.nonce,
                search: searchQuery
            })
            .done(function(response) {
                console.log('AJAX response received:', response);
                if (response.success) {
                    console.log('Success - rendering events:', response.data.events);
                    EventbriteImport.renderEvents(response.data.events);
                } else {
                    console.log('Error response:', response.data.message);
                    // Display error message (supports HTML content like links)
                    $container.html('<div class="error">' + response.data.message + '</div>');
                }
            })
            .fail(function(xhr, status, error) {
                console.log('AJAX failed:', xhr, status, error);
                $container.html('<div class="error">Failed to load events. Please try again.</div>');
            })
            .always(function() {
                $loadButton.prop('disabled', false);
                $searchButton.prop('disabled', false);
            });
        },

        renderEvents: function(events) {
            const $container = $('#events-container');
            
            if (events.length === 0) {
                $container.html('<div class="no-events">No events found.</div>');
                return;
            }
            
            let html = '<div class="events-list">';
            
            events.forEach(function(event) {
                const startDate = event.start ? new Date(event.start.utc).toLocaleDateString() : 'TBD';
                const venue = event.venue ? event.venue.name : 'Location TBD';
                const price = event.is_free ? 'Free' : (event.ticket_availability ? 'Paid' : 'TBD');
                
                html += `
                    <div class="event-item">
                        <input type="checkbox" class="event-checkbox" value="${event.id}" id="event-${event.id}">
                        <div class="event-details">
                            <div class="event-title">${event.name.text}</div>
                            <div class="event-meta">
                                <span>Date: ${startDate}</span> | 
                                <span>Location: ${venue}</span> | 
                                <span>Price: ${price}</span>
                            </div>
                        </div>
                        <div class="event-actions">
                            <button type="button" class="button button-small preview-event" data-event-id="${event.id}">
                                Preview
                            </button>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            $container.html(html);
            
            // Show selection buttons
            $('#select-all, #deselect-all').show();
            EventbriteImport.updateSelectionButtons();
        },

        updateSelectionButtons: function() {
            const selectedCount = $('.event-checkbox:checked').length;
            const totalCount = $('.event-checkbox').length;
            
            $('#preview-selected, #import-selected').prop('disabled', selectedCount === 0);
            
            if (selectedCount > 0) {
                $('#preview-selected').text(`Preview Selected (${selectedCount})`);
                $('#import-selected').text(`Import Selected (${selectedCount})`);
            } else {
                $('#preview-selected').text('Preview Selected Events');
                $('#import-selected').text('Import Selected Events');
            }
        },

        selectAllEvents: function() {
            $('.event-checkbox').prop('checked', true);
            EventbriteImport.updateSelectionButtons();
        },

        deselectAllEvents: function() {
            $('.event-checkbox').prop('checked', false);
            EventbriteImport.updateSelectionButtons();
        },

        previewSelectedEvents: function() {
            const selectedEvents = $('.event-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedEvents.length === 0) {
                alert(sgEventbriteImport.strings.select_events);
                return;
            }
            
            if (selectedEvents.length === 1) {
                EventbriteImport.previewSingleEvent(null, selectedEvents[0]);
            } else {
                EventbriteImport.showMultiplePreview(selectedEvents);
            }
        },

        previewSingleEvent: function(e, eventId) {
            if (e) {
                eventId = $(e.target).data('event-id');
            }
            
            const $modal = $('#preview-modal');
            const $content = $('#preview-content');
            
            $content.html('<div class="loading-spinner">' + sgEventbriteImport.strings.preview_loading + '</div>');
            $modal.show();
            
            $.post(sgEventbriteImport.ajaxUrl, {
                action: 'sg_eventbrite_preview_event',
                nonce: sgEventbriteImport.nonce,
                event_id: eventId
            })
            .done(function(response) {
                if (response.success) {
                    EventbriteImport.renderPreview(response.data);
                } else {
                    // Display error message (supports HTML content like links)
                    $content.html('<div class="error">' + response.data.message + '</div>');
                }
            })
            .fail(function() {
                $content.html('<div class="error">Failed to load preview.</div>');
            });
        },

        renderPreview: function(data) {
            const event = data.event;
            const course = data.course_data;
            
            const startDate = event.start ? new Date(event.start.utc).toLocaleDateString() : 'TBD';
            const startTime = event.start ? new Date(event.start.utc).toLocaleTimeString() : 'TBD';
            const venue = event.venue ? event.venue.name : 'Location TBD';
            
            let html = `
                <div class="preview-event">
                    <h3>${event.name.text}</h3>
                    <div class="preview-details">
                        <p><strong>Start Date:</strong> ${startDate}</p>
                        <p><strong>Start Time:</strong> ${startTime}</p>
                        <p><strong>Location:</strong> ${venue}</p>
                        <p><strong>Price:</strong> ${course.meta._sg_course_price}</p>
                        ${course.meta._sg_course_instructor ? `<p><strong>Instructor:</strong> ${course.meta._sg_course_instructor}</p>` : ''}
                        ${course.meta._sg_course_class_length ? `<p><strong>Class Length:</strong> ${course.meta._sg_course_class_length} hours</p>` : ''}
                        ${course.meta._sg_course_course_length ? `<p><strong>Course Length:</strong> ${course.meta._sg_course_course_length}</p>` : ''}
                        ${course.meta._sg_course_drop_in_class === '1' ? '<p><strong>Type:</strong> Drop-in Class</p>' : ''}
                    </div>
                    <div class="preview-description">
                        <h4>Description:</h4>
                        <div>${course.description}</div>
                    </div>
                </div>
            `;
            
            $('#preview-content').html(html);
        },

        showMultiplePreview: function(eventIds) {
            // For multiple events, show a summary
            let html = '<div class="multiple-preview">';
            html += '<h3>Selected Events Preview</h3>';
            html += '<p>You have selected ' + eventIds.length + ' events for import.</p>';
            html += '<ul>';
            
            $('.event-checkbox:checked').each(function() {
                const eventId = $(this).val();
                const eventTitle = $(this).closest('.event-item').find('.event-title').text();
                html += '<li>' + eventTitle + '</li>';
            });
            
            html += '</ul>';
            html += '<p>Click "Import Selected Events" to proceed with the import.</p>';
            html += '</div>';
            
            $('#preview-content').html(html);
            $('#preview-modal').show();
        },

        importSelectedEvents: function() {
            const selectedEvents = $('.event-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedEvents.length === 0) {
                alert(sgEventbriteImport.strings.select_events);
                return;
            }
            
            if (!confirm('Are you sure you want to import ' + selectedEvents.length + ' events?')) {
                return;
            }
            
            const $button = $('#import-selected');
            const $results = $('#import-results');
            const $content = $('#import-results-content');
            
            $button.prop('disabled', true).text(sgEventbriteImport.strings.importing_events);
            $results.show();
            $content.html('<div class="loading-spinner">' + sgEventbriteImport.strings.importing_events + '</div>');
            
            const options = {
                update_existing: $('#update-existing').is(':checked'),
                import_images: $('#import-images').is(':checked'),
                extract_keywords: $('#extract-keywords').is(':checked'),
                import_status: $('#import-status').val()
            };
            
            $.post(sgEventbriteImport.ajaxUrl, {
                action: 'sg_eventbrite_import_events',
                nonce: sgEventbriteImport.nonce,
                event_ids: selectedEvents,
                ...options
            })
            .done(function(response) {
                if (response.success) {
                    EventbriteImport.renderImportResults(response.data, true);
                } else {
                    EventbriteImport.renderImportResults(response.data, false);
                }
            })
            .fail(function() {
                EventbriteImport.renderImportResults({
                    message: 'Import failed. Please try again.',
                    errors: ['Network error']
                }, false);
            })
            .always(function() {
                $button.prop('disabled', false).text('Import Selected Events');
            });
        },

        renderImportResults: function(data, success) {
            const $content = $('#import-results-content');
            
            let html = '<div class="import-results ' + (success ? 'success' : 'error') + '">';
            
            if (success) {
                html += '<h3>Import Completed Successfully!</h3>';
                html += '<p>Imported ' + data.imported_count + ' courses in ' + data.duration + ' seconds.</p>';
                
                if (data.imported_events && data.imported_events.length > 0) {
                    html += '<h4>Imported Courses:</h4>';
                    html += '<ul>';
                    data.imported_events.forEach(function(postId) {
                        html += '<li><a href="' + ajaxurl.replace('admin-ajax.php', 'post.php?post=' + postId + '&action=edit') + '">Course #' + postId + '</a></li>';
                    });
                    html += '</ul>';
                }
            } else {
                html += '<h3>Import Failed</h3>';
                // Display error message (supports HTML content like links)
                html += '<p>' + (data.message || 'Unknown error occurred') + '</p>';
                
                if (data.errors && data.errors.length > 0) {
                    html += '<h4>Errors:</h4>';
                    html += '<ul>';
                    data.errors.forEach(function(error) {
                        html += '<li>' + error + '</li>';
                    });
                    html += '</ul>';
                }
            }
            
            html += '</div>';
            $content.html(html);
        },

        importFromPreview: function() {
            const selectedEvents = $('.event-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedEvents.length > 0) {
                this.closePreviewModal();
                this.importSelectedEvents();
            }
        },

        closePreviewModal: function() {
            $('#preview-modal').hide();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        EventbriteImport.init();
    });

})(jQuery);