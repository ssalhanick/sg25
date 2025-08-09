<?php
/**
 * Settings Manager Class.
 *
 * Handles the WordPress admin settings for the Humanitix API Importer plugin.
 * Manages plugin configuration, settings pages, and option handling.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Admin;

/**
 * Settings Manager Class.
 *
 * Handles the WordPress admin settings for the Humanitix API Importer plugin.
 * Manages plugin configuration, settings pages, and option handling.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */
class SettingsManager {

	/**
	 * The options group name for WordPress settings API.
	 *
	 * @var string
	 */
	private $options_group = 'humanitix_importer_settings';

	/**
	 * The options name for storing plugin settings.
	 *
	 * @var string
	 */
	private $options_name = 'humanitix_importer_options';

	/**
	 * Whether settings have been initialized.
	 *
	 * @var bool
	 */
	private static $settings_initialized = false;

	/**
	 * Constructor.
	 *
	 * Initializes the settings manager and hooks into WordPress admin.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if ( ! self::$settings_initialized ) {
			add_action( 'admin_init', array( $this, 'init_settings' ) );
			self::$settings_initialized = true;
		}
	}

	/**
	 * Initialize WordPress settings API.
	 *
	 * Registers settings, sections, and fields for the plugin configuration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_settings() {
		register_setting( $this->options_group, $this->options_name, array( $this, 'sanitize_settings' ) );

		add_settings_section(
			'api_settings',
			'API Settings',
			array( $this, 'render_api_section' ),
			'humanitix-importer-settings'
		);

		add_settings_field(
			'api_key',
			'Humanitix API Key',
			array( $this, 'render_api_key_field' ),
			'humanitix-importer-settings',
			'api_settings'
		);

		add_settings_field(
			'org_id',
			'Organization ID',
			array( $this, 'render_org_id_field' ),
			'humanitix-importer-settings',
			'api_settings'
		);

		add_settings_field(
			'api_endpoint',
			'API Endpoint',
			array( $this, 'render_api_endpoint_field' ),
			'humanitix-importer-settings',
			'api_settings'
		);

		add_settings_section(
			'import_settings',
			'Import Settings',
			array( $this, 'render_import_section' ),
			'humanitix-importer-settings'
		);

		add_settings_field(
			'auto_import',
			'Auto Import',
			array( $this, 'render_auto_import_field' ),
			'humanitix-importer-settings',
			'import_settings'
		);

		add_settings_field(
			'import_frequency',
			'Import Frequency',
			array( $this, 'render_frequency_field' ),
			'humanitix-importer-settings',
			'import_settings'
		);

		add_settings_field(
			'import_time',
			'Import Time',
			array( $this, 'render_import_time_field' ),
			'humanitix-importer-settings',
			'import_settings'
		);

		add_settings_field(
			'update_existing',
			'Update Existing Events',
			array( $this, 'render_update_field' ),
			'humanitix-importer-settings',
			'import_settings'
		);

		add_settings_field(
			'create_venues',
			'Create Venues',
			array( $this, 'render_venues_field' ),
			'humanitix-importer-settings',
			'import_settings'
		);

		add_settings_field(
			'create_organizers',
			'Create Organizers',
			array( $this, 'render_organizers_field' ),
			'humanitix-importer-settings',
			'import_settings'
		);

		add_settings_field(
			'import_images',
			'Import Images',
			array( $this, 'render_images_field' ),
			'humanitix-importer-settings',
			'import_settings'
		);

		add_settings_section(
			'logging_settings',
			'Logging Settings',
			array( $this, 'render_logging_section' ),
			'humanitix-importer-settings'
		);

		add_settings_field(
			'log_level',
			'Log Level',
			array( $this, 'render_log_level_field' ),
			'humanitix-importer-settings',
			'logging_settings'
		);

		add_settings_field(
			'log_retention',
			'Log Retention (days)',
			array( $this, 'render_retention_field' ),
			'humanitix-importer-settings',
			'logging_settings'
		);

		add_settings_field(
			'filter_log_noise',
			'Filter Log Noise',
			array( $this, 'render_filter_log_noise_field' ),
			'humanitix-importer-settings',
			'logging_settings'
		);

		add_settings_section(
			'archive_settings',
			'Archive Settings',
			array( $this, 'render_archive_section' ),
			'humanitix-importer-settings'
		);

		add_settings_field(
			'deleted_410_enable',
			'410 for Deleted Content',
			array( $this, 'render_410_enable_field' ),
			'humanitix-importer-settings',
			'archive_settings'
		);

		add_settings_field(
			'deleted_410_ttl_days',
			'410 Retention (days)',
			array( $this, 'render_410_ttl_field' ),
			'humanitix-importer-settings',
			'archive_settings'
		);

		add_settings_field(
			'archive_age_threshold',
			'Archive Age Threshold (years)',
			array( $this, 'render_archive_age_field' ),
			'humanitix-importer-settings',
			'archive_settings'
		);

		add_settings_field(
			'archive_post_status',
			'Archive Post Status',
			array( $this, 'render_archive_status_field' ),
			'humanitix-importer-settings',
			'archive_settings'
		);


	}

	/**
	 * Render the main settings form.
	 *
	 * Outputs the complete settings page HTML with form and API test section.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings_form() {
		?>
		<div class="wrap">
			<h1>Humanitix Importer Settings</h1>
			
			<form method="post" action="options.php">
				<?php
				settings_fields( $this->options_group );
				do_settings_sections( 'humanitix-importer-settings' );
				submit_button();
				?>
			</form>
			
			<div class="card">
				<h2>API Test</h2>
				<p>Test your API connection to ensure it's working properly.</p>
				<button id="test-api" class="button">Test API Connection</button>
				<div id="api-test-result"></div>
				
				<?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) : ?>
					<hr>
					<p><strong>Debug Tools:</strong></p>
					<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=humanitix-debug' ) ); ?>" class="button">View Debug Page</a></p>
					<p><small>For detailed API analysis and troubleshooting.</small></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the API settings section description.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_api_section() {
		echo '<p>Configure your Humanitix API credentials. You\'ll need your API key and organization ID from your Humanitix account.</p>';
	}

	/**
	 * Render the API key input field.
	 *
	 * Outputs a password field for the Humanitix API key.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_api_key_field() {
		$options = get_option( $this->options_name, array() );
		$api_key = $options['api_key'] ?? '';

		// Validate API key if it exists.
		$validation_message = '';
		$validation_class   = '';

		if ( ! empty( $api_key ) ) {
			$api        = new \SG\HumanitixApiImporter\HumanitixAPI( $api_key );
			$validation = $api->validate_api_key_format( $api_key );

			if ( ! $validation['valid'] ) {
				$validation_class    = 'notice-error';
				$validation_message  = '<strong>API Key Format Issues:</strong><br>';
				$validation_message .= '<ul>';
				foreach ( $validation['issues'] as $issue ) {
					$validation_message .= '<li>' . esc_html( $issue ) . '</li>';
				}
				$validation_message .= '</ul>';

				if ( ! empty( $validation['suggestions'] ) ) {
					$validation_message .= '<strong>Suggestions:</strong><br>';
					$validation_message .= '<ul>';
					foreach ( $validation['suggestions'] as $suggestion ) {
						$validation_message .= '<li>' . esc_html( $suggestion ) . '</li>';
					}
					$validation_message .= '</ul>';
				}
			} else {
				$validation_class   = 'notice-success';
				$validation_message = '<strong>API Key Format:</strong> Valid (' . $validation['length'] . ' characters)';
			}
		}
		?>
		<input type="password" 
				name="<?php echo esc_attr( $this->options_name ); ?>[api_key]" 
				value="<?php echo esc_attr( $api_key ); ?>" 
				class="regular-text" />
		<p class="description">Enter your Humanitix API key from the console. The API key will be sent in the x-api-key header.</p>
		
		<?php if ( ! empty( $validation_message ) ) : ?>
			<div class="notice <?php echo esc_attr( $validation_class ); ?> inline">
				<p><?php echo wp_kses_post( $validation_message ); ?></p>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render the organization ID input field.
	 *
	 * Outputs a text field for the organization ID.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_org_id_field() {
		$options = get_option( $this->options_name, array() );
		$org_id  = $options['org_id'] ?? '5ac597aed8fe7c0c0f212e27';
		?>
		<input type="text" 
				name="<?php echo esc_attr( $this->options_name ); ?>[org_id]" 
				value="<?php echo esc_attr( $org_id ); ?>" 
				class="regular-text" 
				placeholder="e.g., 5ac597aed8fe7c0c0f212e27" />
		<p class="description">
			Enter your Humanitix organization ID (optional). This can be used to scope API requests to your organization.<br>
			<strong>Note:</strong> The Humanitix API only requires the x-api-key header. Organization ID is optional and may not be supported by all API endpoints.<br>
			<strong>How to find it:</strong> Log into your Humanitix account and check your organization settings or API documentation.
		</p>
		<?php
	}

	/**
	 * Render the API endpoint input field.
	 *
	 * Outputs a text field for the Humanitix API endpoint.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_api_endpoint_field() {
		$options      = get_option( $this->options_name, array() );
		$api_endpoint = $options['api_endpoint'] ?? '';
		?>
		<input type="text" 
				name="<?php echo esc_attr( $this->options_name ); ?>[api_endpoint]" 
				value="<?php echo esc_attr( $api_endpoint ); ?>" 
				class="regular-text" 
				placeholder="https://api.humanitix.com/v1" />
		<p class="description">
			Enter your Humanitix API endpoint. Leave blank for the default endpoint.<br>
			<strong>For testing:</strong> Use the mock server: <code>https://stoplight.io/mocks/humanitix/humanitix-public-api/259010741</code><br>
			<strong>Note:</strong> The mock server may have different endpoint paths than the live API.
		</p>
		<?php
	}

	/**
	 * Render the import settings section description.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_import_section() {
		echo '<p>Configure import behavior and scheduling.</p>';

		// Refresh the cron schedule to ensure it's up to date.
		$this->refresh_cron_schedule();

		// Display auto import status.
		$options     = get_option( $this->options_name, array() );
		$auto_import = $options['auto_import'] ?? false;

		if ( $auto_import ) {
			$frequency   = $options['import_frequency'] ?? 'daily';
			$import_time = $options['import_time'] ?? '00:00';

			// Check if cron job is scheduled
			$next_scheduled = wp_next_scheduled( 'humanitix_auto_import' );
			
			if ( $next_scheduled ) {
				// Calculate the next run time dynamically to ensure it's always in the future
				$next_run = $this->calculate_next_run_time( $frequency, $import_time );
				
				// If the scheduled time is in the past, use the calculated future time
				if ( $next_scheduled <= time() ) {
					$next_run_display = date( 'Y-m-d H:i:s', $next_run );
					$status_message = 'Enabled (Next run: ' . esc_html( $next_run_display ) . ') - Rescheduled';
				} else {
					$next_run_display = date( 'Y-m-d H:i:s', $next_scheduled );
					$status_message = 'Enabled (Next run: ' . esc_html( $next_run_display ) . ')';
				}
				
				echo '<div class="notice notice-success inline"><p><strong>Auto Import Status:</strong> ' . $status_message . '</p></div>';
				
				// Add debug information if WP_DEBUG is enabled
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					echo '<div class="notice notice-info inline"><p><strong>Debug Info:</strong> Current time: ' . date( 'Y-m-d H:i:s' ) . ' | Scheduled time: ' . date( 'Y-m-d H:i:s', $next_scheduled ) . ' | Calculated time: ' . date( 'Y-m-d H:i:s', $next_run ) . '</p></div>';
				}
			} else {
				echo '<div class="notice notice-warning inline"><p><strong>Auto Import Status:</strong> Enabled but not scheduled. Please save settings to schedule.</p></div>';
			}
		} else {
			echo '<div class="notice notice-info inline"><p><strong>Auto Import Status:</strong> Disabled</p></div>';
		}
	}

	/**
	 * Refresh the cron schedule to ensure it's properly set up.
	 *
	 * This method checks if the auto import is enabled and ensures the cron job is scheduled.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function refresh_cron_schedule() {
		$options = get_option( $this->options_name, array() );
		
		// Only proceed if auto import is enabled.
		if ( empty( $options['auto_import'] ) ) {
			return;
		}

		// Get the plugin instance and call the public reschedule method.
		$plugin = \SG\HumanitixApiImporter\Plugin::get_instance();
		if ( $plugin ) {
			$plugin->reschedule_auto_import();
		}
	}

	/**
	 * Calculate the next run time for the cron job.
	 *
	 * This method replicates the logic from Plugin.php to ensure consistent calculation.
	 *
	 * @since 1.0.0
	 * @param string $frequency The import frequency.
	 * @param string $import_time The time to run (HH:MM format).
	 * @return int Unix timestamp for the next run.
	 */
	private function calculate_next_run_time( $frequency, $import_time ) {
		$time_parts = explode( ':', $import_time );
		$hour       = intval( $time_parts[0] );
		$minute     = intval( $time_parts[1] );

		// Get WordPress timezone.
		$timezone = wp_timezone();

		// Get current time in local timezone.
		$now = new \DateTime( 'now', $timezone );

		// Create a DateTime object for today at the specified time in WordPress timezone.
		$next_run = new \DateTime( 'today ' . $import_time, $timezone );

		// If the time has already passed today, schedule for tomorrow.
		if ( $next_run <= $now ) {
			$next_run = new \DateTime( 'tomorrow ' . $import_time, $timezone );
		}

		// For weekly frequency, adjust to the next occurrence.
		if ( 'weekly' === $frequency ) {
			$next_run = new \DateTime( 'next ' . $next_run->format( 'l' ) . ' ' . $import_time, $timezone );
		}

		// For hourly frequency, calculate the next hour.
		if ( 'hourly' === $frequency ) {
			$next_run = new \DateTime( 'now', $timezone );
			$next_run->setTime( $hour, $minute, 0 );

			// If the time has passed this hour, go to next hour.
			if ( $next_run <= $now ) {
				$next_run->modify( '+1 hour' );
			}
		}

		// Convert to UTC for WordPress cron (WordPress cron uses UTC).
		$next_run->setTimezone( new \DateTimeZone( 'UTC' ) );

		return $next_run->getTimestamp();
	}

