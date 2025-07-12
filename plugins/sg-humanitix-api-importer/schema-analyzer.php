<?php
/**
 * Humanitix API Schema Analyzer
 *
 * This script analyzes the Humanitix API structure and suggests field mappings.
 * Run this from the command line: php schema-analyzer.php [--limit=N]
 *
 * @package SG\HumanitixApiImporter
 */

// Load WordPress.
require_once dirname( __DIR__, 3 ) . '/wp-load.php';

// Check if we're in CLI.
if ( php_sapi_name() !== 'cli' ) {
	die( 'This script must be run from the command line.' );
}

// Parse command line arguments.
$sg_limit = 10; // Default to 10 events
foreach ( $argv as $sg_arg ) {
	if ( strpos( $sg_arg, '--limit=' ) === 0 ) {
		$sg_limit = (int) substr( $sg_arg, 8 );
		if ( $sg_limit < 1 ) {
			$sg_limit = 1;
		} elseif ( $sg_limit > 50 ) {
			$sg_limit = 50;
		}
	}
}

echo "=== Humanitix API Schema Analyzer ===\n\n";
echo "Analyzing up to {$sg_limit} events...\n\n";

// Get API settings from WordPress admin.
$sg_options      = get_option( 'humanitix_importer_options', array() );
$sg_api_key      = $sg_options['api_key'] ?? '';
$sg_org_id       = $sg_options['org_id'] ?? '';
$sg_api_endpoint = $sg_options['api_endpoint'] ?? 'https://api.humanitix.com/v1';

echo 'API Endpoint: ' . esc_html( $sg_api_endpoint ) . "\n";
echo 'API Key: ' . ( empty( $sg_api_key ) ? 'NOT SET - Please configure in WordPress Admin' : esc_html( substr( $sg_api_key, 0, 8 ) ) . '...' ) . "\n";
echo 'Organization ID: ' . ( empty( $sg_org_id ) ? 'NOT SET - Please configure in WordPress Admin' : esc_html( $sg_org_id ) ) . "\n\n";

// Check if API key is configured.
if ( empty( $sg_api_key ) ) {
	echo "❌ API key not configured!\n";
	echo "Please go to WordPress Admin → Humanitix Importer → Settings\n";
	echo "Enter your API key and save the settings.\n\n";
	echo "For testing with mock server, you can use any dummy key like 'test-key'.\n\n";
	exit( 1 );
}

// Check if organization ID is configured.
if ( empty( $sg_org_id ) ) {
	echo "❌ Organization ID not configured!\n";
	echo "Please go to WordPress Admin → Humanitix Importer → Settings\n";
	echo "Enter your organization ID and save the settings.\n\n";
	echo "For testing with mock server, you can use any dummy org ID like 'org_test123'.\n\n";
	exit( 1 );
}

// Check if the plugin classes are available.
if ( ! class_exists( 'SG\HumanitixApiImporter\HumanitixAPI' ) ) {
	echo "❌ HumanitixAPI class not found. Make sure the plugin is loaded.\n";
	exit( 1 );
}

if ( ! class_exists( 'SG\HumanitixApiImporter\Importer\DataMapper' ) ) {
	echo "❌ DataMapper class not found. Make sure the plugin is loaded.\n";
	exit( 1 );
}

