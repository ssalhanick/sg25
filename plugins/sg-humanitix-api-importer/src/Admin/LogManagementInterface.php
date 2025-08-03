<?php
/**
 * Log Management Interface Class.
 *
 * Provides admin interface for log management, cleanup, and monitoring.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Log Management Interface Class.
 *
 * Provides admin interface for log management, cleanup, and monitoring.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */
class LogManagementInterface {

	/**
	 * The log manager instance.
	 *
	 * @var LogManager
	 */
	private $log_manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->log_manager = new LogManager();
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'wp_ajax_humanitix_log_cleanup', array( $this, 'handle_log_cleanup_ajax' ) );
		add_action( 'wp_ajax_humanitix_log_export', array( $this, 'handle_log_export_ajax' ) );
		add_action( 'wp_ajax_humanitix_log_stats', array( $this, 'handle_log_stats_ajax' ) );
		add_action( 'wp_ajax_humanitix_log_compress', array( $this, 'handle_log_compress_ajax' ) );
		add_action( 'wp_ajax_humanitix_schedule_cleanup', array( $this, 'handle_schedule_cleanup_ajax' ) );
		add_action( 'wp_ajax_humanitix_clear_cleanup', array( $this, 'handle_clear_cleanup_ajax' ) );
	}

	/**
	 * Add admin menu for log management.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'humanitix-importer',
			'Log Management',
			'Log Management',
			'manage_options',
			'humanitix-log-management',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render the log management admin page.
	 */
	public function render_admin_page() {
		?>
		<div class="wrap">
			<h1>Humanitix Log Management</h1>
			
			<div class="card">
				<h2>Log Statistics</h2>
				<div id="log-stats-container">
					<p>Loading statistics...</p>
				</div>
				<button type="button" class="button" onclick="refreshLogStats()">Refresh Stats</button>
			</div>

			<div class="card">
				<h2>Log Cleanup</h2>
				<p>Clean up old logs to reduce database size and improve performance.</p>
				
				<table class="form-table">
					<tr>
						<th scope="row">Days to Keep</th>
						<td>
							<input type="number" id="days-to-keep" value="30" min="1" max="365" />
							<p class="description">Logs older than this many days will be deleted.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Maximum Logs</th>
						<td>
							<input type="number" id="max-logs" value="10000" min="1000" max="100000" />
							<p class="description">Maximum number of logs to keep in database.</p>
						</td>
					</tr>
				</table>
				
				<button type="button" class="button button-primary" onclick="runLogCleanup()">Run Cleanup</button>
				<div id="cleanup-results"></div>
			</div>

			<div class="card">
				<h2>Context Compression</h2>
				<p>Compress large context entries to save database space.</p>
				
				<table class="form-table">
					<tr>
						<th scope="row">Minimum Size (bytes)</th>
						<td>
							<input type="number" id="min-compress-size" value="1000" min="100" max="10000" />
							<p class="description">Only compress contexts larger than this size.</p>
						</td>
					</tr>
				</table>
				
				<button type="button" class="button button-secondary" onclick="runContextCompression()">Compress Contexts</button>
				<div id="compression-results"></div>
			</div>

			<div class="card">
				<h2>Log Export</h2>
				<p>Export logs to file for backup or analysis.</p>
				
				<table class="form-table">
					<tr>
						<th scope="row">Export Format</th>
						<td>
							<select id="export-format">
								<option value="json">JSON</option>
								<option value="csv">CSV</option>
								<option value="txt">Text</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">Maximum Logs</th>
						<td>
							<input type="number" id="export-limit" value="1000" min="100" max="10000" />
							<p class="description">Maximum number of logs to export.</p>
						</td>
					</tr>
				</table>
				
				<button type="button" class="button button-secondary" onclick="exportLogs()">Export Logs</button>
				<div id="export-results"></div>
			</div>

			<div class="card">
				<h2>Automatic Cleanup</h2>
				<p>Schedule automatic log cleanup to run weekly.</p>
				
				<button type="button" class="button button-primary" onclick="scheduleCleanup()">Schedule Weekly Cleanup</button>
				<button type="button" class="button button-secondary" onclick="clearCleanupSchedule()">Clear Schedule</button>
				<div id="schedule-results"></div>
			</div>
		</div>

		<script>
		function refreshLogStats() {
			fetch(ajaxurl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'humanitix_log_stats',
					nonce: '<?php echo wp_create_nonce( 'humanitix_log_management' ); ?>'
				})
			})
			.then(response => response.json())
			.then(response => {
				if (response.success) {
					displayLogStats(response.data);
				} else {
					document.getElementById('log-stats-container').innerHTML = '<p>Error loading statistics: ' + response.data + '</p>';
				}
			})
			.catch(error => {
				document.getElementById('log-stats-container').innerHTML = '<p>Error loading statistics: ' + error.message + '</p>';
			});
		}

		function displayLogStats(stats) {
			let html = '<table class="widefat">';
			html += '<tr><td><strong>Total Logs:</strong></td><td>' + stats.total_logs + '</td></tr>';
			html += '<tr><td><strong>Total Size:</strong></td><td>' + formatBytes(stats.total_size) + '</td></tr>';
			html += '<tr><td><strong>Large Contexts:</strong></td><td>' + stats.large_contexts + '</td></tr>';
			html += '<tr><td><strong>Logs Today:</strong></td><td>' + stats.logs_today + '</td></tr>';
			html += '<tr><td><strong>Logs This Week:</strong></td><td>' + stats.logs_this_week + '</td></tr>';
			html += '</table>';
			
			if (stats.level_distribution) {
				html += '<h3>Level Distribution</h3><ul>';
				stats.level_distribution.forEach(function(level) {
					html += '<li>' + level.level + ': ' + level.count + '</li>';
				});
				html += '</ul>';
			}
			
			document.getElementById('log-stats-container').innerHTML = html;
		}

		function formatBytes(bytes) {
			if (bytes === null || bytes === undefined) return '0 B';
			const sizes = ['B', 'KB', 'MB', 'GB'];
			const i = Math.floor(Math.log(bytes) / Math.log(1024));
			return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
		}

		function runLogCleanup() {
			const daysToKeep = document.getElementById('days-to-keep').value;
			const maxLogs = document.getElementById('max-logs').value;
			
			fetch(ajaxurl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'humanitix_log_cleanup',
					days_to_keep: daysToKeep,
					max_logs: maxLogs,
					nonce: '<?php echo wp_create_nonce( 'humanitix_log_management' ); ?>'
				})
			})
			.then(response => response.json())
			.then(response => {
				if (response.success) {
					displayCleanupResults(response.data);
				} else {
					document.getElementById('cleanup-results').innerHTML = '<p>Error: ' + response.data + '</p>';
				}
			})
			.catch(error => {
				document.getElementById('cleanup-results').innerHTML = '<p>Error: ' + error.message + '</p>';
			});
		}

		function displayCleanupResults(results) {
			let html = '<h3>Cleanup Results</h3>';
			html += '<ul>';
			html += '<li>Deleted by age: ' + results.deleted_by_age + '</li>';
			html += '<li>Deleted by count: ' + results.deleted_by_count + '</li>';
			html += '<li>Total deleted: ' + results.total_deleted + '</li>';
			html += '</ul>';
			
			if (results.errors && results.errors.length > 0) {
				html += '<h4>Errors:</h4><ul>';
				results.errors.forEach(function(error) {
					html += '<li>' + error + '</li>';
				});
				html += '</ul>';
			}
			
			document.getElementById('cleanup-results').innerHTML = html;
			refreshLogStats();
		}

		function runContextCompression() {
			const minSize = document.getElementById('min-compress-size').value;
			
			fetch(ajaxurl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'humanitix_log_compress',
					min_size: minSize,
					nonce: '<?php echo wp_create_nonce( 'humanitix_log_management' ); ?>'
				})
			})
			.then(response => response.json())
			.then(response => {
				if (response.success) {
					displayCompressionResults(response.data);
				} else {
					document.getElementById('compression-results').innerHTML = '<p>Error: ' + response.data + '</p>';
				}
			})
			.catch(error => {
				document.getElementById('compression-results').innerHTML = '<p>Error: ' + error.message + '</p>';
			});
		}

		function displayCompressionResults(results) {
			let html = '<h3>Compression Results</h3>';
			html += '<ul>';
			html += '<li>Compressed: ' + results.compressed + ' contexts</li>';
			html += '<li>Space saved: ' + formatBytes(results.space_saved) + '</li>';
			html += '</ul>';
			
			if (results.errors && results.errors.length > 0) {
				html += '<h4>Errors:</h4><ul>';
				results.errors.forEach(function(error) {
					html += '<li>' + error + '</li>';
				});
				html += '</ul>';
			}
			
			document.getElementById('compression-results').innerHTML = html;
			refreshLogStats();
		}

		function exportLogs() {
			const format = document.getElementById('export-format').value;
			const limit = document.getElementById('export-limit').value;
			
			fetch(ajaxurl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'humanitix_log_export',
					format: format,
					limit: limit,
					nonce: '<?php echo wp_create_nonce( 'humanitix_log_management' ); ?>'
				})
			})
			.then(response => response.json())
			.then(response => {
				if (response.success) {
					displayExportResults(response.data);
				} else {
					document.getElementById('export-results').innerHTML = '<p>Error: ' + response.data + '</p>';
				}
			})
			.catch(error => {
				document.getElementById('export-results').innerHTML = '<p>Error: ' + error.message + '</p>';
			});
		}

		function displayExportResults(results) {
			let html = '<h3>Export Results</h3>';
			html += '<ul>';
			html += '<li>Exported: ' + results.exported_count + ' logs</li>';
			html += '<li>File size: ' + formatBytes(results.file_size) + '</li>';
			html += '<li>File path: ' + results.file_path + '</li>';
			html += '</ul>';
			
			document.getElementById('export-results').innerHTML = html;
		}

		function scheduleCleanup() {
			fetch(ajaxurl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'humanitix_schedule_cleanup',
					nonce: '<?php echo wp_create_nonce( 'humanitix_log_management' ); ?>'
				})
			})
			.then(response => response.json())
			.then(response => {
				if (response.success) {
					document.getElementById('schedule-results').innerHTML = '<p>Weekly cleanup scheduled successfully!</p>';
				} else {
					document.getElementById('schedule-results').innerHTML = '<p>Error: ' + response.data + '</p>';
				}
			})
			.catch(error => {
				document.getElementById('schedule-results').innerHTML = '<p>Error: ' + error.message + '</p>';
			});
		}

		function clearCleanupSchedule() {
			fetch(ajaxurl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'humanitix_clear_cleanup',
					nonce: '<?php echo wp_create_nonce( 'humanitix_log_management' ); ?>'
				})
			})
			.then(response => response.json())
			.then(response => {
				if (response.success) {
					document.getElementById('schedule-results').innerHTML = '<p>Cleanup schedule cleared successfully!</p>';
				} else {
					document.getElementById('schedule-results').innerHTML = '<p>Error: ' + response.data + '</p>';
				}
			})
			.catch(error => {
				document.getElementById('schedule-results').innerHTML = '<p>Error: ' + error.message + '</p>';
			});
		}

		// Load stats on page load
		document.addEventListener('DOMContentLoaded', function() {
			refreshLogStats();
		});
		</script>
		<?php
	}

	/**
	 * Handle log cleanup AJAX request.
	 */
	public function handle_log_cleanup_ajax() {
		check_ajax_referer( 'humanitix_log_management', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$days_to_keep = absint( $_POST['days_to_keep'] ?? 30 );
		$max_logs = absint( $_POST['max_logs'] ?? 10000 );

		$results = $this->log_manager->cleanup_old_logs( $days_to_keep, $max_logs );

		if ( empty( $results['errors'] ) ) {
			wp_send_json_success( $results );
		} else {
			wp_send_json_error( 'Cleanup completed with errors: ' . implode( ', ', $results['errors'] ) );
		}
	}

	/**
	 * Handle log export AJAX request.
	 */
	public function handle_log_export_ajax() {
		check_ajax_referer( 'humanitix_log_management', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$format = sanitize_text_field( $_POST['format'] ?? 'json' );
		$limit = absint( $_POST['limit'] ?? 1000 );

		$results = $this->log_manager->export_logs( $format, $limit );

		if ( $results['success'] ) {
			wp_send_json_success( $results );
		} else {
			wp_send_json_error( $results['error'] );
		}
	}

	/**
	 * Handle log statistics AJAX request.
	 */
	public function handle_log_stats_ajax() {
		check_ajax_referer( 'humanitix_log_management', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$stats = $this->log_manager->get_log_statistics();

		if ( isset( $stats['error'] ) ) {
			wp_send_json_error( $stats['error'] );
		} else {
			wp_send_json_success( $stats );
		}
	}

	/**
	 * Handle log compression AJAX request.
	 */
	public function handle_log_compress_ajax() {
		check_ajax_referer( 'humanitix_log_management', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$min_size = absint( $_POST['min_size'] ?? 1000 );

		$results = $this->log_manager->compress_large_contexts( $min_size );

		if ( empty( $results['errors'] ) ) {
			wp_send_json_success( $results );
		} else {
			wp_send_json_error( 'Compression completed with errors: ' . implode( ', ', $results['errors'] ) );
		}
	}

	/**
	 * Handle schedule cleanup AJAX request.
	 */
	public function handle_schedule_cleanup_ajax() {
		check_ajax_referer( 'humanitix_log_management', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$this->log_manager->schedule_cleanup();
		wp_send_json_success( 'Weekly cleanup scheduled successfully' );
	}

	/**
	 * Handle clear cleanup schedule AJAX request.
	 */
	public function handle_clear_cleanup_ajax() {
		check_ajax_referer( 'humanitix_log_management', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$this->log_manager->clear_cleanup_schedule();
		wp_send_json_success( 'Cleanup schedule cleared successfully' );
	}
} 