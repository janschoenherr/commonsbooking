<?php

namespace CommonsBooking\Wordpress\CustomPostType;

use CommonsBooking\Repository\UserRepository;
use CommonsBooking\Settings\Settings;

class Location extends CustomPostType {

	public static $postType = 'cb_location';

	/**
	 * Item constructor.
	 */
	public function __construct() {
		add_filter( 'the_content', array( $this, 'getTemplate' ) );
		add_action( 'cmb2_admin_init', array( $this, 'registerMetabox' ) );

		// Listing of items for location
		add_shortcode( 'cb_items', array( \CommonsBooking\View\Item::class, 'shortcode' ) );

		// Filter only for current user allowed posts
		add_action( 'pre_get_posts', array( $this, 'filterAdminList' ) );

		// Save-handling
		add_action( 'save_post', array( $this, 'handleFormRequest' ) );
	}

	/**
	 * Handles save-Request for location.
	 */
	public function handleFormRequest() {
		$postType = isset( $_REQUEST['post_type'] ) ? sanitize_text_field( $_REQUEST['post_type'] ) : null;
		$postId   = isset( $_REQUEST['post_ID'] ) ? sanitize_text_field( $_REQUEST['post_ID'] ) : null;

		if ( $postType == self::$postType && $postId ) {
			$location = new \CommonsBooking\Model\Location( intval( $postId ) );
			$location->updateGeoLocation();
		}
	}

	/**
	 * Filters admin list by type (e.g. bookable, repair etc. )
	 *
	 * @param  (wp_query object) $query
	 *
	 * @return Void
	 */
	public static function filterAdminList( $query ) {
		global $pagenow;

		if (
			is_admin() && $query->is_main_query() &&
			isset( $_GET['post_type'] ) && self::$postType == $_GET['post_type'] &&
			$pagenow == 'edit.php'
		) {
			// Check if current user is allowed to see posts
			if ( ! commonsbooking_isCurrentUserAdmin() ) {
				$locations = \CommonsBooking\Repository\Location::getByCurrentUser();
				array_walk(
					$locations,
					function ( &$item, $key ) {
						$item = $item->ID;
					}
				);

				$query->query_vars['post__in'] = $locations;
			}
		}
	}

	public static function getView() {
		return new \CommonsBooking\View\Location();
	}

	public function getTemplate( $content ) {
		$cb_content = '';
		if ( is_singular( self::getPostType() ) ) {
			ob_start();
			commonsbooking_get_template_part( 'location', 'single' );
			$cb_content = ob_get_clean();
		} // if archive...

		return $content . $cb_content;
	}

	public function getArgs() {
		$labels = array(
			'name'                  => esc_html__( 'Locations', 'commonsbooking' ),
			'singular_name'         => esc_html__( 'Location', 'commonsbooking' ),
			'add_new'               => esc_html__( 'Add new', 'commonsbooking' ),
			'add_new_item'          => esc_html__( 'Add new location', 'commonsbooking' ),
			'edit_item'             => esc_html__( 'Edit location', 'commonsbooking' ),
			'new_item'              => esc_html__( 'Add new location', 'commonsbooking' ),
			'view_item'             => esc_html__( 'Show location', 'commonsbooking' ),
			'view_items'            => esc_html__( 'Show locations', 'commonsbooking' ),
			'search_items'          => esc_html__( 'Search locations', 'commonsbooking' ),
			'not_found'             => esc_html__( 'location not found', 'commonsbooking' ),
			'not_found_in_trash'    => esc_html__( 'No locations found in trash', 'commonsbooking' ),
			'parent_item_colon'     => esc_html__( 'Parent location:', 'commonsbooking' ),
			'all_items'             => esc_html__( 'All locations', 'commonsbooking' ),
			'archives'              => esc_html__( 'Location archive', 'commonsbooking' ),
			'attributes'            => esc_html__( 'Location attributes', 'commonsbooking' ),
			'insert_into_item'      => esc_html__( 'Add to location', 'commonsbooking' ),
			'uploaded_to_this_item' => esc_html__( 'Added to location', 'commonsbooking' ),
			'featured_image'        => esc_html__( 'Location image', 'commonsbooking' ),
			'set_featured_image'    => esc_html__( 'set location image', 'commonsbooking' ),
			'remove_featured_image' => esc_html__( 'remove location image', 'commonsbooking' ),
			'use_featured_image'    => esc_html__( 'use as location image', 'commonsbooking' ),
			'menu_name'             => esc_html__( 'Locations', 'commonsbooking' ),
		);

		$slug = Settings::getOption( 'commonsbooking_options_general', 'posttypes_locations-slug' );

		// args for the new post_type
		return array(
			'labels'            => $labels,

			// Sichtbarkeit des Post Types
			'public'            => true,

			// Standart Ansicht im Backend aktivieren (Wie Artikel / Seiten)
			'show_ui'           => true,

			// Soll es im Backend Menu sichtbar sein?
			'show_in_menu'      => false,

			// Position im Menu
			'menu_position'     => 4,

			// Post Type in der oberen Admin-Bar anzeigen?
			'show_in_admin_bar' => true,

			// in den Navigations Menüs sichtbar machen?
			'show_in_nav_menus' => true,

			// Hier können Berechtigungen in einem Array gesetzt werden
			// oder die standart Werte post und page in form eines Strings gesetzt werden
			'capability_type'   => array( self::$postType, self::$postType . 's' ),

			'map_meta_cap'        => true,

			// Soll es im Frontend abrufbar sein?
			'publicly_queryable'  => true,

			// Soll der Post Type aus der Suchfunktion ausgeschlossen werden?
			'exclude_from_search' => true,

			// Welche Elemente sollen in der Backend-Detailansicht vorhanden sein?
			'supports'            => array(
				'title',
				'editor',
				'thumbnail',
				'custom-fields',
				'revisions',
				'excerpt',
				'author'
			),

			// Soll der Post Type Kategien haben?
			'taxonomies'          => array( self::$postType . 's_category' ),

			// Soll der Post Type Archiv-Seiten haben?
			'has_archive'         => false,

			// Soll man den Post Type exportieren können?
			'can_export'          => false,

			// Slug unseres Post Types für die redirects
			// dieser Wert wird später in der URL stehen
			'rewrite'             => array( 'slug' => $slug ),

			'show_in_rest' => true,
		);
	}

