<?php
/**
 * Course Level Helper.
 *
 * @package SG\EventbriteCourseImporter\Utils
 */

namespace SG\EventbriteCourseImporter\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper for detecting and formatting course level data.
 */
class CourseLevelHelper {
	/**
	 * Default level number used when detection fails.
	 */
	const DEFAULT_LEVEL_NUMBER = 999;

	/**
	 * Map of numbers to their word equivalents.
	 *
	 * @var array<int, string>
	 */
	private static $number_words = array(
		1  => 'One',
		2  => 'Two',
		3  => 'Three',
		4  => 'Four',
		5  => 'Five',
		6  => 'Six',
		7  => 'Seven',
		8  => 'Eight',
		9  => 'Nine',
		10 => 'Ten',
		11 => 'Eleven',
		12 => 'Twelve',
	);

	/**
	 * Parse level data from a course title.
	 *
	 * @param string $title Course title.
	 * @return array{number:int,label:string}
	 */
	public static function parse_level_from_title( $title ) {
		$result = self::get_default_level();

		if ( empty( $title ) ) {
			return $result;
		}

		// First look for explicit numeric declarations like "Level 2" or "Lvl 3".
		if ( preg_match( '/\b(?:level|lvl)\s*(\d{1,3})\b/i', $title, $matches ) ) {
			$number = intval( $matches[1] );
			if ( $number > 0 ) {
				return self::build_level_data( $number );
			}
		}

		// Next, look for spelled-out numbers (Level One, Level Two, etc.).
		if ( preg_match( '/\b(?:level|lvl)\s*(one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve)\b/i', $title, $matches ) ) {
			$word   = strtolower( $matches[1] );
			$number = self::word_to_number( $word );
			if ( $number > 0 ) {
				return self::build_level_data( $number );
			}
		}

		return $result;
	}

	/**
	 * Format the level label for display.
	 *
	 * @param int $number Level number.
	 * @return string
	 */
	public static function format_level_label( $number ) {
		$number = intval( $number );
		if ( $number <= 0 || self::DEFAULT_LEVEL_NUMBER === $number ) {
			return '';
		}

		$word = self::$number_words[ $number ] ?? null;

		if ( $word ) {
			/* translators: %s: spelled out level number (e.g., One, Two). */
			return sprintf( __( 'Level %s', 'sg-eventbrite-course-importer' ), $word );
		}

		/* translators: %d: numeric level number. */
		return sprintf( __( 'Level %d', 'sg-eventbrite-course-importer' ), $number );
	}

	/**
	 * Determine whether the provided level number is the default/fallback value.
	 *
	 * @param int $number Level number.
	 * @return bool
	 */
	public static function is_default_level_number( $number ) {
		return self::DEFAULT_LEVEL_NUMBER === intval( $number );
	}

	/**
	 * Return the default level structure.
	 *
	 * @return array{number:int,label:string}
	 */
	public static function get_default_level() {
		return array(
			'number' => self::DEFAULT_LEVEL_NUMBER,
			'label'  => '',
		);
	}

	/**
	 * Convert a spelled-out number to its numeric representation.
	 *
	 * @param string $word Number word.
	 * @return int
	 */
	private static function word_to_number( $word ) {
		$word = strtolower( $word );

		$map = array(
			'one'    => 1,
			'two'    => 2,
			'three'  => 3,
			'four'   => 4,
			'five'   => 5,
			'six'    => 6,
			'seven'  => 7,
			'eight'  => 8,
			'nine'   => 9,
			'ten'    => 10,
			'eleven' => 11,
			'twelve' => 12,
		);

		return $map[ $word ] ?? 0;
	}

	/**
	 * Build the level data array for a detected level number.
	 *
	 * @param int $number Level number.
	 * @return array{number:int,label:string}
	 */
	private static function build_level_data( $number ) {
		return array(
			'number' => intval( $number ),
			'label'  => self::format_level_label( $number ),
		);
	}
}

