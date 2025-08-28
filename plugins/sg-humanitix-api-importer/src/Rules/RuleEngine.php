<?php
/**
 * Rule Engine Class.
 *
 * Processes rules against event data to determine inclusion/exclusion.
 *
 * @package SG\HumanitixApiImporter\Rules
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Rules;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Rule Engine Class.
 *
 * Processes rules against event data to determine inclusion/exclusion.
 *
 * @package SG\HumanitixApiImporter\Rules
 * @since 1.0.0
 */
class RuleEngine {

	/**
	 * Collection of rules to evaluate.
	 *
	 * @var AbstractEventRule[]
	 */
	private $rules;

	/**
	 * Results of rule evaluations.
	 *
	 * @var RuleResult[]
	 */
	private $results;

	/**
	 * Constructor.
	 *
	 * @param array $rules Array of rule objects or rule data.
	 */
	public function __construct( $rules = array() ) {
		$this->rules = array();
		$this->results = array();
		$this->add_rules( $rules );
	}

	/**
	 * Add rules to the engine.
	 *
	 * @param array $rules Array of rule objects or rule data.
	 */
	public function add_rules( $rules ) {
		if ( ! is_array( $rules ) ) {
			return;
		}

		foreach ( $rules as $rule ) {
			$this->add_rule( $rule );
		}

		// Sort rules by priority (lower numbers = higher priority)
		usort( $this->rules, function( $a, $b ) {
			return $a->get_priority() - $b->get_priority();
		} );
	}

	/**
	 * Add a single rule to the engine.
	 *
	 * @param AbstractEventRule|array $rule Rule object or rule data.
	 */
	public function add_rule( $rule ) {
		if ( $rule instanceof AbstractEventRule ) {
			$this->rules[] = $rule;
		} elseif ( is_array( $rule ) ) {
			// Try to create rule from data
			$rule_object = $this->create_rule_from_data( $rule );
			if ( $rule_object ) {
				$this->rules[] = $rule_object;
			}
		}
	}

	/**
	 * Create a rule object from rule data.
	 *
	 * @param array $rule_data Rule configuration data.
	 * @return AbstractEventRule|null Rule object or null if creation fails.
	 */
	private function create_rule_from_data( $rule_data ) {
		$rule_type = $rule_data['type'] ?? '';
		
		if ( empty( $rule_type ) ) {
			return null;
		}

		// Map rule types to classes
		$rule_class_map = array(
			'TitleKeywordRule' => TitleKeywordRule::class,
			// Add more rule types here as they're created
		);

		$class_name = $rule_class_map[ $rule_type ] ?? $rule_type;
		
		if ( ! class_exists( $class_name ) ) {
			return null;
		}

		try {
			return new $class_name( $rule_data );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Evaluate all rules against event data.
	 *
	 * @param array $event_data The event data to evaluate.
	 * @return bool Whether the event should be included.
	 */
	public function evaluate_event( $event_data ) {
		$this->results = array();
		
		// Evaluate each rule
		foreach ( $this->rules as $rule ) {
			if ( ! $rule->is_active() ) {
				continue;
			}

			try {
				$result = $rule->evaluate( $event_data );
				$this->results[] = $result;
			} catch ( \Exception $e ) {
				// Log error and continue with other rules
				error_log( 'Rule evaluation error: ' . $e->getMessage() );
				continue;
			}
		}

		// Determine final result based on rule results
		return $this->determine_final_result();
	}

	/**
	 * Determine final result based on rule evaluation results.
	 *
	 * @return bool Whether the event should be included.
	 */
	private function determine_final_result() {
		if ( empty( $this->results ) ) {
			return true; // No rules = include everything
		}

		// Process results in priority order
		foreach ( $this->results as $result ) {
			if ( $result->should_exclude() ) {
				return false; // Exclusion rules take precedence
			}
		}

		// Check if any include rules matched
		$has_include_match = false;
		foreach ( $this->results as $result ) {
			if ( $result->matched() && $result->should_include() ) {
				$has_include_match = true;
				break;
			}
		}

		// If we have rules but no include rules matched, exclude the event
		// This implements "whitelist" behavior - only include if explicitly matched
		return $has_include_match;
	}

	/**
	 * Get all rule evaluation results.
	 *
	 * @return RuleResult[]
	 */
	public function get_results() {
		return $this->results;
	}

	/**
	 * Get rules.
	 *
	 * @return AbstractEventRule[]
	 */
	public function get_rules() {
		return $this->rules;
	}

	/**
	 * Get rule by ID.
	 *
	 * @param string $rule_id The rule ID.
	 * @return AbstractEventRule|null The rule or null if not found.
	 */
	public function get_rule( $rule_id ) {
		foreach ( $this->rules as $rule ) {
			if ( $rule->get_id() === $rule_id ) {
				return $rule;
			}
		}
		return null;
	}

	/**
	 * Remove rule by ID.
	 *
	 * @param string $rule_id The rule ID.
	 * @return bool Whether the rule was removed.
	 */
	public function remove_rule( $rule_id ) {
		foreach ( $this->rules as $key => $rule ) {
			if ( $rule->get_id() === $rule_id ) {
				unset( $this->rules[ $key ] );
				$this->rules = array_values( $this->rules ); // Re-index array
				return true;
			}
		}
		return false;
	}

	/**
	 * Clear all rules.
	 */
	public function clear_rules() {
		$this->rules = array();
		$this->results = array();
	}

	/**
	 * Get summary of rule evaluation.
	 *
	 * @return array
	 */
	public function get_evaluation_summary() {
		$summary = array(
			'total_rules' => count( $this->rules ),
			'active_rules' => 0,
			'evaluated_rules' => count( $this->results ),
			'inclusion_rules' => 0,
			'exclusion_rules' => 0,
			'matched_rules' => 0,
		);

		foreach ( $this->rules as $rule ) {
			if ( $rule->is_active() ) {
				$summary['active_rules']++;
			}
		}

		foreach ( $this->results as $result ) {
			if ( $result->should_include() ) {
				$summary['inclusion_rules']++;
			} else {
				$summary['exclusion_rules']++;
			}

			if ( $result->matched() ) {
				$summary['matched_rules']++;
			}
		}

		return $summary;
	}
} 