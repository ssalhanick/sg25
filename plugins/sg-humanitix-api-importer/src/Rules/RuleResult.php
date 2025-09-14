<?php
/**
 * Rule Result Class.
 *
 * Contains the result of evaluating a rule against event data.
 *
 * @package SG\HumanitixApiImporter\Rules
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Rules;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Rule Result Class.
 *
 * Contains the result of evaluating a rule against event data.
 *
 * @package SG\HumanitixApiImporter\Rules
 * @since 1.0.0
 */
class RuleResult {

	/**
	 * Whether the rule matched.
	 *
	 * @var bool
	 */
	private $matched;

	/**
	 * The rule that was evaluated.
	 *
	 * @var AbstractEventRule
	 */
	private $rule;

	/**
	 * Additional metadata about the result.
	 *
	 * @var array
	 */
	private $metadata;

	/**
	 * Constructor.
	 *
	 * @param bool                $matched  Whether the rule matched.
	 * @param AbstractEventRule  $rule     The rule that was evaluated.
	 * @param array              $metadata Additional metadata.
	 */
	public function __construct( $matched, AbstractEventRule $rule, $metadata = array() ) {
		$this->matched  = (bool) $matched;
		$this->rule     = $rule;
		$this->metadata = is_array( $metadata ) ? $metadata : array();
	}

	/**
	 * Check if the rule matched.
	 *
	 * @return bool
	 */
	public function matched() {
		return $this->matched;
	}

	/**
	 * Get the rule that was evaluated.
	 *
	 * @return AbstractEventRule
	 */
	public function get_rule() {
		return $this->rule;
	}

	/**
	 * Get additional metadata.
	 *
	 * @return array
	 */
	public function get_metadata() {
		return $this->metadata;
	}

	/**
	 * Get the rule action.
	 *
	 * @return string
	 */
	public function get_action() {
		return $this->rule->get_action();
	}

	/**
	 * Get the rule priority.
	 *
	 * @return int
	 */
	public function get_priority() {
		return $this->rule->get_priority();
	}

	/**
	 * Check if this result should include the event.
	 *
	 * @return bool
	 */
	public function should_include() {
		if ( ! $this->matched ) {
			return true; // If rule doesn't match, don't affect inclusion
		}

		return 'include' === $this->rule->get_action();
	}

	/**
	 * Check if this result should exclude the event.
	 *
	 * @return bool
	 */
	public function should_exclude() {
		if ( ! $this->matched ) {
			return false; // If rule doesn't match, don't exclude
		}

		return 'exclude' === $this->rule->get_action();
	}

	/**
	 * Get result as array for logging/debugging.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'matched'  => $this->matched,
			'rule_id'  => $this->rule->get_id(),
			'rule_name' => $this->rule->get_name(),
			'action'   => $this->rule->get_action(),
			'priority' => $this->rule->get_priority(),
			'metadata' => $this->metadata,
		);
	}
} 