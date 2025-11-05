<?php
/**
 * Settings Manager Class.
 *
 * Handles plugin settings and configuration.
 *
 * @package SG\EventbriteCourseImporter\Admin
 * @since 1.0.0
 */

namespace SG\EventbriteCourseImporter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Settings Manager Class.
 *
 * Manages plugin settings, API configuration, and admin options.
 *
 * @package SG\EventbriteCourseImporter\Admin
 * @since 1.0.0
 */
class SettingsManager {

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
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_sg_eventbrite_test_api', array( $this, 'ajax_test_api' ) );
		add_action( 'wp_ajax_sg_eventbrite_clear_cache', array( $this, 'ajax_clear_cache' ) );
		add_action( 'wp_ajax_sg_eventbrite_fetch_organizations', array( $this, 'ajax_fetch_organizations' ) );
		add_action( 'wp_ajax_sg_eventbrite_show_debug_logs', array( $this, 'ajax_show_debug_logs' ) );
		add_action( 'wp_ajax_sg_eventbrite_test_oauth_status', array( $this, 'ajax_test_oauth_status' ) );
		add_action( 'wp_ajax_sg_eventbrite_revoke_auth', array( $this, 'ajax_revoke_auth' ) );
		add_action( 'admin_init', array( $this, 'handle_oauth_callback' ) );
	}

	/**
	 * Add admin menu page.
	 */
	public function add_admin_menu() {
		// Add settings page under Courses post type
		add_submenu_page(
			'edit.php?post_type=sg_course',
			__( 'Eventbrite Settings', 'sg-eventbrite-course-importer' ),
			__( 'Eventbrite Settings', 'sg-eventbrite-course-importer' ),
			'manage_options',
			'sg-eventbrite-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		// OAuth Settings
		register_setting(
			'sg_eventbrite_settings',
			'sg_eventbrite_client_id',
			array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'sg_eventbrite_settings',
			'sg_eventbrite_client_secret',
			array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'sg_eventbrite_settings',
			'sg_eventbrite_organization_id',
			array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		// Import Settings
		register_setting(
			'sg_eventbrite_settings',
			'sg_eventbrite_default_status',
			array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'publish',
			)
		);

		register_setting(
			'sg_eventbrite_settings',
			'sg_eventbrite_auto_import_images',
			array(
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		register_setting(
			'sg_eventbrite_settings',
			'sg_eventbrite_auto_extract_keywords',
			array(
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		register_setting(
			'sg_eventbrite_settings',
			'sg_eventbrite_update_existing',
			array(
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		// Cache Settings
		register_setting(
			'sg_eventbrite_settings',
			'sg_eventbrite_cache_duration',
			array(
				'sanitize_callback' => 'absint',
				'default'           => 3600,
			)
		);

		// Rate Limiting Settings
		register_setting(
			'sg_eventbrite_settings',
			'sg_eventbrite_rate_limit',
			array(
				'sanitize_callback' => 'absint',
				'default'           => 60,
			)
		);

		// Debug Settings
		register_setting(
			'sg_eventbrite_settings',
			'sg_eventbrite_debug_mode',
			array(
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		// Add settings sections
		add_settings_section(
			'sg_eventbrite_api_section',
			__( 'API Configuration', 'sg-eventbrite-course-importer' ),
			array( $this, 'render_api_section_description' ),
			'sg-eventbrite-settings'
		);

		add_settings_section(
			'sg_eventbrite_import_section',
			__( 'Import Settings', 'sg-eventbrite-course-importer' ),
			array( $this, 'render_import_section_description' ),
			'sg-eventbrite-settings'
		);

		add_settings_section(
			'sg_eventbrite_advanced_section',
			__( 'Advanced Settings', 'sg-eventbrite-course-importer' ),
			array( $this, 'render_advanced_section_description' ),
			'sg-eventbrite-settings'
		);

		// Add settings fields
		$this->add_settings_fields();
	}

	/**
	 * Add settings fields.
	 */
	private function add_settings_fields() {
		// Client ID field
		add_settings_field(
			'sg_eventbrite_client_id',
			__( 'Eventbrite Client ID', 'sg-eventbrite-course-importer' ),
			array( $this, 'render_client_id_field' ),
			'sg-eventbrite-settings',
			'sg_eventbrite_api_section'
		);

		// Client Secret field
		add_settings_field(
			'sg_eventbrite_client_secret',
			__( 'Eventbrite Client Secret', 'sg-eventbrite-course-importer' ),
			array( $this, 'render_client_secret_field' ),
			'sg-eventbrite-settings',
			'sg_eventbrite_api_section'
		);

		// OAuth Authorization field
		add_settings_field(
			'sg_eventbrite_oauth_auth',
			__( 'OAuth Authorization', 'sg-eventbrite-course-importer' ),
			array( $this, 'render_oauth_auth_field' ),
			'sg-eventbrite-settings',
			'sg_eventbrite_api_section'
		);

		// Organization ID field
		add_settings_field(
			'sg_eventbrite_organization_id',
			__( 'Organization ID', 'sg-eventbrite-course-importer' ),
			array( $this, 'render_organization_id_field' ),
			'sg-eventbrite-settings',
			'sg_eventbrite_api_section'
		);

		// Default status field
		add_settings_field(
			'sg_eventbrite_default_status',
			__( 'Default Import Status', 'sg-eventbrite-course-importer' ),
			array( $this, 'render_default_status_field' ),
			'sg-eventbrite-settings',
			'sg_eventbrite_import_section'
		);

		// Auto import images field
		add_settings_field(
			'sg_eventbrite_auto_import_images',
			__( 'Auto Import Images', 'sg-eventbrite-course-importer' ),
			array( $this, 'render_auto_import_images_field' ),
			'sg-eventbrite-settings',
			'sg_eventbrite_import_section'
		);

		// Auto extract keywords field
		add_settings_field(
			'sg_eventbrite_auto_extract_keywords',
			__( 'Auto Extract Keywords', 'sg-eventbrite-course-importer' ),
			array( $this, 'render_auto_extract_keywords_field' ),
			'sg-eventbrite-settings',
			'sg_eventbrite_import_section'
		);

		// Update existing field
		add_settings_field(
			'sg_eventbrite_update_existing',
			__( 'Update Existing Courses', 'sg-eventbrite-course-importer' ),
			array( $this, 'render_update_existing_field' ),
			'sg-eventbrite-settings',
			'sg_eventbrite_import_section'
		);

		// Cache duration field
		add_settings_field(
			'sg_eventbrite_cache_duration',
			__( 'Cache Duration (seconds)', 'sg-eventbrite-course-importer' ),
			array( $this, 'render_cache_duration_field' ),
			'sg-eventbrite-settings',
			'sg_eventbrite_advanced_section'
		);

		// Rate limit field
		add_settings_field(
			'sg_eventbrite_rate_limit',
			__( 'Rate Limit (requests per minute)', 'sg-eventbrite-course-importer' ),
			array( $this, 'render_rate_limit_field' ),
			'sg-eventbrite-settings',
			'sg_eventbrite_advanced_section'
		);

		// Debug mode field
		add_settings_field(
			'sg_eventbrite_debug_mode',
			__( 'Debug Mode', 'sg-eventbrite-course-importer' ),
			array( $this, 'render_debug_mode_field' ),
			'sg-eventbrite-settings',
			'sg_eventbrite_advanced_section'
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only log and enqueue scripts on our specific settings page
		if ( 'sg_course_page_sg-eventbrite-settings' !== $hook ) {
			return;
		}
		
		error_log( 'SG Eventbrite: Enqueuing admin scripts for hook: ' . $hook );

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'wp-util' );

		// Remove the missing settings.js file reference
		// wp_enqueue_script(
		//	'sg-eventbrite-settings',
		//	SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_URL . '/assets/build/js/settings.js',
		//	array( 'jquery', 'wp-util' ),
		//	SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_VERSION,
		//	true
		// );

		wp_localize_script(
			'jquery',
			'sgEventbriteSettings',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'sg_eventbrite_settings_nonce' ),
				'strings'    => array(
					'testing_api'        => __( 'Testing API connection...', 'sg-eventbrite-course-importer' ),
					'api_success'        => __( 'API connection successful!', 'sg-eventbrite-course-importer' ),
					'api_failed'         => __( 'API connection failed. Please check your credentials.', 'sg-eventbrite-course-importer' ),
					'clearing_cache'     => __( 'Clearing cache...', 'sg-eventbrite-course-importer' ),
					'cache_cleared'      => __( 'Cache cleared successfully!', 'sg-eventbrite-course-importer' ),
					'cache_clear_failed' => __( 'Failed to clear cache.', 'sg-eventbrite-course-importer' ),
				),
			)
		);
		

		// Add JavaScript to footer using WordPress action
		add_action( 'admin_footer', array( $this, 'add_admin_footer_scripts' ) );
	}

	/**
	 * Add JavaScript to admin footer.
	 */
	public function add_admin_footer_scripts() {
		// Only add scripts on our settings page
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'sg_course_page_sg-eventbrite-settings' ) {
			return;
		}
		?>
		<script type="text/javascript">
		console.log('SG Eventbrite: Script starting...');
		jQuery(document).ready(function($) {
			console.log('SG Eventbrite: jQuery ready');
			// Debug: Check if button exists
			console.log('jQuery ready, checking for debug logs button...');
			console.log('Button exists:', $('#show-debug-logs-btn').length > 0);
			console.log('Revoke auth button exists:', $('#revoke-auth-btn').length > 0);
			console.log('Fetch organizations button exists:', $('#fetch-organizations-btn').length > 0);
			
			// Fetch Organizations button handler
			$('#fetch-organizations-btn').on('click', function() {
				var $button = $(this);
				var $select = $('#sg_eventbrite_organization_id');
				var $loading = $('#organization-loading');
				
				$button.prop('disabled', true);
				$loading.show();
				
				$.ajax({
					url: sgEventbriteSettings.ajaxUrl,
					type: 'POST',
					data: {
						action: 'sg_eventbrite_fetch_organizations',
						nonce: sgEventbriteSettings.nonce
					},
					success: function(response) {
						if (response.success) {
							// Clear existing options except the first one
							$select.find('option:not(:first)').remove();
							
							// Add new organization options
							if (response.data.organizations && response.data.organizations.length > 0) {
								$.each(response.data.organizations, function(index, org) {
									$select.append($('<option>', {
										value: org.id,
										text: org.name + (org.description ? ' - ' + org.description : '')
									}));
								});
							} else {
								$select.append($('<option>', {
									value: '',
									text: 'No organizations found'
								}));
							}
							
							// Show success message with debug info
							var message = response.data.message;
							if (response.data.debug_info) {
								message += '<br><small>Debug: ' + JSON.stringify(response.data.debug_info) + '</small>';
							}
							$('#action-results').html('<div class="notice notice-success"><p>' + message + '</p></div>');
						} else {
							var errorMsg = response.data.message || 'Unknown error';
							if (response.data.debug_info) {
								errorMsg += '<br><small>Debug: ' + JSON.stringify(response.data.debug_info) + '</small>';
							}
							$('#action-results').html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
						}
					},
					error: function(xhr, status, error) {
						$('#action-results').html('<div class="notice notice-error"><p>Error fetching organizations: ' + error + '</p></div>');
					},
					complete: function() {
						$button.prop('disabled', false);
						$loading.hide();
					}
				});
			});

			// Test API Connection button handler
			$('#test-api-connection').on('click', function() {
				console.log('Test API connection button clicked');
				var $button = $(this);
				var $results = $('#action-results');
				
				$button.prop('disabled', true).text(sgEventbriteSettings.strings.testing_api);
				$results.html('<div class="notice notice-info"><p>Testing API connection...</p></div>');
				
				$.ajax({
					url: sgEventbriteSettings.ajaxUrl,
					type: 'POST',
					data: {
						action: 'sg_eventbrite_test_api',
						nonce: sgEventbriteSettings.nonce
					},
					success: function(response) {
						console.log('Test API response:', response);
						if (response.success) {
							$results.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
						} else {
							$results.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
						}
					},
					error: function(xhr, status, error) {
						console.log('Test API AJAX error:', xhr, status, error);
						$results.html('<div class="notice notice-error"><p>Error testing API connection: ' + error + '</p></div>');
					},
					complete: function() {
						$button.prop('disabled', false).text('Test API Connection');
					}
				});
			});

			// Show Debug Logs button handler
			$('#show-debug-logs-btn').on('click', function() {
				console.log('Debug logs button clicked');
				var $button = $(this);
				var $results = $('#action-results');
				
				$button.prop('disabled', true);
				$results.html('<div class="notice notice-info"><p>Loading debug logs...</p></div>');
				
				$.ajax({
					url: sgEventbriteSettings.ajaxUrl,
					type: 'POST',
					data: {
						action: 'sg_eventbrite_show_debug_logs',
						nonce: sgEventbriteSettings.nonce
					},
					success: function(response) {
						console.log('Debug logs response:', response);
						if (response.success) {
							var logsHtml = '<div class="notice notice-info"><p>' + response.data.message + '</p></div>';
							logsHtml += '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">';
							
							if (response.data.logs && response.data.logs.length > 0) {
								$.each(response.data.logs, function(index, log) {
									var levelClass = 'info';
									if (log.level === 'error') levelClass = 'error';
									if (log.level === 'warning') levelClass = 'warning';
									
									logsHtml += '<div style="margin-bottom: 10px; padding: 5px; border-left: 3px solid #' + (log.level === 'error' ? 'dc3232' : log.level === 'warning' ? 'ffb900' : '00a0d2') + ';">';
									logsHtml += '<strong>[' + log.created_at + '] ' + log.level.toUpperCase() + ':</strong> ' + log.message;
									if (log.context && Object.keys(log.context).length > 0) {
										logsHtml += '<br><small style="color: #666;">Context: ' + JSON.stringify(log.context, null, 2) + '</small>';
									}
									logsHtml += '</div>';
								});
							} else {
								logsHtml += '<p>No logs found. Total logs in database: ' + (response.data.total_logs || 0) + '</p>';
							}
							
							logsHtml += '</div>';
							$results.html(logsHtml);
						} else {
							$results.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
						}
					},
					error: function(xhr, status, error) {
						console.log('AJAX error:', status, error);
						console.log('Response:', xhr.responseText);
						$results.html('<div class="notice notice-error"><p>Error loading debug logs: ' + error + '. Check browser console for details.</p></div>');
					},
					complete: function() {
						$button.prop('disabled', false);
					}
				});
			});

			// Test OAuth Status button handler
			$('#test-oauth-status-btn').on('click', function() {
				console.log('Test OAuth status button clicked');
				var $button = $(this);
				var $results = $('#action-results');
				
				$button.prop('disabled', true);
				$results.html('<div class="notice notice-info"><p>Testing OAuth status...</p></div>');
				
				// Get OAuth credentials
				var clientId = $('#sg_eventbrite_client_id').val();
				var clientSecret = $('#sg_eventbrite_client_secret').val();
				
				if (!clientId || !clientSecret) {
					$results.html('<div class="notice notice-error"><p>Please enter Client ID and Client Secret first.</p></div>');
					$button.prop('disabled', false);
					return;
				}
				
				// Test OAuth status
				$.ajax({
					url: sgEventbriteSettings.ajaxUrl,
					type: 'POST',
					data: {
						action: 'sg_eventbrite_test_oauth_status',
						nonce: sgEventbriteSettings.nonce,
						client_id: clientId,
						client_secret: clientSecret
					},
					success: function(response) {
						console.log('OAuth status response:', response);
						if (response.success) {
							$results.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
						} else {
							$results.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
						}
					},
					error: function(xhr, status, error) {
						console.log('OAuth status AJAX error:', xhr, status, error);
						$results.html('<div class="notice notice-error"><p>Error testing OAuth status: ' + error + '</p></div>');
					},
					complete: function() {
						$button.prop('disabled', false);
					}
				});
			});

			// Revoke Authorization button handler
			$('#revoke-auth-btn').on('click', function() {
				if (confirm('Are you sure you want to revoke authorization? This will clear all stored tokens and you will need to re-authorize.')) {
					var $button = $(this);
					var originalText = $button.text();
					$button.prop('disabled', true).text('Revoking...');
					
					$.ajax({
						url: sgEventbriteSettings.ajaxUrl,
						type: 'POST',
						data: {
							action: 'sg_eventbrite_revoke_auth',
							nonce: sgEventbriteSettings.nonce
						},
						success: function(response) {
							console.log('Revoke auth response:', response);
							alert('Authorization revoked successfully. Please refresh the page.');
							location.reload();
						},
						error: function(xhr, status, error) {
							console.error('Revoke auth error:', error);
							alert('Error revoking authorization: ' + error);
						},
						complete: function() {
							$button.prop('disabled', false).text(originalText);
						}
					});
				}
			});

		});
		</script>
		<?php
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php _e( 'Eventbrite Course Importer Settings', 'sg-eventbrite-course-importer' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'sg_eventbrite_settings' );
				do_settings_sections( 'sg-eventbrite-settings' );
				submit_button();
				?>
			</form>

			<div class="sg-settings-actions">
				<h2><?php _e( 'Quick Actions', 'sg-eventbrite-course-importer' ); ?></h2>
				<p>
					<button type="button" id="test-api-connection" class="button button-secondary">
						<?php _e( 'Test API Connection', 'sg-eventbrite-course-importer' ); ?>
					</button>
					<button type="button" id="clear-api-cache" class="button button-secondary">
						<?php _e( 'Clear API Cache', 'sg-eventbrite-course-importer' ); ?>
					</button>
					<?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ): ?>
					<button type="button" id="show-debug-logs-btn" class="button button-secondary">
						<?php _e( 'Show Debug Logs', 'sg-eventbrite-course-importer' ); ?>
					</button>
					<button type="button" id="test-oauth-status-btn" class="button button-secondary">
						<?php _e( 'Test OAuth Status', 'sg-eventbrite-course-importer' ); ?>
					</button>
					<?php endif; ?>
				</p>
				<div id="action-results"></div>
			</div>

			<div class="sg-settings-info">
				<h2><?php _e( 'Getting Started', 'sg-eventbrite-course-importer' ); ?></h2>
				<ol>
					<li>
						<?php _e( 'Create an OAuth2 application in the', 'sg-eventbrite-course-importer' ); ?>
						<a href="https://www.eventbrite.com/platform/api-keys/" target="_blank">
							<?php _e( 'Eventbrite Developer Portal', 'sg-eventbrite-course-importer' ); ?>
						</a>
					</li>
					<li>
						<?php _e( 'Get your Client ID and Client Secret from the OAuth2 application', 'sg-eventbrite-course-importer' ); ?>
					</li>
					<li>
						<?php _e( 'Enter your Client ID and Client Secret below', 'sg-eventbrite-course-importer' ); ?>
					</li>
					<li>
						<?php _e( 'Click "Authorize with Eventbrite" to grant permissions to your application', 'sg-eventbrite-course-importer' ); ?>
					</li>
					<li>
						<?php _e( 'After authorization, click "Fetch Organizations" to load your available organizations', 'sg-eventbrite-course-importer' ); ?>
					</li>
					<li>
						<?php _e( 'Select your organization from the dropdown and configure your import preferences', 'sg-eventbrite-course-importer' ); ?>
					</li>
					<li>
						<?php _e( 'Go to', 'sg-eventbrite-course-importer' ); ?>
						<a href="<?php echo admin_url( 'edit.php?post_type=sg_course&page=sg-eventbrite-import' ); ?>">
							<?php _e( 'Import from Eventbrite', 'sg-eventbrite-course-importer' ); ?>
						</a>
						<?php _e( 'to start importing events', 'sg-eventbrite-course-importer' ); ?>
					</li>
				</ol>
			</div>
		</div>

		<style>
		.sg-settings-actions,
		.sg-settings-info {
			background: #fff;
			border: 1px solid #ccd0d4;
			margin: 20px 0;
			padding: 20px;
		}
		.sg-settings-info ol {
			margin-left: 20px;
		}
		.sg-settings-info li {
			margin-bottom: 10px;
		}
		#action-results {
			margin-top: 10px;
			padding: 10px;
			border-radius: 4px;
			display: none;
		}
		#action-results.success {
			background: #d4edda;
			color: #155724;
			border: 1px solid #c3e6cb;
		}
		#action-results.error {
			background: #f8d7da;
			color: #721c24;
			border: 1px solid #f5c6cb;
		}
		.form-table th {
			width: 200px;
		}
		.description {
			font-style: italic;
			color: #666;
		}
		</style>
		<?php
	}

	/**
	 * Render API section description.
	 */
	public function render_api_section_description() {
		echo '<p>' . __( 'Configure your Eventbrite API credentials to enable event importing.', 'sg-eventbrite-course-importer' ) . '</p>';
	}

	/**
	 * Render import section description.
	 */
	public function render_import_section_description() {
		echo '<p>' . __( 'Configure default settings for importing events as courses.', 'sg-eventbrite-course-importer' ) . '</p>';
	}

	/**
	 * Render advanced section description.
	 */
	public function render_advanced_section_description() {
		echo '<p>' . __( 'Advanced configuration options for power users.', 'sg-eventbrite-course-importer' ) . '</p>';
	}

	/**
	 * Render Client ID field.
	 */
	public function render_client_id_field() {
		$value = get_option( 'sg_eventbrite_client_id', '' );
		?>
		<input type="text" 
			   id="sg_eventbrite_client_id" 
			   name="sg_eventbrite_client_id" 
			   value="<?php echo esc_attr( $value ); ?>" 
			   class="regular-text" />
		<p class="description">
			<?php _e( 'Your Eventbrite OAuth2 Client ID. Get it from the', 'sg-eventbrite-course-importer' ); ?>
			<a href="https://www.eventbrite.com/platform/api-keys/" target="_blank">
				<?php _e( 'Eventbrite Developer Portal', 'sg-eventbrite-course-importer' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Render Client Secret field.
	 */
	public function render_client_secret_field() {
		$value = get_option( 'sg_eventbrite_client_secret', '' );
		?>
		<input type="password" 
			   id="sg_eventbrite_client_secret" 
			   name="sg_eventbrite_client_secret" 
			   value="<?php echo esc_attr( $value ); ?>" 
			   class="regular-text" />
		<p class="description">
			<?php _e( 'Your Eventbrite OAuth2 Client Secret. Keep this secure and never share it publicly.', 'sg-eventbrite-course-importer' ); ?>
		</p>
		<?php
	}

	/**
	 * Render OAuth authorization field.
	 */
	public function render_oauth_auth_field() {
		$client_id = get_option( 'sg_eventbrite_client_id', '' );
		$client_secret = get_option( 'sg_eventbrite_client_secret', '' );
		
		if ( empty( $client_id ) || empty( $client_secret ) ) {
			?>
			<p class="description">
				<?php _e( 'Please enter your Client ID and Client Secret first.', 'sg-eventbrite-course-importer' ); ?>
			</p>
			<?php
			return;
		}

		$oauth = new \SG\EventbriteCourseImporter\EventbriteOAuth( $client_id, $client_secret );
		$is_authenticated = $oauth->is_authenticated();
		
		if ( $is_authenticated ) {
			?>
			<div style="display: flex; gap: 10px; align-items: center;">
				<span style="color: green;">✓ <?php _e( 'Authenticated with Eventbrite', 'sg-eventbrite-course-importer' ); ?></span>
				<button type="button" id="revoke-auth-btn" class="button button-secondary">
					<?php _e( 'Revoke Authorization', 'sg-eventbrite-course-importer' ); ?>
				</button>
			</div>
			<p class="description">
				<?php _e( 'You are authenticated with Eventbrite. You can now fetch organizations and import events.', 'sg-eventbrite-course-importer' ); ?>
			</p>
			<?php
		} else {
			?>
			<div style="display: flex; gap: 10px; align-items: center;">
				<a href="<?php echo esc_url( $oauth->get_authorization_url() ); ?>" class="button button-primary">
					<?php _e( 'Authorize with Eventbrite', 'sg-eventbrite-course-importer' ); ?>
				</a>
			</div>
			<p class="description">
				<?php _e( 'Click to authorize this application with your Eventbrite account. You will be redirected to Eventbrite to grant permissions.', 'sg-eventbrite-course-importer' ); ?>
			</p>
			<?php
		}
	}

	/**
	 * Render organization ID field.
	 */
	public function render_organization_id_field() {
		$value = get_option( 'sg_eventbrite_organization_id', '' );
		$client_id = get_option( 'sg_eventbrite_client_id', '' );
		$client_secret = get_option( 'sg_eventbrite_client_secret', '' );
		
		if ( empty( $client_id ) || empty( $client_secret ) ) {
			?>
			<p class="description">
				<?php _e( 'Please enter your Client ID and Client Secret first.', 'sg-eventbrite-course-importer' ); ?>
			</p>
			<?php
			return;
		}

		$oauth = new \SG\EventbriteCourseImporter\EventbriteOAuth( $client_id, $client_secret );
		$is_authenticated = $oauth->is_authenticated();
		?>
		<div style="display: flex; gap: 10px; align-items: center;">
			<select id="sg_eventbrite_organization_id" name="sg_eventbrite_organization_id" class="regular-text">
				<option value=""><?php _e( 'Select an organization...', 'sg-eventbrite-course-importer' ); ?></option>
				<?php if ( ! empty( $value ) ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" selected>
						<?php echo esc_html( $value ); ?> (<?php _e( 'Current Selection', 'sg-eventbrite-course-importer' ); ?>)
					</option>
				<?php endif; ?>
			</select>
			<?php if ( $is_authenticated ) : ?>
				<button type="button" id="fetch-organizations-btn" class="button button-secondary">
					<?php _e( 'Fetch Organizations', 'sg-eventbrite-course-importer' ); ?>
				</button>
			<?php else : ?>
				<button type="button" class="button button-secondary" disabled>
					<?php _e( 'Fetch Organizations', 'sg-eventbrite-course-importer' ); ?>
				</button>
			<?php endif; ?>
		</div>
		<p class="description">
			<?php if ( $is_authenticated ) : ?>
				<?php _e( 'Your Eventbrite Organization ID. Click "Fetch Organizations" to load your available organizations.', 'sg-eventbrite-course-importer' ); ?>
			<?php else : ?>
				<?php _e( 'Please authorize with Eventbrite first to fetch organizations.', 'sg-eventbrite-course-importer' ); ?>
			<?php endif; ?>
		</p>
		<div id="organization-loading" style="display: none;">
			<span class="spinner is-active"></span>
			<?php _e( 'Loading organizations...', 'sg-eventbrite-course-importer' ); ?>
		</div>
		<?php
	}

	/**
	 * Render default status field.
	 */
	public function render_default_status_field() {
		$value = get_option( 'sg_eventbrite_default_status', 'publish' );
		?>
		<select id="sg_eventbrite_default_status" name="sg_eventbrite_default_status">
			<option value="publish" <?php selected( $value, 'publish' ); ?>>
				<?php _e( 'Published', 'sg-eventbrite-course-importer' ); ?>
			</option>
			<option value="draft" <?php selected( $value, 'draft' ); ?>>
				<?php _e( 'Draft', 'sg-eventbrite-course-importer' ); ?>
			</option>
			<option value="private" <?php selected( $value, 'private' ); ?>>
				<?php _e( 'Private', 'sg-eventbrite-course-importer' ); ?>
			</option>
		</select>
		<p class="description">
			<?php _e( 'Default status for imported courses.', 'sg-eventbrite-course-importer' ); ?>
		</p>
		<?php
	}

	/**
	 * Render auto import images field.
	 */
	public function render_auto_import_images_field() {
		$value = get_option( 'sg_eventbrite_auto_import_images', true );
		?>
		<label>
			<input type="checkbox" 
				   id="sg_eventbrite_auto_import_images" 
				   name="sg_eventbrite_auto_import_images" 
				   value="1" 
				   <?php checked( $value, true ); ?> />
			<?php _e( 'Automatically download and set featured images for imported courses', 'sg-eventbrite-course-importer' ); ?>
		</label>
		<?php
	}

	/**
	 * Render auto extract keywords field.
	 */
	public function render_auto_extract_keywords_field() {
		$value = get_option( 'sg_eventbrite_auto_extract_keywords', true );
		?>
		<label>
			<input type="checkbox" 
				   id="sg_eventbrite_auto_extract_keywords" 
				   name="sg_eventbrite_auto_extract_keywords" 
				   value="1" 
				   <?php checked( $value, true ); ?> />
			<?php _e( 'Automatically extract instructor, course length, and other details from event descriptions', 'sg-eventbrite-course-importer' ); ?>
		</label>
		<?php
	}

	/**
	 * Render update existing field.
	 */
	public function render_update_existing_field() {
		$value = get_option( 'sg_eventbrite_update_existing', true );
		?>
		<label>
			<input type="checkbox" 
				   id="sg_eventbrite_update_existing" 
				   name="sg_eventbrite_update_existing" 
				   value="1" 
				   <?php checked( $value, true ); ?> />
			<?php _e( 'Update existing courses when re-importing the same Eventbrite event', 'sg-eventbrite-course-importer' ); ?>
		</label>
		<?php
	}

	/**
	 * Render cache duration field.
	 */
	public function render_cache_duration_field() {
		$value = get_option( 'sg_eventbrite_cache_duration', 3600 );
		?>
		<input type="number" 
			   id="sg_eventbrite_cache_duration" 
			   name="sg_eventbrite_cache_duration" 
			   value="<?php echo esc_attr( $value ); ?>" 
			   min="300" 
			   max="86400" 
			   class="small-text" />
		<p class="description">
			<?php _e( 'How long to cache API responses (300-86400 seconds).', 'sg-eventbrite-course-importer' ); ?>
		</p>
		<?php
	}

	/**
	 * Render rate limit field.
	 */
	public function render_rate_limit_field() {
		$value = get_option( 'sg_eventbrite_rate_limit', 60 );
		?>
		<input type="number" 
			   id="sg_eventbrite_rate_limit" 
			   name="sg_eventbrite_rate_limit" 
			   value="<?php echo esc_attr( $value ); ?>" 
			   min="1" 
			   max="300" 
			   class="small-text" />
		<p class="description">
			<?php _e( 'Maximum API requests per minute (1-300).', 'sg-eventbrite-course-importer' ); ?>
		</p>
		<?php
	}

	/**
	 * Render debug mode field.
	 */
	public function render_debug_mode_field() {
		$value = get_option( 'sg_eventbrite_debug_mode', false );
		?>
		<label>
			<input type="checkbox" 
				   id="sg_eventbrite_debug_mode" 
				   name="sg_eventbrite_debug_mode" 
				   value="1" 
				   <?php checked( $value, true ); ?> />
			<?php _e( 'Enable debug logging (check error logs for detailed information)', 'sg-eventbrite-course-importer' ); ?>
		</label>
		<?php
	}

	/**
	 * AJAX handler for testing API connection.
	 */
	public function ajax_test_api() {
		check_ajax_referer( 'sg_eventbrite_settings_nonce', 'nonce' );

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
				'message' => __( 'Not authenticated with Eventbrite. Please click "Authorize with Eventbrite" first to grant permissions to your application.', 'sg-eventbrite-course-importer' )
			) );
		}
		
		$api = new \SG\EventbriteCourseImporter\EventbriteAPI( $oauth, $organization_id );
		$result = $api->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => $result['message'] ) );
	}

	/**
	 * AJAX handler for clearing API cache.
	 */
	public function ajax_clear_cache() {
		check_ajax_referer( 'sg_eventbrite_settings_nonce', 'nonce' );

		$client_id = get_option( 'sg_eventbrite_client_id', '' );
		$client_secret = get_option( 'sg_eventbrite_client_secret', '' );
		$organization_id = get_option( 'sg_eventbrite_organization_id', '' );

		if ( empty( $client_id ) || empty( $client_secret ) || empty( $organization_id ) ) {
			wp_send_json_error( array( 'message' => __( 'OAuth credentials not configured', 'sg-eventbrite-course-importer' ) ) );
		}

		$oauth = new \SG\EventbriteCourseImporter\EventbriteOAuth( $client_id, $client_secret );
		$api = new \SG\EventbriteCourseImporter\EventbriteAPI( $oauth, $organization_id );
		$api->clear_cache();

		wp_send_json_success( array( 'message' => __( 'Cache cleared successfully', 'sg-eventbrite-course-importer' ) ) );
	}

	/**
	 * Handle OAuth callback from Eventbrite.
	 */
	public function handle_oauth_callback() {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'sg-eventbrite-settings' ) {
			return;
		}

		if ( ! isset( $_GET['oauth_callback'] ) || $_GET['oauth_callback'] !== '1' ) {
			return;
		}

		if ( ! isset( $_GET['code'] ) ) {
			// Handle error case
			if ( isset( $_GET['error'] ) ) {
				$error_message = sanitize_text_field( $_GET['error'] );
				add_action( 'admin_notices', function() use ( $error_message ) {
					echo '<div class="notice notice-error"><p>' . sprintf( __( 'OAuth authorization failed: %s', 'sg-eventbrite-course-importer' ), esc_html( $error_message ) ) . '</p></div>';
				});
			}
			return;
		}

		$code = sanitize_text_field( $_GET['code'] );
		$client_id = get_option( 'sg_eventbrite_client_id', '' );
		$client_secret = get_option( 'sg_eventbrite_client_secret', '' );

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . __( 'OAuth credentials not configured', 'sg-eventbrite-course-importer' ) . '</p></div>';
			});
			return;
		}

		$oauth = new \SG\EventbriteCourseImporter\EventbriteOAuth( $client_id, $client_secret );
		$result = $oauth->exchange_code_for_token( $code );

		if ( is_wp_error( $result ) ) {
			add_action( 'admin_notices', function() use ( $result ) {
				echo '<div class="notice notice-error"><p>' . sprintf( __( 'OAuth token exchange failed: %s', 'sg-eventbrite-course-importer' ), esc_html( $result->get_error_message() ) ) . '</p></div>';
			});
		} else {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success"><p>' . __( 'Successfully authenticated with Eventbrite! You can now fetch organizations.', 'sg-eventbrite-course-importer' ) . '</p></div>';
			});
		}

		// Redirect to remove the callback parameters from URL
		wp_redirect( admin_url( 'edit.php?post_type=sg_course&page=sg-eventbrite-settings' ) );
		exit;
	}

	/**
	 * AJAX handler for fetching user organizations.
	 */
	public function ajax_fetch_organizations() {
		check_ajax_referer( 'sg_eventbrite_settings_nonce', 'nonce' );

		$client_id = get_option( 'sg_eventbrite_client_id', '' );
		$client_secret = get_option( 'sg_eventbrite_client_secret', '' );

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			wp_send_json_error( array( 'message' => __( 'OAuth credentials not configured', 'sg-eventbrite-course-importer' ) ) );
		}

		$oauth = new \SG\EventbriteCourseImporter\EventbriteOAuth( $client_id, $client_secret );
		
		// Debug: Check if authenticated
		if ( ! $oauth->is_authenticated() ) {
			wp_send_json_error( array( 'message' => __( 'Not authenticated with Eventbrite. Please authorize first.', 'sg-eventbrite-course-importer' ) ) );
		}

		$api = new \SG\EventbriteCourseImporter\EventbriteAPI( $oauth, '' ); // No org ID needed for this call
		
		
		$result = $api->get_user_organizations();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Format organizations for dropdown
		$organizations = array();
		if ( isset( $result['organizations'] ) && is_array( $result['organizations'] ) ) {
			foreach ( $result['organizations'] as $org ) {
				$organizations[] = array(
					'id' => $org['id'] ?? '',
					'name' => $org['name'] ?? 'Unnamed Organization',
					'description' => $org['description'] ?? '',
				);
			}
		}

		wp_send_json_success( array( 
			'message' => sprintf( __( 'Organizations fetched successfully. Found %d organizations.', 'sg-eventbrite-course-importer' ), count( $organizations ) ),
			'organizations' => $organizations,
			'debug_info' => array(
				'response_keys' => array_keys( $result ),
				'organizations_count' => count( $organizations )
			)
		) );
	}

	/**
	 * AJAX handler for showing debug logs.
	 */
	public function ajax_show_debug_logs() {
		// Only allow in debug mode
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			wp_send_json_error( array( 'message' => 'Debug mode is not enabled.' ) );
		}
		
		
		check_ajax_referer( 'sg_eventbrite_settings_nonce', 'nonce' );

		try {
			// Get debug.log file path
			$debug_log_path = WP_CONTENT_DIR . '/debug.log';
			
			// Check if debug.log exists
			if ( ! file_exists( $debug_log_path ) ) {
				wp_send_json_error( array( 'message' => 'Debug log file does not exist. Make sure WP_DEBUG_LOG is enabled in wp-config.php.' ) );
			}

			// Read the last 50 lines from debug.log
			$lines = file( $debug_log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
			$recent_lines = array_slice( $lines, -50 );
			
			// Filter for SG Eventbrite related logs
			$sg_logs = array();
			foreach ( $recent_lines as $line ) {
				if ( strpos( $line, 'SG Eventbrite:' ) !== false ) {
					$sg_logs[] = $line;
				}
			}

			wp_send_json_success( array(
				'message' => sprintf( __( 'Retrieved %d recent SG Eventbrite log entries from debug.log', 'sg-eventbrite-course-importer' ), count( $sg_logs ) ),
				'logs' => $sg_logs,
				'total_logs' => count( $sg_logs )
			) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => 'Error retrieving logs: ' . $e->getMessage() ) );
		}
	}


	/**
	 * AJAX handler for testing OAuth status.
	 */
	public function ajax_test_oauth_status() {
		// Only allow in debug mode
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			wp_send_json_error( array( 'message' => 'Debug mode is not enabled.' ) );
		}
		
		check_ajax_referer( 'sg_eventbrite_settings_nonce', 'nonce' );

		$client_id = sanitize_text_field( $_POST['client_id'] ?? '' );
		$client_secret = sanitize_text_field( $_POST['client_secret'] ?? '' );

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			wp_send_json_error( array( 'message' => __( 'Client ID and Client Secret are required', 'sg-eventbrite-course-importer' ) ) );
		}

		$oauth = new \SG\EventbriteCourseImporter\EventbriteOAuth( $client_id, $client_secret );
		
		$is_authenticated = $oauth->is_authenticated();
		$access_token = get_option( 'sg_eventbrite_access_token', '' );
		$refresh_token = get_option( 'sg_eventbrite_refresh_token', '' );
		$token_expires_at = get_option( 'sg_eventbrite_token_expires_at', 0 );
		
		$status_info = array(
			'is_authenticated' => $is_authenticated,
			'has_access_token' => !empty( $access_token ),
			'has_refresh_token' => !empty( $refresh_token ),
			'token_expires_at' => $token_expires_at,
			'token_expired' => $token_expires_at > 0 && time() >= $token_expires_at,
			'access_token_length' => strlen( $access_token ),
		);

		if ( $is_authenticated ) {
			$message = __( 'OAuth is authenticated and ready to use.', 'sg-eventbrite-course-importer' );
		} else {
			$message = __( 'OAuth is not authenticated. Please authorize with Eventbrite first.', 'sg-eventbrite-course-importer' );
		}

		wp_send_json_success( array(
			'message' => $message,
			'status_info' => $status_info
		) );
	}

	/**
	 * AJAX handler for revoking OAuth authorization.
	 */
	public function ajax_revoke_auth() {
		check_ajax_referer( 'sg_eventbrite_settings_nonce', 'nonce' );

		// Clear all OAuth tokens
		delete_option( 'sg_eventbrite_access_token' );
		delete_option( 'sg_eventbrite_refresh_token' );
		delete_option( 'sg_eventbrite_token_expires_at' );

		wp_send_json_success( array( 
			'message' => __( 'Authorization revoked successfully. All tokens have been cleared.', 'sg-eventbrite-course-importer' )
		) );
	}
}