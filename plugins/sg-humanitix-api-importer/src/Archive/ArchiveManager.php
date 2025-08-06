<?php
/**
 * Archive Manager Class.
 *
 * Handles the archiving of TEC events that are older than a specified threshold.
 * Manages archive operations, custom post status, and archive validation.
 *
 * @package SG\HumanitixApiImporter\Archive
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Archive;

use SG\HumanitixApiImporter\Admin\Logger;
use SG\HumanitixApiImporter\Admin\ErrorCode;
use SG\HumanitixApiImporter\Archive\ArchiveQueries;
use SG\HumanitixApiImporter\Archive\ArchiveValidator;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Archive Manager Class.
 *
 * Handles the archiving of TEC events that are older than a specified threshold.
 * Manages archive operations, custom post status, and archive validation.
 *
 * @package SG\HumanitixApiImporter\Archive
 * @since 1.0.0
 */
class ArchiveManager {

	/**
	 * The logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * The archive queries instance.
	 *
	 * @var ArchiveQueries
	 */
	private $queries;

	/**
	 * The archive validator instance.
	 *
	 * @var ArchiveValidator
	 */
	private $validator;

	/**
	 * Archive settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Static instance to prevent multiple instances.
	 *
	 * @var ArchiveManager
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 *
	 * Initializes the archive manager and registers custom post status.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Prevent multiple instances
		if ( self::$instance !== null ) {
			// Return the existing instance properties
			$this->logger = self::$instance->logger;
			$this->queries = self::$instance->queries;
			$this->validator = self::$instance->validator;
			$this->settings = self::$instance->settings;
			return;
		}
		self::$instance = $this;
		
		$this->logger = new Logger();
		$this->queries = new ArchiveQueries();
		$this->validator = new ArchiveValidator();
		$this->settings = $this->get_archive_settings();
		
		// Register custom post status with higher priority to ensure it runs after TEC
		add_action( 'init', array( $this, 'register_archive_post_status' ), 20 );
		
		// Add TEC-specific integration to ensure archived status is recognized
		add_action( 'tribe_events_register_post_type', array( $this, 'register_archive_post_status' ) );
		
		// Add archive status to admin filters
		add_action( 'admin_footer-post.php', array( $this, 'add_archive_status_to_dropdown' ) );
		add_action( 'admin_footer-edit.php', array( $this, 'add_archive_status_to_dropdown' ) );
		
		// Add TEC-specific hooks for better integration (consolidated to prevent duplicates)
		if ( ! has_filter( 'tribe_events_admin_list_table_statuses', array( $this, 'add_archived_to_tec_statuses' ) ) ) {
			add_filter( 'tribe_events_admin_list_table_statuses', array( $this, 'add_archived_to_tec_statuses' ) );
		}
		
		// Add TEC-specific event counting integration
		if ( ! has_filter( 'tribe_events_admin_list_table_statuses', array( $this, 'adjust_tec_event_counts' ) ) ) {
			add_filter( 'tribe_events_admin_list_table_statuses', array( $this, 'adjust_tec_event_counts' ) );
		}
		
		add_action( 'admin_footer-edit.php', array( $this, 'add_tec_count_adjustment' ) );
		
		// Add debug logging for post status registration
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			add_action( 'init', array( $this, 'debug_post_status_registration' ), 25 );
			add_action( 'admin_notices', array( $this, 'debug_admin_notice' ) );
		}
		
		// Add debug admin interface for manual testing
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	
		}
		
		// Add hooks to ensure archived status is available in dropdowns
		add_filter( 'display_post_states', array( $this, 'add_archived_post_state' ), 10, 2 );
		add_action( 'post_submitbox_misc_actions', array( $this, 'add_archived_to_submitbox' ) );
		
		// Add filter to ensure archived status is available in post status lists
		add_filter( 'get_post_statuses', array( $this, 'add_archived_to_post_statuses' ) );
		
		// Add support for quick edit and bulk edit
		add_action( 'quick_edit_custom_box', array( $this, 'add_archived_to_quick_edit' ), 10, 2 );
		add_action( 'bulk_edit_custom_box', array( $this, 'add_archived_to_bulk_edit' ), 10, 2 );
		
		// Add JavaScript to populate quick edit dropdowns
		add_action( 'admin_footer-edit.php', array( $this, 'add_quick_edit_script' ) );
		
		// Add support for quick edit form
		add_action( 'quick_edit_custom_box', array( $this, 'add_archived_to_quick_edit_form' ), 10, 2 );
		add_filter( 'quick_edit_dropdown_pages_args', array( $this, 'modify_quick_edit_args' ) );
		
		// Add filter to ensure archived status is available in quick edit
		add_filter( 'wp_dropdown_pages', array( $this, 'add_archived_to_dropdown_pages' ), 10, 2 );
		
		// Add support for WordPress admin hooks
		add_action( 'admin_head-edit.php', array( $this, 'add_admin_head_script' ) );
		add_filter( 'wp_insert_post_data', array( $this, 'handle_quick_edit_archive' ), 10, 2 );
	}

	/**
	 * Register the 'archived' post status.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_archive_post_status() {
		register_post_status(
			'archived',
			array(
				'label'                     => _x( 'Archived', 'post status', 'sg-humanitix-api-importer' ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop(
					'Archived <span class="count">(%s)</span>',
					'Archived <span class="count">(%s)</span>',
					'sg-humanitix-api-importer'
				),
			)
		);
	}

	/**
	 * Add archive status to post status dropdown.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_archive_status_to_dropdown() {
		global $post_type;
		
		if ( $post_type === 'tribe_events' ) {
			?>
			<script>
			document.addEventListener('DOMContentLoaded', function() {
				// Add to main post status dropdown
				const postStatusSelect = document.getElementById('post_status');
				if (postStatusSelect) {
					const archivedOption = document.createElement('option');
					archivedOption.value = 'archived';
					archivedOption.textContent = '<?php esc_html_e( 'Archived', 'sg-humanitix-api-importer' ); ?>';
					postStatusSelect.appendChild(archivedOption);
				}
				
				// Add to publish box dropdown
				const publishSelect = document.getElementById('publish');
				if (publishSelect) {
					const archivedOption = document.createElement('option');
					archivedOption.value = 'archived';
					archivedOption.textContent = '<?php esc_html_e( 'Archived', 'sg-humanitix-api-importer' ); ?>';
					publishSelect.appendChild(archivedOption);
				}
				
				// Add to TEC-specific dropdowns
				const statusSelects = document.querySelectorAll('select[name="post_status"]');
				statusSelects.forEach(function(select) {
					if (!select.querySelector('option[value="archived"]')) {
						const archivedOption = document.createElement('option');
						archivedOption.value = 'archived';
						archivedOption.textContent = '<?php esc_html_e( 'Archived', 'sg-humanitix-api-importer' ); ?>';
						select.appendChild(archivedOption);
					}
				});
			});
			</script>
			<?php
		}
	}

	/**
	 * Add archived status to TEC admin list table statuses.
	 *
	 * @since 1.0.0
	 * @param array $statuses Existing TEC statuses.
	 * @return array Modified statuses including archived.
	 */
	public function add_archived_to_tec_statuses( $statuses ) {
		if ( ! isset( $statuses['archived'] ) ) {
			$statuses['archived'] = __( 'Archived', 'sg-humanitix-api-importer' );
		}
		return $statuses;
	}

