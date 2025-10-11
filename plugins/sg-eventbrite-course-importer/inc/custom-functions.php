<?php
/**
 * Custom Functions for Eventbrite Course Importer
 *
 * @package SG\EventbriteCourseImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Get plugin instance.
 *
 * @return \SG\EventbriteCourseImporter\Plugin|null
 */
function sg_eventbrite_course_importer_get_plugin() {
	if ( class_exists( 'SG\EventbriteCourseImporter\\Plugin' ) ) {
		return new \SG\EventbriteCourseImporter\Plugin();
	}
	return null;
}

/**
 * Helper function to check if plugin is active.
 *
 * @return bool
 */
function sg_eventbrite_course_importer_is_active() {
	return defined( 'SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_VERSION' );
}
