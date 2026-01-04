<?php
/**
 * WP-CLI command to backfill course levels.
 *
 * @package SG\EventbriteCourseImporter\CLI
 */

namespace SG\EventbriteCourseImporter\CLI;

use SG\EventbriteCourseImporter\Utils\CourseLevelHelper;
use WP_CLI;
use WP_CLI_Command;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Command to backfill level meta for existing courses.
 */
class CourseLevelBackfillCommand extends WP_CLI_Command {

	/**
	 * Backfill level metadata for existing sg_course posts.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Evaluate and report changes without updating the database.
	 *
	 * @param array $args Positional args.
	 * @param array $assoc_args Assoc args.
	 */
	public function __invoke( $args, $assoc_args ) {
		$dry_run = isset( $assoc_args['dry-run'] );
		$paged   = 1;
		$perpage = 50;
		$updated = 0;

		WP_CLI::log( sprintf( 'Scanning courses for level metadata%s...', $dry_run ? ' (dry run)' : '' ) );

		do {
			$query = new WP_Query(
				array(
					'post_type'      => 'sg_course',
					'post_status'    => 'any',
					'posts_per_page' => $perpage,
					'paged'          => $paged,
					'fields'         => 'ids',
				)
			);

			if ( ! $query->have_posts() ) {
				break;
			}

			foreach ( $query->posts as $post_id ) {
				$title      = get_the_title( $post_id );
				$level_data = CourseLevelHelper::parse_level_from_title( $title );

				if ( CourseLevelHelper::is_default_level_number( $level_data['number'] ) ) {
					continue;
				}

				$current_number = intval( get_post_meta( $post_id, '_sg_course_level_number', true ) );
				$current_label  = get_post_meta( $post_id, '_sg_course_level_label', true );

				// Skip if metadata already matches.
				if ( $current_number === $level_data['number'] && $current_label === $level_data['label'] ) {
					continue;
				}

				if ( $dry_run ) {
					WP_CLI::log(
						sprintf(
							'[DRY RUN] Would update post %d (%s) to level %d (%s)',
							$post_id,
							$title,
							$level_data['number'],
							$level_data['label']
						)
					);
				} else {
					update_post_meta( $post_id, '_sg_course_level_number', $level_data['number'] );
					update_post_meta( $post_id, '_sg_course_level_label', $level_data['label'] );
					WP_CLI::log(
						sprintf(
							'Updated post %d (%s) to level %d (%s)',
							$post_id,
							$title,
							$level_data['number'],
							$level_data['label']
						)
					);
				}

				$updated++;
			}

			$paged ++;
			wp_reset_postdata();
		} while ( $query->max_num_pages >= $paged );

		if ( $dry_run ) {
			WP_CLI::success( sprintf( 'Dry run complete. %d courses would be updated.', $updated ) );
		} else {
			WP_CLI::success( sprintf( 'Backfill complete. %d courses updated.', $updated ) );
		}
	}
}