	/**
	 * Debug post status registration for troubleshooting.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function debug_post_status_registration() {
		global $post_type;
		
		if ( is_admin() && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'tribe_events' ) {
			$statuses = get_post_stati();
			$tec_statuses = apply_filters( 'tribe_events_admin_list_table_statuses', array() );
			
			error_log( '[ArchiveManager] Available post statuses: ' . print_r( array_keys( $statuses ), true ) );
			error_log( '[ArchiveManager] TEC admin statuses: ' . print_r( $tec_statuses, true ) );
			error_log( '[ArchiveManager] Archived status registered: ' . ( isset( $statuses['archived'] ) ? 'YES' : 'NO' ) );
		}
	}

	/**
	 * Debug admin notice for troubleshooting.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function debug_admin_notice() {
		global $post_type;
		
		if ( is_admin() && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'tribe_events' ) {
			$statuses = get_post_stati();
			$tec_statuses = apply_filters( 'tribe_events_admin_list_table_statuses', array() );
			
			// Safety check for queries
			try {
				if ( ! $this->queries ) {
					$this->queries = new ArchiveQueries();
				}
				$archived_count = $this->queries->get_archived_events_count();
			} catch ( \Exception $e ) {
				$archived_count = 'Error: ' . $e->getMessage();
			}
			
			echo '<div class="notice notice-info">';
			echo '<p><strong>ArchiveManager Debug:</strong></p>';
			echo '<p>Available post statuses: ' . implode( ', ', array_keys( $statuses ) ) . '</p>';
			echo '<p>Archived events count: ' . $archived_count . '</p>';
			echo '<p>Archived status registered: ' . ( isset( $statuses['archived'] ) ? 'YES' : 'NO' ) . '</p>';
			echo '<p>TEC statuses: ' . implode( ', ', array_keys( $tec_statuses ) ) . '</p>';
			
			
			
			echo '</div>';
		}
	}



	/**
	 * Adjust TEC event counts to properly handle archived events.
	 *
	 * @since 1.0.0
	 * @param array $statuses Existing TEC statuses.
	 * @return array Modified statuses with proper counting.
	 */
	public function adjust_tec_event_counts( $statuses ) {
		// Ensure archived events are counted separately from published
		if ( ! isset( $statuses['archived'] ) ) {
			$statuses['archived'] = __( 'Archived', 'sg-humanitix-api-importer' );
		}
		return $statuses;
	}

