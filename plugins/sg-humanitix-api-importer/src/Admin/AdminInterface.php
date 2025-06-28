<?php
/**
 * Admin Interface Class.
 *
 * Handles the WordPress admin interface for the Humanitix API Importer plugin.
 * Provides admin pages for importing events, viewing logs, and managing settings.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Admin;

use SG\HumanitixApiImporter\Importer\EventsImporter;

/**
 * Admin Interface Class.
 *
 * Handles the WordPress admin interface for the Humanitix API Importer plugin.
 * Provides admin pages for importing events, viewing logs, and managing settings.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */
class AdminInterface {

	/**
	 * The events importer instance.
	 *
	 * @var EventsImporter
	 */
	private $importer;

	/**
	 * The settings manager instance.
	 *
	 * @var SettingsManager
	 */
	private $settings;

	/**
	 * The logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param EventsImporter  $importer The events importer instance.
	 * @param SettingsManager $settings The settings manager instance.
	 */
	public function __construct( EventsImporter $importer, SettingsManager $settings ) {
		$this->importer = $importer;
		$this->settings = $settings;
		$this->logger   = new Logger();

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_import_events', array( $this, 'handle_import_ajax' ) );
		add_action( 'wp_ajax_get_import_logs', array( $this, 'handle_logs_ajax' ) );
		add_action( 'wp_ajax_get_import_stats', array( $this, 'handle_stats_ajax' ) );
	}

