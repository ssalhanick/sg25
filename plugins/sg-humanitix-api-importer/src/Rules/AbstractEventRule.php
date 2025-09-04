<?php
/**
 * Abstract Event Rule Class.
 *
 * Base class for all event filtering rules.
 *
 * @package SG\HumanitixApiImporter\Rules
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Rules;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Abstract Event Rule Class.
 *
 * Base class for all event filtering rules.
 *
 * @package SG\HumanitixApiImporter\Rules
 * @since 1.0.0
 */
abstract class AbstractEventRule {

	/**
	 * Rule ID.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Rule name.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * Rule action (include/exclude).
	 *
	 * @var string
	 */
	protected $action;

	/**
	 * Rule priority (lower numbers = higher priority).
	 *
	 * @var int
	 */
	protected $priority;

	/**
	 * Whether the rule is active.
	 *
	 * @var bool
	 */
	protected $is_active;

	/**
	 * Rule conditions.
	 *
	 * @var array
	 */
	protected $conditions;

	/**
	 * Constructor.
	 *
	 * @param array $rule_data Rule configuration data.
	 */
	public function __construct( $rule_data = array() ) {
		$this->id          = isset( $rule_data['id'] ) ? sanitize_text_field( $rule_data['id'] ) : uniqid( 'rule_' );
		$this->name        = isset( $rule_data['name'] ) ? sanitize_text_field( $rule_data['name'] ) : 'Unnamed Rule';
		$this->action      = isset( $rule_data['action'] ) ? sanitize_text_field( $rule_data['action'] ) : 'include';
		$this->priority    = isset( $rule_data['priority'] ) ? absint( $rule_data['priority'] ) : 10;
		$this->is_active   = isset( $rule_data['is_active'] ) ? (bool) $rule_data['is_active'] : true;
		$this->conditions  = isset( $rule_data['conditions'] ) ? $this->sanitize_conditions( $rule_data['conditions'] ) : array();
	}

	/**
	 * Evaluate the rule against event data.
	 *
	 * @param array $event_data The event data to evaluate.
	 * @return RuleResult The result of the rule evaluation.
	 */
	abstract public function evaluate( $event_data );

	/**
	 * Get rule ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get rule name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get rule action.
	 *
	 * @return string
	 */
	public function get_action() {
		return $this->action;
	}

	/**
	 * Get rule priority.
	 *
	 * @return int
	 */
	public function get_priority() {
		return $this->priority;
	}

	/**
	 * Check if rule is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->is_active;
	}

	/**
	 * Get rule conditions.
	 *
	 * @return array
	 */
	public function get_conditions() {
		return $this->conditions;
	}

	/**
	 * Sanitize rule conditions.
	 *
	 * @param array $conditions Raw conditions data.
	 * @return array Sanitized conditions.
	 */
	protected function sanitize_conditions( $conditions ) {
		if ( ! is_array( $conditions ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $conditions as $key => $value ) {
			$sanitized[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
		}

		return $sanitized;
	}

	/**
	 * Validate rule configuration.
	 *
	 * @return bool Whether the rule is valid.
	 */
	public function is_valid() {
		return ! empty( $this->name ) && in_array( $this->action, array( 'include', 'exclude' ), true );
	}

	/**
	 * Get rule as array for storage.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'id'         => $this->id,
			'name'       => $this->name,
			'action'     => $this->action,
			'priority'   => $this->priority,
			'is_active'  => $this->is_active,
			'conditions' => $this->conditions,
			'type'       => static::class,
		);
	}
} 