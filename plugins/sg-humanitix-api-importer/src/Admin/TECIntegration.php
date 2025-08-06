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