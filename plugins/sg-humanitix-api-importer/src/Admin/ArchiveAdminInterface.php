<?php
/**
 * Archive Admin Interface Class.
 *
 * Provides admin interface for managing event archives.
 * Includes manual archive controls, statistics, and settings.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Admin;

use SG\HumanitixApiImporter\Archive\ArchiveManager;
use SG\HumanitixApiImporter\Archive\ArchiveCronHandler;
use SG\HumanitixApiImporter\Archive\ArchiveQueries;
use SG\HumanitixApiImporter\Archive\ArchiveValidator;
use SG\HumanitixApiImporter\Admin\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Archive Admin Interface Class.
 *
 * Provides admin interface for managing event archives.
 * Includes manual archive controls, statistics, and settings.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */
class ArchiveAdminInterface {

	/**
	 * The logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * The archive manager instance.
	 *
	 * @var ArchiveManager
	 */
	private $archive_manager;

	/**
	 * The cron handler instance.
	 *
	 * @var ArchiveCronHandler
	 */
	private $cron_handler;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->logger = new Logger();
		$this->archive_manager = new ArchiveManager();
		$this->cron_handler = new ArchiveCronHandler();
		
		// Add admin menu
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		
		// Handle AJAX requests
		add_action( 'wp_ajax_humanitix_manual_archive', array( $this, 'handle_manual_archive' ) );
		add_action( 'wp_ajax_humanitix_manual_unarchive', array( $this, 'handle_manual_unarchive' ) );
		add_action( 'wp_ajax_humanitix_run_auto_archive', array( $this, 'handle_run_auto_archive' ) );
		add_action( 'wp_ajax_humanitix_run_monthly_archive', array( $this, 'handle_run_monthly_archive' ) );
		add_action( 'wp_ajax_humanitix_get_archive_stats', array( $this, 'handle_get_archive_stats' ) );
		
