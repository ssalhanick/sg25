<?php
/**
 * Custom Post Type Class.
 *
 * Handles the registration and management of the SG Course custom post type.
 *
 * @package SG\EventbriteCourseImporter
 * @since 1.0.0
 */

namespace SG\EventbriteCourseImporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Custom Post Type Class.
 *
 * Manages the SG Course custom post type registration and meta fields.
 *
 * @package SG\EventbriteCourseImporter
 * @since 1.0.0
 */
class CustomPostType {

	/**
	 * Post type name.
	 *
	 * @var string
	 */
	const POST_TYPE = 'sg_course';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_boxes' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'populate_admin_columns' ), 10, 2 );
		add_filter( 'template_include', array( $this, 'load_single_course_template' ) );
	}

	/**
	 * Register the custom post type.
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => _x( 'Courses', 'Post type general name', 'sg-eventbrite-course-importer' ),
			'singular_name'         => _x( 'Course', 'Post type singular name', 'sg-eventbrite-course-importer' ),
			'menu_name'             => _x( 'Courses', 'Admin Menu text', 'sg-eventbrite-course-importer' ),
			'name_admin_bar'        => _x( 'Course', 'Add New on Toolbar', 'sg-eventbrite-course-importer' ),
			'add_new'               => __( 'Add New', 'sg-eventbrite-course-importer' ),
			'add_new_item'          => __( 'Add New Course', 'sg-eventbrite-course-importer' ),
			'new_item'              => __( 'New Course', 'sg-eventbrite-course-importer' ),
			'edit_item'             => __( 'Edit Course', 'sg-eventbrite-course-importer' ),
			'view_item'             => __( 'View Course', 'sg-eventbrite-course-importer' ),
			'view_items'            => __( 'View Courses', 'sg-eventbrite-course-importer' ),
			'all_items'             => __( 'All Courses', 'sg-eventbrite-course-importer' ),
			'search_items'          => __( 'Search Courses', 'sg-eventbrite-course-importer' ),
			'parent_item_colon'     => __( 'Parent Courses:', 'sg-eventbrite-course-importer' ),
			'not_found'             => __( 'No courses found.', 'sg-eventbrite-course-importer' ),
			'not_found_in_trash'    => __( 'No courses found in Trash.', 'sg-eventbrite-course-importer' ),
			'featured_image'        => _x( 'Course Image', 'Overrides the "Featured Image" phrase', 'sg-eventbrite-course-importer' ),
			'set_featured_image'    => _x( 'Set course image', 'Overrides the "Set featured image" phrase', 'sg-eventbrite-course-importer' ),
			'remove_featured_image' => _x( 'Remove course image', 'Overrides the "Remove featured image" phrase', 'sg-eventbrite-course-importer' ),
			'use_featured_image'    => _x( 'Use as course image', 'Overrides the "Use as featured image" phrase', 'sg-eventbrite-course-importer' ),
			'archives'              => _x( 'Course archives', 'The post type archive label', 'sg-eventbrite-course-importer' ),
			'insert_into_item'      => _x( 'Insert into course', 'Overrides the "Insert into post" phrase', 'sg-eventbrite-course-importer' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this course', 'Overrides the "Uploaded to this post" phrase', 'sg-eventbrite-course-importer' ),
			'filter_items_list'     => _x( 'Filter courses list', 'Screen reader text for the filter links', 'sg-eventbrite-course-importer' ),
			'items_list_navigation' => _x( 'Courses list navigation', 'Screen reader text for the pagination', 'sg-eventbrite-course-importer' ),
			'items_list'            => _x( 'Courses list', 'Screen reader text for the items list', 'sg-eventbrite-course-importer' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'courses' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 20,
			'menu_icon'          => 'dashicons-book-alt',
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
			'template'           => array(
				array( 'core/paragraph', array( 'placeholder' => 'Course description...' ) ),
				array( 'core/heading', array( 'level' => 2, 'placeholder' => 'Course Details' ) ),
			),
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register taxonomies for the custom post type.
	 */
	public function register_taxonomies() {
		// Course Category taxonomy
		$category_labels = array(
			'name'              => _x( 'Course Categories', 'taxonomy general name', 'sg-eventbrite-course-importer' ),
			'singular_name'     => _x( 'Course Category', 'taxonomy singular name', 'sg-eventbrite-course-importer' ),
			'search_items'      => __( 'Search Course Categories', 'sg-eventbrite-course-importer' ),
			'all_items'         => __( 'All Course Categories', 'sg-eventbrite-course-importer' ),
			'parent_item'       => __( 'Parent Course Category', 'sg-eventbrite-course-importer' ),
			'parent_item_colon' => __( 'Parent Course Category:', 'sg-eventbrite-course-importer' ),
			'edit_item'         => __( 'Edit Course Category', 'sg-eventbrite-course-importer' ),
			'update_item'       => __( 'Update Course Category', 'sg-eventbrite-course-importer' ),
			'add_new_item'      => __( 'Add New Course Category', 'sg-eventbrite-course-importer' ),
			'new_item_name'     => __( 'New Course Category Name', 'sg-eventbrite-course-importer' ),
			'menu_name'         => __( 'Course Categories', 'sg-eventbrite-course-importer' ),
		);

		register_taxonomy(
			'sg_course_category',
			self::POST_TYPE,
			array(
				'hierarchical'      => true,
				'labels'            => $category_labels,
				'show_ui'           => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => array( 'slug' => 'course-category' ),
			)
		);

		// Course Tags taxonomy
		$tag_labels = array(
			'name'                       => _x( 'Course Tags', 'taxonomy general name', 'sg-eventbrite-course-importer' ),
			'singular_name'              => _x( 'Course Tag', 'taxonomy singular name', 'sg-eventbrite-course-importer' ),
			'search_items'               => __( 'Search Course Tags', 'sg-eventbrite-course-importer' ),
			'popular_items'              => __( 'Popular Course Tags', 'sg-eventbrite-course-importer' ),
			'all_items'                  => __( 'All Course Tags', 'sg-eventbrite-course-importer' ),
			'edit_item'                  => __( 'Edit Course Tag', 'sg-eventbrite-course-importer' ),
			'update_item'                => __( 'Update Course Tag', 'sg-eventbrite-course-importer' ),
			'add_new_item'               => __( 'Add New Course Tag', 'sg-eventbrite-course-importer' ),
			'new_item_name'              => __( 'New Course Tag Name', 'sg-eventbrite-course-importer' ),
			'separate_items_with_commas' => __( 'Separate course tags with commas', 'sg-eventbrite-course-importer' ),
			'add_or_remove_items'        => __( 'Add or remove course tags', 'sg-eventbrite-course-importer' ),
			'choose_from_most_used'      => __( 'Choose from the most used course tags', 'sg-eventbrite-course-importer' ),
			'not_found'                  => __( 'No course tags found.', 'sg-eventbrite-course-importer' ),
			'menu_name'                  => __( 'Course Tags', 'sg-eventbrite-course-importer' ),
		);

		register_taxonomy(
			'sg_course_tag',
			self::POST_TYPE,
			array(
				'hierarchical'          => false,
				'labels'                => $tag_labels,
				'show_ui'               => true,
				'show_in_rest'          => true,
				'show_admin_column'     => true,
				'update_count_callback' => '_update_post_term_count',
				'query_var'             => true,
				'rewrite'               => array( 'slug' => 'course-tag' ),
			)
		);
	}

	/**
	 * Add meta boxes for course details.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'sg_course_details',
			__( 'Course Details', 'sg-eventbrite-course-importer' ),
			array( $this, 'course_details_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'sg_course_eventbrite',
			__( 'Eventbrite Information', 'sg-eventbrite-course-importer' ),
			array( $this, 'eventbrite_info_meta_box' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Course details meta box callback.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function course_details_meta_box( $post ) {
		wp_nonce_field( 'sg_course_meta_box', 'sg_course_meta_box_nonce' );

		$start_date = get_post_meta( $post->ID, '_sg_course_start_date', true );
		$start_time = get_post_meta( $post->ID, '_sg_course_start_time', true );
		$price = get_post_meta( $post->ID, '_sg_course_price', true );
		$location = get_post_meta( $post->ID, '_sg_course_location', true );
		$instructor = get_post_meta( $post->ID, '_sg_course_instructor', true );
		$class_length = get_post_meta( $post->ID, '_sg_course_class_length', true );
		$course_length = get_post_meta( $post->ID, '_sg_course_course_length', true );
		$drop_in_class = get_post_meta( $post->ID, '_sg_course_drop_in_class', true );
		$day_of_week = get_post_meta( $post->ID, '_sg_course_day_of_week', true );
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="sg_course_start_date"><?php _e( 'Start Date', 'sg-eventbrite-course-importer' ); ?></label>
				</th>
				<td>
					<input type="date" id="sg_course_start_date" name="sg_course_start_date" value="<?php echo esc_attr( $start_date ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="sg_course_start_time"><?php _e( 'Start Time', 'sg-eventbrite-course-importer' ); ?></label>
				</th>
				<td>
					<input type="time" id="sg_course_start_time" name="sg_course_start_time" value="<?php echo esc_attr( $start_time ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="sg_course_price"><?php _e( 'Price', 'sg-eventbrite-course-importer' ); ?></label>
				</th>
				<td>
					<input type="text" id="sg_course_price" name="sg_course_price" value="<?php echo esc_attr( $price ); ?>" class="regular-text" placeholder="e.g., $50.00 or Free" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="sg_course_location"><?php _e( 'Location', 'sg-eventbrite-course-importer' ); ?></label>
				</th>
				<td>
					<input type="text" id="sg_course_location" name="sg_course_location" value="<?php echo esc_attr( $location ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="sg_course_instructor"><?php _e( 'Instructor', 'sg-eventbrite-course-importer' ); ?></label>
				</th>
				<td>
					<input type="text" id="sg_course_instructor" name="sg_course_instructor" value="<?php echo esc_attr( $instructor ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="sg_course_class_length"><?php _e( 'Class Length (hours)', 'sg-eventbrite-course-importer' ); ?></label>
				</th>
				<td>
					<input type="number" id="sg_course_class_length" name="sg_course_class_length" value="<?php echo esc_attr( $class_length ); ?>" class="small-text" step="0.5" min="0" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="sg_course_course_length"><?php _e( 'Course Length', 'sg-eventbrite-course-importer' ); ?></label>
				</th>
				<td>
					<input type="text" id="sg_course_course_length" name="sg_course_course_length" value="<?php echo esc_attr( $course_length ); ?>" class="regular-text" placeholder="e.g., 4 weeks, 8 sessions" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="sg_course_day_of_week"><?php _e( 'Day of Week', 'sg-eventbrite-course-importer' ); ?></label>
				</th>
				<td>
					<select id="sg_course_day_of_week" name="sg_course_day_of_week">
						<option value=""><?php _e( 'Select day', 'sg-eventbrite-course-importer' ); ?></option>
						<option value="Monday" <?php selected( $day_of_week, 'Monday' ); ?>><?php _e( 'Monday', 'sg-eventbrite-course-importer' ); ?></option>
						<option value="Tuesday" <?php selected( $day_of_week, 'Tuesday' ); ?>><?php _e( 'Tuesday', 'sg-eventbrite-course-importer' ); ?></option>
						<option value="Wednesday" <?php selected( $day_of_week, 'Wednesday' ); ?>><?php _e( 'Wednesday', 'sg-eventbrite-course-importer' ); ?></option>
						<option value="Thursday" <?php selected( $day_of_week, 'Thursday' ); ?>><?php _e( 'Thursday', 'sg-eventbrite-course-importer' ); ?></option>
						<option value="Friday" <?php selected( $day_of_week, 'Friday' ); ?>><?php _e( 'Friday', 'sg-eventbrite-course-importer' ); ?></option>
						<option value="Saturday" <?php selected( $day_of_week, 'Saturday' ); ?>><?php _e( 'Saturday', 'sg-eventbrite-course-importer' ); ?></option>
						<option value="Sunday" <?php selected( $day_of_week, 'Sunday' ); ?>><?php _e( 'Sunday', 'sg-eventbrite-course-importer' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="sg_course_drop_in_class"><?php _e( 'Drop-in Class', 'sg-eventbrite-course-importer' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="sg_course_drop_in_class" name="sg_course_drop_in_class" value="1" <?php checked( $drop_in_class, '1' ); ?> />
						<?php _e( 'This is a drop-in class', 'sg-eventbrite-course-importer' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Eventbrite information meta box callback.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function eventbrite_info_meta_box( $post ) {
		$eventbrite_id = get_post_meta( $post->ID, '_sg_course_eventbrite_id', true );
		$eventbrite_url = get_post_meta( $post->ID, '_sg_course_eventbrite_url', true );
		$last_imported = get_post_meta( $post->ID, '_sg_course_last_imported', true );
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="sg_course_eventbrite_id"><?php _e( 'Eventbrite ID', 'sg-eventbrite-course-importer' ); ?></label>
				</th>
				<td>
					<input type="text" id="sg_course_eventbrite_id" name="sg_course_eventbrite_id" value="<?php echo esc_attr( $eventbrite_id ); ?>" class="regular-text" readonly />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="sg_course_eventbrite_url"><?php _e( 'Eventbrite URL', 'sg-eventbrite-course-importer' ); ?></label>
				</th>
				<td>
					<input type="url" id="sg_course_eventbrite_url" name="sg_course_eventbrite_url" value="<?php echo esc_attr( $eventbrite_url ); ?>" class="regular-text" readonly />
				</td>
			</tr>
			<?php if ( $last_imported ) : ?>
			<tr>
				<th scope="row">
					<label><?php _e( 'Last Imported', 'sg-eventbrite-course-importer' ); ?></label>
				</th>
				<td>
					<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_imported ) ) ); ?>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	/**
	 * Save meta box data.
	 *
	 * @param int $post_id The post ID.
	 */
	public function save_meta_boxes( $post_id ) {
		// Check if our nonce is set and verify it.
		if ( ! isset( $_POST['sg_course_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['sg_course_meta_box_nonce'], 'sg_course_meta_box' ) ) {
			return;
		}

		// Check if this is an autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check the user's permissions.
		if ( isset( $_POST['post_type'] ) && self::POST_TYPE === $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}

		// Save meta fields
		$meta_fields = array(
			'sg_course_start_date',
			'sg_course_start_time',
			'sg_course_price',
			'sg_course_location',
			'sg_course_instructor',
			'sg_course_class_length',
			'sg_course_course_length',
			'sg_course_day_of_week',
			'sg_course_eventbrite_id',
			'sg_course_eventbrite_url',
		);

		foreach ( $meta_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, '_' . $field, sanitize_text_field( $_POST[ $field ] ) );
			}
		}

		// Handle checkbox
		$drop_in_class = isset( $_POST['sg_course_drop_in_class'] ) ? '1' : '0';
		update_post_meta( $post_id, '_sg_course_drop_in_class', $drop_in_class );
	}

	/**
	 * Add custom columns to the admin list table.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_admin_columns( $columns ) {
		$new_columns = array();
		
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			
			if ( 'title' === $key ) {
				$new_columns['start_date'] = __( 'Start Date', 'sg-eventbrite-course-importer' );
				$new_columns['price'] = __( 'Price', 'sg-eventbrite-course-importer' );
				$new_columns['location'] = __( 'Location', 'sg-eventbrite-course-importer' );
			}
		}
		
		return $new_columns;
	}

	/**
	 * Populate custom admin columns.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function populate_admin_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'start_date':
				$start_date = get_post_meta( $post_id, '_sg_course_start_date', true );
				$start_time = get_post_meta( $post_id, '_sg_course_start_time', true );
				if ( $start_date ) {
					echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) );
					if ( $start_time ) {
						echo '<br><small>' . esc_html( date_i18n( get_option( 'time_format' ), strtotime( $start_time ) ) ) . '</small>';
					}
				} else {
					echo '—';
				}
				break;
				
			case 'price':
				$price = get_post_meta( $post_id, '_sg_course_price', true );
				echo $price ? esc_html( $price ) : '—';
				break;
				
			case 'location':
				$location = get_post_meta( $post_id, '_sg_course_location', true );
				echo $location ? esc_html( $location ) : '—';
				break;
		}
	}

	/**
	 * Load custom template for single course posts.
	 *
	 * @param string $template The template path.
	 * @return string The modified template path.
	 */
	public function load_single_course_template( $template ) {
		if ( is_singular( self::POST_TYPE ) ) {
			$custom_template = SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_PATH . '/templates/single-sg_course.php';
			if ( file_exists( $custom_template ) ) {
				return $custom_template;
			}
		}
		return $template;
	}
}