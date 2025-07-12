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
	 * @var EventsImporter|null
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
<<<<<<< Updated upstream
=======
	 * The security handler instance.
	 *
	 * @var \SG\HumanitixApiImporter\Security\AjaxSecurityHandler|null
	 */
	private $security_handler;

	/**
>>>>>>> Stashed changes
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param mixed           $importer The importer instance.
	 * @param SettingsManager $settings The settings manager instance.
	 */
	public function __construct( $importer = null, SettingsManager $settings = null ) {
<<<<<<< Updated upstream
		error_log( 'Humanitix Admin: AdminInterface constructor called' );

		$this->importer = $importer;
		$this->settings = $settings ?? new SettingsManager();
		$this->logger   = new Logger();

		$this->init_hooks();

		error_log( 'Humanitix Admin: AdminInterface constructor completed' );
=======
		$this->importer = $importer;
		$this->settings = $settings ?? new SettingsManager();
		$this->logger   = new Logger();
		$this->security_handler = new \SG\HumanitixApiImporter\Security\AjaxSecurityHandler();

		$this->init_hooks();
>>>>>>> Stashed changes
	}

	/**
	 * Set the importer instance.
	 *
	 * @param EventsImporter $importer The events importer instance.
	 * @return void
	 */
	public function set_importer( $importer ) {
		$this->importer = $importer;
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks() {
<<<<<<< Updated upstream
		error_log( 'Humanitix Admin: Setting up hooks' );

=======
>>>>>>> Stashed changes
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_import_events', array( $this, 'handle_import_ajax' ) );
		add_action( 'wp_ajax_get_import_logs', array( $this, 'handle_logs_ajax' ) );
		add_action( 'wp_ajax_get_import_stats', array( $this, 'handle_stats_ajax' ) );
		add_action( 'wp_ajax_test_api_connection', array( $this, 'handle_api_test_ajax' ) );
<<<<<<< Updated upstream

		// Add debugging for admin_enqueue_scripts hook.
		add_action(
			'admin_enqueue_scripts',
			function ( $hook ) {
				error_log( 'Humanitix Admin: admin_enqueue_scripts hook fired with: ' . $hook );
			},
			5
		); // Lower priority to run before our function.

		error_log( 'Humanitix Admin: Hooks registered' );
=======
>>>>>>> Stashed changes
	}

	/**
	 * Add admin menu pages.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_admin_menu() {
<<<<<<< Updated upstream
		error_log( 'Humanitix Admin: Adding admin menu' );

=======
>>>>>>> Stashed changes
		add_menu_page(
			'Humanitix Importer',
			'Humanitix',
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
			'humanitix-settings',
			array( $this, 'render_settings_page' )
		);

		// Only show debug page to plugin authors or when debug is enabled.
		if ( $this->is_debug_enabled() ) {
			add_submenu_page(
				'humanitix-importer',
				'Debug',
				'Debug',
				'manage_options',
				'humanitix-debug',
				array( $this, 'render_debug_page' )
			);
		}

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
<<<<<<< Updated upstream

		error_log( 'Humanitix Admin: Admin menu added' );
=======
>>>>>>> Stashed changes
	}

	/**
	 * Check if debug mode is enabled.
	 *
	 * @return bool Whether debug mode is enabled.
	 */
	private function is_debug_enabled() {
<<<<<<< Updated upstream
		// Check for debug constant.
		if ( defined( 'HUMANITIX_DEBUG' ) && HUMANITIX_DEBUG ) {
			return true;
		}

		// Check for WordPress debug mode.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return true;
		}

		// Check for specific user capabilities (plugin authors).
		if ( current_user_can( 'manage_network_options' ) ) {
			return true;
		}

		// Check for specific user roles (administrators with specific capabilities).
		$user = wp_get_current_user();
		if ( in_array( 'administrator', $user->roles, true ) && current_user_can( 'edit_plugins' ) ) {
			return true;
		}

		// Check for specific user IDs (you can add your user ID here).
		$debug_user_ids = array(
			// Add your WordPress user ID here for development.
			// 1, // Example user ID.
		);

		if ( in_array( $user->ID, $debug_user_ids, true ) ) {
			return true;
		}

		return false;
=======
		// Only show debug when HUMANITIX_DEBUG constant is defined and true.
		return defined( 'HUMANITIX_DEBUG' ) && HUMANITIX_DEBUG;
>>>>>>> Stashed changes
	}

	/**
	 * Render the main admin page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_main_page() {
		// Check if API credentials are configured.
		$options = get_option( 'humanitix_importer_options', array() );
		$api_key = $options['api_key'] ?? '';
		$org_id  = $options['org_id'] ?? '';

		$missing_credentials = array();
		if ( empty( $api_key ) ) {
			$missing_credentials[] = 'API key';
		}
		if ( empty( $org_id ) ) {
			$missing_credentials[] = 'Organization ID';
		}

		$is_configured = empty( $missing_credentials );
<<<<<<< Updated upstream
=======
		$is_debug_mode = $this->is_debug_enabled();
>>>>>>> Stashed changes
		?>
		<div class="wrap">
			<h1>Humanitix Event Importer</h1>
			
			<div class="card">
				<h2>Quick Import</h2>
				<p>Import events from Humanitix to The Events Calendar.</p>
				
				<?php if ( ! $is_configured ) : ?>
					<div class="notice notice-warning">
						<p><strong>API Not Configured:</strong> Please set up your Humanitix <?php echo esc_html( implode( ' and ', $missing_credentials ) ); ?> in the <a href="<?php echo esc_url( admin_url( 'admin.php?page=humanitix-importer-settings' ) ); ?>">Settings</a> page to start importing events.</p>
					</div>
				<?php endif; ?>
				
<<<<<<< Updated upstream
=======
				<?php if ( $is_debug_mode ) : ?>
					<div class="debug-options" style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-left: 4px solid #0073aa;">
						<h4 style="margin-top: 0;">Debug Mode Options</h4>
						<label for="import-limit" style="display: inline-block; margin-right: 10px;">
							<strong>Limit Import to:</strong>
						</label>
						<select id="import-limit" name="import_limit" style="margin-right: 10px;">
							<option value="">All Events (No Limit)</option>
							<option value="1">1 Event</option>
							<option value="5">5 Events</option>
							<option value="10">10 Events</option>
							<option value="25">25 Events</option>
							<option value="50">50 Events</option>
							<option value="100">100 Events</option>
						</select>
						<span style="color: #666; font-size: 12px;">
							<i class="dashicons dashicons-info"></i> 
							This option is only available in debug mode to help with testing.
						</span>
					</div>
				<?php endif; ?>
				
>>>>>>> Stashed changes
				<div class="import-controls">
					<button id="start-import" class="button button-primary" <?php echo ! $is_configured ? 'disabled' : ''; ?>>
						<?php echo $is_configured ? 'Start Import' : 'API Not Configured'; ?>
					</button>
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
	 * Render recent activity section.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_recent_activity() {
		$recent_logs = $this->logger->get_recent_imports( 5 );

		if ( empty( $recent_logs ) ) {
			echo '<p>No recent activity found.</p>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>Date</th><th>Message</th><th>Level</th></tr></thead>';
		echo '<tbody>';

		foreach ( $recent_logs as $log ) {
			echo '<tr>';
			echo '<td>' . esc_html( $log->created_at ) . '</td>';
			echo '<td>' . esc_html( $log->message ) . '</td>';
			echo '<td><span class="log-level-' . esc_attr( $log->level ) . '">' . esc_html( $log->level ) . '</span></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
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
	 * Render logs table.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_logs_table() {
		$logs = $this->logger->get_logs();

		if ( empty( $logs ) ) {
			echo '<p>No logs found.</p>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>Date</th><th>Level</th><th>Message</th><th>Context</th></tr></thead>';
		echo '<tbody>';

		foreach ( $logs as $log ) {
			echo '<tr>';
			echo '<td>' . esc_html( $log->created_at ) . '</td>';
			echo '<td><span class="log-level-' . esc_attr( $log->level ) . '">' . esc_html( $log->level ) . '</span></td>';
			echo '<td>' . esc_html( $log->message ) . '</td>';
			echo '<td>' . esc_html( $log->context ? wp_json_encode( json_decode( $log->context ) ) : '' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
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
		<style>
		.button-link {
			background: none;
			border: none;
			padding: 0;
			margin: 0;
			font: inherit;
			cursor: pointer;
			text-decoration: none;
			color: #dc3232;
			font-weight: bold;
		}
		.button-link:hover {
			color: #a00;
			text-decoration: underline;
		}
		.button-link:focus {
			outline: none;
			text-decoration: underline;
		}
		.error-toggle.expanded {
			color: #a00;
			text-decoration: underline;
		}
		</style>
		
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
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
							<?php
							// Parse context to get import details.
							$context         = json_decode( $import->context, true );
							$events_imported = $context['events_imported'] ?? 0;
							$errors          = $context['errors'] ?? 0;
							$error_messages  = $context['error_messages'] ?? array();
							$duration        = $context['duration'] ?? 0;
							$status          = $context['status'] ?? 'unknown';
							$import_id       = 'import-' . $import->id;
							?>
						<tr>
							<td><?php echo esc_html( gmdate( 'M j, Y g:i A', strtotime( $import->created_at ) ) ); ?></td>
							<td><?php echo esc_html( $events_imported ); ?></td>
							<td>
								<?php if ( $errors > 0 ) : ?>
									<button type="button" class="error-toggle button-link" data-target="<?php echo esc_attr( $import_id ); ?>">
										<?php echo esc_html( $errors ); ?> error<?php echo 1 !== $errors ? 's' : ''; ?>
									</button>
								<?php else : ?>
									<?php echo esc_html( $errors ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $duration . 's' ); ?></td>
							<td><span class="status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span></td>
						</tr>
							<?php if ( $errors > 0 && ! empty( $error_messages ) ) : ?>
						<tr class="error-details" id="<?php echo esc_attr( $import_id ); ?>" style="display: none;">
							<td colspan="5">
								<div class="error-messages">
									<h4>Error Details:</h4>
									<ul>
										<?php foreach ( $error_messages as $error ) : ?>
											<li><?php echo esc_html( $error ); ?></li>
										<?php endforeach; ?>
									</ul>
								</div>
							</td>
						</tr>
						<?php endif; ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="dashboard-connection-tests">
				<h3>API Connection Tests</h3>
				<?php
				$connection_stats = $this->logger->get_connection_test_stats( 30 );
				$recent_tests     = $this->logger->get_recent_connection_tests( 5 );
				?>
				
				<div class="connection-stats">
					<div class="stat-card">
						<h4>Success Rate (30 days)</h4>
						<div class="stat-number <?php echo $connection_stats->success_rate >= 90 ? 'success' : ( $connection_stats->success_rate >= 70 ? 'warning' : 'error' ); ?>">
							<?php echo esc_html( $connection_stats->success_rate ); ?>%
						</div>
					</div>
					
					<div class="stat-card">
						<h4>Total Tests (30 days)</h4>
						<div class="stat-number"><?php echo esc_html( $connection_stats->total_tests ); ?></div>
					</div>
					
					<div class="stat-card">
						<h4>Last Test</h4>
						<div class="stat-number"><?php echo $connection_stats->last_test ? esc_html( gmdate( 'M j, Y g:i A', strtotime( $connection_stats->last_test ) ) ) : 'Never'; ?></div>
					</div>
				</div>

				<div class="recent-connection-tests">
					<h4>Recent Connection Tests</h4>
					<?php if ( empty( $recent_tests ) ) : ?>
						<p>No connection tests found.</p>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th>Date</th>
									<th>Status</th>
									<th>Message</th>
									<th>Details</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $recent_tests as $test ) : ?>
									<?php
									$context = json_decode( $test->context, true );
									$details = '';
									if ( $context ) {
										if ( isset( $context['endpoint'] ) ) {
											$details .= 'Endpoint: ' . esc_html( $context['endpoint'] ) . '<br>';
										}
										if ( isset( $context['status_code'] ) ) {
											$details .= 'Status: ' . esc_html( $context['status_code'] ) . '<br>';
										}
										if ( isset( $context['is_mock_server'] ) && $context['is_mock_server'] ) {
											$details .= 'Mock Server: Yes';
										}
									}
									?>
									<tr>
										<td><?php echo esc_html( gmdate( 'M j, Y g:i A', strtotime( $test->created_at ) ) ); ?></td>
										<td>
											<span class="status-<?php echo esc_attr( $test->status ); ?>">
												<?php echo esc_html( ucfirst( $test->status ) ); ?>
											</span>
										</td>
										<td><?php echo esc_html( $test->message ); ?></td>
										<td><?php echo wp_kses_post( $details ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the debug page.
	 */
	public function render_debug_page() {
		// Security check - ensure only authorized users can access debug page.
		if ( ! $this->is_debug_enabled() ) {
			wp_die( 'Access denied. Debug mode is not enabled for your user account.', 'Access Denied', array( 'response' => 403 ) );
		}

		// Additional capability check.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.', 'Access Denied', array( 'response' => 403 ) );
		}

		?>
		<div class="wrap">
			<h1>Humanitix Import Debug</h1>
			
			<div class="notice notice-warning">
				<p><strong>Debug Mode Active:</strong> This page contains sensitive information and should only be accessed by plugin authors and developers.</p>
			</div>
			
			<div class="card">
				<h2>WordPress Debug Settings</h2>
				<table class="form-table">
					<tr>
						<th>WP_DEBUG:</th>
						<td><?php echo defined( 'WP_DEBUG' ) && WP_DEBUG ? '<span style="color: green;">Enabled</span>' : '<span style="color: red;">Disabled</span>'; ?></td>
					</tr>
					<tr>
						<th>WP_DEBUG_LOG:</th>
						<td><?php echo defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ? '<span style="color: green;">Enabled</span>' : '<span style="color: red;">Disabled</span>'; ?></td>
					</tr>
					<tr>
						<th>WP_DEBUG_DISPLAY:</th>
						<td><?php echo defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ? '<span style="color: green;">Enabled</span>' : '<span style="color: red;">Disabled</span>'; ?></td>
					</tr>
					<tr>
						<th>HUMANITIX_DEBUG:</th>
						<td><?php echo defined( 'HUMANITIX_DEBUG' ) && HUMANITIX_DEBUG ? '<span style="color: green;">Enabled</span>' : '<span style="color: red;">Disabled</span>'; ?></td>
					</tr>
				</table>
			</div>

			<div class="card">
				<h2>Plugin Settings</h2>
				<?php
				$options      = get_option( 'humanitix_importer_options', array() );
				$api_key      = $options['api_key'] ?? '';
				$api_endpoint = $options['api_endpoint'] ?? '';
				$org_id       = $options['org_id'] ?? '';
				?>
				<table class="form-table">
					<tr>
						<th>API Key:</th>
						<td><?php echo ! empty( $api_key ) ? esc_html( 'Set (' . substr( $api_key, 0, 8 ) . '...)' ) : esc_html( '<span style="color: red;">Not set</span>' ); ?></td>
					</tr>
					<tr>
						<th>API Endpoint:</th>
						<td><?php echo ! empty( $api_endpoint ) ? esc_html( $api_endpoint ) : 'Not set (using default)'; ?></td>
					</tr>
					<tr>
						<th>Organization ID:</th>
						<td><?php echo ! empty( $org_id ) ? esc_html( $org_id ) : '<span style="color: red;">Not set</span>'; ?></td>
					</tr>
				</table>
			</div>

			<div class="card">
				<h2>API Connection Test</h2>
				<?php
				if ( ! empty( $api_key ) ) {
					try {
						$api         = new \SG\HumanitixApiImporter\HumanitixAPI( $api_key, $api_endpoint, $org_id );
						$test_result = $api->test_connection();

						echo '<p><strong>Connection Test:</strong> ' . ( $test_result['success'] ? '<span style="color: green;">SUCCESS</span>' : '<span style="color: red;">FAILED</span>' ) . '</p>';
						echo '<p><strong>Message:</strong> ' . esc_html( $test_result['message'] ) . '</p>';

						if ( isset( $test_result['debug'] ) ) {
							echo '<p><strong>Debug Info:</strong></p><pre>' . esc_html( print_r( $test_result['debug'], true ) ) . '</pre>';
						}

						// Test getting events.
						echo '<h3>Events Test</h3>';
						try {
							$events = $api->get_events( 1 );

							if ( is_wp_error( $events ) ) {
								echo '<p><strong>Events Test:</strong> <span style="color: red;">FAILED</span></p>';
								echo '<p><strong>Error:</strong> ' . esc_html( $events->get_error_message() ) . '</p>';
							} else {
								echo '<p><strong>Events Test:</strong> <span style="color: green;">SUCCESS</span></p>';
								echo '<p><strong>Events Found:</strong> ' . count( $events ) . '</p>';

								// Debug: Show the actual structure.
								echo esc_html( '<p><strong>Events Type:</strong> ' . gettype( $events ) . '</p>' );
								echo '<p><strong>Events Structure:</strong></p><pre style="max-height: 200px; overflow-y: auto;">' . esc_html( print_r( $events, true ) ) . '</pre>';

								if ( ! empty( $events ) && is_array( $events ) ) {
									$first_event = reset( $events ); // Get the first element.
									echo esc_html( '<p><strong>First Event Type:</strong> ' . gettype( $first_event ) . '</p>' );
									echo '<p><strong>First Event Value:</strong> ' . ( $first_event ? 'NOT NULL' : 'NULL/FALSE' ) . '</p>';

									if ( $first_event ) {
										echo '<p><strong>First Event:</strong></p><pre style="max-height: 300px; overflow-y: auto;">' . esc_html( print_r( $first_event, true ) ) . '</pre>';
									} else {
										echo '<p><strong>First Event:</strong> No events available to display</p>';
									}
								} else {
									echo '<p><strong>First Event:</strong> No events available to display</p>';
								}
							}
						} catch ( Exception $e ) {
							echo '<p><strong>Events Test Error:</strong> ' . esc_html( $e->getMessage() ) . '</p>';
						}
					} catch ( Exception $e ) {
						echo '<p><strong>API Test Error:</strong> ' . esc_html( $e->getMessage() ) . '</p>';
					}
				} else {
					echo '<p><strong>API Key not set.</strong> Please configure the API key in the plugin settings.</p>';
				}
				?>
			</div>

			<div class="card">
				<h2>Debug Logs</h2>
				<?php
				$log_file = WP_CONTENT_DIR . '/humanitix-debug.log';
				if ( file_exists( $log_file ) ) {
<<<<<<< Updated upstream
					$log_contents = wp_remote_get( $log_file );
=======
					$log_contents = file_get_contents( $log_file );
>>>>>>> Stashed changes
					if ( ! empty( $log_contents ) ) {
						echo '<p><strong>Custom Debug Log File:</strong> ' . esc_html( $log_file ) . '</p>';
						echo '<p><strong>Log Size:</strong> ' . esc_html( size_format( filesize( $log_file ) ) ) . '</p>';
						echo '<p><strong>Last Modified:</strong> ' . esc_html( gmdate( 'Y-m-d H:i:s', filemtime( $log_file ) ) ) . '</p>';
						echo '<h3>Recent Log Entries (Last 50 lines):</h3>';
						echo '<pre style="max-height: 400px; overflow-y: auto; background: #f5f5f5; padding: 10px; border: 1px solid #ddd;">' . esc_html( implode( '', array_slice( explode( PHP_EOL, $log_contents ), -50 ) ) ) . '</pre>';
					} else {
						echo '<p>Debug log file exists but is empty.</p>';
					}
				} else {
					echo '<p>Debug log file not found at: ' . esc_html( $log_file ) . '</p>';
					echo '<p>This means no debug information has been logged yet.</p>';
				}
				?>
			</div>

			<div class="card">
				<h2>Recent Logs</h2>
				<?php
				try {
					$logger      = new \SG\HumanitixApiImporter\Admin\Logger();
					$recent_logs = $logger->get_recent_logs( 10 );

					if ( ! empty( $recent_logs ) ) {
						echo '<table class="wp-list-table widefat fixed striped" style="width: 100%;">';
						echo '<thead><tr><th style="width: 15%;">Time</th><th style="width: 10%;">Type</th><th style="width: 25%;">Message</th><th style="width: 50%;">Context</th></tr></thead>';
						echo '<tbody>';

						foreach ( $recent_logs as $log ) {
							echo '<tr>';
							echo '<td>' . esc_html( $log->created_at ) . '</td>';
							echo '<td>' . esc_html( $log->level ) . '</td>';
							echo '<td>' . esc_html( $log->message ) . '</td>';
							echo '<td><pre style="max-height: 100px; overflow-y: auto;">' . esc_html( print_r( json_decode( $log->context, true ), true ) ) . '</pre></td>';
							echo '</tr>';
						}

						echo '</tbody></table>';
					} else {
						echo '<p>No recent logs found.</p>';
					}
				} catch ( Exception $e ) {
					echo '<p><strong>Error loading logs:</strong> ' . esc_html( $e->getMessage() ) . '</p>';
				}
				?>
			</div>

			<div class="card">
				<h2>Next Steps</h2>
				<ol>
					<li>Check the WordPress debug log for detailed error messages</li>
					<li>Try the 'Test API Connection' button in the plugin settings</li>
					<li>Try the 'Start Import' button on the main plugin page</li>
					<li>Check browser console for JavaScript errors</li>
					<li>Check browser Network tab for AJAX requests</li>
				</ol>
				
				<p><strong>Debug Log Location:</strong> <?php echo defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ? esc_html( WP_CONTENT_DIR . '/debug.log' ) : 'Not enabled'; ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Handling AJAX Imports
	 */
	public function handle_import_ajax() {
<<<<<<< Updated upstream
		error_log( 'Humanitix Import: AJAX handler called' );

		check_ajax_referer( 'humanitix_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			error_log( 'Humanitix Import: User not authorized' );
=======
		// Add basic error logging for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Humanitix Import: AJAX handler called' );
		}
		
		check_ajax_referer( 'humanitix_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
>>>>>>> Stashed changes
			wp_die( 'Unauthorized' );
		}

		// Check if importer is available.
		if ( ! $this->importer ) {
<<<<<<< Updated upstream
			error_log( 'Humanitix Import: Importer not available' );
			wp_send_json_error( array( 'message' => 'API not configured. Please set up your Humanitix API key in the settings.' ) );
		}

		error_log( 'Humanitix Import: Starting import process' );
		$start_time = microtime( true );

		try {
			error_log( 'Humanitix Import: Calling importer->import_events()' );
			$result = $this->importer->import_events();
			error_log( 'Humanitix Import: Import result: ' . print_r( $result, true ) );
=======
			wp_send_json_error( array( 'message' => 'API not configured. Please set up your Humanitix API key in the settings.' ) );
		}

		// Get import limit if provided (only in debug mode)
		$import_limit = null;
		if ( $this->is_debug_enabled() && isset( $_POST['import_limit'] ) && ! empty( $_POST['import_limit'] ) ) {
			$import_limit = intval( $_POST['import_limit'] );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Humanitix Import: Import limit set to: ' . $import_limit );
			}
		}

		$start_time = microtime( true );

		try {
			// Pass import limit to the importer if set
			if ( $import_limit ) {
				$result = $this->importer->import_events( 1, $import_limit );
			} else {
				$result = $this->importer->import_events();
			}
>>>>>>> Stashed changes

			$end_time = microtime( true );
			$duration = round( $end_time - $start_time, 2 );

<<<<<<< Updated upstream
			// Log the import.
			$this->logger->log(
				'import',
				'Import completed',
				array(
					'events_imported' => $result['imported'],
					'errors'          => count( $result['errors'] ),
					'error_messages'  => $result['errors'],
					'duration'        => $duration,
					'status'          => $result['success'] ? 'success' : 'error',
				)
			);

			error_log( 'Humanitix Import: Sending success response' );
=======
			// Clean up debug log if it's getting too large
			$this->logger->cleanup_debug_log( 10 );

			// Log the import with duration
			$this->logger->log_import_summary( $result['imported'], $result['errors'], $duration );

>>>>>>> Stashed changes
			wp_send_json_success(
				array(
					'message'        => $result['message'],
					'imported_count' => $result['imported'],
					'errors'         => $result['errors'],
					'duration'       => $duration,
				)
			);

		} catch ( \Exception $e ) {
<<<<<<< Updated upstream
			error_log( 'Humanitix Import: Exception caught: ' . $e->getMessage() );
=======
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Humanitix Import: Exception caught: ' . $e->getMessage() );
			}