	/**
	 * Creates MetaBoxes for Custom Post Type Location using CMB2
	 * more information on usage: https://cmb2.io/
	 *
	 * @return void
	 */
	public function registerMetabox() {
		// Initiate the metabox Adress
		$cmb = new_cmb2_box( array(
			'id'           => COMMONSBOOKING_METABOX_PREFIX . 'location_adress',
			'title'        => esc_html__( 'Address', 'commonsbooking' ),
			'object_types' => array( self::$postType ), // Post type
			'context'      => 'normal',
			'priority'     => 'high',
			'show_names'   => true, // Show field names on the left
		) );

		// Adress
		$cmb->add_field( array(
			'name'       => esc_html__( 'Street / No.', 'commonsbooking' ),
			'id'         => COMMONSBOOKING_METABOX_PREFIX . 'location_street',
			'type'       => 'text',
			'show_on_cb' => 'cmb2_hide_if_no_cats', // function should return a bool value
			'attributes' => array(
				'required' => 'required',
			),
		) );

		// Postcode
		$cmb->add_field( array(
			'name'       => esc_html__( 'Postcode', 'commonsbooking' ),
			'id'         => COMMONSBOOKING_METABOX_PREFIX . 'location_postcode',
			'type'       => 'text',
			'show_on_cb' => 'cmb2_hide_if_no_cats', // function should return a bool value
			'attributes' => array(
				'required' => 'required',
			),
		) );

		// City
		$cmb->add_field( array(
			'name'       => esc_html__( 'City', 'commonsbooking' ),
			'id'         => COMMONSBOOKING_METABOX_PREFIX . 'location_city',
			'type'       => 'text',
			'show_on_cb' => 'cmb2_hide_if_no_cats', // function should return a bool value
			'attributes' => array(
				'required' => 'required',
			),
		) );

		// Country
		$cmb->add_field( array(
			'name'       => esc_html__( 'Country', 'commonsbooking' ),
			//'desc'       => esc_html__('field description (optional)', 'commonsbooking'),
			'id'         => COMMONSBOOKING_METABOX_PREFIX . 'location_country',
			'type'       => 'text',
			'show_on_cb' => 'cmb2_hide_if_no_cats', // function should return a bool value
		) );

		// Latitude
		$cmb->add_field( array(
			'name'       => esc_html__( 'Latitude', 'commonsbooking' ),
			//'desc'       => esc_html__('field description (optional)', 'commonsbooking'),
			'id'         => 'geo_latitude',
			'type'       => 'text',
			'show_on_cb' => 'cmb2_hide_if_no_cats', // function should return a bool value
		) );

		// Longitude
		$cmb->add_field( array(
			'name'       => esc_html__( 'Longitude', 'commonsbooking' ),
			//'desc'       => esc_html__('field description (optional)', 'commonsbooking'),
			'id'         => 'geo_longitude',
			'type'       => 'text',
			'show_on_cb' => 'cmb2_hide_if_no_cats', // function should return a bool value
		) );

		// Map
		$cmb->add_field( array(
			'name'       => esc_html__( 'Position', 'commonsbooking' ),
			//'desc'       => esc_html__('field description (optional)', 'commonsbooking'),
			'id'         => COMMONSBOOKING_METABOX_PREFIX . '_map_position',
			'type'       => 'cb_map',
			'show_on_cb' => 'cmb2_hide_if_no_cats', // function should return a bool value
		) );

		// Show map on item view checkbox
		$cmb->add_field( array(
			'name'       => esc_html__( 'Show location map on item view' ),
			//'desc'       => esc_html__('field description (optional)', 'commonsbooking'),
			'id'         => 'loc_showmap',
			'type'       => 'checkbox',
			'show_on_cb' => 'cmb2_hide_if_no_cats', // function should return a bool value
		) );

		// Initiate the metabox Information
		$cmb = new_cmb2_box( array(
			'id'           => COMMONSBOOKING_METABOX_PREFIX . 'location_info',
			'title'        => esc_html__( 'General Location information', 'commonsbooking' ),
			'object_types' => array( self::$postType ), // Post type
			'context'      => 'normal',
			'priority'     => 'high',
			'show_names'   => true, // Show field names on the left
		) );

		// short description
		$cmb->add_field( array(
			'name'       => esc_html__( 'Location email', 'commonsbooking' ),
			'desc'       => esc_html__( 'email-address to get copy of booking confirmation and cancellation mails',
				'commonsbooking' ),
			'id'         => COMMONSBOOKING_METABOX_PREFIX . 'location_email',
			'type'       => 'text',
			'show_on_cb' => 'cmb2_hide_if_no_cats', // function should return a bool value
			// 'repeatable'      => true,
		) );

		// pickup description
		$cmb->add_field( array(
			'name'       => esc_html__( 'Pickup instructions', 'commonsbooking' ),
			'desc'       => esc_html__( 'Type in information about the pickup process (e.g. detailed route description, opening hours, etc.). This will be shown to user in booking process and booking confirmation mail',
				'commonsbooking' ),
			'id'         => COMMONSBOOKING_METABOX_PREFIX . 'location_pickupinstructions',
			'type'       => 'textarea_small',
			'show_on_cb' => 'cmb2_hide_if_no_cats', // function should return a bool value
			// 'repeatable'      => true,
		) );

		// location contact
		$cmb->add_field( array(
			'name'       => esc_html__( 'Location contact information', 'commonsbooking' ),
			'desc'       => esc_html__( 'information about how to contact the location (e.g. contact person, phone number, e-mail etc.). This will be shown to user in booking process and booking confirmation mail',
				'commonsbooking' ),
			'id'         => COMMONSBOOKING_METABOX_PREFIX . 'location_contact',
			'type'       => 'textarea_small',
			'show_on_cb' => 'cmb2_hide_if_no_cats', // function should return a bool value
		) );

		// Show selection only to admins
		if ( commonsbooking_isCurrentUserAdmin() ) {
			// Location admin selection
			$users       = UserRepository::getCBManagers();
			$userOptions = [];
			foreach ( $users as $user ) {
				$userOptions[ $user->ID ] = $user->get( 'user_nicename' ) . " (" . $user->last_name . " " . $user->last_name . ")";
			}
			$cmb->add_field( array(
				'name'       => esc_html__( 'Location Admin(s)', 'commonsbooking' ),
				'desc'       => esc_html__( 'choose one or more users to give them the permisssion to edit and manage this specific location. Only users with the role CommonsBooking Manager can be selected here.',
					'commonsbooking' ),
				'id'         => COMMONSBOOKING_METABOX_PREFIX . 'location_admins',
				'type'       => 'pw_multiselect',
				'options'    => $userOptions,
				'attributes' => array(
					'placeholder' => esc_html__( 'Select location admins.', 'commonsbooking' )
				),
			) );
		}

		$cmb->add_field( array(
			'name' => esc_html__( 'Allow locked day overbooking', 'commonsbooking' ),
			'desc' => commonsbooking_sanitizeHTML( __( 'If selected, all not selected days in any bookable timeframe that is connected to this location can be overbooked. Read the documentation <a target="_blank" href="https://commonsbooking.org/?p=435">Create Locations</a> for more information.', 'commonsbooking' ) ),
			'id'   => COMMONSBOOKING_METABOX_PREFIX . 'allow_lockdays_in_range',
			'type' => 'checkbox',
		) );

		// Check if custom meta fields are set in CB Options and generate MetaData-Box and fields
		if ( is_array( self::getCMB2FieldsArrayFromCustomMetadata( 'location' ) ) ) {
			$customMetaData = self::getCMB2FieldsArrayFromCustomMetadata( 'location' );

			// Initiate the metabox Adress
			$cmb = new_cmb2_box( array(
				'id'           => COMMONSBOOKING_METABOX_PREFIX . 'location_custom_meta',
				'title'        => esc_html__( 'Location Meta-Data', 'commonsbooking' ),
				'object_types' => array( self::$postType ), // Post type
				'context'      => 'normal',
				'priority'     => 'high',
				'show_names'   => true, // Show field names on the left
			) );

			// Add Custom Meta Fields defined in CommonsBooking Options (Tab MetaData)
			foreach ( $customMetaData as $customMetaDataField ) {
				$cmb->add_field( $customMetaDataField );
			}

		}

	}

}
