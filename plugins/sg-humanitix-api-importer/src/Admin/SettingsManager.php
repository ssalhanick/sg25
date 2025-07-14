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
		$org_id  = $options['org_id'] ?? '';
		?>
		<input type="text" 
				name="<?php echo esc_attr( $this->options_name ); ?>[org_id]" 
				value="<?php echo esc_attr( $org_id ); ?>" 
				class="regular-text" 
				placeholder="e.g., org_1234567890abcdef" />
		<p class="description">
			Enter your Humanitix organization ID. This is required to scope API requests to your organization.<br>
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

		// Display auto import status.
		$options     = get_option( $this->options_name, array() );
		$auto_import = $options['auto_import'] ?? false;

		if ( $auto_import ) {
			$next_scheduled = wp_next_scheduled( 'humanitix_auto_import' );
			$frequency      = $options['import_frequency'] ?? 'daily';
			$import_time    = $options['import_time'] ?? '00:00';

			if ( $next_scheduled ) {
				$next_run = date( 'Y-m-d H:i:s', $next_scheduled );
				echo '<div class="notice notice-success inline"><p><strong>Auto Import Status:</strong> Enabled (Next run: ' . esc_html( $next_run ) . ')</p></div>';
			} else {
				echo '<div class="notice notice-warning inline"><p><strong>Auto Import Status:</strong> Enabled but not scheduled. Please save settings to schedule.</p></div>';
			}
		} else {
			echo '<div class="notice notice-info inline"><p><strong>Auto Import Status:</strong> Disabled</p></div>';
		}
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
}