	/**
	 * Add admin menu pages.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_admin_menu() {
		add_menu_page(
			'Humanitix Importer',
			'Humanitix Importer',
			'manage_options',
			'humanitix-importer',
			array( $this, 'render_main_page' ),
			'dashicons-calendar-alt',
			30
		);

		add_submenu_page(
			'humanitix-importer',
			'Settings',
			'Settings',
			'manage_options',
			'humanitix-importer-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'humanitix-importer',
			'Import Logs',
			'Import Logs',
			'manage_options',
			'humanitix-importer-logs',
			array( $this, 'render_logs_page' )
		);

		add_submenu_page(
			'humanitix-importer',
			'Dashboard',
			'Dashboard',
			'manage_options',
			'humanitix-importer-dashboard',
			array( $this, 'render_dashboard_page' )
		);
	}

	/**
	 * Render the main admin page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_main_page() {
		?>
		<div class="wrap">
			<h1>Humanitix Event Importer</h1>
			
			<div class="card">
				<h2>Quick Import</h2>
				<p>Import events from Humanitix to The Events Calendar.</p>
				
				<div class="import-controls">
					<button id="start-import" class="button button-primary">Start Import</button>
					<button id="stop-import" class="button button-secondary" style="display:none;">Stop Import</button>
					<span id="import-status"></span>
				</div>
				
				<div id="import-progress" style="display:none;">
					<div class="progress-bar">
						<div class="progress-fill"></div>
					</div>
					<div class="progress-text">Processing events...</div>
				</div>
				
				<div id="import-results" style="display:none;">
					<h3>Import Results</h3>
					<div id="results-content"></div>
				</div>
			</div>
			
			<div class="card">
				<h2>Recent Activity</h2>
				<div id="recent-activity">
					<?php $this->render_recent_activity(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings_page() {
		$this->settings->render_settings_form();
	}

	/**
	 * Render the logs page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_logs_page() {
		?>
		<div class="wrap">
			<h1>Import Logs</h1>
			
			<div class="log-filters">
				<select id="log-level">
					<option value="">All Levels</option>
					<option value="info">Info</option>
					<option value="warning">Warning</option>
					<option value="error">Error</option>
				</select>
				
				<input type="date" id="log-date" />
				<button id="filter-logs" class="button">Filter</button>
				<button id="export-logs" class="button">Export Logs</button>
			</div>
			
			<div id="logs-container">
				<?php $this->render_logs_table(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the dashboard page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_dashboard_page() {
		$stats = $this->get_dashboard_stats();
		?>
		<div class="wrap">
			<h1>Import Dashboard</h1>
			
			<div class="dashboard-stats">
				<div class="stat-card">
					<h3>Total Events Imported</h3>
					<div class="stat-number"><?php echo esc_html( $stats['total_events'] ); ?></div>
				</div>
				
				<div class="stat-card">
					<h3>Total Venues</h3>
					<div class="stat-number"><?php echo esc_html( $stats['total_venues'] ); ?></div>
				</div>
				
				<div class="stat-card">
					<h3>Total Organizers</h3>
					<div class="stat-number"><?php echo esc_html( $stats['total_organizers'] ); ?></div>
				</div>
				
				<div class="stat-card">
					<h3>Last Import</h3>
					<div class="stat-number"><?php echo esc_html( $stats['last_import'] ); ?></div>
				</div>
			</div>
			
			<div class="dashboard-charts">
				<div class="chart-container">
					<h3>Import Activity (Last 30 Days)</h3>
					<canvas id="import-chart"></canvas>
				</div>
				
				<div class="chart-container">
					<h3>Error Rate</h3>
					<canvas id="error-chart"></canvas>
				</div>
			</div>
			
			<div class="dashboard-recent">
				<h3>Recent Imports</h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Date</th>
							<th>Events Imported</th>
							<th>Errors</th>
							<th>Duration</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $stats['recent_imports'] as $import ) : ?>
						<tr>
							<td><?php echo esc_html( $import['date'] ); ?></td>
							<td><?php echo esc_html( $import['events_imported'] ); ?></td>
							<td><?php echo esc_html( $import['errors'] ); ?></td>
							<td><?php echo esc_html( $import['duration'] ); ?></td>
							<td><span class="status-<?php echo esc_html( $import['status'] ); ?>"><?php echo esc_html( $import['status'] ); ?></span></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Handling AJAX Imports
	 */
	public function handle_import_ajax() {
		check_ajax_referer( 'humanitix_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$start_time = microtime( true );

		try {
			$result = $this->importer->import_events();

			$end_time = microtime( true );
			$duration = round( $end_time - $start_time, 2 );

			// Log the import.
			$this->logger->log(
				'import',
				'Import completed',
				array(
					'events_imported' => $result['imported'],
					'errors'          => count( $result['errors'] ),
					'duration'        => $duration,
					'status'          => $result['success'] ? 'success' : 'error',
				)
			);

			wp_send_json_success(
				array(
					'message'        => $result['success'] ? 'Import completed successfully' : $result['error'],
					'imported_count' => $result['imported'],
					'errors'         => $result['errors'],
					'duration'       => $duration,
				)
			);

		} catch ( \Exception $e ) {
			$this->logger->log( 'error', 'Import failed: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Handling AJAX Logs
	 */
	public function handle_logs_ajax() {
		check_ajax_referer( 'humanitix_logs_nonce', 'nonce' );

		$level = isset( $_POST['level'] ) ? sanitize_text_field( wp_unslash( $_POST['level'] ) ) : '';
		$date  = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';

		$logs = $this->logger->get_logs( $level, $date );
		wp_send_json_success( $logs );
	}

	/**
	 * Handling AJAX Statistics
	 */
	public function handle_stats_ajax() {
		check_ajax_referer( 'humanitix_stats_nonce', 'nonce' );

		$stats = $this->get_dashboard_stats();
		wp_send_json_success( $stats );
	}

	/**
	 * Getting Statistics for Dashboard.
	 */
	private function get_dashboard_stats() {
		global $wpdb;

		// Get total events imported.
		$total_events = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_humanitix_event_id'"
		);

		// Get total venues.
		$total_venues = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'tribe_venue' AND post_status = 'publish'"
		);

		// Get total organizers.
		$total_organizers = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'tribe_organizer' AND post_status = 'publish'"
		);

		// Get last import.
		$last_import = $wpdb->get_var(
			"SELECT meta_value FROM {$wpdb->postmeta} 
             WHERE meta_key = '_humanitix_last_import' 
             ORDER BY meta_value DESC LIMIT 1"
		);

		// Get recent imports from logs.
		$recent_imports = $this->logger->get_recent_imports( 10 );

		return array(
			'total_events'     => $total_events,
			'total_venues'     => $total_venues,
			'total_organizers' => $total_organizers,
			'last_import'      => $last_import ? gmdate( 'Y-m-d H:i:s', strtotime( $last_import ) ) : 'Never',
			'recent_imports'   => $recent_imports,
		);
	}

	/**
	 * Enqueue admin scripts and styles for the plugin.
	 *
	 * @since 1.0.0
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( strpos( $hook, 'humanitix-importer' ) === false ) {
			return;
		}

		wp_enqueue_script(
			'humanitix-admin',
			SG_HUMANITIX_API_IMPORTER_PLUGIN_URL . '/assets/js/admin.js',
			array( 'jquery' ),
			SG_HUMANITIX_API_IMPORTER_PLUGIN_VERSION,
			true
		);

		wp_enqueue_style(
			'humanitix-admin',
			SG_HUMANITIX_API_IMPORTER_PLUGIN_URL . '/assets/css/admin.css',
			array(),
			SG_HUMANITIX_API_IMPORTER_PLUGIN_VERSION
		);

		wp_localize_script(
			'humanitix-admin',
			'humanitixAdmin',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'humanitix_import_nonce' ),
				'logsNonce'  => wp_create_nonce( 'humanitix_logs_nonce' ),
				'statsNonce' => wp_create_nonce( 'humanitix_stats_nonce' ),
			)
		);
	}
}
