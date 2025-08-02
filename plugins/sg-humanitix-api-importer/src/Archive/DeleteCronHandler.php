<?php
/**
 * Delete Cron Handler Class.
 *
 * Handles automated deletion of archived events via WordPress cron jobs.
 * Manages scheduled deletion tasks and notifications.
 *
 * @package SG\HumanitixApiImporter\Archive
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Archive;

use SG\HumanitixApiImporter\Admin\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Delete Cron Handler Class.
 *
 * Handles automated deletion of archived events via WordPress cron jobs.
 * Manages scheduled deletion tasks and notifications.
 *
 * @package SG\HumanitixApiImporter\Archive
 * @since 1.0.0
 */
class DeleteCronHandler {

	/**
	 * The logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * The delete manager instance.
	 *
	 * @var DeleteManager
	 */
	private $delete_manager;

	/**
	 * Constructor.
	 *
	 * Initializes the cron handler and registers cron hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->logger = new Logger();
		$this->delete_manager = new DeleteManager();

		// Register cron hooks
		add_action( 'init', array( $this, 'register_cron_hooks' ) );
		add_action( 'humanitix_auto_delete', array( $this, 'run_auto_delete' ) );
		add_action( 'humanitix_cleanup_backups', array( $this, 'run_backup_cleanup' ) );

		// Add custom cron schedules
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
	}

	/**
	 * Register cron hooks and schedules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_cron_hooks() {
		$settings = $this->get_delete_settings();

		// Only schedule if deletion is enabled
		if ( ! $settings['delete_enabled'] ) {
			$this->clear_scheduled_events();
			return;
		}

		// Schedule auto deletion (daily)
		if ( ! wp_next_scheduled( 'humanitix_auto_delete' ) ) {
			wp_schedule_event( time(), 'daily', 'humanitix_auto_delete' );
		}

		// Schedule backup cleanup (weekly)
		if ( ! wp_next_scheduled( 'humanitix_cleanup_backups' ) ) {
			wp_schedule_event( time(), 'weekly', 'humanitix_cleanup_backups' );
		}
	}

	/**
	 * Add custom cron schedules.
	 *
	 * @since 1.0.0
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified cron schedules.
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['weekly'] = array(
			'interval' => 604800, // 7 days
			'display'  => 'Weekly',
		);

		return $schedules;
	}

	/**
	 * Run automatic deletion of old archived events.
	 *
	 * @since 1.0.0
	 * @return array Deletion results.
	 */
	public function run_auto_delete() {
		$settings = $this->get_delete_settings();

		if ( ! $settings['delete_enabled'] ) {
			return array(
				'success' => false,
				'message' => 'Delete feature is disabled',
			);
		}

		$this->logger->log( 'info', 'Starting automatic deletion process' );

		// Get events to delete
		$events_to_delete = $this->delete_manager->get_events_to_delete(
			$settings['delete_age_threshold'],
			$settings['delete_batch_size']
		);

		if ( empty( $events_to_delete ) ) {
			$result = array(
				'success' => true,
				'message' => 'No events found for deletion',
				'deleted_count' => 0,
			);

			$this->logger->log( 'info', 'No events found for deletion' );
			return $result;
		}

		// Process deletion batch
		$batch_results = $this->delete_manager->process_delete_batch(
			$events_to_delete,
			$settings['delete_dry_run']
		);

		$result = array(
			'success' => $batch_results['failed'] === 0,
			'message' => sprintf(
				'Processed %d events: %d successful, %d failed',
				$batch_results['total'],
				$batch_results['successful'],
				$batch_results['failed']
			),
			'deleted_count' => $batch_results['successful'],
			'failed_count' => $batch_results['failed'],
			'errors' => $batch_results['errors'],
		);

		// Send notification if enabled
		if ( $settings['delete_notifications'] ) {
			$this->send_delete_notification( $result );
		}

		$this->logger->log(
			'info',
			'Automatic deletion process completed',
			array(
				'total_processed' => $batch_results['total'],
				'successful' => $batch_results['successful'],
				'failed' => $batch_results['failed'],
				'dry_run' => $settings['delete_dry_run'],
			)
		);

		return $result;
	}

