<?php
/**
 * Custom Single Event Template
 * 
 * Override for The Events Calendar single event display
 * 
 * @package SG\HumanitixApiImporter\Templates
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Debug: Check if this template is being loaded
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( '[sg-humanitix-api-importer] Custom single-event.php template is being loaded!' );
}

$featured_image = get_the_post_thumbnail_url( get_the_ID(), 'full' );
// Get the height
$attachment_id = get_post_thumbnail_id( get_the_ID() );
$image_data = wp_get_attachment_image_src( $attachment_id, 'full' );
$image_height = $image_data ? $image_data[2] : null;

$event_id = get_the_ID();
$humanitix_url = get_post_meta( $event_id, '_EventURL', true );

get_header(); ?>

<div id="tribe-events-pg-template" class="tribe-events-pg-template">
    <div class="sg-humanitix-event-container">
        <div class="background-image" style="background-image: url('<?php echo esc_url( $featured_image ); ?>');"></div>
        <section class="meta-content" style="max-height: <?php echo $image_height; ?>px;">
            <div class="meta-content-inner">
                <h1 class="sg-humanitix-event-title">
                    <?php the_title(); ?>
                </h1>
                <!-- Event Meta -->
                <div class="sg-humanitix-event-meta">
                    
                    <!-- Date/Time -->
                    <div class="sg-humanitix-meta-item">
                        <span class="sg-humanitix-meta-icon"></span>
                        <span class="sg-humanitix-meta-text">
                            <?php 
                            if ( function_exists( 'tribe_events_event_schedule_details' ) ) {
                                echo tribe_events_event_schedule_details();
                            } else {
                                echo get_the_date();
                            }
                            ?>
                        </span>
                    </div>
                    <!-- Price -->
                    <div class="sg-humanitix-meta-item">
                        <span class="sg-humanitix-meta-icon"></span>
                        <?php

                        function get_general_admission_price( $event_id = null ) {
                            if ( ! $event_id ) {
                                $event_id = get_the_ID();
                            }
                            
                            $ticket_types_json = get_post_meta( $event_id, 'humanitix_ticket_types', true );
                            $ticket_types = json_decode( $ticket_types_json, true );
                            if ( $ticket_types && is_array( $ticket_types ) ) {
                                foreach ( $ticket_types as $ticket ) {
                                    if ( $ticket['name'] === 'General Admission' ) {
                                        return $ticket['price'];
                                    }
                                }
                            }
                            
                            return null;
                        }

                        // Usage
                        $price = get_general_admission_price();
                        if ( $price !== null ) {
                            $cost = $price;
                        } else {
                            $cost = tribe_get_formatted_cost( get_the_ID() );
                        }
                            
                        if ( $cost ) {?>
                            <span class="sg-humanitix-meta-text">
                                <?php echo $cost . ' and up'; // This will include proper formatting and currency ?>
                            </span>
                        <?php }
                        ?>
                    </div>
                    <!-- Location -->
                    <div class="sg-humanitix-meta-item">
                        <span class="sg-humanitix-meta-icon"></span>
                        <span class="sg-humanitix-meta-text">
                            <?php if ( function_exists( 'tribe_get_venue' ) && function_exists( 'tribe_get_venue_link' ) && tribe_get_venue_link() ) {
                                echo tribe_get_venue_link();
                            } ?>
                        </span>
                    </div>
                    
                </div>
                <!-- Action Buttons -->
                <?php if ( function_exists( 'tribe_get_event_website_link' ) ) {
                    $website_link = tribe_get_event_website_link( get_the_ID() );
                    if ( $website_link ) { ?>
                        <div class="sg-humanitix-event-actions">
                            <button class="sg-humanitix-interest-btn">
                                <a href="<?php echo esc_url($humanitix_url) ?>" target="_blank">
                                Buy Tickets
                                </a>
                            </button>
                        </div>
                    <?php }
                } ?>
                
            </div>
            <div class="featured-image">
                <?php the_post_thumbnail( 'full' ); ?>
            </div>
        </section>
    <?php if ( function_exists( 'tribe_events_before_html' ) ) : tribe_events_before_html(); endif; ?>
    </div>
    <!-- Main Event Description -->
    <div class="sg-humanitix-event-description">
       
        
        <!-- Event Content Below -->
        <div class="sg-humanitix-event-body">
            <?php 
            if ( function_exists( 'tribe_events_single_event_content' ) ) {
                tribe_events_single_event_content();
            } else {
                the_content();
            }
            ?>
        </div>
        
        <!-- Event Meta Below -->
        <div class="sg-humanitix-event-meta-full">
            <?php 
            if ( function_exists( 'tribe_events_single_event_meta' ) ) {
                tribe_events_single_event_meta();
            }
            ?>
        </div>
        
    </div>
    
    <?php if ( function_exists( 'tribe_events_after_html' ) ) : tribe_events_after_html(); endif; ?>
</div>

<?php get_footer(); ?>