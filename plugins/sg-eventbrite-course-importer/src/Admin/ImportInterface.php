<?php
/**
 * Import Interface Class.
 *
 * Handles the admin interface for Eventbrite event import functionality.
 *
 * @package SG\EventbriteCourseImporter\Admin
 * @since 1.0.0
 */

namespace SG\EventbriteCourseImporter\Admin;

use SG\EventbriteCourseImporter\EventbriteAPI;
use SG\EventbriteCourseImporter\EventbriteImporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Import Interface Class.
 *
 * Manages the admin interface for importing Eventbrite events.
 *
 * @package SG\EventbriteCourseImporter\Admin
 * @since 1.0.0
 */
class ImportInterface {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger = new Logger();
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_sg_eventbrite_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_sg_eventbrite_fetch_events', array( $this, 'ajax_fetch_events' ) );
		add_action( 'wp_ajax_sg_eventbrite_import_events', array( $this, 'ajax_import_events' ) );
		add_action( 'wp_ajax_sg_eventbrite_preview_event', array( $this, 'ajax_preview_event' ) );
	}

	/**
	 * Add admin menu page.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=sg_course',
			__( 'Import from Eventbrite', 'sg-eventbrite-course-importer' ),
			__( 'Import from Eventbrite', 'sg-eventbrite-course-importer' ),
			'manage_options',
			'sg-eventbrite-import',
			array( $this, 'render_import_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'sg_course_page_sg-eventbrite-import' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'wp-util' );
		wp_enqueue_style( 'wp-admin' );

		// Enqueue our custom admin styles and scripts
		wp_enqueue_style(
			'sg-eventbrite-import-admin',
			SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_URL . '/assets/build/css/admin-style.css',
			array(),
			SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'sg-eventbrite-import-admin',
			SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_URL . '/assets/build/js/admin.js',
			array( 'jquery' ),
			SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'sg-eventbrite-import-admin',
			'sgEventbriteImport',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'sg_eventbrite_import_nonce' ),
				'strings'    => array(
					'testing_connection'    => __( 'Testing connection...', 'sg-eventbrite-course-importer' ),
					'connection_success'    => __( 'Connection successful!', 'sg-eventbrite-course-importer' ),
					'connection_failed'     => __( 'Connection failed. Please check your API credentials.', 'sg-eventbrite-course-importer' ),
					'fetching_events'       => __( 'Fetching events...', 'sg-eventbrite-course-importer' ),
					'events_loaded'         => __( 'Events loaded successfully', 'sg-eventbrite-course-importer' ),
					'importing_events'      => __( 'Importing events...', 'sg-eventbrite-course-importer' ),
					'import_complete'       => __( 'Import completed successfully', 'sg-eventbrite-course-importer' ),
					'import_failed'         => __( 'Import failed. Please check the logs.', 'sg-eventbrite-course-importer' ),
					'preview_loading'       => __( 'Loading preview...', 'sg-eventbrite-course-importer' ),
					'select_events'         => __( 'Please select at least one event to import.', 'sg-eventbrite-course-importer' ),
				),
			)
		);
		
		// Debug: Add console logging to check if scripts are loading
		error_log( 'SG Eventbrite: Scripts being enqueued for hook: ' . $hook );
		error_log( 'SG Eventbrite: Plugin URL: ' . SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_URL );
		error_log( 'SG Eventbrite: Plugin Version: ' . SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_VERSION );
		
		// Check if script file exists
		$script_path = SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_URL . '/assets/build/js/admin.js';
		$script_file = str_replace( SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_URL, plugin_dir_path( __FILE__ ) . '../../', $script_path );
		error_log( 'SG Eventbrite: Script file path: ' . $script_file );
		error_log( 'SG Eventbrite: Script file exists: ' . (file_exists( $script_file ) ? 'YES' : 'NO') );
	}

	/**
	 * Render the import page.
	 */
	public function render_import_page() {
		$client_id = get_option( 'sg_eventbrite_client_id', '' );
		$client_secret = get_option( 'sg_eventbrite_client_secret', '' );
		$organization_id = get_option( 'sg_eventbrite_organization_id', '' );
		
		// Debug: Log page rendering
		error_log( 'SG Eventbrite: render_import_page called' );
		error_log( 'SG Eventbrite: Client ID: ' . (empty($client_id) ? 'EMPTY' : 'SET') );
		error_log( 'SG Eventbrite: Client Secret: ' . (empty($client_secret) ? 'EMPTY' : 'SET') );
		error_log( 'SG Eventbrite: Organization ID: ' . (empty($organization_id) ? 'EMPTY' : $organization_id) );
		?>
		<div class="wrap">
			<h1><?php _e( 'Import from Eventbrite', 'sg-eventbrite-course-importer' ); ?></h1>

			<?php if ( empty( $client_id ) || empty( $client_secret ) || empty( $organization_id ) ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php _e( 'Please configure your Eventbrite API credentials first.', 'sg-eventbrite-course-importer' ); ?>
						<a href="<?php echo admin_url( 'options-general.php?page=sg-eventbrite-settings' ); ?>" class="button button-secondary">
							<?php _e( 'Go to Settings', 'sg-eventbrite-course-importer' ); ?>
						</a>
					</p>
				</div>
			<?php else : ?>

				<div class="sg-eventbrite-import-container">
					<!-- API Connection Test -->
					<div class="sg-import-section">
						<h2><?php _e( 'API Connection', 'sg-eventbrite-course-importer' ); ?></h2>
						<p><?php _e( 'Test your Eventbrite API connection before importing events.', 'sg-eventbrite-course-importer' ); ?></p>
						
						<?php
						// Check OAuth status
						$oauth = new \SG\EventbriteCourseImporter\EventbriteOAuth( $client_id, $client_secret );
						$is_authenticated = $oauth->is_authenticated();
						?>
						
						<div class="connection-status-indicator">
							<?php if ( $is_authenticated ) : ?>
								<span class="status-indicator success">✓ <?php _e( 'Authenticated with Eventbrite', 'sg-eventbrite-course-importer' ); ?></span>
							<?php else : ?>
								<span class="status-indicator error">✗ <?php _e( 'Not authenticated - Please authorize in Settings', 'sg-eventbrite-course-importer' ); ?></span>
							<?php endif; ?>
						</div>
						
						<button type="button" id="test-connection" class="button button-secondary">
							<?php _e( 'Test API Connection', 'sg-eventbrite-course-importer' ); ?>
						</button>
						<div id="connection-status" class="connection-status"></div>
					</div>

					<!-- Event Selection -->
					<div class="sg-import-section">
						<h2><?php _e( 'Select Events to Import', 'sg-eventbrite-course-importer' ); ?></h2>
						<p><?php _e( 'Search for events to import as courses. Use the search bar to find specific events.', 'sg-eventbrite-course-importer' ); ?></p>
						
						<div class="event-filters">
							<div class="search-container">
								<label for="event-search">
									<?php _e( 'Search Events:', 'sg-eventbrite-course-importer' ); ?>
								</label>
								<div class="search-input-group">
									<input type="text" id="event-search" placeholder="<?php _e( 'Enter keywords (e.g., "workshop", "training", "conference")', 'sg-eventbrite-course-importer' ); ?>" class="search-input" />
									<button type="button" id="search-events" class="button button-primary search-button">
										<?php _e( 'Search Events', 'sg-eventbrite-course-importer' ); ?>
									</button>
								</div>
								
								<!-- Date Range Filter -->
								<div class="date-range-container" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
									<h4 style="margin-top: 0; margin-bottom: 10px;"><?php _e( 'Date Range Filter (Optional)', 'sg-eventbrite-course-importer' ); ?></h4>
									<div class="date-inputs" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
										<div class="date-input-group">
											<label for="start-date" style="display: block; font-weight: 600; margin-bottom: 5px;">
												<?php _e( 'Start Date:', 'sg-eventbrite-course-importer' ); ?>
											</label>
											<input type="date" id="start-date" class="date-input" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;" />
										</div>
										<div class="date-input-group">
											<label for="end-date" style="display: block; font-weight: 600; margin-bottom: 5px;">
												<?php _e( 'End Date:', 'sg-eventbrite-course-importer' ); ?>
											</label>
											<input type="date" id="end-date" class="date-input" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;" />
										</div>
										<div class="date-actions" style="margin-top: 20px;">
											<button type="button" id="clear-dates" class="button button-secondary" style="margin-right: 10px;">
												<?php _e( 'Clear Dates', 'sg-eventbrite-course-importer' ); ?>
											</button>
					<button type="button" id="set-today" class="button button-secondary">
						<?php _e( 'Today', 'sg-eventbrite-course-importer' ); ?>
					</button>
										</div>
									</div>
									<p class="date-help" style="margin-top: 10px; margin-bottom: 0; font-size: 13px; color: #666;">
										<strong><?php _e( 'Date Filter Tips:', 'sg-eventbrite-course-importer' ); ?></strong>
										<?php _e( 'Leave dates empty to search all events. Use start date only to find events from that date forward. Use both dates to find events within a specific range.', 'sg-eventbrite-course-importer' ); ?>
									</p>
								</div>
								
								<p class="search-help" style="margin-top: 15px;">
									<strong><?php _e( 'Search Tips:', 'sg-eventbrite-course-importer' ); ?></strong>
									<?php _e( 'Use multiple keywords separated by commas (e.g., "workshop, training, online") to find events containing any of those terms.', 'sg-eventbrite-course-importer' ); ?>
								</p>
							</div>
						</div>

						<div id="events-container" class="events-container">
							<div class="no-events-message">
								<p><?php _e( 'No events loaded yet. Use the search bar above to find events to import.', 'sg-eventbrite-course-importer' ); ?></p>
							</div>
						</div>
					</div>

					<!-- Import Options -->
					<div class="sg-import-section">
						<h2><?php _e( 'Import Options', 'sg-eventbrite-course-importer' ); ?></h2>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="update-existing"><?php _e( 'Update Existing Courses', 'sg-eventbrite-course-importer' ); ?></label>
								</th>
								<td>
									<input type="checkbox" id="update-existing" name="update-existing" value="1" checked />
									<p class="description">
										<?php _e( 'Update courses that already exist (based on Eventbrite ID).', 'sg-eventbrite-course-importer' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="import-images"><?php _e( 'Import Images', 'sg-eventbrite-course-importer' ); ?></label>
								</th>
								<td>
									<input type="checkbox" id="import-images" name="import-images" value="1" checked />
									<p class="description">
										<?php _e( 'Download and set featured images for imported courses.', 'sg-eventbrite-course-importer' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="extract-keywords"><?php _e( 'Extract Keywords', 'sg-eventbrite-course-importer' ); ?></label>
								</th>
								<td>
									<input type="checkbox" id="extract-keywords" name="extract-keywords" value="1" checked />
									<p class="description">
										<?php _e( 'Extract instructor, course length, and other details from event descriptions.', 'sg-eventbrite-course-importer' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="import-status"><?php _e( 'Import Status', 'sg-eventbrite-course-importer' ); ?></label>
								</th>
								<td>
									<select id="import-status" name="import-status">
										<option value="publish"><?php _e( 'Published', 'sg-eventbrite-course-importer' ); ?></option>
										<option value="draft"><?php _e( 'Draft', 'sg-eventbrite-course-importer' ); ?></option>
										<option value="private"><?php _e( 'Private', 'sg-eventbrite-course-importer' ); ?></option>
									</select>
									<p class="description">
										<?php _e( 'Set the status for imported courses.', 'sg-eventbrite-course-importer' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>

					<!-- Import Actions -->
					<div class="sg-import-section">
						<h2><?php _e( 'Import Actions', 'sg-eventbrite-course-importer' ); ?></h2>
						<button type="button" id="preview-selected" class="button button-secondary" disabled>
							<?php _e( 'Preview Selected Events', 'sg-eventbrite-course-importer' ); ?>
						</button>
						<button type="button" id="import-selected" class="button button-primary" disabled>
							<?php _e( 'Import Selected Events', 'sg-eventbrite-course-importer' ); ?>
						</button>
						<button type="button" id="select-all" class="button button-secondary" style="display: none;">
							<?php _e( 'Select All', 'sg-eventbrite-course-importer' ); ?>
						</button>
						<button type="button" id="deselect-all" class="button button-secondary" style="display: none;">
							<?php _e( 'Deselect All', 'sg-eventbrite-course-importer' ); ?>
						</button>
					</div>

					<!-- Import Results -->
					<div id="import-results" class="sg-import-section" style="display: none;">
						<h2><?php _e( 'Import Results', 'sg-eventbrite-course-importer' ); ?></h2>
						<div id="import-results-content"></div>
					</div>

					<!-- Preview Modal -->
					<div id="preview-modal" class="sg-preview-modal" style="display: none;">
						<div class="sg-preview-content">
							<div class="sg-preview-header">
								<h3><?php _e( 'Event Preview', 'sg-eventbrite-course-importer' ); ?></h3>
								<button type="button" class="sg-close-modal">&times;</button>
							</div>
							<div class="sg-preview-body">
								<div id="preview-content"></div>
							</div>
							<div class="sg-preview-footer">
								<button type="button" class="button button-secondary sg-close-modal">
									<?php _e( 'Close', 'sg-eventbrite-course-importer' ); ?>
								</button>
								<button type="button" class="button button-primary" id="import-from-preview">
									<?php _e( 'Import This Event', 'sg-eventbrite-course-importer' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>

			<?php endif; ?>
		</div>
		
		<script>
		console.log('SG Eventbrite: Page loaded, checking JavaScript...');
		console.log('SG Eventbrite: jQuery available:', typeof jQuery !== 'undefined');
		console.log('SG Eventbrite: sgEventbriteImport available:', typeof sgEventbriteImport !== 'undefined');
		
		// Manual fallback if wp_localize_script failed
		if (typeof sgEventbriteImport === 'undefined') {
			console.log('SG Eventbrite: Creating manual fallback...');
			window.sgEventbriteImport = {
				ajaxUrl: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
				nonce: '<?php echo wp_create_nonce( 'sg_eventbrite_import_nonce' ); ?>',
				strings: {
					testing_connection: 'Testing connection...',
					connection_success: 'Connection successful!',
					connection_failed: 'Connection failed. Please check your API credentials.',
					fetching_events: 'Fetching events...',
					events_loaded: 'Events loaded successfully',
					importing_events: 'Importing events...',
					import_complete: 'Import completed successfully',
					import_failed: 'Import failed. Please check the logs.',
					preview_loading: 'Loading preview...',
					select_events: 'Please select at least one event to import.'
				}
			};
			console.log('SG Eventbrite: Manual fallback created:', window.sgEventbriteImport);
		}
		
		// Test if buttons exist and add manual event handlers
		console.log('SG Eventbrite: Testing button existence...');
		console.log('SG Eventbrite: Test connection button exists:', document.getElementById('test-connection') !== null);
		console.log('SG Eventbrite: Search events button exists:', document.getElementById('search-events') !== null);
		console.log('SG Eventbrite: Event search input exists:', document.getElementById('event-search') !== null);
		
		// Add manual event handlers as fallback
		jQuery(document).ready(function($) {
			console.log('SG Eventbrite: jQuery ready, adding manual event handlers...');
			
			// Test connection button
			$('#test-connection').on('click', function() {
				console.log('SG Eventbrite: Test connection button clicked!');
				alert('Test connection button clicked! This is working.');
			});
			
			// Search events button
			$('#search-events').on('click', function() {
				console.log('SG Eventbrite: Search events button clicked!');
				var searchQuery = $('#event-search').val().trim();
				var startDate = $('#start-date').val();
				var endDate = $('#end-date').val();
				
				console.log('SG Eventbrite: Search query:', searchQuery);
				console.log('SG Eventbrite: Start date:', startDate);
				console.log('SG Eventbrite: End date:', endDate);
				
				// Validate date range
				if (startDate && endDate && startDate > endDate) {
					alert('Start date must be before end date!');
					return;
				}
				
				// Call the actual search function
				window.searchEventsWithDateRange(searchQuery, startDate, endDate);
			});
			
			// Clear dates button
			$('#clear-dates').on('click', function() {
				$('#start-date, #end-date').val('');
				console.log('SG Eventbrite: Dates cleared');
			});
			
			// Set today button
			$('#set-today').on('click', function() {
				var today = new Date();
				var todayStr = today.getFullYear() + '-' + 
					String(today.getMonth() + 1).padStart(2, '0') + '-' + 
					String(today.getDate()).padStart(2, '0');
				$('#start-date').val(todayStr);
				$('#end-date').val(todayStr);
				console.log('SG Eventbrite: Set to today:', todayStr);
			});
			
			console.log('SG Eventbrite: Manual event handlers added');
		});
		
		// Search function with date range support (global scope)
		window.searchEventsWithDateRange = function(searchQuery, startDate, endDate, page) {
			page = page || 1; // Default to page 1 if not provided
			console.log('SG Eventbrite: Starting search with date range... Page:', page);
			
			var $container = jQuery('#events-container');
			var $searchButton = jQuery('#search-events');
			
			// Show loading state
			$container.html('<div class="loading-spinner">Searching events...</div>');
			$searchButton.prop('disabled', true).text('Searching...');
			
			// Prepare AJAX data
			var ajaxData = {
				action: 'sg_eventbrite_fetch_events',
				nonce: sgEventbriteImport.nonce,
				search: searchQuery || '',
				page: page
			};
			
			// Add date range if provided
			if (startDate) {
				ajaxData.start_date = startDate;
			}
			if (endDate) {
				ajaxData.end_date = endDate;
			}
			
			console.log('SG Eventbrite: AJAX data:', ajaxData);
			
			// Make AJAX request
			jQuery.post(sgEventbriteImport.ajaxUrl, ajaxData)
				.done(function(response) {
					console.log('SG Eventbrite: Search response:', response);
					if (response.success) {
						renderEvents(response.data.events, response.data.pagination);
					} else {
						$container.html('<div class="error">' + response.data.message + '</div>');
					}
				})
				.fail(function(xhr, status, error) {
					console.error('SG Eventbrite: Search failed:', error);
					$container.html('<div class="error">Search failed. Please try again.</div>');
				})
				.always(function() {
					$searchButton.prop('disabled', false).text('Search Events');
				});
		}
		
		// Enhanced event rendering function with pagination
		function renderEvents(events, pagination) {
			var $container = jQuery('#events-container');
			
			if (!events || events.length === 0) {
				$container.html('<div class="no-events">No events found matching your criteria.</div>');
				return;
			}
			
			var html = '<div class="events-list">';
			events.forEach(function(event) {
				var eventDate = event.start ? new Date(event.start.utc).toLocaleDateString() : 'TBD';
				var venue = event.venue ? event.venue.name : 'Location TBD';
				var price = event.is_free ? 'Free' : (event.ticket_availability ? 'Paid' : 'TBD');
				
				html += '<div class="event-item" style="border: 1px solid #ddd; margin: 10px 0; padding: 15px; border-radius: 4px;">';
				html += '<input type="checkbox" class="event-checkbox" value="' + event.id + '" id="event-' + event.id + '" style="margin-right: 10px;">';
				html += '<div class="event-details" style="display: inline-block; width: calc(100% - 120px);">';
				html += '<div class="event-title" style="font-weight: bold; margin-bottom: 5px;">' + event.name.text + '</div>';
				html += '<div class="event-meta" style="color: #666; font-size: 14px;">';
				html += '<span>Date: ' + eventDate + '</span> | ';
				html += '<span>Location: ' + venue + '</span> | ';
				html += '<span>Price: ' + price + '</span>';
				html += '</div>';
				html += '</div>';
				html += '<div class="event-actions" style="float: right;">';
				html += '<button type="button" class="button button-small preview-event" data-event-id="' + event.id + '">Preview</button>';
				html += '</div>';
				html += '<div style="clear: both;"></div>';
				html += '</div>';
			});
			html += '</div>';
			
			// Add pagination controls (always show, even for single page)
			if (pagination) {
				html += '<div class="events-pagination">';
				html += '<div class="pagination-info">';
				html += 'Showing page ' + pagination.page_number + ' of ' + pagination.page_count + ' (Total: ' + pagination.object_count + ' events)';
				html += '</div>';
				html += '<div class="pagination-buttons">';
				
				// Previous button (always show, disabled if on first page)
				if (pagination.page_number > 1) {
					html += '<button type="button" class="button pagination-btn" data-page="' + (pagination.page_number - 1) + '">← Previous</button>';
				} else {
					html += '<button type="button" class="button pagination-btn" disabled>← Previous</button>';
				}
				
				// Page numbers
				var startPage = Math.max(1, pagination.page_number - 2);
				var endPage = Math.min(pagination.page_count, pagination.page_number + 2);
				
				if (startPage > 1) {
					html += '<button type="button" class="button pagination-btn" data-page="1">1</button>';
					if (startPage > 2) {
						html += '<span class="pagination-ellipsis">...</span>';
					}
				}
				
				for (var i = startPage; i <= endPage; i++) {
					var activeClass = (i === pagination.page_number) ? ' button-primary' : '';
					html += '<button type="button" class="button pagination-btn' + activeClass + '" data-page="' + i + '">' + i + '</button>';
				}
				
				if (endPage < pagination.page_count) {
					if (endPage < pagination.page_count - 1) {
						html += '<span class="pagination-ellipsis">...</span>';
					}
					html += '<button type="button" class="button pagination-btn" data-page="' + pagination.page_count + '">' + pagination.page_count + '</button>';
				}
				
				// Next button (always show, disabled if on last page)
				if (pagination.page_number < pagination.page_count) {
					html += '<button type="button" class="button pagination-btn" data-page="' + (pagination.page_number + 1) + '">Next →</button>';
				} else {
					html += '<button type="button" class="button pagination-btn" disabled>Next →</button>';
				}
				
				html += '</div>';
				html += '</div>';
			}
			
			$container.html(html);
			
			// Update import button state after rendering events
			updateImportButtonState();
			
			// Add click handlers for pagination buttons
			$container.find('.pagination-btn').on('click', function() {
				// Don't process clicks on disabled buttons
				if (jQuery(this).prop('disabled')) {
					return;
				}
				
				var page = parseInt(jQuery(this).data('page'));
				var searchQuery = jQuery('#event-search').val().trim();
				var startDate = jQuery('#start-date').val();
				var endDate = jQuery('#end-date').val();
				
				console.log('SG Eventbrite: Pagination clicked - Page:', page);
				console.log('SG Eventbrite: Search query:', searchQuery);
				console.log('SG Eventbrite: Start date:', startDate);
				console.log('SG Eventbrite: End date:', endDate);
				console.log('SG Eventbrite: searchEventsWithDateRange function exists:', typeof window.searchEventsWithDateRange);
				
				if (typeof window.searchEventsWithDateRange === 'function') {
					window.searchEventsWithDateRange(searchQuery, startDate, endDate, page);
				} else {
					console.error('SG Eventbrite: searchEventsWithDateRange function not found!');
				}
			});
		}
		
		if (typeof sgEventbriteImport !== 'undefined') {
			console.log('SG Eventbrite: AJAX URL:', sgEventbriteImport.ajaxUrl);
			console.log('SG Eventbrite: Nonce:', sgEventbriteImport.nonce);
		}
		
		// Function to update import button state based on selected checkboxes
		function updateImportButtonState() {
			var selectedCheckboxes = jQuery('.event-checkbox:checked');
			var importButton = jQuery('#import-selected');
			
			if (selectedCheckboxes.length > 0) {
				importButton.prop('disabled', false);
				importButton.text('Import Selected (' + selectedCheckboxes.length + ')');
			} else {
				importButton.prop('disabled', true);
				importButton.text('Import Selected');
			}
		}
		
		// Function to handle import button click
		function handleImportClick() {
			var selectedCheckboxes = jQuery('.event-checkbox:checked');
			var eventIds = [];
			
			selectedCheckboxes.each(function() {
				eventIds.push(jQuery(this).val());
			});
			
			if (eventIds.length === 0) {
				alert('Please select at least one event to import.');
				return;
			}
			
			if (!confirm('Are you sure you want to import ' + eventIds.length + ' selected events?')) {
				return;
			}
			
			// Show loading state
			var importButton = jQuery('#import-selected');
			importButton.prop('disabled', true).text('Importing...');
			
			// Make AJAX request to import events
			jQuery.post(sgEventbriteImport.ajaxUrl, {
				action: 'sg_eventbrite_import_events',
				nonce: sgEventbriteImport.nonce,
				event_ids: eventIds
			})
			.done(function(response) {
				console.log('SG Eventbrite: Import response:', response);
				if (response.success) {
					alert('Successfully imported ' + response.data.imported_count + ' events!');
					// Refresh the events list
					window.searchEventsWithDateRange(
						jQuery('#event-search').val().trim(),
						jQuery('#start-date').val(),
						jQuery('#end-date').val(),
						1
					);
				} else {
					alert('Import failed: ' + response.data.message);
				}
			})
			.fail(function(xhr, status, error) {
				console.error('SG Eventbrite: Import failed:', error);
				alert('Import failed. Please try again.');
			})
			.always(function() {
				importButton.prop('disabled', false).text('Import Selected');
			});
		}
		
		// Add event delegation for dynamically created checkboxes
		jQuery(document).on('change', '.event-checkbox', function() {
			updateImportButtonState();
		});
		
		// Add click handler for import button
		jQuery(document).on('click', '#import-selected', function() {
			handleImportClick();
		});
		</script>

		<style>
		.sg-eventbrite-import-container {
			max-width: 1200px;
		}
		.sg-import-section {
			background: #fff;
			border: 1px solid #ccd0d4;
			margin: 20px 0;
			padding: 20px;
		}
		.search-container {
			margin-bottom: 20px;
		}
		.search-input-group {
			display: flex;
			align-items: center;
			gap: 10px;
			margin: 10px 0;
		}
		.search-input {
			flex: 1;
			max-width: 400px;
			padding: 8px 12px;
			border: 2px solid #0073aa;
			border-radius: 4px;
			font-size: 14px;
		}
		.search-input:focus {
			border-color: #005177;
			box-shadow: 0 0 0 1px #005177;
			outline: none;
		}
		.search-button {
			padding: 8px 16px;
			font-weight: bold;
		}
		.search-help {
			font-size: 13px;
			color: #666;
			margin: 5px 0 0 0;
			padding: 10px;
			background: #f9f9f9;
			border-left: 3px solid #0073aa;
		}
		.events-container {
			max-height: 400px;
			overflow-y: auto;
			border: 1px solid #ddd;
			padding: 10px;
			margin: 10px 0;
		}
		.no-events-message {
			text-align: center;
			padding: 40px 20px;
			color: #666;
			font-style: italic;
		}
		.connection-status-indicator {
			margin: 10px 0;
		}
		.status-indicator {
			display: inline-block;
			padding: 8px 12px;
			border-radius: 4px;
			font-weight: bold;
			margin-bottom: 10px;
		}
		.status-indicator.success {
			background: #d4edda;
			color: #155724;
			border: 1px solid #c3e6cb;
		}
		.status-indicator.error {
			background: #f8d7da;
			color: #721c24;
			border: 1px solid #f5c6cb;
		}
		.events-pagination {
			margin-top: 20px;
			padding: 15px;
			border-top: 1px solid #ddd;
			background: #f9f9f9;
		}
		.pagination-info {
			text-align: center;
			margin-bottom: 10px;
			font-size: 14px;
			color: #666;
		}
		.pagination-buttons {
			text-align: center;
			display: flex;
			justify-content: center;
			gap: 5px;
			flex-wrap: wrap;
		}
		.pagination-btn {
			min-width: 40px;
			padding: 5px 10px;
		}
		.event-item {
			border: 1px solid #eee;
			margin: 5px 0;
			padding: 10px;
			display: flex;
			align-items: center;
		}
		.event-item input[type="checkbox"] {
			margin-right: 10px;
		}
		.event-details {
			flex: 1;
		}
		.event-title {
			font-weight: bold;
			margin-bottom: 5px;
		}
		.event-meta {
			font-size: 12px;
			color: #666;
		}
		.event-actions {
			margin-left: 10px;
		}
		.connection-status {
			margin-top: 10px;
			padding: 10px;
			border-radius: 4px;
		}
		.connection-status.success {
			background: #d4edda;
			color: #155724;
			border: 1px solid #c3e6cb;
		}
		.connection-status.error {
			background: #f8d7da;
			color: #721c24;
			border: 1px solid #f5c6cb;
		}
		.loading-spinner {
			text-align: center;
			padding: 20px;
			color: #666;
		}
		.sg-preview-modal {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.5);
			z-index: 9999;
		}
		.sg-preview-content {
			position: absolute;
			top: 50%;
			left: 50%;
			transform: translate(-50%, -50%);
			background: #fff;
			width: 90%;
			max-width: 800px;
			max-height: 90%;
			overflow-y: auto;
			border-radius: 4px;
		}
		.sg-preview-header {
			padding: 20px;
			border-bottom: 1px solid #ddd;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.sg-preview-body {
			padding: 20px;
		}
		.sg-preview-footer {
			padding: 20px;
			border-top: 1px solid #ddd;
			text-align: right;
		}
		.sg-close-modal {
			background: none;
			border: none;
			font-size: 24px;
			cursor: pointer;
		}
		.events-pagination {
			margin-top: 20px;
			padding: 15px;
			border-top: 1px solid #ddd;
			background: #f9f9f9;
		}
		.pagination-info {
			text-align: center;
			margin-bottom: 10px;
			font-size: 14px;
			color: #666;
		}
		.pagination-buttons {
			text-align: center;
			display: flex;
			justify-content: center;
			gap: 5px;
			flex-wrap: wrap;
		}
		.pagination-btn {
			min-width: 40px;
			padding: 5px 10px;
		}
		.pagination-ellipsis {
			padding: 5px 10px;
			color: #666;
		}
		.pagination-btn:disabled {
			opacity: 0.5;
			cursor: not-allowed;
			background: #f1f1f1;
			color: #999;
		}
		</style>
		<?php
	}

	/**
	 * AJAX handler for testing API connection.
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'sg_eventbrite_import_nonce', 'nonce' );

		$client_id = get_option( 'sg_eventbrite_client_id', '' );
		$client_secret = get_option( 'sg_eventbrite_client_secret', '' );
		$organization_id = get_option( 'sg_eventbrite_organization_id', '' );

		if ( empty( $client_id ) || empty( $client_secret ) || empty( $organization_id ) ) {
			wp_send_json_error( array( 'message' => __( 'OAuth credentials not configured', 'sg-eventbrite-course-importer' ) ) );
		}

		$oauth = new \SG\EventbriteCourseImporter\EventbriteOAuth( $client_id, $client_secret );
		
		// Check if user is authenticated first
		if ( ! $oauth->is_authenticated() ) {
			wp_send_json_error( array( 
				'message' => __( 'Not authenticated with Eventbrite. Please go to Settings and authorize with Eventbrite first.', 'sg-eventbrite-course-importer' )
			) );
		}
		
		$api = new EventbriteAPI( $oauth, $organization_id );
		$result = $api->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Add more detailed success information
		$message = $result['message'];
		if ( isset( $result['user'] ) && isset( $result['user']['name'] ) ) {
			$message .= ' - Connected as: ' . $result['user']['name'];
		}

		wp_send_json_success( array( 
			'message' => $message,
			'user_info' => $result['user'] ?? null
		) );
	}

	/**
	 * AJAX handler for fetching events.
	 */
	public function ajax_fetch_events() {
		// Add debugging
		error_log( 'SG Eventbrite: ajax_fetch_events called' );
		error_log( 'SG Eventbrite: POST data: ' . print_r( $_POST, true ) );
		
		check_ajax_referer( 'sg_eventbrite_import_nonce', 'nonce' );

		$client_id = get_option( 'sg_eventbrite_client_id', '' );
		$client_secret = get_option( 'sg_eventbrite_client_secret', '' );
		$organization_id = get_option( 'sg_eventbrite_organization_id', '' );
		$search_query = sanitize_text_field( $_POST['search'] ?? '' );
		$page = intval( $_POST['page'] ?? 1 );
		$start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
		$end_date = sanitize_text_field( $_POST['end_date'] ?? '' );
		
		error_log( 'SG Eventbrite: Search query: ' . $search_query . ', Page: ' . $page );
		error_log( 'SG Eventbrite: Start date: ' . $start_date . ', End date: ' . $end_date );
		
		// If no search query, let's get all organization events with proper pagination
		if ( empty( $search_query ) ) {
			$oauth = new \SG\EventbriteCourseImporter\EventbriteOAuth( $client_id, $client_secret );
			$api = new EventbriteAPI( $oauth, $organization_id );
			
			// Fetch ALL events by getting multiple pages
			$all_events = array();
			$current_page = 1;
			$max_pages = 50; // Safety limit to prevent infinite loops (increased to handle 50 events per page)
			
			error_log( 'SG Eventbrite: Fetching all organization events...' );
			
			// Prepare API parameters (Eventbrite API date filtering doesn't work reliably)
			$api_params = array( 
				'time_filter' => 'all', 
				'page_size' => 100, // Get 100 events per page
				'page' => $current_page,
				'order_by' => 'start_desc' // Most recent first (though API may not respect this)
			);
			
			error_log( 'SG Eventbrite: API parameters: ' . print_r( $api_params, true ) );
			
			while ( $current_page <= $max_pages ) {
				// Update page number for current iteration
				$api_params['page'] = $current_page;
				$page_events = $api->get_organization_events( $api_params );
				
				if ( is_wp_error( $page_events ) ) {
					wp_send_json_error( array( 'message' => $page_events->get_error_message() ) );
				}
				
				$events_on_page = $page_events['events'] ?? array();
				if ( empty( $events_on_page ) ) {
					// No more events, we've reached the end
					break;
				}
				
				$all_events = array_merge( $all_events, $events_on_page );
				error_log( 'SG Eventbrite: Fetched page ' . $current_page . ' with ' . count( $events_on_page ) . ' events. Total so far: ' . count( $all_events ) );
				
				// Check if there are more pages using the pagination metadata
				$pagination = $page_events['pagination'] ?? array();
				$has_more_items = $pagination['has_more_items'] ?? false;
				
				error_log( 'SG Eventbrite: Page ' . $current_page . ' pagination info: ' . print_r( $pagination, true ) );
				
				// If there are no more items, we've reached the last page
				if ( ! $has_more_items ) {
					error_log( 'SG Eventbrite: No more items available, stopping pagination' );
					break;
				}
				
				$current_page++;
			}
			
			error_log( 'SG Eventbrite: Fetched ' . count( $all_events ) . ' total events from ' . ($current_page - 1) . ' pages' );
			
			// Apply client-side date filtering since Eventbrite API doesn't support it reliably
			if ( ! empty( $start_date ) || ! empty( $end_date ) ) {
				$all_events = $this->filter_events_by_date_range( $all_events, $start_date, $end_date );
				error_log( 'SG Eventbrite: After client-side date filtering: ' . count( $all_events ) . ' events remain' );
			}
			
			// Sort events by date (most recent first) - 2025, 2024, 2023, etc.
			usort( $all_events, function( $a, $b ) {
				$date_a = isset( $a['start']['utc'] ) ? strtotime( $a['start']['utc'] ) : 0;
				$date_b = isset( $b['start']['utc'] ) ? strtotime( $b['start']['utc'] ) : 0;
				return $date_b - $date_a; // Most recent first (2025 → 2024 → 2023...)
			});
			
			// Debug: Log the first few events after sorting
			error_log( 'SG Eventbrite: First 5 events after sorting all ' . count( $all_events ) . ' events:' );
			for ( $i = 0; $i < min(5, count($all_events)); $i++ ) {
				$event = $all_events[$i];
				$date = isset( $event['start']['utc'] ) ? $event['start']['utc'] : 'No date';
				$timestamp = isset( $event['start']['utc'] ) ? strtotime( $event['start']['utc'] ) : 0;
				error_log( 'SG Eventbrite: ' . ($i+1) . '. "' . $event['name']['text'] . '" - ' . $date . ' (timestamp: ' . $timestamp . ')' );
			}
			
			// Paginate the sorted results
			$per_page = 10;
			$total_events = count( $all_events );
			$total_pages = ceil( $total_events / $per_page );
			$offset = ( $page - 1 ) * $per_page;
			$paginated_events = array_slice( $all_events, $offset, $per_page );
			
			$pagination = array(
				'object_count' => $total_events,
				'page_number' => $page,
				'page_size' => $per_page,
				'page_count' => $total_pages,
				'has_more_items' => $page < $total_pages,
			);
			
			error_log( 'SG Eventbrite: Showing ' . count( $paginated_events ) . ' events for page ' . $page . ' of ' . $total_pages . ' (total: ' . $total_events . ' events)' );
			
			wp_send_json_success( array( 
				'events' => $paginated_events,
				'pagination' => $pagination,
				'search_query' => '',
				'keywords_used' => array()
			) );
		}

		if ( empty( $client_id ) || empty( $client_secret ) || empty( $organization_id ) ) {
			wp_send_json_error( array( 'message' => __( 'OAuth credentials not configured', 'sg-eventbrite-course-importer' ) ) );
		}

		$oauth = new \SG\EventbriteCourseImporter\EventbriteOAuth( $client_id, $client_secret );
		$api = new EventbriteAPI( $oauth, $organization_id );

		if ( ! empty( $search_query ) ) {
			// Process multiple keywords separated by commas
			$keywords = array_map( 'trim', explode( ',', $search_query ) );
			$keywords = array_filter( $keywords ); // Remove empty keywords
			
			if ( count( $keywords ) > 1 ) {
				// For multiple keywords, we'll search each one and combine results
				// Note: This approach doesn't support pagination well, so we'll get all results
				$all_events = array();
				$event_ids = array();
				
				foreach ( $keywords as $keyword ) {
					// Prepare search parameters with date filtering
					$search_params = array( 'page' => 1 );
					if ( ! empty( $start_date ) ) {
						$search_params['start_date.range_start'] = $start_date . 'T00:00:00Z';
					}
					if ( ! empty( $end_date ) ) {
						$search_params['start_date.range_end'] = $end_date . 'T23:59:59Z';
					}
					
					$result = $api->search_events( $keyword, $search_params );
					if ( ! is_wp_error( $result ) && ! empty( $result['events'] ) ) {
						foreach ( $result['events'] as $event ) {
							// Avoid duplicates by checking event ID
							if ( ! in_array( $event['id'], $event_ids ) ) {
								$all_events[] = $event;
								$event_ids[] = $event['id'];
							}
						}
					}
				}
				
				// Apply client-side date filtering since Eventbrite API doesn't support it reliably
			if ( ! empty( $start_date ) || ! empty( $end_date ) ) {
				$all_events = $this->filter_events_by_date_range( $all_events, $start_date, $end_date );
				error_log( 'SG Eventbrite: After client-side date filtering: ' . count( $all_events ) . ' events remain' );
			}
				
				// Sort combined results by date (most recent first)
				usort( $all_events, function( $a, $b ) {
					$date_a = isset( $a['start']['utc'] ) ? strtotime( $a['start']['utc'] ) : 0;
					$date_b = isset( $b['start']['utc'] ) ? strtotime( $b['start']['utc'] ) : 0;
					return $date_b - $date_a;
				});
				
				// Apply pagination to combined results
				$per_page = 10;
				$total_events = count( $all_events );
				$total_pages = ceil( $total_events / $per_page );
				$offset = ( $page - 1 ) * $per_page;
				$paginated_events = array_slice( $all_events, $offset, $per_page );
				
				$result = array( 
					'events' => $paginated_events,
					'pagination' => array(
						'object_count' => $total_events,
						'page_number' => $page,
						'page_size' => $per_page,
						'page_count' => $total_pages,
						'has_more_items' => $page < $total_pages,
					)
				);
			} else {
				// Single keyword search with pagination and date filtering
				$search_params = array( 'page' => $page );
				if ( ! empty( $start_date ) ) {
					$search_params['start_date.range_start'] = $start_date . 'T00:00:00Z';
				}
				if ( ! empty( $end_date ) ) {
					$search_params['start_date.range_end'] = $end_date . 'T23:59:59Z';
				}
				
				$result = $api->search_events( $search_query, $search_params );
			}
		} else {
			wp_send_json_error( array( 'message' => __( 'Please enter search keywords', 'sg-eventbrite-course-importer' ) ) );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$response_data = array( 
			'events' => $result['events'] ?? array(),
			'search_query' => $search_query,
			'keywords_used' => ! empty( $search_query ) ? array_map( 'trim', explode( ',', $search_query ) ) : array()
		);
		
		error_log( 'SG Eventbrite: Sending success response with ' . count( $response_data['events'] ) . ' events' );
		wp_send_json_success( $response_data );
	}

	/**
	 * AJAX handler for importing events.
	 */
	public function ajax_import_events() {
		check_ajax_referer( 'sg_eventbrite_import_nonce', 'nonce' );

		$event_ids = array_map( 'sanitize_text_field', $_POST['event_ids'] ?? array() );
		$options = array(
			'update_existing'  => ! empty( $_POST['update_existing'] ),
			'import_images'    => ! empty( $_POST['import_images'] ),
			'extract_keywords' => ! empty( $_POST['extract_keywords'] ),
			'status'           => sanitize_text_field( $_POST['import_status'] ?? 'publish' ),
		);

		if ( empty( $event_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No events selected for import', 'sg-eventbrite-course-importer' ) ) );
		}

		$client_id = get_option( 'sg_eventbrite_client_id', '' );
		$client_secret = get_option( 'sg_eventbrite_client_secret', '' );
		$organization_id = get_option( 'sg_eventbrite_organization_id', '' );

		if ( empty( $client_id ) || empty( $client_secret ) || empty( $organization_id ) ) {
			wp_send_json_error( array( 'message' => __( 'OAuth credentials not configured', 'sg-eventbrite-course-importer' ) ) );
		}

		$oauth = new \SG\EventbriteCourseImporter\EventbriteOAuth( $client_id, $client_secret );
		$api = new EventbriteAPI( $oauth, $organization_id );
		$importer = new EventbriteImporter( $api );
		$result = $importer->import_events( $event_ids, $options );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler for previewing an event.
	 */
	public function ajax_preview_event() {
		check_ajax_referer( 'sg_eventbrite_import_nonce', 'nonce' );

		$event_id = sanitize_text_field( $_POST['event_id'] ?? '' );

		if ( empty( $event_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Event ID is required', 'sg-eventbrite-course-importer' ) ) );
		}

		$client_id = get_option( 'sg_eventbrite_client_id', '' );
		$client_secret = get_option( 'sg_eventbrite_client_secret', '' );
		$organization_id = get_option( 'sg_eventbrite_organization_id', '' );

		if ( empty( $client_id ) || empty( $client_secret ) || empty( $organization_id ) ) {
			wp_send_json_error( array( 'message' => __( 'OAuth credentials not configured', 'sg-eventbrite-course-importer' ) ) );
		}

		$oauth = new \SG\EventbriteCourseImporter\EventbriteOAuth( $client_id, $client_secret );
		$api = new EventbriteAPI( $oauth, $organization_id );
		$event = $api->get_event( $event_id );

		if ( is_wp_error( $event ) ) {
			wp_send_json_error( array( 'message' => $event->get_error_message() ) );
		}

		// Map event to course format for preview
		$importer = new EventbriteImporter( $api );
		$reflection = new \ReflectionClass( $importer );
		$method = $reflection->getMethod( 'map_event_to_course' );
		$method->setAccessible( true );
		$course_data = $method->invoke( $importer, $event, array( 'extract_keywords' => true ) );

		if ( is_wp_error( $course_data ) ) {
			wp_send_json_error( array( 'message' => $course_data->get_error_message() ) );
		}

		wp_send_json_success( array( 'event' => $event, 'course_data' => $course_data ) );
	}
	
	/**
	 * Filter events by date range (client-side filtering since Eventbrite API doesn't support it reliably).
	 *
	 * @param array $events Array of events to filter
	 * @param string $start_date Start date in YYYY-MM-DD format
	 * @param string $end_date End date in YYYY-MM-DD format
	 * @return array Filtered events
	 */
	private function filter_events_by_date_range( $events, $start_date, $end_date ) {
		$filtered_events = array();
		
		foreach ( $events as $event ) {
			$event_date = isset( $event['start']['utc'] ) ? $event['start']['utc'] : null;
			
			if ( ! $event_date ) {
				// If no date, include it (or exclude based on your preference)
				$filtered_events[] = $event;
				continue;
			}
			
			$event_timestamp = strtotime( $event_date );
			$event_date_only = date( 'Y-m-d', $event_timestamp );
			
			$include_event = true;
			
			// Check start date
			if ( ! empty( $start_date ) && $event_date_only < $start_date ) {
				$include_event = false;
			}
			
			// Check end date
			if ( ! empty( $end_date ) && $event_date_only > $end_date ) {
				$include_event = false;
			}
			
			if ( $include_event ) {
				$filtered_events[] = $event;
			}
		}
		
		error_log( 'SG Eventbrite: Client-side date filtering - Start: ' . $start_date . ', End: ' . $end_date . ', Original: ' . count( $events ) . ', Filtered: ' . count( $filtered_events ) );
		
		return $filtered_events;
	}
}