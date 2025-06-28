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
	 * Constructor.
	 *
	 * Initializes the settings manager and hooks into WordPress admin.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'init_settings' ) );
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
		echo '<p>Configure your Humanitix API settings.</p>';
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
		?>
		<input type="password" 
				name="<?php echo esc_attr( $this->options_name ); ?>[api_key]" 
				value="<?php echo esc_attr( $api_key ); ?>" 
				class="regular-text" />
		<p class="description">Enter your Humanitix API key from the console.</p>
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
		$sanitized['auto_import']       = isset( $input['auto_import'] );
		$sanitized['import_frequency']  = sanitize_text_field( $input['import_frequency'] ?? 'daily' );
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