		// New quick archive AJAX handlers
		add_action( 'wp_ajax_humanitix_preview_quick_archive', array( $this, 'handle_preview_quick_archive' ) );
		add_action( 'wp_ajax_humanitix_execute_quick_archive', array( $this, 'handle_execute_quick_archive' ) );
		add_action( 'wp_ajax_humanitix_restore_from_backup', array( $this, 'handle_restore_from_backup' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'humanitix-importer',
			__( 'Event Archives', 'sg-humanitix-api-importer' ),
			__( 'Event Archives', 'sg-humanitix-api-importer' ),
			'manage_options',
			'humanitix-archives',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render admin page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_admin_page() {
		$stats = $this->archive_manager->get_archive_statistics();
		$next_runs = $this->cron_handler->get_next_run_times();
		$validator = new ArchiveValidator();
		$health = $validator->check_system_health();
		
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Event Archives', 'sg-humanitix-api-importer' ); ?></h1>
			
			<!-- System Health -->
			<div class="card">
				<h2><?php esc_html_e( 'System Health', 'sg-humanitix-api-importer' ); ?></h2>
				<div class="health-status">
					<?php foreach ( $health['checks'] as $check_name => $check_result ) : ?>
						<div class="health-check">
							<span class="status-<?php echo esc_attr( $check_result['status'] ); ?>">
								<?php echo esc_html( $check_result['message'] ); ?>
							</span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Quick Archive Controls -->
			<div class="card">
				<h2><?php esc_html_e( 'Quick Archive Controls', 'sg-humanitix-api-importer' ); ?></h2>
				<div class="quick-archive-form">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="age-threshold"><?php esc_html_e( 'Age Threshold (years)', 'sg-humanitix-api-importer' ); ?></label>
							</th>
							<td>
								<input type="number" id="age-threshold" name="age_threshold" value="0.5" min="0.1" max="10" step="0.1" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Archive events older than this many years (e.g., 0.5 = 6 months, 1.0 = 1 year)', 'sg-humanitix-api-importer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="action-type"><?php esc_html_e( 'Action Type', 'sg-humanitix-api-importer' ); ?></label>
							</th>
							<td>
								<select id="action-type" name="action_type" class="regular-text">
									<option value="archive"><?php esc_html_e( 'Archive Events', 'sg-humanitix-api-importer' ); ?></option>
									<option value="delete"><?php esc_html_e( 'Delete Events', 'sg-humanitix-api-importer' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Choose whether to archive or delete old events', 'sg-humanitix-api-importer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="dry-run"><?php esc_html_e( 'Dry Run', 'sg-humanitix-api-importer' ); ?></label>
							</th>
							<td>
								<input type="checkbox" id="dry-run" name="dry_run" value="1" />
								<label for="dry-run"><?php esc_html_e( 'Preview changes without making them', 'sg-humanitix-api-importer' ); ?></label>
								<p class="description"><?php esc_html_e( 'Check this to see what would be affected without making changes', 'sg-humanitix-api-importer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="batch-size"><?php esc_html_e( 'Batch Size', 'sg-humanitix-api-importer' ); ?></label>
							</th>
							<td>
								<input type="number" id="batch-size" name="batch_size" value="50" min="1" max="500" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Number of events to process per batch', 'sg-humanitix-api-importer' ); ?></p>
							</td>
						</tr>
					</table>
					
					<div class="quick-archive-actions">
						<button type="button" class="button button-primary" id="preview-quick-archive">
							<?php esc_html_e( 'Preview Changes', 'sg-humanitix-api-importer' ); ?>
						</button>
						<button type="button" class="button button-secondary" id="execute-quick-archive">
							<?php esc_html_e( 'Execute Archive', 'sg-humanitix-api-importer' ); ?>
						</button>
						<button type="button" class="button button-secondary" id="restore-from-backup">
							<?php esc_html_e( 'Restore from Backup', 'sg-humanitix-api-importer' ); ?>
						</button>
					</div>
					
					<div id="quick-archive-results"></div>
				</div>
			</div>

			<!-- Archive Statistics -->
			<div class="card">
				<h2><?php esc_html_e( 'Archive Statistics', 'sg-humanitix-api-importer' ); ?></h2>
				<div class="stats-grid">
					<div class="stat-item">
						<span class="stat-number"><?php echo esc_html( $stats['total_events'] ); ?></span>
						<span class="stat-label"><?php esc_html_e( 'Total Events', 'sg-humanitix-api-importer' ); ?></span>
					</div>
					<div class="stat-item">
						<span class="stat-number"><?php echo esc_html( $stats['total_archived'] ); ?></span>
						<span class="stat-label"><?php esc_html_e( 'Archived Events', 'sg-humanitix-api-importer' ); ?></span>
					</div>
					<div class="stat-item">
						<span class="stat-number"><?php echo esc_html( $stats['events_to_archive'] ); ?></span>
						<span class="stat-label"><?php esc_html_e( 'Events to Archive', 'sg-humanitix-api-importer' ); ?></span>
					</div>
					<div class="stat-item">
						<span class="stat-number"><?php echo esc_html( $stats['archived_this_month'] ); ?></span>
						<span class="stat-label"><?php esc_html_e( 'Archived This Month', 'sg-humanitix-api-importer' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Manual Archive Controls -->
			<div class="card">
				<h2><?php esc_html_e( 'Manual Archive Controls', 'sg-humanitix-api-importer' ); ?></h2>
				<div class="manual-controls">
					<button type="button" class="button button-primary" id="run-auto-archive">
						<?php esc_html_e( 'Run Auto Archive', 'sg-humanitix-api-importer' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="run-monthly-archive">
						<?php esc_html_e( 'Run Monthly Archive', 'sg-humanitix-api-importer' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="refresh-stats">
						<?php esc_html_e( 'Refresh Statistics', 'sg-humanitix-api-importer' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="clear-results" onclick="clearResults()">
						<?php esc_html_e( 'Clear Results', 'sg-humanitix-api-importer' ); ?>
					</button>
				</div>
				<div id="archive-results"></div>
			</div>

			<!-- Scheduled Tasks -->
			<div class="card">
				<h2><?php esc_html_e( 'Scheduled Tasks', 'sg-humanitix-api-importer' ); ?></h2>
				<div class="scheduled-tasks">
					<p><strong><?php esc_html_e( 'Auto Archive:', 'sg-humanitix-api-importer' ); ?></strong> 
						<?php echo esc_html( $next_runs['auto_archive'] ); ?></p>
					<p><strong><?php esc_html_e( 'Monthly Archive:', 'sg-humanitix-api-importer' ); ?></strong> 
						<?php echo esc_html( $next_runs['monthly_archive'] ); ?></p>
				</div>
			</div>

			<!-- Archive Settings -->
			<div class="card">
				<h2><?php esc_html_e( 'Archive Settings', 'sg-humanitix-api-importer' ); ?></h2>
				<p><?php esc_html_e( 'Configure archive settings in the main plugin settings page.', 'sg-humanitix-api-importer' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=humanitix-importer' ) ); ?>" class="button">
					<?php esc_html_e( 'Go to Settings', 'sg-humanitix-api-importer' ); ?>
				</a>
			</div>
		</div>

		<style>
		.card {
			background: #fff;
			border: 1px solid #ccd0d4;
			border-radius: 4px;
			padding: 20px;
			margin-bottom: 20px;
		}
		.stats-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 20px;
			margin-top: 15px;
		}
		.stat-item {
			text-align: center;
			padding: 15px;
			background: #f9f9f9;
			border-radius: 4px;
		}
		.stat-number {
			display: block;
			font-size: 24px;
			font-weight: bold;
			color: #0073aa;
		}
		.stat-label {
			display: block;
			margin-top: 5px;
			color: #666;
		}
		.manual-controls, .quick-archive-actions {
			margin: 15px 0;
		}
		.manual-controls .button, .quick-archive-actions .button {
			margin-right: 10px;
		}
		.health-status {
			margin-top: 15px;
		}
		.health-check {
			margin-bottom: 8px;
		}
		.status-ok {
			color: #46b450;
		}
		.status-warning {
			color: #ffb900;
		}
		.status-error {
			color: #dc3232;
		}
		#archive-results, #quick-archive-results {
			margin-top: 15px;
			padding: 15px;
			border-radius: 4px;
			border-left: 4px solid #0073aa;
			animation: fadeIn 0.5s ease-in;
		}
		
		@keyframes fadeIn {
			from { opacity: 0; transform: translateY(-10px); }
			to { opacity: 1; transform: translateY(0); }
		}
		.result-success {
			background: #d4edda;
			border: 1px solid #c3e6cb;
			color: #155724;
		}
		.result-error {
			background: #f8d7da;
			border: 1px solid #f5c6cb;
			color: #721c24;
		}
		.result-warning {
			background: #fff3cd;
			border: 1px solid #ffeaa7;
			color: #856404;
		}
		.quick-archive-form {
			margin-top: 15px;
		}
		.form-table th {
			width: 200px;
		}
		.preview-results {
			margin-top: 15px;
			padding: 15px;
			background: #f8f9fa;
			border: 1px solid #dee2e6;
			border-radius: 4px;
		}
		.preview-item {
			margin-bottom: 8px;
			padding: 8px;
			background: #fff;
			border-radius: 3px;
			border-left: 3px solid #0073aa;
		}
		</style>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Restore last result if available
			restoreLastResult();
			
			// Existing functionality
			document.getElementById('run-auto-archive').addEventListener('click', function() {
				runArchiveAction('humanitix_run_auto_archive', 'Running auto archive...');
			});
			
			document.getElementById('run-monthly-archive').addEventListener('click', function() {
				runArchiveAction('humanitix_run_monthly_archive', 'Running monthly archive...');
			});
			
			document.getElementById('refresh-stats').addEventListener('click', function() {
				refreshStats();
			});
			
			// New quick archive functionality
			document.getElementById('preview-quick-archive').addEventListener('click', function() {
				previewQuickArchive();
			});
			
			document.getElementById('execute-quick-archive').addEventListener('click', function() {
				executeQuickArchive();
			});
			
			document.getElementById('restore-from-backup').addEventListener('click', function() {
				restoreFromBackup();
			});
			
			function runArchiveAction(action, message) {
				document.getElementById('archive-results').innerHTML = '<div class="result-success">' + message + '</div>';
				
				fetch(ajaxurl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams({
						action: action,
						nonce: '<?php echo wp_create_nonce( 'humanitix_archive_nonce' ); ?>'
					})
				})
				.then(response => response.json())
				.then(response => {
					if (response.success) {
						let html = '<div class="result-success">' + response.data.message + '</div>';
						// Add a "Results will be preserved" message
						html += '<div class="result-warning" style="margin-top: 10px; font-size: 12px;">Results will be preserved. Stats will update automatically.</div>';
						html += '<button type="button" class="button" onclick="clearArchiveResults()" style="margin-top: 10px;">Clear Results</button>';
						document.getElementById('archive-results').innerHTML = html;
						// Add a delay before refreshing stats so user can see the results
						setTimeout(function() {
							refreshStats();
						}, 3000); // 3 second delay
					} else {
						document.getElementById('archive-results').innerHTML = '<div class="result-error">' + response.data + '</div>';
					}
				})
				.catch(error => {
					document.getElementById('archive-results').innerHTML = '<div class="result-error">Request failed</div>';
				});
			}
			
			function clearArchiveResults() {
				document.getElementById('archive-results').innerHTML = '';
			}
			
			function refreshStats() {
				fetch(ajaxurl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams({
						action: 'humanitix_get_archive_stats',
						nonce: '<?php echo wp_create_nonce( 'humanitix_archive_nonce' ); ?>'
					})
				})
				.then(response => response.json())
				.then(response => {
					if (response.success) {
						// Update stats without reloading
						updateStatsDisplay(response.data);
					}
				})
				.catch(error => {
					console.error('Error refreshing stats:', error);
				});
			}
			
			function updateStatsDisplay(stats) {
				// Update the statistics display without reloading
				const statElements = document.querySelectorAll('.stat-number');
				if (statElements.length >= 4 && stats) {
					// Add a brief visual feedback
					statElements.forEach(function(element) {
						element.style.transition = 'color 0.3s ease';
						element.style.color = '#0073aa';
						setTimeout(function() {
							element.style.color = '';
						}, 300);
					});
					
					statElements[0].textContent = stats.total_events || '0';
					statElements[1].textContent = stats.total_archived || '0';
					statElements[2].textContent = stats.events_to_archive || '0';
					statElements[3].textContent = stats.archived_this_month || '0';
				}
			}
			
			function restoreLastResult() {
				const lastResult = sessionStorage.getItem('lastArchiveResult');
				const lastTime = sessionStorage.getItem('lastArchiveTime');
				if (lastResult && lastTime) {
					const resultsDiv = document.getElementById('archive-results');
					if (resultsDiv) {
						resultsDiv.innerHTML = lastResult + '<p><small>Last updated: ' + lastTime + '</small></p>';
					}
				}
			}
			
			function clearResults() {
				if (confirm('Are you sure you want to clear the archive results?')) {
					document.getElementById('archive-results').innerHTML = '';
					sessionStorage.removeItem('lastArchiveResult');
					sessionStorage.removeItem('lastArchiveTime');
				}
			}
			
			function clearQuickArchiveResults() {
				if (confirm('Are you sure you want to clear the quick archive results?')) {
					document.getElementById('quick-archive-results').innerHTML = '';
				}
			}
			
			function previewQuickArchive() {
				const ageThreshold = document.getElementById('age-threshold').value;
				const actionType = document.getElementById('action-type').value;
				const batchSize = document.getElementById('batch-size').value;
				
				document.getElementById('quick-archive-results').innerHTML = '<div class="result-warning">Previewing changes...</div>';
				
				fetch(ajaxurl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams({
						action: 'humanitix_preview_quick_archive',
						age_threshold: ageThreshold,
						action_type: actionType,
						batch_size: batchSize,
						dry_run: '1',
						nonce: '<?php echo wp_create_nonce( 'humanitix_archive_nonce' ); ?>'
					})
				})
				.then(response => response.json())
				.then(response => {
					if (response.success) {
						let html = '<div class="result-warning">Preview Results:</div>';
						html += '<div class="preview-results">';
						html += '<p><strong>Events that would be affected:</strong> ' + response.data.total + '</p>';
						html += '<p><small>Age threshold: ' + response.data.age_threshold + ' years</small></p>';
						if (response.data.events && response.data.events.length > 0) {
							response.data.events.forEach(function(event) {
								html += '<div class="preview-item">';
								html += '<strong>' + event.title + '</strong> (ID: ' + event.id + ')';
								html += '<br><small>Start Date: ' + event.start_date + '</small>';
								html += '</div>';
							});
						}
						html += '</div>';
						html += '<button type="button" class="button" onclick="clearQuickArchiveResults()" style="margin-top: 10px;">Clear Results</button>';
						document.getElementById('quick-archive-results').innerHTML = html;
					} else {
						document.getElementById('quick-archive-results').innerHTML = '<div class="result-error">' + response.data + '</div>';
					}
				})
				.catch(error => {
					document.getElementById('quick-archive-results').innerHTML = '<div class="result-error">Preview request failed</div>';
				});
			}
			
			function executeQuickArchive() {
				const ageThreshold = document.getElementById('age-threshold').value;
				const actionType = document.getElementById('action-type').value;
				const batchSize = document.getElementById('batch-size').value;
				const dryRun = document.getElementById('dry-run').checked ? '1' : '0';
				
				if (!confirm('Are you sure you want to ' + (dryRun === '1' ? 'preview' : 'execute') + ' this archive operation?')) {
					return;
				}
				
				document.getElementById('quick-archive-results').innerHTML = '<div class="result-warning">Processing...</div>';
				
				fetch(ajaxurl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams({
						action: 'humanitix_execute_quick_archive',
						age_threshold: ageThreshold,
						action_type: actionType,
						batch_size: batchSize,
						dry_run: dryRun,
						nonce: '<?php echo wp_create_nonce( 'humanitix_archive_nonce' ); ?>'
					})
				})
				.then(response => response.json())
				.then(response => {
					// Debug logging to understand the response structure
					console.log('Quick archive response:', response);
					
					if (response.success) {
						let html = '<div class="result-success">Operation completed successfully!</div>';
						html += '<div class="preview-results">';
						html += '<p><strong>Results:</strong></p>';
						html += '<p>Total processed: ' + response.data.total + '</p>';
						html += '<p>Successful: ' + response.data.successful + '</p>';
						html += '<p>Failed: ' + response.data.failed + '</p>';
						if (response.data.errors && response.data.errors.length > 0) {
							html += '<p><strong>Errors:</strong></p>';
							response.data.errors.forEach(function(error) {
								// Handle different error object structures
								let eventId = 'Unknown';
								let errorMessage = 'Unknown error';
								
								if (typeof error === 'object' && error !== null) {
									eventId = error.event_id || error.id || 'Unknown';
									errorMessage = error.error || error.message || 'Unknown error';
								} else if (typeof error === 'string') {
									errorMessage = error;
								}
								
								html += '<div class="preview-item">Event ID ' + eventId + ': ' + errorMessage + '</div>';
							});
						}
						html += '</div>';
						html += '<button type="button" class="button" onclick="clearQuickArchiveResults()" style="margin-top: 10px;">Clear Results</button>';
						document.getElementById('quick-archive-results').innerHTML = html;
						refreshStats();
					} else {
						document.getElementById('quick-archive-results').innerHTML = '<div class="result-error">' + response.data + '</div>';
					}
				})
				.catch(error => {
					document.getElementById('quick-archive-results').innerHTML = '<div class="result-error">Execution request failed</div>';
				});
			}
			
			function restoreFromBackup() {
				if (!confirm('Are you sure you want to restore events from backup? This will overwrite current events.')) {
					return;
				}
				
				document.getElementById('quick-archive-results').innerHTML = '<div class="result-warning">Restoring from backup...</div>';
				
				fetch(ajaxurl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams({
						action: 'humanitix_restore_from_backup',
						nonce: '<?php echo wp_create_nonce( 'humanitix_archive_nonce' ); ?>'
					})
				})
				.then(response => response.json())
				.then(response => {
					if (response.success) {
						document.getElementById('quick-archive-results').innerHTML = '<div class="result-success">' + response.data.message + '</div>';
						refreshStats();
					} else {
						document.getElementById('quick-archive-results').innerHTML = '<div class="result-error">' + response.data + '</div>';
					}
				})
				.catch(error => {
					document.getElementById('quick-archive-results').innerHTML = '<div class="result-error">Restore request failed</div>';
				});
			}

			function clearQuickArchiveResults() {
				document.getElementById('quick-archive-results').innerHTML = '';
			}
		});
		</script>
		<?php
	}

