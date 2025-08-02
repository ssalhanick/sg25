<?php
/**
 * Archive Cron Handler Class.
 *
 * Handles automated archiving via WordPress cron jobs.
 * Manages scheduled archiving tasks and rolling monthly archives.
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
 * Archive Cron Handler Class.
 *
 * Handles automated archiving via WordPress cron jobs.
 * Manages scheduled archiving tasks and rolling monthly archives.
 *
 * @package SG\HumanitixApiImporter\Archive
 * @since 1.0.0
 */
class ArchiveCronHandler {

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
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->logger = new Logger();
		$this->archive_manager = new ArchiveManager();
		
		// Register cron hooks
		add_action( 'init', array( $this, 'register_cron_hooks' ) );
		add_action( 'humanitix_auto_archive', array( $this, 'run_auto_archive' ) );
		add_action( 'humanitix_monthly_archive', array( $this, 'run_monthly_archive' ) );
	}

	/**
	 * Register cron hooks and schedules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_cron_hooks() {
		// Add custom cron schedule
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
		
		// Schedule events if not already scheduled
		if ( ! wp_next_scheduled( 'humanitix_auto_archive' ) ) {
			wp_schedule_event( time(), 'daily', 'humanitix_auto_archive' );
		}
		
		if ( ! wp_next_scheduled( 'humanitix_monthly_archive' ) ) {
			// Schedule for first day of each month at 2 AM
			$next_month = strtotime( 'first day of next month 2:00 AM' );
			wp_schedule_event( $next_month, 'monthly', 'humanitix_monthly_archive' );
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
		$schedules['monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => __( 'Monthly', 'sg-humanitix-api-importer' ),
		);
		
		return $schedules;
	}

	/**
	 * Run automated archive process.
	 *
	 * @since 1.0.0
	 * @return array Archive results.
	 */
	public function run_auto_archive() {
		$this->logger->log( 'info', 'Starting automated archive process' );
		
		$settings = $this->get_archive_settings();
		
		if ( ! $settings['archive_enabled'] ) {
			$this->logger->log( 'info', 'Archive feature is disabled' );
			return array(
				'success' => false,
				'message' => 'Archive feature is disabled',
			);
		}

		// Get events to archive
		$events_to_archive = $this->archive_manager->get_events_to_archive(
			$settings['archive_age_threshold'],
			$settings['archive_batch_size']
		);

		if ( empty( $events_to_archive ) ) {
			$this->logger->log( 'info', 'No events to archive' );
			return array(
				'success' => true,
				'message' => 'No events to archive',
				'archived' => 0,
			);
		}

		// Process archive batch
		$results = $this->archive_manager->process_archive_batch(
			$events_to_archive,
			$settings['archive_dry_run']
		);

		// Send notifications if enabled
		if ( $settings['archive_notifications'] && $results['successful'] > 0 ) {
			$this->send_archive_notification( $results );
		}

		$this->logger->log(
			'info',
			'Automated archive process completed',
			array(
				'total' => $results['total'],
				'successful' => $results['successful'],
				'failed' => $results['failed'],
			)
		);

		return array(
			'success' => true,
			'message' => sprintf(
				'Archived %d events, %d failed',
				$results['successful'],
				$results['failed']
			),
			'results' => $results,
		);
	}

	/**
	 * Run monthly rolling archive process.
	 *
	 * @since 1.0.0
	 * @return array Archive results.
	 */
	public function run_monthly_archive() {
		$this->logger->log( 'info', 'Starting monthly rolling archive process' );
		
		$settings = $this->get_archive_settings();
		
		if ( ! $settings['archive_enabled'] ) {
			$this->logger->log( 'info', 'Archive feature is disabled' );
			return array(
				'success' => false,
				'message' => 'Archive feature is disabled',
			);
		}

		// Calculate the month to archive (2 years ago)
		$archive_month = date( 'Y-m', strtotime( '-2 years' ) );
		
		// Get events from that specific month
		$queries = new ArchiveQueries();
		$events_from_month = $queries->get_events_from_month( $archive_month );
		
		if ( empty( $events_from_month ) ) {
			$this->logger->log( 'info', "No events found for month {$archive_month}" );
			return array(
				'success' => true,
				'message' => "No events found for month {$archive_month}",
				'archived' => 0,
			);
		}

		// Process archive batch
		$results = $this->archive_manager->process_archive_batch(
			$events_from_month,
			$settings['archive_dry_run']
		);

		// Send notifications if enabled
		if ( $settings['archive_notifications'] && $results['successful'] > 0 ) {
			$this->send_monthly_archive_notification( $results, $archive_month );
		}

		$this->logger->log(
			'info',
			'Monthly rolling archive process completed',
			array(
				'month' => $archive_month,
				'total' => $results['total'],
				'successful' => $results['successful'],
				'failed' => $results['failed'],
			)
		);

		return array(
			'success' => true,
			'message' => sprintf(
				'Monthly archive for %s: %d events archived, %d failed',
				$archive_month,
				$results['successful'],
				$results['failed']
			),
			'results' => $results,
			'month' => $archive_month,
		);
	}

	/**
	 * Send archive notification email.
	 *
	 * @since 1.0.0
	 * @param array $results Archive results.
	 * @return bool Whether email was sent successfully.
	 */
	private function send_archive_notification( $results ) {
		$admin_email = get_option( 'admin_email' );
		$site_name = get_bloginfo( 'name' );
		
		$subject = sprintf( '[%s] Event Archive Report', $site_name );
		
		$message = sprintf(
			"Event Archive Report\n\n" .
			"Total events processed: %d\n" .
			"Successfully archived: %d\n" .
			"Failed: %d\n\n" .
			"Time: %s\n" .
			"Site: %s",
			$results['total'],
			$results['successful'],
			$results['failed'],
			current_time( 'mysql' ),
			get_site_url()
		);

		if ( ! empty( $results['errors'] ) ) {
			$message .= "\n\nErrors:\n";
			foreach ( $results['errors'] as $error ) {
				$message .= sprintf( "- Event %d: %s\n", $error['event_id'], $error['error'] );
			}
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		
		$sent = wp_mail( $admin_email, $subject, $message, $headers );
		
		$this->logger->log(
			$sent ? 'info' : 'error',
			'Archive notification email ' . ( $sent ? 'sent' : 'failed' ),
			array( 'admin_email' => $admin_email )
		);

		return $sent;
	}

	/**
	 * Send monthly archive notification email.
	 *
	 * @since 1.0.0
	 * @param array  $results Archive results.
	 * @param string $month   Archived month.
	 * @return bool Whether email was sent successfully.
	 */
	private function send_monthly_archive_notification( $results, $month ) {
		$admin_email = get_option( 'admin_email' );
		$site_name = get_bloginfo( 'name' );
		
		$subject = sprintf( '[%s] Monthly Event Archive Report - %s', $site_name, $month );
		
		$message = sprintf(
			"Monthly Event Archive Report\n\n" .
			"Month: %s\n" .
			"Total events processed: %d\n" .
			"Successfully archived: %d\n" .
			"Failed: %d\n\n" .
			"Time: %s\n" .
			"Site: %s",
			$month,
			$results['total'],
			$results['successful'],
			$results['failed'],
			current_time( 'mysql' ),
			get_site_url()
		);

		if ( ! empty( $results['errors'] ) ) {
			$message .= "\n\nErrors:\n";
			foreach ( $results['errors'] as $error ) {
				$message .= sprintf( "- Event %d: %s\n", $error['event_id'], $error['error'] );
			}
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		
		$sent = wp_mail( $admin_email, $subject, $message, $headers );
		
		$this->logger->log(
			$sent ? 'info' : 'error',
			'Monthly archive notification email ' . ( $sent ? 'sent' : 'failed' ),
			array( 'admin_email' => $admin_email, 'month' => $month )
		);

		return $sent;
	}

	/**
	 * Get archive settings.
	 *
	 * @since 1.0.0
	 * @return array Archive settings.
	 */
	private function get_archive_settings() {
		$defaults = array(
			'archive_enabled'        => false,
			'archive_age_threshold'  => 2, // years
			'archive_frequency'      => 'monthly',
			'archive_post_status'    => 'archived',
			'archive_batch_size'     => 50,
			'archive_notifications'  => true,
			'archive_dry_run'        => false,
		);

		$options = get_option( 'humanitix_importer_options', array() );
		
		return wp_parse_args( $options, $defaults );
	}

	/**
	 * Clear all scheduled cron events.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function clear_scheduled_events() {
		wp_clear_scheduled_hook( 'humanitix_auto_archive' );
		wp_clear_scheduled_hook( 'humanitix_monthly_archive' );
		
		$this->logger->log( 'info', 'Cleared all scheduled archive events' );
	}

	/**
	 * Get next scheduled run times.
	 *
	 * @since 1.0.0
	 * @return array Next run times.
	 */
	public function get_next_run_times() {
		$next_auto = wp_next_scheduled( 'humanitix_auto_archive' );
		$next_monthly = wp_next_scheduled( 'humanitix_monthly_archive' );
		
		return array(
			'auto_archive' => $next_auto ? date( 'Y-m-d H:i:s', $next_auto ) : 'Not scheduled',
			'monthly_archive' => $next_monthly ? date( 'Y-m-d H:i:s', $next_monthly ) : 'Not scheduled',
		);
	}
} 