	/**
	 * Add JavaScript to adjust TEC event counts in the admin interface.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_tec_count_adjustment() {
		global $post_type;
		
		if ( $post_type === 'tribe_events' ) {
			// Safety check for queries
			try {
				if ( ! $this->queries ) {
					$this->queries = new ArchiveQueries();
				}
				$archived_count = $this->queries->get_archived_events_count();
			} catch ( \Exception $e ) {
				$archived_count = 0;
			}
			?>
			<script>
			document.addEventListener('DOMContentLoaded', function() {
				// Add archived count to the status list if it doesn't exist
				const statusList = document.querySelector('.subsubsub');
				if (statusList && !statusList.querySelector('a[href*="post_status=archived"]')) {
					const archivedLink = document.createElement('a');
					archivedLink.href = '<?php echo esc_url( add_query_arg( 'post_status', 'archived', admin_url( 'edit.php?post_type=tribe_events' ) ) ); ?>';
					archivedLink.innerHTML = '<?php esc_html_e( 'Archived', 'sg-humanitix-api-importer' ); ?> <span class="count">(<?php echo esc_html( $archived_count ); ?>)</span>';
					statusList.appendChild(document.createTextNode(' | '));
					statusList.appendChild(archivedLink);
				}
			});
			</script>
			<?php
		}
	}

	/**
	 * Obfuscate sensitive data for logging.
	 *
	 * @since 1.0.0
	 * @param mixed $data The data to obfuscate.
	 * @return mixed The obfuscated data.
	 */
	private function obfuscate_sensitive_data( $data ) {
		if ( is_array( $data ) ) {
			$sensitive_keys = array( 'api_key', 'token', 'password', 'secret', 'key', 'auth' );
			foreach ( $data as $key => $value ) {
				if ( in_array( strtolower( $key ), $sensitive_keys, true ) ) {
					if ( is_string( $value ) && strlen( $value ) > 8 ) {
						$data[ $key ] = substr( $value, 0, 8 ) . '...' . substr( $value, -4 );
					} else {
						$data[ $key ] = '[REDACTED]';
					}
				} elseif ( is_array( $value ) ) {
					$data[ $key ] = $this->obfuscate_sensitive_data( $value );
				}
			}
		}
		return $data;
	}

	/**
	 * Get archive settings.
	 *
	 * @since 1.0.0
	 * @return array Archive settings.
	 */
	private function get_archive_settings() {
		$defaults = array(
			'archive_enabled'        => true, // Changed to true for testing
			'archive_age_threshold'  => 2, // years
			'archive_frequency'      => 'monthly',
			'archive_post_status'    => 'archived',
			'archive_batch_size'     => 50,
			'archive_notifications'  => true,
			'archive_dry_run'        => false,
		);

		$options = get_option( 'humanitix_importer_options', array() );
		$settings = wp_parse_args( $options, $defaults );
		
		// Debug logging for settings (obfuscated)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$obfuscated_settings = $this->obfuscate_sensitive_data( $settings );
			error_log( '[ArchiveManager] Archive settings: ' . print_r( $obfuscated_settings, true ) );
		}
		