	/**
	 * Render the auto import checkbox field.
	 *
	 * Outputs a checkbox to enable/disable automatic imports.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_auto_import_field() {
		$options     = get_option( $this->options_name, array() );
		$auto_import = $options['auto_import'] ?? false;
		?>
		<label>
			<input type="checkbox" 
					name="<?php echo esc_attr( $this->options_name ); ?>[auto_import]" 
					value="1" 
					<?php checked( $auto_import, true ); ?> />
			Enable automatic imports
		</label>
		<?php
	}

	/**
	 * Render the import frequency select field.
	 *
	 * Outputs a dropdown to select import frequency (hourly, daily, weekly).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_frequency_field() {
		$options   = get_option( $this->options_name, array() );
		$frequency = $options['import_frequency'] ?? 'daily';
		?>
		<select name="<?php echo esc_attr( $this->options_name ); ?>[import_frequency]">
			<option value="hourly" <?php selected( $frequency, 'hourly' ); ?>>Hourly</option>
			<option value="daily" <?php selected( $frequency, 'daily' ); ?>>Daily</option>
			<option value="weekly" <?php selected( $frequency, 'weekly' ); ?>>Weekly</option>
		</select>
		<p class="description">Select how often the automatic import should run.</p>
		<?php
	}

	/**
	 * Render the import time input field.
	 *
	 * Outputs a time input field for scheduling the import time.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_import_time_field() {
		$options     = get_option( $this->options_name, array() );
		$import_time = $options['import_time'] ?? '00:00';
		?>
		<input type="time" 
				name="<?php echo esc_attr( $this->options_name ); ?>[import_time]" 
				value="<?php echo esc_attr( $import_time ); ?>" />
		<p class="description">
			Select the time when the automatic import should run. This time is in your WordPress timezone.<br>
			<strong>Note:</strong> For hourly imports, this time will be used as the starting point.
		</p>
		<?php
	}

	/**
	 * Render the update existing events checkbox field.
	 *
	 * Outputs a checkbox to enable/disable updating existing events.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_update_field() {
		$options         = get_option( $this->options_name, array() );
		$update_existing = $options['update_existing'] ?? true;
		?>
		<label>
			<input type="checkbox" 
					name="<?php echo esc_attr( $this->options_name ); ?>[update_existing]" 
					value="1" 
					<?php checked( $update_existing, true ); ?> />
			Update existing events when re-importing
		</label>
		<?php
	}

	/**
	 * Render the create venues checkbox field.
	 *
	 * Outputs a checkbox to enable/disable creating new venues.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_venues_field() {
		$options       = get_option( $this->options_name, array() );
		$create_venues = $options['create_venues'] ?? true;
		?>
		<label>
			<input type="checkbox" 
					name="<?php echo esc_attr( $this->options_name ); ?>[create_venues]" 
					value="1" 
					<?php checked( $create_venues, true ); ?> />
			Create new venues if they don't exist
		</label>
		<?php
	}

	/**
	 * Render the create organizers checkbox field.
	 *
	 * Outputs a checkbox to enable/disable creating new organizers.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_organizers_field() {
		$options           = get_option( $this->options_name, array() );
		$create_organizers = $options['create_organizers'] ?? true;
		?>
		<label>
			<input type="checkbox" 
					name="<?php echo esc_attr( $this->options_name ); ?>[create_organizers]" 
					value="1" 
					<?php checked( $create_organizers, true ); ?> />
			Create new organizers if they don't exist
		</label>
		<?php
	}

	/**
	 * Render the import images checkbox field.
	 *
	 * Outputs a checkbox to enable/disable importing event images.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_images_field() {
		$options       = get_option( $this->options_name, array() );
		$import_images = $options['import_images'] ?? true;
		?>
		<label>
			<input type="checkbox" 
					name="<?php echo esc_attr( $this->options_name ); ?>[import_images]" 
					value="1" 
					<?php checked( $import_images, true ); ?> />
			Import event images as featured images
		</label>
		<?php
	}

	/**
	 * Render the logging settings section description.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_logging_section() {
		echo '<p>Configure logging behavior and retention.</p>';
	}

	/**
	 * Render the log level select field.
	 *
	 * Outputs a dropdown to select logging level (debug, info, warning, error).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_log_level_field() {
		$options   = get_option( $this->options_name, array() );
		$log_level = $options['log_level'] ?? 'info';
		?>
		<select name="<?php echo esc_attr( $this->options_name ); ?>[log_level]">
			<option value="debug" <?php selected( $log_level, 'debug' ); ?>>Debug</option>
			<option value="info" <?php selected( $log_level, 'info' ); ?>>Info</option>
			<option value="warning" <?php selected( $log_level, 'warning' ); ?>>Warning</option>
			<option value="error" <?php selected( $log_level, 'error' ); ?>>Error</option>
		</select>
		<?php
	}

	/**
	 * Render the filter log noise field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_filter_log_noise_field() {
		$options = get_option( $this->options_name, array() );
		$filter_noise = isset( $options['filter_log_noise'] ) ? $options['filter_log_noise'] : true;

		?>
		<input type="checkbox" 
			   name="<?php echo esc_attr( $this->options_name ); ?>[filter_log_noise]" 
			   value="1" 
			   <?php checked( $filter_noise ); ?> />
		<p class="description">
			Filter out template assets, hooks initiations, and other noise from logs to reduce database size and improve performance.
		</p>
		<?php
	}

	/**
	 * Render the log retention number field.
	 *
	 * Outputs a number input to set log retention period in days.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_retention_field() {
		$options   = get_option( $this->options_name, array() );
		$retention = $options['log_retention'] ?? 30;
		?>
		<input type="number" 
				name="<?php echo esc_attr( $this->options_name ); ?>[log_retention]" 
				value="<?php echo esc_attr( $retention ); ?>" 
				min="1" 
				max="365" />
		<p class="description">Number of days to keep log entries.</p>
		<?php
	}

	/**
	 * Sanitize and validate settings input.
	 *
	 * Processes and validates all form inputs before saving to database.
	 *
	 * @since 1.0.0
	 * @param array $input The raw input array from the form.
	 * @return array The sanitized settings array.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		$sanitized['api_key']           = sanitize_text_field( $input['api_key'] ?? '' );
		$sanitized['org_id']            = sanitize_text_field( $input['org_id'] ?? '' );
		$sanitized['api_endpoint']      = sanitize_text_field( $input['api_endpoint'] ?? '' );
		$sanitized['auto_import']       = isset( $input['auto_import'] );
		$sanitized['import_frequency']  = sanitize_text_field( $input['import_frequency'] ?? 'daily' );
		$sanitized['import_time']       = sanitize_text_field( $input['import_time'] ?? '00:00' );
		$sanitized['update_existing']   = isset( $input['update_existing'] );
		$sanitized['create_venues']     = isset( $input['create_venues'] );
		$sanitized['create_organizers'] = isset( $input['create_organizers'] );
		$sanitized['import_images']     = isset( $input['import_images'] );
		$sanitized['log_level']         = sanitize_text_field( $input['log_level'] ?? 'info' );
		$sanitized['log_retention']     = absint( $input['log_retention'] ?? 30 );

		// Archive settings
		$sanitized['archive_enabled']        = isset( $input['archive_enabled'] );
		$sanitized['archive_age_threshold']  = floatval( $input['archive_age_threshold'] ?? 2 );
		$sanitized['archive_frequency']      = sanitize_text_field( $input['archive_frequency'] ?? 'monthly' );
		$sanitized['archive_post_status']    = sanitize_text_field( $input['archive_post_status'] ?? 'archived' );
		$sanitized['archive_batch_size']     = absint( $input['archive_batch_size'] ?? 50 );
		$sanitized['archive_notifications']  = isset( $input['archive_notifications'] );
		$sanitized['archive_dry_run']        = isset( $input['archive_dry_run'] );

		// 410 settings for deleted events
		$sanitized['deleted_410_enable']   = isset( $input['deleted_410_enable'] );
		$sanitized['deleted_410_ttl_days'] = max( 0, absint( $input['deleted_410_ttl_days'] ?? 365 ) );



		// Log settings
		$sanitized['log_level'] = sanitize_text_field( $input['log_level'] ?? 'info' );
		$sanitized['filter_log_noise'] = isset( $input['filter_log_noise'] );

		return $sanitized;
	}

	/**
	 * Get a specific setting value.
	 *
	 * Retrieves a single setting value from the options array.
	 *
	 * @since 1.0.0
	 * @param string $key The setting key to retrieve.
	 * @param mixed  $default_settings The default value if setting doesn't exist.
	 * @return mixed The setting value or default.
	 */
	public function get_setting( $key, $default_settings = null ) {
		$options = get_option( $this->options_name, array() );
		return $options[ $key ] ?? $default_settings;
	}