>>>>>>> Stashed changes
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
	 * Handle API test AJAX request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_api_test_ajax() {
		check_ajax_referer( 'humanitix_api_test_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		// Get API settings.
		$options      = get_option( 'humanitix_importer_options', array() );
		$api_key      = $options['api_key'] ?? '';
		$org_id       = $options['org_id'] ?? '';
		$api_endpoint = $options['api_endpoint'] ?? '';

		if ( empty( $api_key ) ) {
			wp_send_json_error(
				array(
					'message' => 'API key is required. Please enter your Humanitix API key in the settings.',
					'debug'   => array( 'missing_api_key' => true ),
				)
			);
		}

		if ( empty( $org_id ) ) {
			wp_send_json_error(
				array(
					'message' => 'Organization ID is required. Please enter your Humanitix organization ID in the settings.',
					'debug'   => array( 'missing_org_id' => true ),
				)
			);
		}

		try {
			// Create API instance and test connection.
			$api    = new \SG\HumanitixApiImporter\HumanitixAPI( $api_key, $api_endpoint, $org_id );
			$result = $api->test_connection();

			if ( $result['success'] ) {
				$debug_info = isset( $result['debug'] ) ? '<br><small><strong>Debug:</strong> ' . esc_html( wp_json_encode( $result['debug'] ) ) . '</small>' : '';
				wp_send_json_success(
					array(
						'message' => $result['message'] . $debug_info,
						'debug'   => $result['debug'] ?? array(),
					)
				);
			} else {
				$debug_info = isset( $result['debug'] ) ? '<br><small><strong>Debug:</strong> ' . esc_html( wp_json_encode( $result['debug'] ) ) . '</small>' : '';
				wp_send_json_error(
					array(
						'message' => $result['message'] . $debug_info,
						'debug'   => $result['debug'] ?? array(),
					)
				);
			}
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => 'API test failed: ' . $e->getMessage(),
					'debug'   => array( 'exception' => $e->getMessage() ),
				)
			);
		}
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
			SG_HUMANITIX_API_IMPORTER_PLUGIN_URL . '/assets/build/js/admin.js',
			array(),
			SG_HUMANITIX_API_IMPORTER_PLUGIN_VERSION,
			true
		);

		wp_enqueue_style(
			'humanitix-admin',
			SG_HUMANITIX_API_IMPORTER_PLUGIN_URL . '/assets/build/css/admin.css',
			array(),
			SG_HUMANITIX_API_IMPORTER_PLUGIN_VERSION
		);

		wp_localize_script(
			'humanitix-admin',
			'humanitixAdmin',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'humanitix_import_nonce' ),
				'logsNonce'    => wp_create_nonce( 'humanitix_logs_nonce' ),
				'statsNonce'   => wp_create_nonce( 'humanitix_stats_nonce' ),
				'apiTestNonce' => wp_create_nonce( 'humanitix_api_test_nonce' ),
			)
		);

		// Add a simple test script.
		wp_add_inline_script( 'humanitix-admin', 'console.log("Admin script enqueued - humanitixAdmin:", typeof humanitixAdmin !== "undefined" ? "AVAILABLE" : "NOT FOUND");' );

		// Add working Test API Connection functionality.
		wp_add_inline_script(
			'humanitix-admin',
			'
			setTimeout(function() {
				var testApiButton = document.getElementById("test-api");
				if (testApiButton) {
					testApiButton.addEventListener("click", function(e) {
						e.preventDefault();
						
						var resultDiv = document.getElementById("api-test-result");
						
						// Show loading state.
						this.disabled = true;
						this.textContent = "Testing...";
						if (resultDiv) resultDiv.innerHTML = "<div class=\"notice notice-info\"><p><span class=\"spinner is-active\"></span> Testing API connection...</p></div>";
						
						fetch(humanitixAdmin.ajaxUrl, {
							method: "POST",
							headers: {
								"Content-Type": "application/x-www-form-urlencoded",
							},
							body: new URLSearchParams({
								action: "test_api_connection",
								nonce: humanitixAdmin.apiTestNonce
							})
						})
						.then(response => response.json())
						.then(data => {
							if (data.success) {
								if (resultDiv) {
									resultDiv.innerHTML = "<div class=\"notice notice-success\"><p><span class=\"dashicons dashicons-yes-alt\"></span> <strong>API Connection Successful!</strong><br>" + data.data.message + "</p></div>";
								}
							} else {
								if (resultDiv) {
									resultDiv.innerHTML = "<div class=\"notice notice-error\"><p><span class=\"dashicons dashicons-no-alt\"></span> <strong>API Connection Failed!</strong><br>" + data.data.message + "</p></div>";
								}
							}
						})
						.catch(error => {
							console.error("API test error:", error);
							if (resultDiv) {
								resultDiv.innerHTML = "<div class=\"notice notice-error\"><p><span class=\"dashicons dashicons-no-alt\"></span> <strong>API Connection Failed!</strong><br>Failed to test API connection. Please try again.</p></div>";
							}
						})
						.finally(() => {
							this.disabled = false;
							this.textContent = "Test API Connection";
						});
					});
				}
			}, 1000);
		'
		);

		// Add working Start Import functionality.
		wp_add_inline_script(
			'humanitix-admin',
			'
			setTimeout(function() {
				var startImportButton = document.getElementById("start-import");
				if (startImportButton) {
					startImportButton.addEventListener("click", function(e) {
						e.preventDefault();
						
						var stopButton = document.getElementById("stop-import");
						var statusDiv = document.getElementById("import-status");
						var progressDiv = document.getElementById("import-progress");
						var resultsDiv = document.getElementById("import-results");
						
<<<<<<< Updated upstream
=======
						// Get import limit if debug mode is enabled
						var importLimit = "";
						var importLimitSelect = document.getElementById("import-limit");
						if (importLimitSelect && importLimitSelect.value) {
							importLimit = importLimitSelect.value;
						}
						
>>>>>>> Stashed changes
						// Show progress and disable start button.
						this.disabled = true;
						this.textContent = "Importing...";
						if (stopButton) stopButton.style.display = "inline-block";
						if (progressDiv) progressDiv.style.display = "block";
						if (resultsDiv) resultsDiv.style.display = "none";
						if (statusDiv) statusDiv.innerHTML = "<span class=\"spinner is-active\"></span> Starting import...";
						
<<<<<<< Updated upstream
=======
						// Prepare request data
						var requestData = {
							action: "import_events",
							nonce: humanitixAdmin.nonce
						};
						
						// Add import limit if set
						if (importLimit) {
							requestData.import_limit = importLimit;
						}
						
>>>>>>> Stashed changes
						fetch(humanitixAdmin.ajaxUrl, {
							method: "POST",
							headers: {
								"Content-Type": "application/x-www-form-urlencoded",
							},
<<<<<<< Updated upstream
							body: new URLSearchParams({
								action: "import_events",
								nonce: humanitixAdmin.nonce
							})
=======
							body: new URLSearchParams(requestData)
>>>>>>> Stashed changes
						})
						.then(response => response.json())
						.then(data => {
							if (data.success) {
								if (statusDiv) statusDiv.innerHTML = "<span class=\"dashicons dashicons-yes-alt\"></span> Import completed successfully";
								if (resultsDiv) {
									var resultsContent = resultsDiv.querySelector("#results-content");
									if (resultsContent) {
<<<<<<< Updated upstream
										resultsContent.innerHTML = 
											"<p><strong>Events imported:</strong> " + data.data.imported_count + "</p>" +
=======
										var limitText = importLimit ? " (Limited to " + importLimit + " events)" : "";
										resultsContent.innerHTML = 
											"<p><strong>Events imported:</strong> " + data.data.imported_count + limitText + "</p>" +
>>>>>>> Stashed changes
											"<p><strong>Duration:</strong> " + data.data.duration + " seconds</p>" +
											(data.data.errors.length > 0 ? "<p><strong>Errors:</strong> " + data.data.errors.join(", ") + "</p>" : "");
									}
									resultsDiv.style.display = "block";
								}
							} else {
								if (statusDiv) statusDiv.innerHTML = "<span class=\"dashicons dashicons-no-alt\"></span> Import failed: " + data.data.message;
							}
						})
						.catch(error => {
							console.error("Import error:", error);
							if (statusDiv) statusDiv.innerHTML = "<span class=\"dashicons dashicons-no-alt\"></span> Import failed. Please try again.";
						})
						.finally(() => {
							this.disabled = false;
							this.textContent = "Start Import";
							if (stopButton) stopButton.style.display = "none";
							if (progressDiv) progressDiv.style.display = "none";
						});
					});
				}
			}, 1000);
		'
		);

		// Add working Filter Logs functionality.
		wp_add_inline_script(
			'humanitix-admin',
			'
			setTimeout(function() {
				var filterLogsButton = document.getElementById("filter-logs");
				if (filterLogsButton) {
					filterLogsButton.addEventListener("click", function(e) {
						e.preventDefault();
						
						var levelSelect = document.getElementById("log-level");
						var dateInput = document.getElementById("log-date");
						
						fetch(humanitixAdmin.ajaxUrl, {
							method: "POST",
							headers: {
								"Content-Type": "application/x-www-form-urlencoded",
							},
							body: new URLSearchParams({
								action: "get_import_logs",
								nonce: humanitixAdmin.logsNonce,
								level: levelSelect ? levelSelect.value : "",
								date: dateInput ? dateInput.value : ""
							})
						})
						.then(response => response.json())
						.then(data => {
							if (data.success) {
								// Update logs table.
								var tableBody = document.querySelector("#logs-container table tbody");
								if (tableBody) {
									tableBody.innerHTML = "";
									
									if (data.data.length === 0) {
										tableBody.innerHTML = "<tr><td colspan=\"4\">No logs found</td></tr>";
									} else {
										data.data.forEach(function(log) {
											var row = document.createElement("tr");
											row.innerHTML = 
												"<td>" + log.created_at + "</td>" +
												"<td><span class=\"log-level-" + log.level + "\">" + log.level + "</span></td>" +
												"<td>" + log.message + "</td>" +
												"<td>" + (log.context ? JSON.stringify(log.context) : "") + "</td>";
											tableBody.appendChild(row);
										});
									}
								}
							}
						})
						.catch(error => {
							console.error("Log filtering error:", error);
						});
					});
				}
			}, 1000);
		'
		);

		// Add working Error Toggle functionality.
		wp_add_inline_script(
			'humanitix-admin',
			'
			setTimeout(function() {
				// Add error toggle functionality.
				document.addEventListener("click", function(e) {
					if (e.target.classList.contains("error-toggle")) {
						e.preventDefault();
						
						var targetId = e.target.dataset.target;
						var errorRow = document.getElementById(targetId);
						
						if (errorRow) {
							var isVisible = errorRow.style.display !== "none";
							
							if (isVisible) {
								errorRow.style.display = "none";
								e.target.classList.remove("expanded");
							} else {
								errorRow.style.display = "table-row";
								e.target.classList.add("expanded");
							}
						}
					}
				});
			}, 1000);
		'
		);

		// Add CSS for alternating row colors and error details.
		wp_add_inline_style(
			'humanitix-admin',
			'
			/* Alternating row colors for tables */
			.wp-list-table tbody tr:nth-child(even) {
				background-color: #f9f9f9;
			}
			
			.wp-list-table tbody tr:nth-child(odd) {
				background-color: #ffffff;
			}
			
			/* Error details styling */
			.error-details {
				background-color: #fef7f1 !important;
				border-left: 4px solid #dc3232;
			}
			
			.error-details .error-messages {
				padding: 1rem;
			}
			
			.error-details .error-messages h4 {
				margin: 0 0 0.5rem 0;
				color: #dc3232;
				font-size: 1rem;
			}
			
			.error-details .error-messages ul {
				margin: 0;
				padding-left: 1.5rem;
			}
			
			.error-details .error-messages li {
				margin-bottom: 0.5rem;
				color: #666;
				font-family: monospace;
				font-size: 0.9rem;
				line-height: 1.4;
			}
			
			.error-details .error-messages li:last-child {
				margin-bottom: 0;
			}
			
			/* Button link styling */
			.button-link {
				background: none;
				border: none;
				padding: 0;
				margin: 0;
				font: inherit;
				cursor: pointer;
				text-decoration: none;
				color: #dc3232;
				font-weight: bold;
			}
			
			.button-link:hover {
				color: #a00;
				text-decoration: underline;
			}
			
			.button-link:focus {
				outline: none;
				text-decoration: underline;
			}
			
			.error-toggle.expanded {
				color: #a00;
				text-decoration: underline;
			}
		'
		);
	}
}
