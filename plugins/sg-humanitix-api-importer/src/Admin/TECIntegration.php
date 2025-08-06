<?php
/**
 * TEC Integration Class.
 *
 * Provides integration with The Events Calendar admin interface.
 * Includes quick archive buttons and admin notices.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Admin;

use SG\HumanitixApiImporter\Archive\ArchiveManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * TEC Integration Class.
 *
 * Provides integration with The Events Calendar admin interface.
 * Includes quick archive buttons and admin notices.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */
class TECIntegration {

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
		$this->archive_manager = new ArchiveManager();
		
		// Add TEC admin page integration
		add_action( 'admin_init', array( $this, 'init_tec_integration' ) );
		
		// Handle AJAX requests for quick archive
		add_action( 'wp_ajax_humanitix_quick_archive_event', array( $this, 'handle_quick_archive_event' ) );
		add_action( 'wp_ajax_humanitix_quick_unarchive_event', array( $this, 'handle_quick_unarchive_event' ) );
		
		// Add admin notices
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
	}

	/**
	 * Initialize TEC integration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_tec_integration() {
		// Only run on TEC admin pages
		if ( ! $this->is_tec_admin_page() ) {
			return;
		}

		// Add quick archive buttons to the admin interface
		add_action( 'admin_footer-edit.php', array( $this, 'add_quick_archive_buttons' ) );
		add_action( 'admin_footer-post.php', array( $this, 'add_quick_archive_buttons' ) );
		
		// Add bulk actions
		add_filter( 'bulk_actions-edit-tribe_events', array( $this, 'add_bulk_archive_actions' ) );
		add_filter( 'handle_bulk_actions-edit-tribe_events', array( $this, 'handle_bulk_archive_actions' ), 10, 3 );
		
		// Add custom columns
		add_filter( 'manage_tribe_events_posts_columns', array( $this, 'add_archive_column' ) );
		add_action( 'manage_tribe_events_posts_custom_column', array( $this, 'render_archive_column' ), 10, 2 );
	}

	/**
	 * Check if current page is a TEC admin page.
	 *
	 * @since 1.0.0
	 * @return bool Whether current page is TEC admin.
	 */
	private function is_tec_admin_page() {
		global $post_type, $pagenow;
		
		return ( 'tribe_events' === $post_type || 
				 ( 'edit.php' === $pagenow && isset( $_GET['post_type'] ) && 'tribe_events' === $_GET['post_type'] ) ||
				 ( 'post.php' === $pagenow && isset( $_GET['post'] ) && 'tribe_events' === get_post_type( $_GET['post'] ) ) );
	}

	/**
	 * Add quick archive buttons to TEC admin interface.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_quick_archive_buttons() {
		global $post_type;
		
		if ( 'tribe_events' !== $post_type ) {
			return;
		}
		
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Add quick archive buttons to the admin interface
			const addQuickArchiveButtons = function() {
				// Add to row actions
				const rows = document.querySelectorAll('.wp-list-table tr');
				rows.forEach(function(row) {
					const postId = row.getAttribute('data-post-id') || row.querySelector('.check-column input')?.value;
					if (!postId) return;
					
					const rowActions = row.querySelector('.row-actions');
					if (rowActions && !rowActions.querySelector('.quick-archive')) {
						const archiveLink = document.createElement('span');
						archiveLink.className = 'quick-archive';
						archiveLink.innerHTML = ' | <a href="#" class="quick-archive-btn" data-post-id="' + postId + '">Quick Archive</a>';
						rowActions.appendChild(archiveLink);
					}
				});
				
				// Add to publish box on single event page
				const publishBox = document.querySelector('#submitdiv');
				if (publishBox && !publishBox.querySelector('.quick-archive-publish')) {
					const postId = document.querySelector('#post_ID')?.value;
					if (postId) {
						const archiveButton = document.createElement('div');
						archiveButton.className = 'quick-archive-publish';
						archiveButton.innerHTML = '<a href="#" class="button quick-archive-btn" data-post-id="' + postId + '">Quick Archive</a>';
						publishBox.appendChild(archiveButton);
					}
				}
			};
			
			// Add buttons on page load
			addQuickArchiveButtons();
			
			// Add buttons after AJAX updates
			document.addEventListener('click', function(event) {
				if (event.target.classList.contains('quick-archive-btn')) {
					event.preventDefault();
					
					const postId = event.target.getAttribute('data-post-id');
					const isArchived = event.target.textContent.includes('Unarchive');
					
					if (confirm('Are you sure you want to ' + (isArchived ? 'unarchive' : 'archive') + ' this event?')) {
						const action = isArchived ? 'humanitix_quick_unarchive_event' : 'humanitix_quick_archive_event';
						
						fetch(ajaxurl, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded',
							},
							body: new URLSearchParams({
								action: action,
								post_id: postId,
								nonce: '<?php echo wp_create_nonce( 'humanitix_quick_archive_nonce' ); ?>'
							})
						})
						.then(response => response.json())
						.then(response => {
							if (response.success) {
								// Show success message
								const notice = document.createElement('div');
								notice.className = 'notice notice-success is-dismissible';
								notice.innerHTML = '<p>' + response.data.message + '</p>';
								document.querySelector('#wpbody-content').insertBefore(notice, document.querySelector('#wpbody-content').firstChild);
								
								// Update button text
								event.target.textContent = isArchived ? 'Quick Archive' : 'Quick Unarchive';
								
								// Reload page after a short delay to update status
								setTimeout(function() {
									location.reload();
								}, 1000);
							} else {
								// Show error message
								const notice = document.createElement('div');
								notice.className = 'notice notice-error is-dismissible';
								notice.innerHTML = '<p>Error: ' + response.data + '</p>';
								document.querySelector('#wpbody-content').insertBefore(notice, document.querySelector('#wpbody-content').firstChild);
							}
						})
						.catch(error => {
							console.error('Quick archive error:', error);
						});
					}
				}
			});
		});
		</script>
		
		<style>
		.quick-archive {
			margin-left: 5px;
		}
		.quick-archive-publish {
			margin-top: 10px;
			padding-top: 10px;
			border-top: 1px solid #ddd;
		}
		.quick-archive-btn {
			text-decoration: none;
		}
		</style>
		<?php
	}

	/**
	 * Add bulk archive actions.
	 *
	 * @since 1.0.0
	 * @param array $actions Existing bulk actions.
	 * @return array Modified bulk actions.
	 */
	public function add_bulk_archive_actions( $actions ) {
		$actions['archive_events'] = __( 'Archive Events', 'sg-humanitix-api-importer' );
		$actions['unarchive_events'] = __( 'Unarchive Events', 'sg-humanitix-api-importer' );
		return $actions;
	}

	/**
	 * Handle bulk archive actions.
	 *
	 * @since 1.0.0
	 * @param string $redirect_to Redirect URL.
	 * @param string $doaction Action being performed.
	 * @param array $post_ids Array of post IDs.
	 * @return string Modified redirect URL.
	 */
	public function handle_bulk_archive_actions( $redirect_to, $doaction, $post_ids ) {
		if ( 'archive_events' === $doaction ) {
			$archived = 0;
			foreach ( $post_ids as $post_id ) {
				$result = $this->archive_manager->archive_event( $post_id );
				if ( $result['success'] ) {
					$archived++;
				}
			}
			
			$redirect_to = add_query_arg( 'bulk_archived', $archived, $redirect_to );
		} elseif ( 'unarchive_events' === $doaction ) {
			$unarchived = 0;
			foreach ( $post_ids as $post_id ) {
				$result = $this->archive_manager->unarchive_event( $post_id );
				if ( $result['success'] ) {
					$unarchived++;
				}
			}
			
			$redirect_to = add_query_arg( 'bulk_unarchived', $unarchived, $redirect_to );
		}
		
		return $redirect_to;
	}

	/**
	 * Add archive column to TEC admin table.
	 *
	 * @since 1.0.0
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_archive_column( $columns ) {
		$columns['archive_status'] = __( 'Archive Status', 'sg-humanitix-api-importer' );
		return $columns;
	}

	/**
	 * Render archive column content.
	 *
	 * @since 1.0.0
	 * @param string $column Column name.
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function render_archive_column( $column, $post_id ) {
		if ( 'archive_status' === $column ) {
			$post = get_post( $post_id );
			if ( 'archived' === $post->post_status ) {
				echo '<span class="archive-status archived">' . esc_html__( 'Archived', 'sg-humanitix-api-importer' ) . '</span>';
			} else {
				echo '<span class="archive-status active">' . esc_html__( 'Active', 'sg-humanitix-api-importer' ) . '</span>';
			}
		}
	}

	/**
	 * Handle quick archive event AJAX request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_quick_archive_event() {
		check_ajax_referer( 'humanitix_quick_archive_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$post_id = intval( $_POST['post_id'] ?? 0 );
		
		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post ID' );
		}

		$result = $this->archive_manager->archive_event( $post_id );
		
		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message' => 'Event archived successfully',
				'post_id' => $post_id,
			) );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}

	/**
	 * Handle quick unarchive event AJAX request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_quick_unarchive_event() {
		check_ajax_referer( 'humanitix_quick_archive_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$post_id = intval( $_POST['post_id'] ?? 0 );
		
		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post ID' );
		}

		$result = $this->archive_manager->unarchive_event( $post_id );
		
		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message' => 'Event unarchived successfully',
				'post_id' => $post_id,
			) );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}

	/**
	 * Display admin notices for archive operations.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_admin_notices() {
		if ( ! $this->is_tec_admin_page() ) {
			return;
		}

		// Bulk archive notices
		if ( isset( $_GET['bulk_archived'] ) ) {
			$archived = intval( $_GET['bulk_archived'] );
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php printf( esc_html__( '%d events archived successfully.', 'sg-humanitix-api-importer' ), $archived ); ?></p>
			</div>
			<?php
		}

		if ( isset( $_GET['bulk_unarchived'] ) ) {
			$unarchived = intval( $_GET['bulk_unarchived'] );
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php printf( esc_html__( '%d events unarchived successfully.', 'sg-humanitix-api-importer' ), $unarchived ); ?></p>
			</div>
			<?php
		}

		// Archive system status notice
		$stats = $this->archive_manager->get_archive_statistics();
		if ( $stats['events_to_archive'] > 0 ) {
			?>
			<div class="notice notice-info is-dismissible">
				<p>
					<strong><?php esc_html_e( 'Archive System:', 'sg-humanitix-api-importer' ); ?></strong>
					<?php printf( esc_html__( '%d events are eligible for archiving. ', 'sg-humanitix-api-importer' ), $stats['events_to_archive'] ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=humanitix-archives' ) ); ?>">
						<?php esc_html_e( 'Manage Archives', 'sg-humanitix-api-importer' ); ?>
					</a>
				</p>
			</div>
			<?php
		}
	}
} 