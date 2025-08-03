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
		.manual-controls {
			margin: 15px 0;
		}
		.manual-controls .button {
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
		#archive-results {
			margin-top: 15px;
			padding: 10px;
			border-radius: 4px;
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
		</style>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			document.getElementById('run-auto-archive').addEventListener('click', function() {
				runArchiveAction('humanitix_run_auto_archive', 'Running auto archive...');
			});
			
			document.getElementById('run-monthly-archive').addEventListener('click', function() {
				runArchiveAction('humanitix_run_monthly_archive', 'Running monthly archive...');
			});
			
			document.getElementById('refresh-stats').addEventListener('click', function() {
				refreshStats();
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
						document.getElementById('archive-results').innerHTML = '<div class="result-success">' + response.data.message + '</div>';
						refreshStats();
					} else {
						document.getElementById('archive-results').innerHTML = '<div class="result-error">' + response.data + '</div>';
					}
				})
				.catch(error => {
					document.getElementById('archive-results').innerHTML = '<div class="result-error">Request failed</div>';
				});
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
						location.reload();
					}
				})
				.catch(error => {
					console.error('Error refreshing stats:', error);
				});
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
} 