		return $settings;
	}

	/**
	 * Archive a single event.
	 *
	 * @since 1.0.0
	 * @param int $event_id The event ID to archive.
	 * @return array Archive result with success status and message.
	 */
	public function archive_event( $event_id ) {
		$validation = $this->validate_archive_operation( $event_id );
		if ( ! $validation['success'] ) {
			return array(
				'success' => false,
				'message' => $validation['message'],
			);
		}

		$event = get_post( $event_id );
		if ( ! $event || 'tribe_events' !== $event->post_type ) {
			return array(
				'success' => false,
				'message' => 'Invalid event ID',
			);
		}

		// Check if already archived
		if ( 'archived' === $event->post_status ) {
			return array(
				'success' => false,
				'message' => 'Event is already archived',
			);
		}

		// Create backup before archiving
		$backup_created = $this->create_event_backup( $event_id );
		if ( ! $backup_created ) {
			return array(
				'success' => false,
				'message' => 'Failed to create backup',
			);
		}

		// Archive the event
		$update_result = wp_update_post( array(
			'ID'          => $event_id,
			'post_status' => $this->settings['archive_post_status'],
		), true );

		if ( is_wp_error( $update_result ) ) {
			$this->logger->log(
				'error',
				'Failed to archive event',
				array(
					'event_id' => $event_id,
					'error'    => $update_result->get_error_message(),
				)
			);

			return array(
				'success' => false,
				'message' => 'Failed to update event status: ' . $update_result->get_error_message(),
			);
		}

		// Add archive metadata
		update_post_meta( $event_id, '_event_archived_date', current_time( 'mysql' ) );
		update_post_meta( $event_id, '_event_archived_by', 'system' );

		$this->logger->log(
			'info',
			'Event archived successfully',
			array(
				'event_id'    => $event_id,
				'event_title' => $event->post_title,
				'archive_date' => current_time( 'mysql' ),
			)
		);

		return array(
			'success' => true,
			'message' => 'Event archived successfully',
			'event_id' => $event_id,
		);
	}

	/**
	 * Unarchive a single event.
	 *
	 * @since 1.0.0
	 * @param int $event_id The event ID to unarchive.
	 * @return array Unarchive result with success status and message.
	 */
	public function unarchive_event( $event_id ) {
		$event = get_post( $event_id );
		if ( ! $event || 'tribe_events' !== $event->post_type ) {
			return array(
				'success' => false,
				'message' => 'Invalid event ID',
			);
		}

		if ( 'archived' !== $event->post_status ) {
			return array(
				'success' => false,
				'message' => 'Event is not archived',
			);
		}

		// Restore the event
		$update_result = wp_update_post( array(
			'ID'          => $event_id,
			'post_status' => 'publish',
		), true );

		if ( is_wp_error( $update_result ) ) {
			$this->logger->log(
				'error',
				'Failed to unarchive event',
				array(
					'event_id' => $event_id,
					'error'    => $update_result->get_error_message(),
				)
			);

			return array(
				'success' => false,
				'message' => 'Failed to restore event: ' . $update_result->get_error_message(),
			);
		}

		// Remove archive metadata
		delete_post_meta( $event_id, '_event_archived_date' );
		delete_post_meta( $event_id, '_event_archived_by' );

		$this->logger->log(
			'info',
			'Event unarchived successfully',
			array(
				'event_id'    => $event_id,
				'event_title' => $event->post_title,
				'restore_date' => current_time( 'mysql' ),
			)
		);

		return array(
			'success' => true,
			'message' => 'Event restored successfully',
			'event_id' => $event_id,
		);
	}

	/**
	 * Get events that should be archived based on age threshold.
	 *
	 * @since 1.0.0
	 * @param int $age_threshold Age threshold in years.
	 * @param int $limit Maximum number of events to return.
	 * @return array Array of event IDs to archive.
	 */
	public function get_events_to_archive( $age_threshold = null, $limit = null ) {
		try {
			if ( ! $this->queries ) {
				$this->queries = new ArchiveQueries();
			}
			
			if ( null === $age_threshold ) {
				$age_threshold = $this->settings['archive_age_threshold'];
			}

			if ( null === $limit ) {
				$limit = $this->settings['archive_batch_size'];
			}

			// Debug logging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[ArchiveManager] Getting events to archive with threshold: ' . $age_threshold . ' years, limit: ' . $limit );
			}

			$events = $this->queries->get_events_older_than( $age_threshold, $limit );

			// Debug logging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[ArchiveManager] Found ' . count( $events ) . ' events to archive' );
				if ( ! empty( $events ) ) {
					error_log( '[ArchiveManager] Event IDs to archive: ' . print_r( $events, true ) );
				}
			}

			return $events;
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[ArchiveManager] Error getting events to archive: ' . $e->getMessage() );
			}
			return array();
		}
	}

	/**
	 * Process a batch of events for archiving.
	 *
	 * @since 1.0.0
	 * @param array $events Array of event IDs to archive.
	 * @param bool  $dry_run Whether to perform a dry run.
	 * @return array Batch processing results.
	 */
	public function process_archive_batch( $events, $dry_run = false ) {
		$results = array(
			'total'      => count( $events ),
			'successful' => 0,
			'failed'     => 0,
			'errors'     => array(),
		);

		foreach ( $events as $event_id ) {
			if ( $dry_run ) {
				$results['successful']++;
				continue;
			}

			$result = $this->archive_event( $event_id );
			
			if ( $result['success'] ) {
				$results['successful']++;
			} else {
				$results['failed']++;
				$results['errors'][] = array(
					'event_id' => $event_id,
					'error'    => $result['message'],
				);
			}
		}

		$this->logger->log(
			'info',
			'Archive batch processing completed',
			array(
				'total'      => $results['total'],
				'successful' => $results['successful'],
				'failed'     => $results['failed'],
				'dry_run'    => $dry_run,
			)
		);

		return $results;
	}

	/**
	 * Validate archive operation for an event.
	 *
	 * @since 1.0.0
	 * @param int $event_id The event ID to validate.
	 * @return bool Whether the archive operation is valid.
	 */
	public function validate_archive_operation( $event_id ) {
		// Check if event exists
		$event = get_post( $event_id );
		if ( ! $event ) {
			return array(
				'success' => false,
				'message' => 'Event does not exist',
			);
		}

		// Check if it's a TEC event
		if ( 'tribe_events' !== $event->post_type ) {
			return array(
				'success' => false,
				'message' => 'Not a TEC event',
			);
		}

		// Check if already archived
		if ( 'archived' === $event->post_status ) {
			return array(
				'success' => false,
				'message' => 'Event is already archived',
			);
		}

		// Check if event has start date (try multiple possible meta keys)
		$start_date = get_post_meta( $event_id, '_EventStartDate', true );
		if ( empty( $start_date ) ) {
			// Try alternative meta keys
			$start_date = get_post_meta( $event_id, 'event_start_date', true );
		}
		if ( empty( $start_date ) ) {
			$start_date = get_post_meta( $event_id, 'start_date', true );
		}
		if ( empty( $start_date ) ) {
			// If no start date found, we'll still allow archiving but log it
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[ArchiveManager] Event ' . $event_id . ' has no start date, but allowing archive' );
			}
		}

		return array(
			'success' => true,
			'message' => 'Event validation passed',
		);
	}

	/**
	 * Create a backup of an event before archiving.
	 *
	 * @since 1.0.0
	 * @param int $event_id The event ID to backup.
	 * @return bool Whether backup was successful.
	 */
	private function create_event_backup( $event_id ) {
		$event = get_post( $event_id );
		if ( ! $event ) {
			return false;
		}

		// Get all event meta
		$meta = get_post_meta( $event_id );
		
		// Create backup data
		$backup_data = array(
			'post_data' => array(
				'ID'           => $event->ID,
				'post_title'   => $event->post_title,
				'post_content' => $event->post_content,
				'post_status'  => $event->post_status,
				'post_date'    => $event->post_date,
				'post_modified' => $event->post_modified,
			),
			'meta_data' => $meta,
			'backup_date' => current_time( 'mysql' ),
		);

		// Store backup in post meta
		$backup_stored = update_post_meta( $event_id, '_event_archive_backup', $backup_data );
		
		if ( $backup_stored ) {
			$this->logger->log(
				'info',
				'Event backup created',
				array(
					'event_id' => $event_id,
					'backup_date' => current_time( 'mysql' ),
				)
			);
		}

		return $backup_stored;
	}

	/**
	 * Get archive statistics.
	 *
	 * @since 1.0.0
	 * @return array Archive statistics.
	 */
	public function get_archive_statistics() {
		try {
			if ( ! $this->queries ) {
				$this->queries = new ArchiveQueries();
			}
			return $this->queries->get_archive_statistics();
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[ArchiveManager] Error getting archive statistics: ' . $e->getMessage() );
			}
			// Return default statistics if there's an error
			return array(
				'total_archived' => 0,
				'total_events'   => 0,
				'archived_this_month' => 0,
				'events_to_archive' => 0,
			);
		}
	}

	/**
	 * Manually trigger archive process for testing.
	 *
	 * @since 1.0.0
	 * @param int $age_threshold Age threshold in years (optional).
	 * @param int $limit Maximum number of events to process (optional).
	 * @return array Archive results.
	 */
	public function manual_trigger_archive( $age_threshold = null, $limit = null ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ArchiveManager] Manual archive trigger called with threshold: ' . ( $age_threshold ?? 'default' ) . ', limit: ' . ( $limit ?? 'default' ) );
		}

		// Get events to archive
		$events_to_archive = $this->get_events_to_archive( $age_threshold, $limit );

		if ( empty( $events_to_archive ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[ArchiveManager] No events found for manual archive' );
			}
			return array(
				'success' => false,
				'message' => 'No events found to archive',
				'events_found' => 0,
			);
		}

		// Process archive batch
		$results = $this->process_archive_batch( $events_to_archive, false );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ArchiveManager] Manual archive results: ' . print_r( $results, true ) );
		}

		return array(
			'success' => true,
			'message' => sprintf(
				'Manual archive completed: %d successful, %d failed',
				$results['successful'],
				$results['failed']
			),
			'results' => $results,
		);
	}

	/**
	 * Add archived post state to the post submit box.
	 *
	 * @since 1.0.0
	 * @param array $post_states Array of post states.
	 * @param WP_Post $post The post object.
	 * @return array Modified post states.
	 */
	public function add_archived_post_state( $post_states, $post ) {
		if ( 'tribe_events' === $post->post_type && 'archived' === $post->post_status ) {
			$post_states['archived'] = __( 'Archived', 'sg-humanitix-api-importer' );
		}
		return $post_states;
	}

	/**
	 * Add archived status to the post submit box.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_archived_to_submitbox() {
		global $post;

		if ( $post && 'tribe_events' === $post->post_type ) {
			$status = $post->post_status;
			$archived_status = 'archived';

			if ( $status === $archived_status ) {
				echo '<div class="misc-pub-section">';
				echo '<label for="post_status">' . __( 'Status', 'sg-humanitix-api-importer' ) . ':</label> ';
				echo '<span id="post-status-display">';
				echo esc_html( get_post_status_object( $status )->label );
				echo '</span>';
				echo '<script>jQuery(document).ready(function($) { $(\'#post-status-display\').text(\'' . esc_js( get_post_status_object( $status )->label ) . '\'); });</script>';
				echo '<a href="' . esc_url( add_query_arg( array( 'post_status' => $archived_status, 'post' => $post->ID ), admin_url( 'edit.php?post_type=tribe_events' ) ) ) . '" class="edit-status hide-if-js">' . __( 'Edit', 'sg-humanitix-api-importer' ) . '</a>';
				echo '<div class="hidden">';
				wp_dropdown_pages( array(
					'post_type' => 'tribe_events',
					'selected'  => $post->ID,
					'name'      => 'post_status',
					'show_option_none' => '— ' . __( 'Select', 'sg-humanitix-api-importer' ) . ' —',
					'sort_column' => 'menu_order, post_title',
					'echo' => false,
				) );
				echo '</div>';
				echo '</div>';
			}
		}
	}

	/**
	 * Add archived status to the list of available post statuses.
	 *
	 * @since 1.0.0
	 * @param array $statuses Array of available post statuses.
	 * @return array Modified array of statuses.
	 */
	public function add_archived_to_post_statuses( $statuses ) {
		$statuses['archived'] = array(
			'label'                     => _x( 'Archived', 'post status', 'sg-humanitix-api-importer' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop(
				'Archived <span class="count">(%s)</span>',
				'Archived <span class="count">(%s)</span>',
				'sg-humanitix-api-importer'
			),
		);
		return $statuses;
	}

	/**
	 * Add archived status to quick edit custom box.
	 *
	 * @since 1.0.0
	 * @param string $column_name The column name.
	 * @param string $post_type The post type.
	 * @return void
	 */
	public function add_archived_to_quick_edit( $column_name, $post_type ) {
		if ( 'post_status' === $column_name && 'tribe_events' === $post_type ) {
			$status = 'archived';
			$current_status = get_post_status( get_the_ID() );

			echo '<fieldset class="inline-edit-col-left">';
			echo '<div class="inline-edit-col">';
			echo '<label>';
			echo '<span class="title">' . __( 'Status', 'sg-humanitix-api-importer' ) . '</span>';
			echo '<span class="input-text-wrap">';
			echo '<select name="post_status">';
			echo '<option value="' . esc_attr( $current_status ) . '">' . esc_html( get_post_status_object( $current_status )->label ) . '</option>';
			echo '<option value="archived" selected="selected">' . esc_html( get_post_status_object( $status )->label ) . '</option>';
			echo '</select>';
			echo '</span>';
			echo '</label>';
			echo '</div>';
			echo '</fieldset>';
		}
	}

	/**
	 * Add archived status to bulk edit custom box.
	 *
	 * @since 1.0.0
	 * @param string $column_name The column name.
	 * @param string $post_type The post type.
	 * @return void
	 */
	public function add_archived_to_bulk_edit( $column_name, $post_type ) {
		if ( 'post_status' === $column_name && 'tribe_events' === $post_type ) {
			$status = 'archived';
			$current_status = get_post_status( get_the_ID() );

			echo '<fieldset class="inline-edit-col-left">';
			echo '<div class="inline-edit-col">';
			echo '<label>';
			echo '<span class="title">' . __( 'Status', 'sg-humanitix-api-importer' ) . '</span>';
			echo '<span class="input-text-wrap">';
			echo '<select name="post_status">';
			echo '<option value="' . esc_attr( $current_status ) . '">' . esc_html( get_post_status_object( $current_status )->label ) . '</option>';
			echo '<option value="archived" selected="selected">' . esc_html( get_post_status_object( $status )->label ) . '</option>';
			echo '</select>';
			echo '</span>';
			echo '</label>';
			echo '</div>';
			echo '</fieldset>';
		}
	}

	/**
	 * Add JavaScript to populate quick edit dropdowns.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_quick_edit_script() {
		global $post_type;

		if ( 'tribe_events' === $post_type ) {
			?>
			<script>
			jQuery(document).ready(function($) {
				// Function to add archived option to status dropdowns
				function addArchivedToDropdown($select) {
					if (!$select.find('option[value="archived"]').length) {
						$select.append('<option value="archived"><?php esc_html_e( 'Archived', 'sg-humanitix-api-importer' ); ?></option>');
					}
				}

				// Add archived option to existing dropdowns
				$('select[name="post_status"]').each(function() {
					addArchivedToDropdown($(this));
				});

				// Add archived option to quick edit dropdowns when they're created
				$(document).on('click', '.editinline', function() {
					setTimeout(function() {
						$('.inline-edit-row select[name="post_status"]').each(function() {
							addArchivedToDropdown($(this));
						});
					}, 100);
				});

				// Add archived option to bulk edit dropdowns
				$(document).on('click', '.bulk-edit', function() {
					setTimeout(function() {
						$('.bulk-edit-row select[name="post_status"]').each(function() {
							addArchivedToDropdown($(this));
						});
					}, 100);
				});

				// Handle quick edit form submission
				$(document).on('click', '.save', function() {
					var $form = $(this).closest('form');
					var $statusSelect = $form.find('select[name="post_status"]');
					
					if ($statusSelect.length && $statusSelect.val() === 'archived') {
						// Ensure the archived status is properly set
						$form.find('input[name="post_status"]').val('archived');
					}
				});
			});
			</script>
			<?php
		}
	}

	/**
	 * Add archived status to the quick edit form.
	 *
	 * @since 1.0.0
	 * @param string $column_name The column name.
	 * @param string $post_type The post type.
	 * @return void
	 */
	public function add_archived_to_quick_edit_form( $column_name, $post_type ) {
		if ( 'post_status' === $column_name && 'tribe_events' === $post_type ) {
			$status = 'archived';
			$current_status = get_post_status( get_the_ID() );

			echo '<fieldset class="inline-edit-col-left">';
			echo '<div class="inline-edit-col">';
			echo '<label>';
			echo '<span class="title">' . __( 'Status', 'sg-humanitix-api-importer' ) . '</span>';
			echo '<span class="input-text-wrap">';
			echo '<select name="post_status">';
			echo '<option value="' . esc_attr( $current_status ) . '">' . esc_html( get_post_status_object( $current_status )->label ) . '</option>';
			echo '<option value="archived" selected="selected">' . esc_html( get_post_status_object( $status )->label ) . '</option>';
			echo '</select>';
			echo '</span>';
			echo '</label>';
			echo '</div>';
			echo '</fieldset>';
		}
	}

	/**
	 * Modify quick edit dropdown arguments.
	 *
	 * @since 1.0.0
	 * @param array $args Quick edit dropdown arguments.
	 * @return array Modified arguments.
	 */
	public function modify_quick_edit_args( $args ) {
		$args['post_status'] = 'archived'; // Force the status to 'archived' in quick edit
		return $args;
	}

	/**
	 * Add archived status to the quick edit dropdown pages arguments.
	 *
	 * @since 1.0.0
	 * @param array $args Quick edit dropdown arguments.
	 * @param array $parsed_args Parsed arguments.
	 * @return array Modified arguments.
	 */
	public function add_archived_to_dropdown_pages( $args, $parsed_args ) {
		if ( isset( $parsed_args['post_type'] ) && 'tribe_events' === $parsed_args['post_type'] ) {
			$args['post_status'] = 'archived'; // Force the status to 'archived' in quick edit
		}
		return $args;
	}

	/**
	 * Add comprehensive JavaScript for quick/bulk edit functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_admin_head_script() {
		global $post_type;
		
		if ( $post_type === 'tribe_events' ) {
			?>
			<script>
			document.addEventListener('DOMContentLoaded', function() {
				// Function to add archived option to status dropdowns
				function addArchivedToDropdown(select) {
					if (!select.querySelector('option[value="archived"]')) {
						const archivedOption = document.createElement('option');
						archivedOption.value = 'archived';
						archivedOption.textContent = '<?php esc_html_e( 'Archived', 'sg-humanitix-api-importer' ); ?>';
						select.appendChild(archivedOption);
					}
				}

				// Add archived option to existing dropdowns
				const statusSelects = document.querySelectorAll('select[name="post_status"]');
				statusSelects.forEach(function(select) {
					addArchivedToDropdown(select);
				});

				// Add archived option to quick edit dropdowns when they're created
				document.addEventListener('click', function(event) {
					if (event.target.classList.contains('editinline')) {
						setTimeout(function() {
							const quickEditSelects = document.querySelectorAll('.inline-edit-row select[name="post_status"]');
							quickEditSelects.forEach(function(select) {
								addArchivedToDropdown(select);
							});
						}, 100);
					}
				});

				// Add archived option to bulk edit dropdowns
				document.addEventListener('click', function(event) {
					if (event.target.classList.contains('bulk-edit')) {
						setTimeout(function() {
							const bulkEditSelects = document.querySelectorAll('.bulk-edit-row select[name="post_status"]');
							bulkEditSelects.forEach(function(select) {
								addArchivedToDropdown(select);
							});
						}, 100);
					}
				});

				// Handle quick edit form submission
				document.addEventListener('click', function(event) {
					if (event.target.classList.contains('save')) {
						const form = event.target.closest('form');
						const statusSelect = form.querySelector('select[name="post_status"]');
						
						if (statusSelect && statusSelect.value === 'archived') {
							// Ensure the archived status is properly set
							const statusInput = form.querySelector('input[name="post_status"]');
							if (statusInput) {
								statusInput.value = 'archived';
							}
						}
					}
				});
			});
			</script>
			<?php
		}
	}

	/**
	 * Handle quick edit archive status update.
	 *
	 * @since 1.0.0
	 * @param array $data The post data.
	 * @param array $postarr The post array.
	 * @return array Modified post data.
	 */
	public function handle_quick_edit_archive( $data, $postarr ) {
		if ( isset( $_POST['post_status'] ) && 'archived' === $_POST['post_status'] ) {
			$data['post_status'] = 'archived';
		}
		return $data;
	}

	/**
	 * Get events to process for quick archive operations.
	 *
	 * @since 1.0.0
	 * @param float $age_threshold Age threshold in years (supports decimals like 0.5 for 6 months).
	 * @param int $limit Maximum number of events to return.
	 * @return array Array of event IDs to process.
	 */
	public function get_events_to_process( $age_threshold = 0.5, $limit = 50 ) {
		$events = $this->get_events_to_archive( $age_threshold, $limit );
		
		// Format events for the frontend
		$formatted_events = array();
		foreach ( $events as $event_id ) {
			$event = get_post( $event_id );
			if ( $event ) {
				$start_date = get_post_meta( $event_id, '_EventStartDate', true );
				$formatted_events[] = array(
					'id' => $event_id,
					'title' => $event->post_title,
					'start_date' => $start_date ? date( 'Y-m-d', strtotime( $start_date ) ) : 'Unknown',
				);
			}
		}
		
		return $formatted_events;
	}
	
	/**
	 * Get event IDs to process for quick archive operations.
	 *
	 * @since 1.0.0
	 * @param float $age_threshold Age threshold in years (supports decimals like 0.5 for 6 months).
	 * @param int $limit Maximum number of events to return.
	 * @return array Array of event IDs to process.
	 */
	public function get_event_ids_to_process( $age_threshold = 0.5, $limit = 50 ) {
		return $this->get_events_to_archive( $age_threshold, $limit );
	}



	/**
	 * Restore events from backup.
	 *
	 * @since 1.0.0
	 * @return array Results of the restore operation.
	 */
	public function restore_from_backup() {
		global $wpdb;
		
		$backup_table = $wpdb->prefix . 'humanitix_event_backups';
		
		// Check if backup table exists
		$table_exists = $wpdb->get_var( $wpdb->prepare( 
			"SHOW TABLES LIKE %s", 
			$backup_table 
		) );
		
		if ( ! $table_exists ) {
			return array(
				'success' => false,
				'message' => 'No backup table found',
			);
		}

		// Get all backup records
		$backups = $wpdb->get_results( "SELECT * FROM {$backup_table}" );
		
		if ( empty( $backups ) ) {
			return array(
				'success' => false,
				'message' => 'No backup records found',
			);
		}

		$results = array(
			'total'      => count( $backups ),
			'successful' => 0,
			'failed'     => 0,
			'errors'     => array(),
		);

		foreach ( $backups as $backup ) {
			$backup_data = json_decode( $backup->event_data, true );
			
			if ( ! $backup_data ) {
				$results['failed']++;
				$results['errors'][] = array(
					'event_id' => $backup->event_id,
					'error'    => 'Invalid backup data',
				);
				continue;
			}

			// Check if event already exists
			$existing_event = get_post( $backup->event_id );
			if ( $existing_event ) {
				// Update existing event
				$update_result = wp_update_post( array(
					'ID'           => $backup->event_id,
					'post_title'   => $backup_data['post_title'],
					'post_content' => $backup_data['post_content'],
					'post_status'  => 'publish',
				), true );
			} else {
				// Create new event
				$update_result = wp_insert_post( array(
					'ID'           => $backup->event_id,
					'post_title'   => $backup_data['post_title'],
					'post_content' => $backup_data['post_content'],
					'post_status'  => 'publish',
					'post_type'    => 'tribe_events',
				), true );
			}

			if ( is_wp_error( $update_result ) ) {
				$results['failed']++;
				$results['errors'][] = array(
					'event_id' => $backup->event_id,
					'error'    => $update_result->get_error_message(),
				);
			} else {
				// Restore post meta
				if ( isset( $backup_data['post_meta'] ) ) {
					foreach ( $backup_data['post_meta'] as $meta_key => $meta_value ) {
						update_post_meta( $backup->event_id, $meta_key, $meta_value );
					}
				}
				
				$results['successful']++;
				
				$this->logger->log(
					'info',
					'Event restored from backup',
					array(
						'event_id'     => $backup->event_id,
						'restore_date' => current_time( 'mysql' ),
					)
				);
			}
		}

		// Clear backup table after successful restore
		if ( $results['successful'] > 0 ) {
			$wpdb->query( "TRUNCATE TABLE {$backup_table}" );
		}

		$this->logger->log(
			'info',
			'Restore from backup completed',
			array(
				'total'      => $results['total'],
				'successful' => $results['successful'],
				'failed'     => $results['failed'],
			)
		);

		return array(
			'success' => true,
			'message' => sprintf( 
				'Restored %d events successfully. %d failed.', 
				$results['successful'], 
				$results['failed'] 
			),
			'results' => $results,
		);
	}
} 