	/**
	 * Render the archive settings section description.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_archive_section() {
		echo '<p>Configure archive settings for the Quick Archive Controls. These settings determine how events are archived when using the manual archive interface. You can also enable automatic 410 (Gone) responses for deleted events.</p>';
	}



	/**
	 * Render the archive age threshold number field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_archive_age_field() {
		$options = get_option( $this->options_name, array() );
		$age_threshold = $options['archive_age_threshold'] ?? 2;
		?>
		<input type="number" 
				name="<?php echo esc_attr( $this->options_name ); ?>[archive_age_threshold]" 
				value="<?php echo esc_attr( $age_threshold ); ?>" 
				min="0.1" 
				max="10" 
				step="0.1" />
		<p class="description">Events older than this number of years will be archived. Use decimal values like 0.5 for 6 months, 0.25 for 3 months, etc.</p>
		<?php
	}

	/**
	 * Render the enable 410 checkbox field.
	 */
	public function render_410_enable_field() {
		$options = get_option( $this->options_name, array() );
		$enabled = isset( $options['deleted_410_enable'] ) ? (bool) $options['deleted_410_enable'] : true;
		?>
		<label>
			<input type="checkbox"
				   name="<?php echo esc_attr( $this->options_name ); ?>[deleted_410_enable]"
				   value="1" <?php checked( $enabled, true ); ?> />
			Enable 410 (Gone) for deleted events
		</label>
		<p class="description">When enabled, requests to previously deleted event URLs will return HTTP 410 Gone instead of 404 Not Found.</p>
		<?php
	}

