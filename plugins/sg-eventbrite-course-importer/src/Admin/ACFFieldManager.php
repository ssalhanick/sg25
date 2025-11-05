<?php
/**
 * ACF Field Manager Class.
 *
 * Handles ACF field registration for course metadata.
 *
 * @package SG\EventbriteCourseImporter\Admin
 * @since 1.1.0
 */

namespace SG\EventbriteCourseImporter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ACF Field Manager Class.
 *
 * Manages ACF field group registration for course metadata.
 *
 * @package SG\EventbriteCourseImporter\Admin
 * @since 1.1.0
 */
class ACFFieldManager {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'acf/init', array( $this, 'register_field_group' ) );
	}

	/**
	 * Register ACF field group for course metadata.
	 */
	public function register_field_group() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group( array(
			'key'                   => 'group_sg_course_metadata',
			'title'                 => __( 'Course Metadata', 'sg-eventbrite-course-importer' ),
			'fields'                => $this->get_fields(),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'sg_course',
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => '',
			'active'                => true,
			'description'           => __( 'Additional metadata fields for imported courses.', 'sg-eventbrite-course-importer' ),
		) );
	}

	/**
	 * Get field definitions.
	 *
	 * @return array Field definitions.
	 */
	private function get_fields() {
		return array(
			array(
				'key'               => 'field_sg_course_instructor',
				'label'             => __( 'Instructor', 'sg-eventbrite-course-importer' ),
				'name'              => 'instructor',
				'type'              => 'text',
				'instructions'      => __( 'Enter the instructor name for this course.', 'sg-eventbrite-course-importer' ),
				'required'          => 0,
				'conditional_logic' => 0,
				'default_value'     => '',
				'placeholder'       => __( 'Instructor name', 'sg-eventbrite-course-importer' ),
			),
			array(
				'key'               => 'field_sg_course_day_of_week',
				'label'             => __( 'Day of Week', 'sg-eventbrite-course-importer' ),
				'name'              => 'day_of_week',
				'type'              => 'select',
				'instructions'      => __( 'Select the day(s) of the week this course meets.', 'sg-eventbrite-course-importer' ),
				'required'          => 0,
				'conditional_logic' => 0,
				'choices'           => array(
					'monday'    => __( 'Monday', 'sg-eventbrite-course-importer' ),
					'tuesday'   => __( 'Tuesday', 'sg-eventbrite-course-importer' ),
					'wednesday' => __( 'Wednesday', 'sg-eventbrite-course-importer' ),
					'thursday'  => __( 'Thursday', 'sg-eventbrite-course-importer' ),
					'friday'    => __( 'Friday', 'sg-eventbrite-course-importer' ),
					'saturday'  => __( 'Saturday', 'sg-eventbrite-course-importer' ),
					'sunday'    => __( 'Sunday', 'sg-eventbrite-course-importer' ),
				),
				'allow_null'        => 1,
				'multiple'          => 1,
				'ui'                => 1,
				'ajax'              => 0,
			),
			array(
				'key'               => 'field_sg_course_class_length',
				'label'             => __( 'Class Length', 'sg-eventbrite-course-importer' ),
				'name'              => 'class_length',
				'type'              => 'text',
				'instructions'      => __( 'Enter the length of each class session (e.g., "2 hours", "90 minutes").', 'sg-eventbrite-course-importer' ),
				'required'          => 0,
				'conditional_logic' => 0,
				'default_value'     => '',
				'placeholder'       => __( 'e.g., 2 hours', 'sg-eventbrite-course-importer' ),
			),
			array(
				'key'               => 'field_sg_course_course_length',
				'label'             => __( 'Course Length', 'sg-eventbrite-course-importer' ),
				'name'              => 'course_length',
				'type'              => 'text',
				'instructions'      => __( 'Enter the total length of the course (e.g., "7 weeks", "8 sessions").', 'sg-eventbrite-course-importer' ),
				'required'          => 0,
				'conditional_logic' => 0,
				'default_value'     => '',
				'placeholder'       => __( 'e.g., 7 weeks', 'sg-eventbrite-course-importer' ),
			),
			array(
				'key'               => 'field_sg_course_ticket_class_id',
				'label'             => __( 'Ticket Class ID', 'sg-eventbrite-course-importer' ),
				'name'              => 'ticket_class_id',
				'type'              => 'text',
				'instructions'      => '',
				'required'          => 0,
				'conditional_logic' => 0,
				'default_value'     => '',
				'wrapper'           => array(
					'width' => '',
					'class' => 'hidden',
					'id'    => '',
				),
			),
			array(
				'key'               => 'field_sg_course_ticket_class_name',
				'label'             => __( 'Ticket Class Name', 'sg-eventbrite-course-importer' ),
				'name'              => 'ticket_class_name',
				'type'              => 'text',
				'instructions'      => __( 'The name of the ticket class for this course.', 'sg-eventbrite-course-importer' ),
				'required'          => 0,
				'conditional_logic' => 0,
				'default_value'     => '',
				'placeholder'       => '',
			),
			array(
				'key'               => 'field_sg_course_ticket_price',
				'label'             => __( 'Ticket Price (Base)', 'sg-eventbrite-course-importer' ),
				'name'              => 'ticket_price',
				'type'              => 'number',
				'instructions'      => __( 'Base ticket price without fees.', 'sg-eventbrite-course-importer' ),
				'required'          => 0,
				'conditional_logic' => 0,
				'default_value'     => '',
				'placeholder'       => '',
				'prepend'           => '',
				'append'            => '',
				'min'               => 0,
				'max'               => '',
				'step'              => 0.01,
			),
			array(
				'key'               => 'field_sg_course_ticket_price_total',
				'label'             => __( 'Ticket Price (Total)', 'sg-eventbrite-course-importer' ),
				'name'              => 'ticket_price_total',
				'type'              => 'number',
				'instructions'      => __( 'Total ticket price including fees (if applicable).', 'sg-eventbrite-course-importer' ),
				'required'          => 0,
				'conditional_logic' => 0,
				'default_value'     => '',
				'placeholder'       => '',
				'prepend'           => '',
				'append'            => '',
				'min'               => 0,
				'max'               => '',
				'step'              => 0.01,
			),
			array(
				'key'               => 'field_sg_course_ticket_expiration',
				'label'             => __( 'Ticket Expiration Date', 'sg-eventbrite-course-importer' ),
				'name'              => 'ticket_expiration',
				'type'              => 'date_time_picker',
				'instructions'      => __( 'When ticket sales end for this class.', 'sg-eventbrite-course-importer' ),
				'required'          => 0,
				'conditional_logic' => 0,
				'display_format'    => 'Y-m-d H:i:s',
				'return_format'     => 'Y-m-d H:i:s',
				'first_day'         => 1,
			),
			array(
				'key'               => 'field_sg_course_ticket_sales_start',
				'label'             => __( 'Ticket Sales Start Date', 'sg-eventbrite-course-importer' ),
				'name'              => 'ticket_sales_start',
				'type'              => 'date_time_picker',
				'instructions'      => __( 'When ticket sales begin for this class.', 'sg-eventbrite-course-importer' ),
				'required'          => 0,
				'conditional_logic' => 0,
				'display_format'    => 'Y-m-d H:i:s',
				'return_format'     => 'Y-m-d H:i:s',
				'first_day'         => 1,
			),
		);
	}
}

