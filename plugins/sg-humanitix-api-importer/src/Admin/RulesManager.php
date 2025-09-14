<?php
/**
 * Rules Manager Class.
 *
 * Handles the admin interface for managing event import rules.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Admin;

use SG\HumanitixApiImporter\Rules\RuleEngine;
use SG\HumanitixApiImporter\Rules\TitleKeywordRule;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Rules Manager Class.
 *
 * Handles the admin interface for managing event import rules.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */
class RulesManager {

	/**
	 * Option name for storing rules.
	 *
	 * @var string
	 */
	const RULES_OPTION = 'event_import_rules';

	/**
	 * Rule engine instance.
	 *
	 * @var RuleEngine
	 */
	private $rule_engine;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
		$this->load_rules();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_rules_menu' ) );
		add_action( 'admin_init', array( $this, 'init_settings' ) );
		add_action( 'wp_ajax_save_rule', array( $this, 'ajax_save_rule' ) );
		add_action( 'wp_ajax_delete_rule', array( $this, 'ajax_delete_rule' ) );
		add_action( 'wp_ajax_test_rule', array( $this, 'ajax_test_rule' ) );
	}

	/**
	 * Add rules management menu.
	 */
	public function add_rules_menu() {
		add_submenu_page(
			'event-importers',
			'Import Rules',
			'Import Rules',
			'manage_options',
			'event-import-rules',
			array( $this, 'render_rules_page' )
		);
	}

	/**
	 * Initialize settings.
	 */
	public function init_settings() {
		register_setting(
			'event_import_rules',
			self::RULES_OPTION,
			array( $this, 'sanitize_rules' )
		);
	}

	/**
	 * Load rules from options.
	 */
	private function load_rules() {
		$rules_data = get_option( self::RULES_OPTION, array() );
		$this->rule_engine = new RuleEngine( $rules_data );
	}

	/**
	 * Save rules to options.
	 */
	private function save_rules() {
		$rules = $this->rule_engine->get_rules();
		$rules_data = array();
		
		foreach ( $rules as $rule ) {
			$rules_data[] = $rule->to_array();
		}
		
		update_option( self::RULES_OPTION, $rules_data );
	}

	/**
	 * Render the rules management page.
	 */
	public function render_rules_page() {
		?>
		<div class="wrap">
			<h1>Event Import Rules</h1>
			<p>Configure rules to automatically filter events during import. Rules are processed in priority order (lower numbers = higher priority).</p>
			
			<div class="rules-container">
				<div class="rules-list">
					<h2>Current Rules</h2>
					<?php $this->render_rules_list(); ?>
				</div>
				
				<div class="rules-form">
					<h2>Add/Edit Rule</h2>
					<?php $this->render_rule_form(); ?>
				</div>
			</div>
			
			<div class="rules-help">
				<h3>How Rules Work</h3>
				<ul>
					<li><strong>Include Rules:</strong> Events must match at least one include rule to be imported</li>
					<li><strong>Exclude Rules:</strong> Events matching exclude rules are never imported</li>
					<li><strong>Priority:</strong> Lower numbers = higher priority. Exclusion rules are processed first.</li>
					<li><strong>No Rules:</strong> If no rules are configured, all events are imported</li>
				</ul>
			</div>
		</div>
		
		<style>
			.rules-container {
				display: flex;
				gap: 2rem;
				margin: 2rem 0;
			}
			.rules-list {
				flex: 1;
			}
			.rules-form {
				flex: 1;
			}
			.rule-item {
				background: #f9f9f9;
				border: 1px solid #ddd;
				padding: 1rem;
				margin-bottom: 1rem;
				border-radius: 4px;
			}
			.rule-item.active {
				border-left: 4px solid #0073aa;
			}
			.rule-item.inactive {
				border-left: 4px solid #ccc;
				opacity: 0.7;
			}
			.rule-actions {
				margin-top: 1rem;
			}
			.rule-actions .button {
				margin-right: 0.5rem;
			}
			.form-table th {
				width: 150px;
			}
			.rules-help {
				background: #f0f6fc;
				padding: 1rem;
				border-left: 4px solid #0073aa;
				margin-top: 2rem;
			}
		</style>
		
		<script>
		jQuery(document).ready(function($) {
			// Handle rule form submission
			$('#rule-form').on('submit', function(e) {
				e.preventDefault();
				
				var formData = $(this).serialize();
				formData += '&action=save_rule&nonce=<?php echo wp_create_nonce( 'save_rule' ); ?>';
				
				$.post(ajaxurl, formData, function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert('Error: ' + response.data);
					}
				});
			});
			
			// Handle rule deletion
			$('.delete-rule').on('click', function(e) {
				e.preventDefault();
				
				if (!confirm('Are you sure you want to delete this rule?')) {
					return;
				}
				
				var ruleId = $(this).data('rule-id');
				
				$.post(ajaxurl, {
					action: 'delete_rule',
					rule_id: ruleId,
					nonce: '<?php echo wp_create_nonce( 'delete_rule' ); ?>'
				}, function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert('Error: ' + response.data);
					}
				});
			});
			
			// Handle rule editing
			$('.edit-rule').on('click', function(e) {
				e.preventDefault();
				
				var ruleId = $(this).data('rule-id');
				// Load rule data into form
				// This would need to be implemented with AJAX
			});
		});
		</script>
		<?php
	}

	/**
	 * Render the list of current rules.
	 */
	private function render_rules_list() {
		$rules = $this->rule_engine->get_rules();
		
		if ( empty( $rules ) ) {
			echo '<p>No rules configured. All events will be imported.</p>';
			return;
		}
		
		foreach ( $rules as $rule ) {
			$status_class = $rule->is_active() ? 'active' : 'inactive';
			$status_text = $rule->is_active() ? 'Active' : 'Inactive';
			$action_text = 'include' === $rule->get_action() ? 'Include' : 'Exclude';
			
			echo '<div class="rule-item ' . esc_attr( $status_class ) . '">';
			echo '<h4>' . esc_html( $rule->get_name() ) . '</h4>';
			echo '<p><strong>Action:</strong> ' . esc_html( $action_text ) . ' | <strong>Priority:</strong> ' . esc_html( $rule->get_priority() ) . ' | <strong>Status:</strong> ' . esc_html( $status_text ) . '</p>';
			
			if ( method_exists( $rule, 'get_description' ) ) {
				echo '<p><strong>Condition:</strong> ' . esc_html( $rule->get_description() ) . '</p>';
			}
			
			echo '<div class="rule-actions">';
			echo '<button class="button edit-rule" data-rule-id="' . esc_attr( $rule->get_id() ) . '">Edit</button>';
			echo '<button class="button button-link-delete delete-rule" data-rule-id="' . esc_attr( $rule->get_id() ) . '">Delete</button>';
			echo '</div>';
			echo '</div>';
		}
	}

	/**
	 * Render the rule creation/editing form.
	 */
	private function render_rule_form() {
		?>
		<form id="rule-form" method="post">
			<table class="form-table">
				<tr>
					<th scope="row"><label for="rule_name">Rule Name</label></th>
					<td>
						<input type="text" id="rule_name" name="rule_name" class="regular-text" required>
						<p class="description">Give your rule a descriptive name</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row"><label for="rule_action">Action</label></th>
					<td>
						<select id="rule_action" name="rule_action" required>
							<option value="include">Include Events</option>
							<option value="exclude">Exclude Events</option>
						</select>
						<p class="description">Whether to include or exclude matching events</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row"><label for="rule_priority">Priority</label></th>
					<td>
						<input type="number" id="rule_priority" name="rule_priority" class="small-text" value="10" min="1" max="100" required>
						<p class="description">Lower numbers = higher priority. Exclusion rules are processed first.</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row"><label for="rule_type">Rule Type</label></th>
					<td>
						<select id="rule_type" name="rule_type" required>
							<option value="">Select Rule Type</option>
							<option value="TitleKeywordRule">Title Keyword</option>
						</select>
						<p class="description">The type of rule to apply</p>
					</td>
				</tr>
				
				<tr class="title-keyword-conditions" style="display: none;">
					<th scope="row"><label for="rule_keywords">Keywords</label></th>
					<td>
						<input type="text" id="rule_keywords" name="rule_keywords" class="regular-text">
						<p class="description">Comma-separated keywords to match against event titles</p>
					</td>
				</tr>
				
				<tr class="title-keyword-conditions" style="display: none;">
					<th scope="row"><label for="rule_match_type">Match Type</label></th>
					<td>
						<select id="rule_match_type" name="rule_match_type">
							<option value="contains">Contains</option>
							<option value="starts_with">Starts with</option>
							<option value="ends_with">Ends with</option>
							<option value="exact">Exact match</option>
						</select>
						<p class="description">How to match the keywords</p>
					</td>
				</tr>
				
				<tr class="title-keyword-conditions" style="display: none;">
					<th scope="row"><label for="rule_case_sensitive">Case Sensitive</label></th>
					<td>
						<input type="checkbox" id="rule_case_sensitive" name="rule_case_sensitive" value="1">
						<label for="rule_case_sensitive">Make keyword matching case sensitive</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row"><label for="rule_active">Active</label></th>
					<td>
						<input type="checkbox" id="rule_active" name="rule_active" value="1" checked>
						<label for="rule_active">Enable this rule</label>
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Rule">
			</p>
		</form>
		
		<script>
		jQuery(document).ready(function($) {
			// Show/hide rule type specific fields
			$('#rule_type').on('change', function() {
				var ruleType = $(this).val();
				
				// Hide all condition fields
				$('.title-keyword-conditions').hide();
				
				// Show relevant condition fields
				if (ruleType === 'TitleKeywordRule') {
					$('.title-keyword-conditions').show();
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX handler for saving rules.
	 */
	public function ajax_save_rule() {
		check_ajax_referer( 'save_rule', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		
		$rule_data = array(
			'name'       => sanitize_text_field( $_POST['rule_name'] ),
			'action'     => sanitize_text_field( $_POST['rule_action'] ),
			'priority'   => absint( $_POST['rule_priority'] ),
			'type'       => sanitize_text_field( $_POST['rule_type'] ),
			'is_active'  => isset( $_POST['rule_active'] ),
		);
		
		// Add rule type specific conditions
		switch ( $rule_data['type'] ) {
			case 'TitleKeywordRule':
				$rule_data['conditions'] = array(
					'keywords'       => sanitize_text_field( $_POST['rule_keywords'] ),
					'match_type'     => sanitize_text_field( $_POST['rule_match_type'] ),
					'case_sensitive' => isset( $_POST['rule_case_sensitive'] ),
				);
				break;
		}
		
		// Create and validate rule
		$rule = $this->create_rule_from_data( $rule_data );
		if ( ! $rule || ! $rule->is_valid() ) {
			wp_send_json_error( 'Invalid rule configuration' );
		}
		
		// Add rule to engine
		$this->rule_engine->add_rule( $rule );
		
		// Save rules
		$this->save_rules();
		
		wp_send_json_success( 'Rule saved successfully' );
	}

	/**
	 * AJAX handler for deleting rules.
	 */
	public function ajax_delete_rule() {
		check_ajax_referer( 'delete_rule', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		
		$rule_id = sanitize_text_field( $_POST['rule_id'] );
		
		if ( $this->rule_engine->remove_rule( $rule_id ) ) {
			$this->save_rules();
			wp_send_json_success( 'Rule deleted successfully' );
		} else {
			wp_send_json_error( 'Rule not found' );
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
	 * Sanitize rules data.
	 *
	 * @param array $rules_data Raw rules data.
	 * @return array Sanitized rules data.
	 */
	public function sanitize_rules( $rules_data ) {
		if ( ! is_array( $rules_data ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $rules_data as $rule_data ) {
			if ( is_array( $rule_data ) ) {
				$sanitized[] = array_map( 'sanitize_text_field', $rule_data );
			}
		}

		return $sanitized;
	}

	/**
	 * Get the rule engine instance.
	 *
	 * @return RuleEngine
	 */
	public function get_rule_engine() {
		return $this->rule_engine;
	}
} 