try {
	// Initialize API and DataMapper.
	$sg_api    = new SG\HumanitixApiImporter\HumanitixAPI( $sg_api_key, $sg_api_endpoint, $sg_org_id );
	$sg_mapper = new SG\HumanitixApiImporter\Importer\DataMapper();

	echo "1. Testing API connection...\n";
	$sg_connection_result = $sg_api->test_connection();

	if ( $sg_connection_result['success'] ) {
		echo "✅ API connection successful!\n";
		echo 'Message: ' . esc_html( $sg_connection_result['message'] ) . "\n\n";
	} else {
		echo '❌ API connection failed: ' . esc_html( $sg_connection_result['message'] ) . "\n\n";
		// Continue anyway for mock server testing.
	}

	echo "2. Attempting to get API schema...\n";
	$sg_schema_result = $sg_api->get_schema_info();

	if ( is_wp_error( $sg_schema_result ) ) {
		echo 'ℹ️  API schema not available: ' . esc_html( $sg_schema_result->get_error_message() ) . "\n";
		echo "This is expected for the Humanitix API, which doesn't provide OpenAPI/Swagger schema endpoints.\n";
		echo "The schema analysis will continue using sample event data instead.\n\n";
	} else {
		echo '✅ API schema retrieved from: ' . esc_html( $sg_schema_result['endpoint'] ) . "\n";
		echo 'Schema type: ' . esc_html( $sg_schema_result['description'] ) . "\n\n";

		// Display schema structure.
		if ( isset( $sg_schema_result['schema']['paths'] ) ) {
			echo "Available API endpoints:\n";
			foreach ( $sg_schema_result['schema']['paths'] as $api_path => $api_methods ) {
				echo '  ' . esc_html( $api_path ) . "\n";
				foreach ( $api_methods as $api_method => $api_details ) {
					echo '    ' . esc_html( $api_method ) . ': ' . esc_html( $api_details['summary'] ?? 'No description' ) . "\n";
				}
			}
			echo "\n";
		}
	}

	// Provide information about Humanitix API structure.
	echo "Humanitix API Information:\n";
	echo "  Base URL: https://api.humanitix.com/v1\n";
	echo "  Authentication: x-api-key header\n";
	echo "  Organization scoping: Required via header or query parameter\n";
	echo "  Main endpoints:\n";
	echo "    - GET /events - List events for organization\n";
	echo "    - GET /events/{id} - Get specific event details\n";
	echo "    - GET /venues - List venues for organization\n";
	echo "    - GET /organizers - List organizers for organization\n";
	echo "  Event data includes:\n";
	echo "    - name: Event title\n";
	echo "    - description: Event description\n";
	echo "    - startDate: Start date/time (ISO 8601 format, e.g., '2021-02-01T23:26:13.485Z')\n";
	echo "    - endDate: End date/time (ISO 8601 format)\n";
	echo "    - timezone: Event timezone (e.g., 'Pacific/Auckland')\n";
	echo "    - venue: Venue information\n";
	echo "    - organizer: Organizer information\n";
	echo "    - ticketTypes: Ticket information\n";
	echo "    - images: Event images\n";
	echo "  Series events: Supported with recurrence rules and instance tracking\n\n";

	echo "3. Attempting to get sample events...\n";
	$sg_sample_result = $sg_api->get_sample_events( $sg_limit );

	if ( is_wp_error( $sg_sample_result ) ) {
		echo '❌ Could not retrieve sample events: ' . esc_html( $sg_sample_result->get_error_message() ) . "\n";
		echo "This might be due to:\n";
		echo "- API key not having event read permissions\n";
		echo "- Mock server not having sample data\n";
		echo "- Different endpoint structure\n\n";

		// Create mock sample data for analysis.
		echo "Creating mock sample data for analysis...\n";
		$sg_mock_event = array(
			'id'          => 'mock_event_001',
			'title'       => 'Sample Event Title',
			'description' => 'This is a sample event description with details about the event.',
			'start_date'  => '2024-01-15',
			'end_date'    => '2024-01-15',
			'start_time'  => '19:00:00',
			'end_time'    => '21:00:00',
			'venue'       => array(
				'name'    => 'Sample Venue',
				'address' => '123 Main Street',
				'city'    => 'Sample City',
				'state'   => 'CA',
				'zip'     => '90210',
				'country' => 'USA',
			),
			'organizer'   => array(
				'name'  => 'Sample Organizer',
				'email' => 'organizer@example.com',
				'phone' => '+1-555-0123',
			),
			'tickets'     => array(
				array(
					'name'      => 'General Admission',
					'price'     => 25.00,
					'currency'  => 'USD',
					'capacity'  => 100,
					'available' => 75,
				),
			),
			'categories'  => array( 'Technology', 'Networking' ),
			'tags'        => array( 'workshop', 'tech' ),
			'status'      => 'published',
			'url'         => 'https://example.com/event',
			'image'       => 'https://example.com/event-image.jpg',
		);

		$sg_sample_result = array(
			'success' => true,
			'events'  => array( $sg_mock_event ),
			'count'   => 1,
		);

		echo "✅ Mock sample data created.\n\n";
	} else {
		echo '✅ Retrieved ' . esc_html( $sg_sample_result['count'] ) . " sample events.\n\n";
	}

	if ( $sg_sample_result['success'] && ! empty( $sg_sample_result['events'] ) ) {
		echo "4. Analyzing event structure...\n";

		foreach ( $sg_sample_result['events'] as $sg_index => $sg_event ) {
			$sg_event_number = (int) $sg_index + 1;
			echo 'Event ' . esc_html( $sg_event_number ) . ":\n";

			// Analyze event structure.
			$sg_analysis = $sg_api->analyze_event_structure( $sg_event );

			echo '  Fields found: ' . esc_html( count( $sg_analysis['fields'] ) ) . "\n";
			echo '  Required fields: ' . esc_html( count( $sg_analysis['required_fields'] ) ) . "\n";
			echo '  Optional fields: ' . esc_html( count( $sg_analysis['optional_fields'] ) ) . "\n";
			echo '  Nested objects: ' . esc_html( count( $sg_analysis['nested_objects'] ) ) . "\n";
			echo '  Arrays: ' . esc_html( count( $sg_analysis['arrays'] ) ) . "\n\n";

			echo "  Field details:\n";
			foreach ( $sg_analysis['fields'] as $sg_field => $sg_info ) {
				$sg_sample_value = is_string( $sg_info['value'] ) ? substr( $sg_info['value'], 0, 30 ) : wp_json_encode( $sg_info['value'] );
				echo '    ' . esc_html( $sg_field ) . ' (' . esc_html( $sg_info['type'] ) . '): ' . esc_html( $sg_sample_value ) . "\n";
			}
			echo "\n";

			// Get mapping suggestions.
			echo "  Suggested field mappings:\n";
			$sg_suggestions = $sg_mapper->suggest_mappings( $sg_event );

			foreach ( $sg_suggestions as $sg_suggestion ) {
				$sg_sample = is_string( $sg_suggestion['sample_value'] ) ? $sg_suggestion['sample_value'] : wp_json_encode( $sg_suggestion['sample_value'] );
				echo '    ' . esc_html( $sg_suggestion['humanitix_field'] ) . ' → ' . esc_html( $sg_suggestion['suggested_tec_field'] ) . ' (' . esc_html( $sg_sample ) . ")\n";
			}
			echo "\n";

			// Test mapping.
			echo "  Testing data mapping...\n";
			$sg_mapped_event = $sg_mapper->map_event( $sg_event );

			if ( ! empty( $sg_mapped_event ) ) {
				echo "    ✅ Event mapped successfully\n";
				echo '    Post title: ' . esc_html( $sg_mapped_event['post_title'] ?? 'Not set' ) . "\n";
				echo '    Post content length: ' . esc_html( strlen( $sg_mapped_event['post_content'] ?? '' ) ) . " characters\n";
				echo '    Meta fields: ' . esc_html( count( $sg_mapped_event['meta_input'] ?? array() ) ) . "\n";

				echo "    Meta field details:\n";
				foreach ( $sg_mapped_event['meta_input'] as $sg_meta_key => $sg_meta_value ) {
					$sg_display_value = is_string( $sg_meta_value ) ? substr( $sg_meta_value, 0, 30 ) : wp_json_encode( $sg_meta_value );
					echo '      ' . esc_html( $sg_meta_key ) . ': ' . esc_html( $sg_display_value ) . "\n";
				}
			} else {
				echo "    ❌ Event mapping failed\n";
			}
			echo "\n";
		}
	}

	echo "5. Summary and recommendations:\n";
	echo "✅ Schema analysis completed successfully!\n\n";

	echo "Usage:\n";
	echo "  php schema-analyzer.php              # Analyze 10 events (default)\n";
	echo "  php schema-analyzer.php --limit=5    # Analyze 5 events\n";
	echo "  php schema-analyzer.php --limit=20   # Analyze 20 events (max 50)\n\n";

	echo "Next steps:\n";
	echo "1. Review the field mappings above\n";
	echo "2. Customize the DataMapper class if needed\n";
	echo "3. Test with real API data\n";
	echo "4. Configure import settings in WordPress admin\n";
	echo "5. Run a test import\n\n";

	echo "For The Events Calendar compatibility, ensure these fields are mapped:\n";
	echo "- EventStartDate and EventEndDate (required)\n";
	echo "- EventStartTime and EventEndTime (optional)\n";
	echo "- EventVenue (optional)\n";
	echo "- EventAddress, EventCity, EventState, EventZip, EventCountry (optional)\n";
	echo "- EventCost (optional)\n";
	echo "- EventURL (optional)\n\n";

} catch ( Exception $sg_exception ) {
	echo '❌ Error: ' . esc_html( $sg_exception->getMessage() ) . "\n";
	echo "Stack trace:\n" . esc_html( $sg_exception->getTraceAsString() ) . "\n";
	exit( 1 );
}

echo "=== Analysis Complete ===\n";
