<?php
/**
 * Content Gone (410) Handler.
 *
 * Records deleted event permalinks as tombstones and upgrades matching 404s to 410 Gone.
 *
 * @package SG\HumanitixApiImporter\Security
 */

namespace SG\HumanitixApiImporter\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class ContentGoneHandler
 */
class ContentGoneHandler {
    /**
     * Option name for tombstones map. Non-autoloaded.
     *
     * @var string
     */
    private const OPTION_TOMBSTONES = 'sg_hai_410_tombstones';

    /**
     * Constructor: registers hooks.
     */
    public function __construct() {
        // Ensure our storage option exists and is not autoloaded.
        add_action( 'init', array( $this, 'ensure_storage' ) );

        // Record tombstones on trash and permanent delete; remove on restore.
        add_action( 'wp_trash_post', array( $this, 'record_tombstone' ), 10, 1 );
        add_action( 'before_delete_post', array( $this, 'record_tombstone' ), 10, 1 );
        add_action( 'untrash_post', array( $this, 'remove_tombstone' ), 10, 1 );

        // Serve 410 for known tombstones only when WordPress resolved to 404.
        add_action( 'template_redirect', array( $this, 'maybe_send_410' ), 0 );
    }

    /**
     * Ensure the tombstones option exists (non-autoloaded).
     */
    public function ensure_storage(): void {
        if ( false === get_option( self::OPTION_TOMBSTONES, false ) ) {
            add_option( self::OPTION_TOMBSTONES, array(), '', 'no' );
        }
    }

    /**
     * Determine if the post type should be tracked for 410s.
     *
     * @param string $post_type Post type slug.
     * @return bool
     */
    private function is_target_post_type( string $post_type ): bool {
        $default = array( 'tribe_events' );
        $targets = apply_filters( 'sg_hai_410_post_types', $default );
        return in_array( $post_type, (array) $targets, true );
    }

    /**
     * Normalize a URL/path to a canonical trailing-slash path for matching.
     *
     * @param string $url Full URL or path.
     * @return string Canonical path beginning and ending with '/'; root is '/'.
     */
    private function normalize_path( string $url ): string {
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        $path = '/' . ltrim( $path, '/' );
        // Ensure trailing slash for non-root paths
        if ( '/' !== $path ) {
            $path = trailingslashit( $path );
        }
        return $path;
    }

    /**
     * Record a tombstone for a post that is being trashed or deleted.
     *
     * @param int $post_id Post ID.
     */
    public function record_tombstone( int $post_id ): void {
        $post = get_post( $post_id );
        if ( ! $post || ! $this->is_target_post_type( $post->post_type ) ) {
            return;
        }

        $permalink = get_permalink( $post );
        if ( ! $permalink ) {
            return;
        }

        $path       = $this->normalize_path( $permalink );
        $tombstones = (array) get_option( self::OPTION_TOMBSTONES, array() );
        $tombstones[ $path ] = array(
            'deleted_at' => time(),
            'post_id'    => (int) $post_id,
            'post_type'  => $post->post_type,
        );
        update_option( self::OPTION_TOMBSTONES, $tombstones, false );
    }

    /**
     * Remove a tombstone when a post is restored from trash.
     *
     * @param int $post_id Post ID.
     */
    public function remove_tombstone( int $post_id ): void {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return;
        }
        $permalink = get_permalink( $post );
        if ( ! $permalink ) {
            return;
        }
        $path       = $this->normalize_path( $permalink );
        $tombstones = (array) get_option( self::OPTION_TOMBSTONES, array() );
        if ( isset( $tombstones[ $path ] ) ) {
            unset( $tombstones[ $path ] );
            update_option( self::OPTION_TOMBSTONES, $tombstones, false );
        }
    }

    /**
     * On 404s, upgrade to 410 if the requested path matches a tombstone.
     */
    public function maybe_send_410(): void {
        // Only front-end 404s; skip admin, AJAX, and REST.
        if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }
        if ( ! is_404() ) {
            return;
        }

        // Check if feature is enabled in settings.
        $options = get_option( 'humanitix_importer_options', array() );
        $enabled = isset( $options['deleted_410_enable'] ) ? (bool) $options['deleted_410_enable'] : true;
        if ( ! $enabled ) {
            return;
        }

        // Determine TTL threshold (days).
        $ttl_days  = isset( $options['deleted_410_ttl_days'] ) ? absint( $options['deleted_410_ttl_days'] ) : 365;
        $threshold = $ttl_days > 0 ? ( time() - ( $ttl_days * DAY_IN_SECONDS ) ) : 0;

        // Current request path (use REQUEST_URI for accuracy with addl path segments and slash handling).
        $request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
        $request_path = $this->normalize_path( $request_uri );

        $tombstones = (array) get_option( self::OPTION_TOMBSTONES, array() );

        // Purge expired entries if TTL is set.
        $changed = false;
        if ( $ttl_days > 0 ) {
            foreach ( $tombstones as $path => $data ) {
                $deleted_at = isset( $data['deleted_at'] ) ? (int) $data['deleted_at'] : 0;
                if ( $deleted_at > 0 && $deleted_at < $threshold ) {
                    unset( $tombstones[ $path ] );
                    $changed = true;
                }
            }
        }
        if ( $changed ) {
            update_option( self::OPTION_TOMBSTONES, $tombstones, false );
        }

        if ( isset( $tombstones[ $request_path ] ) ) {
            status_header( 410 );
            nocache_headers();

            // Use theme 410.php if available; otherwise, show a simple message.
            $template = locate_template( array( '410.php' ) );
            if ( $template ) {
                include $template;
            } else {
                wp_die(
                    esc_html__( 'This content has been permanently removed.', 'sg-humanitix-api-importer' ),
                    esc_html__( 'Gone', 'sg-humanitix-api-importer' ),
                    array( 'response' => 410 )
                );
            }
            exit;
        }
    }
}

