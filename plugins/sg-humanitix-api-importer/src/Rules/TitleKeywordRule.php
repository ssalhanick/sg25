<?php
/**
 * Title Keyword Rule Class.
 *
 * Filters events based on keywords in the title.
 *
 * @package SG\HumanitixApiImporter\Rules
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Rules;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Title Keyword Rule Class.
 *
 * Filters events based on keywords in the title.
 *
 * @package SG\HumanitixApiImporter\Rules
 * @since 1.0.0
 */
class TitleKeywordRule extends AbstractEventRule {

	/**
	 * Constructor.
	 *
	 * @param array $rule_data Rule configuration data.
	 */
	public function __construct( $rule_data = array() ) {
		parent::__construct( $rule_data );
		
		// Set default conditions if not provided
		if ( empty( $this->conditions ) ) {
			$this->conditions = array(
				'keywords' => '',
				'match_type' => 'contains', // contains, starts_with, ends_with, exact
				'case_sensitive' => false,
			);
		}
	}

	/**
	 * Evaluate the rule against event data.
	 *
	 * @param array $event_data The event data to evaluate.
	 * @return RuleResult The result of the rule evaluation.
	 */
	public function evaluate( $event_data ) {
		$keywords = $this->conditions['keywords'] ?? '';
		$match_type = $this->conditions['match_type'] ?? 'contains';
		$case_sensitive = $this->conditions['case_sensitive'] ?? false;

		// If no keywords specified, rule doesn't match
		if ( empty( $keywords ) ) {
			return new RuleResult( false, $this, array( 'reason' => 'No keywords specified' ) );
		}

		// Get event title from various possible fields
		$event_title = $this->get_event_title( $event_data );
		
		if ( empty( $event_title ) ) {
			return new RuleResult( false, $this, array( 'reason' => 'No event title found' ) );
		}

		// Split keywords by comma and trim whitespace
		$keyword_list = array_map( 'trim', explode( ',', $keywords ) );
		$keyword_list = array_filter( $keyword_list ); // Remove empty keywords

		$matched = false;
		$matched_keywords = array();

		foreach ( $keyword_list as $keyword ) {
			if ( $this->keyword_matches( $event_title, $keyword, $match_type, $case_sensitive ) ) {
				$matched = true;
				$matched_keywords[] = $keyword;
			}
		}

		$metadata = array(
			'event_title' => $event_title,
			'keywords' => $keyword_list,
			'match_type' => $match_type,
			'case_sensitive' => $case_sensitive,
			'matched_keywords' => $matched_keywords,
		);

		return new RuleResult( $matched, $this, $metadata );
	}

	/**
	 * Get event title from event data.
	 *
	 * @param array $event_data The event data.
	 * @return string The event title or empty string if not found.
	 */
	private function get_event_title( $event_data ) {
		// Try different possible title fields
		$title_fields = array( 'title', 'name', 'event_title', 'event_name' );
		
		foreach ( $title_fields as $field ) {
			if ( isset( $event_data[ $field ] ) && ! empty( $event_data[ $field ] ) ) {
				return $event_data[ $field ];
			}
		}

		return '';
	}

	/**
	 * Check if a keyword matches the event title.
	 *
	 * @param string $title The event title.
	 * @param string $keyword The keyword to match.
	 * @param string $match_type The type of matching to perform.
	 * @param bool   $case_sensitive Whether matching is case sensitive.
	 * @return bool Whether the keyword matches.
	 */
	private function keyword_matches( $title, $keyword, $match_type, $case_sensitive ) {
		if ( ! $case_sensitive ) {
			$title = strtolower( $title );
			$keyword = strtolower( $keyword );
		}

		switch ( $match_type ) {
			case 'contains':
				return strpos( $title, $keyword ) !== false;
			
			case 'starts_with':
				return strpos( $title, $keyword ) === 0;
			
			case 'ends_with':
				return strrpos( $title, $keyword ) === ( strlen( $title ) - strlen( $keyword ) );
			
			case 'exact':
				return $title === $keyword;
			
			default:
				return strpos( $title, $keyword ) !== false; // Default to contains
		}
	}

	/**
	 * Validate rule configuration.
	 *
	 * @return bool Whether the rule is valid.
	 */
	public function is_valid() {
		if ( ! parent::is_valid() ) {
			return false;
		}

		// Check if keywords are specified
		if ( empty( $this->conditions['keywords'] ) ) {
			return false;
		}

		// Check if match_type is valid
		$valid_match_types = array( 'contains', 'starts_with', 'ends_with', 'exact' );
		if ( ! in_array( $this->conditions['match_type'], $valid_match_types, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get rule description for display.
	 *
	 * @return string
	 */
	public function get_description() {
		$keywords = $this->conditions['keywords'] ?? '';
		$match_type = $this->conditions['match_type'] ?? 'contains';
		$case_sensitive = $this->conditions['case_sensitive'] ?? false;

		$description = sprintf(
			'Title %s "%s"',
			$match_type,
			$keywords
		);

		if ( $case_sensitive ) {
			$description .= ' (case sensitive)';
		}

		return $description;
	}

	/**
	 * Get available match types.
	 *
	 * @return array
	 */
	public static function get_available_match_types() {
		return array(
			'contains' => 'Contains',
			'starts_with' => 'Starts with',
			'ends_with' => 'Ends with',
			'exact' => 'Exact match',
		);
	}
} 