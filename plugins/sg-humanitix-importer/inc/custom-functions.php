<?php
/**
 * Custom Functions for Humanitix Importer
 *
 * @package SG\HumanitixImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Get plugin instance.
 *
 * @return \SG\HumanitixImporter\Plugin|null
 */
function sg_humanitix_importer_get_plugin() {
	if ( class_exists( 'SG\HumanitixImporter\\Plugin' ) ) {
		return new \SG\HumanitixImporter\Plugin();
	}
	return null;
}

/**
 * Helper function to check if plugin is active.
 *
 * @return bool
 */
function sg_humanitix_importer_is_active() {
	return defined( 'SG_HUMANITIX_IMPORTER_PLUGIN_VERSION' );
}