	/**
	 * Handle manual archive AJAX request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_manual_archive() {
		check_ajax_referer( 'humanitix_archive_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$event_id = intval( $_POST['event_id'] ?? 0 );
		
		if ( ! $event_id ) {
			wp_send_json_error( 'Invalid event ID' );
		}

		$result = $this->archive_manager->archive_event( $event_id );
		
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}

	/**
	 * Handle manual unarchive AJAX request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_manual_unarchive() {
		check_ajax_referer( 'humanitix_archive_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$event_id = intval( $_POST['event_id'] ?? 0 );
		
		if ( ! $event_id ) {
			wp_send_json_error( 'Invalid event ID' );
		}

		$result = $this->archive_manager->unarchive_event( $event_id );
		
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}

	/**
	 * Handle run auto archive AJAX request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_run_auto_archive() {
		check_ajax_referer( 'humanitix_archive_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$result = $this->cron_handler->run_auto_archive();
		wp_send_json_success( $result );
	}

	/**
	 * Handle run monthly archive AJAX request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_run_monthly_archive() {
		check_ajax_referer( 'humanitix_archive_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$result = $this->cron_handler->run_monthly_archive();
		wp_send_json_success( $result );
	}

	/**
	 * Handle get archive stats AJAX request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_get_archive_stats() {
		check_ajax_referer( 'humanitix_archive_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$stats = $this->archive_manager->get_archive_statistics();
		wp_send_json_success( $stats );
	}

	/**
	 * Handle preview quick archive AJAX request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_preview_quick_archive() {
		check_ajax_referer( 'humanitix_archive_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$age_threshold = floatval( $_POST['age_threshold'] ?? 0.5 );
		$action_type = sanitize_text_field( $_POST['action_type'] ?? 'archive' );
		$batch_size = intval( $_POST['batch_size'] ?? 50 );

		if ( $age_threshold < 0.1 || $age_threshold > 10 ) {
			wp_send_json_error( 'Invalid age threshold. Must be between 0.1 and 10 years.' );
		}

		if ( ! in_array( $action_type, array( 'archive', 'delete' ), true ) ) {
			wp_send_json_error( 'Invalid action type.' );
		}

		if ( $batch_size < 1 || $batch_size > 500 ) {
			wp_send_json_error( 'Invalid batch size. Must be between 1 and 500.' );
		}

		$events = $this->archive_manager->get_events_to_process( $age_threshold, $batch_size );
		
		wp_send_json_success( array(
			'total' => count( $events ),
			'events' => array_slice( $events, 0, 10 ), // Show first 10 for preview
			'action_type' => $action_type,
			'age_threshold' => $age_threshold,
		) );
	}

	/**
	 * Handle execute quick archive AJAX request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_execute_quick_archive() {
		check_ajax_referer( 'humanitix_archive_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$age_threshold = floatval( $_POST['age_threshold'] ?? 0.5 );
		$action_type = sanitize_text_field( $_POST['action_type'] ?? 'archive' );
		$batch_size = intval( $_POST['batch_size'] ?? 50 );
		$dry_run = ( $_POST['dry_run'] ?? '0' ) === '1';

		if ( $age_threshold < 0.1 || $age_threshold > 10 ) {
			wp_send_json_error( 'Invalid age threshold. Must be between 0.1 and 10 years.' );
		}

		if ( ! in_array( $action_type, array( 'archive', 'delete' ), true ) ) {
			wp_send_json_error( 'Invalid action type.' );
		}

		if ( $batch_size < 1 || $batch_size > 500 ) {
			wp_send_json_error( 'Invalid batch size. Must be between 1 and 500.' );
		}

		$events = $this->archive_manager->get_events_to_process( $age_threshold, $batch_size );
		$event_ids = $this->archive_manager->get_event_ids_to_process( $age_threshold, $batch_size );
		
		if ( $action_type === 'archive' ) {
			$results = $this->archive_manager->process_archive_batch( $event_ids, $dry_run );
		} else {
			$results = $this->archive_manager->process_delete_batch( $event_ids, $dry_run );
		}

		wp_send_json_success( $results );
	}

	/**
	 * Handle restore from backup AJAX request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_restore_from_backup() {
		check_ajax_referer( 'humanitix_archive_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$result = $this->archive_manager->restore_from_backup();
		
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}
} 