	/**
	 * Render the 410 TTL days field.
	 */
	public function render_410_ttl_field() {
		$options  = get_option( $this->options_name, array() );
		$ttl_days = isset( $options['deleted_410_ttl_days'] ) ? absint( $options['deleted_410_ttl_days'] ) : 365;
		?>
		<input type="number"
			   name="<?php echo esc_attr( $this->options_name ); ?>[deleted_410_ttl_days]"
			   value="<?php echo esc_attr( $ttl_days ); ?>"
			   min="0" max="1095" />
		<p class="description">Number of days to keep returning 410 for deleted event URLs. Set to 0 to never expire.</p>
		<?php
	}



	/**
	 * Render the archive post status select field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_archive_status_field() {
		$options = get_option( $this->options_name, array() );
		$post_status = $options['archive_post_status'] ?? 'archived';
		?>
		<select name="<?php echo esc_attr( $this->options_name ); ?>[archive_post_status]">
			<option value="archived" <?php selected( $post_status, 'archived' ); ?>>Archived (Custom Status)</option>
			<option value="draft" <?php selected( $post_status, 'draft' ); ?>>Draft</option>
			<option value="private" <?php selected( $post_status, 'private' ); ?>>Private</option>
		</select>
		<p class="description">The post status to assign to archived events.</p>
		<?php
	}




}