	/**
	 * Run backup cleanup process.
	 *
	 * @since 1.0.0
	 * @return array Cleanup results.
	 */
	public function run_backup_cleanup() {
		$settings = $this->get_delete_settings();
		$recovery_period = $settings['delete_recovery_period'] ?? 30;

		$this->logger->log( 'info', 'Starting backup cleanup process' );

		$cleaned_count = $this->delete_manager->cleanup_old_backups( $recovery_period );

		$result = array(
			'success' => true,
			'message' => sprintf( 'Cleaned up %d old backups', $cleaned_count ),
			'cleaned_count' => $cleaned_count,
		);

		$this->logger->log(
			'info',
			'Backup cleanup process completed',
			array(
				'cleaned_count' => $cleaned_count,
				'recovery_period' => $recovery_period,
			)
		);

		return $result;
	}

	/**
	 * Send deletion notification email.
	 *
	 * @since 1.0.0
	 * @param array $result Deletion results.
	 * @return void
	 */
	private function send_delete_notification( $result ) {
		$admin_email = get_option( 'admin_email' );
		$site_name = get_bloginfo( 'name' );

		$subject = sprintf( '[%s] Event Deletion Report', $site_name );
		
		$message = sprintf(
			"Event deletion process completed on %s.\n\n" .
			"Results:\n" .
			"- Total processed: %d\n" .
			"- Successfully deleted: %d\n" .
			"- Failed: %d\n" .
			"- Message: %s\n\n" .
			"View detailed logs in the WordPress admin.",
			date( 'Y-m-d H:i:s' ),
			$result['deleted_count'] + $result['failed_count'],
			$result['deleted_count'],
			$result['failed_count'],
			$result['message']
		);

		if ( ! empty( $result['errors'] ) ) {
			$message .= "\n\nErrors:\n";
			foreach ( $result['errors'] as $error ) {
				$message .= sprintf( "- Event ID %d: %s\n", $error['event_id'], $error['error'] );
			}
		}

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Get delete settings.
	 *
	 * @since 1.0.0
	 * @return array Delete settings.
	 */
	private function get_delete_settings() {
		$options = get_option( 'humanitix_importer_options', array() );
		
		return array(
			'delete_enabled'           => isset( $options['delete_enabled'] ) ? $options['delete_enabled'] : false,
			'delete_age_threshold'     => isset( $options['delete_age_threshold'] ) ? floatval( $options['delete_age_threshold'] ) : 5.0,
			'delete_recovery_period'   => isset( $options['delete_recovery_period'] ) ? intval( $options['delete_recovery_period'] ) : 30,
			'delete_batch_size'        => isset( $options['delete_batch_size'] ) ? intval( $options['delete_batch_size'] ) : 25,
			'delete_notifications'     => isset( $options['delete_notifications'] ) ? $options['delete_notifications'] : true,
			'delete_dry_run'           => isset( $options['delete_dry_run'] ) ? $options['delete_dry_run'] : true,
			'delete_backup_enabled'    => isset( $options['delete_backup_enabled'] ) ? $options['delete_backup_enabled'] : true,
		);
	}

	/**
	 * Clear scheduled cron events.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function clear_scheduled_events() {
		wp_clear_scheduled_hook( 'humanitix_auto_delete' );
		wp_clear_scheduled_hook( 'humanitix_cleanup_backups' );
	}

	/**
	 * Get next scheduled run times.
	 *
	 * @since 1.0.0
	 * @return array Next run times.
	 */
	public function get_next_run_times() {
		return array(
			'auto_delete' => wp_next_scheduled( 'humanitix_auto_delete' ),
			'backup_cleanup' => wp_next_scheduled( 'humanitix_cleanup_backups' ),
		);
	}
} 