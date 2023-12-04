<?php


namespace CommonsBooking\Wordpress\CustomPostType;


use CommonsBooking\Map\MapShortcode;
use CommonsBooking\Repository\Item;
use CommonsBooking\Repository\Location;
use function __;

class Map extends CustomPostType {

	/**
	 * The default coordinates of the map center.
	 * Is used when no other coordinates are set.
	 * These are currently the coordinates of Cologne, Germany.
	 */
	const LATITUDE_DEFAULT = 50.937531;
	const LONGITUDE_DEFAULT = 6.960279;
	/**
	 * @var string
	 */
	public static $postType = 'cb_map';

	/**
	 * Initiates needed hooks.
	 */
	public function initHooks() {
		// Add shortcodes
		add_shortcode( 'cb_map', array( new MapShortcode(), 'execute' ) );

		// Add actions
		//TODO: Add validation
		//add_action( 'save_post_' . self::$postType, array( self::class, 'savePost' ), 11, 2 );
		add_action( 'cmb2_admin_init', array( $this, 'registerMetabox' ) );
	}

	public function registerMetabox() {
		$cmb = new_cmb2_box(
			[
				'id'           => static::getPostType() . "-custom-fields",
				'title'        => esc_html__( 'Map settings', 'commonsbooking' ),
				'object_types' => array( static::getPostType() ),
			]
		);


		foreach ( self::getCustomFields() as $customField ) {
			$cmb->add_field( $customField );
		}
	}

	public static function getView() {
		return new \CommonsBooking\View\Map();
	}

	public static function getCustomFields(): array {
		return array(
			array(
				'name' => esc_html__( 'These settings help you to configure the usage and appearance of Commons Booking Map', 'commonsbooking' ),
				'id'   => 'map_settings_info',
				'type' => 'title',
			),
			//Begin group presentation
			array(
				'name' => esc_html__( 'Presentation', 'commonsbooking' ),
				'type' => 'title',
				'id'   => 'presentation_info'
			),
			array(
				'name' => esc_html__( 'Shortcode', 'commonsbooking' ),
				'desc' => '[cb_map_id=999]', //TODO: Render callback
				'id'   => 'shortcode',
				'type' => 'title',
			),
			array(
				'name' => esc_html__( 'base map', 'commonsbooking' ),
				'desc' => esc_html__( 'the base map defines the rendering style of the map tiles', 'commonsbooking' ),
				'id'   => 'base_map',
				'type' => 'select',
				'options' => array(
					'1' => esc_html__( 'OSM - mapnik', 'commonsbooking' ),
					'2' => esc_html__( 'OSM - german style', 'commonsbooking' ),
					'3' => esc_html__( 'OSM - hike and bike', 'commonsbooking' ),
					'4' => esc_html__( 'OSM - lokaler (min. zoom: 9)', 'commonsbooking' ),
				)
			),
			array(
				'name' => esc_html__( 'show scale', 'commonsbooking' ),
				'desc' => esc_html__( 'show the current scale in the bottom left corner of the map', 'commonsbooking' ),
				'id'   => 'show_scale',
				'type' => 'checkbox',
			),
			array(
				'name' => esc_html__( 'map height', 'commonsbooking' ),
				'desc' => 'px <br>' . esc_html__( 'the height the map is rendered with - the width is the same as of the parent element', 'commonsbooking' ),
				'id'   => 'map_height',
				'type' => 'text_small',
				'default' => '400',
				'attributes' => array(
					'type' => 'number',
					'pattern' => '\d*',
				)
			),
			array(
				'name' => esc_html__( 'no locations message', 'commonsbooking' ),
				'desc' => esc_html__( 'in case a user filters locations and gets no result, a message is shown - here the text can be customized', 'commonsbooking' ),
				'id'   => 'custom_no_locations_message',
				'type' => 'text',
				'default' => esc_html__( 'No locations found', 'commonsbooking' ),
			),
			array(
				'name' => esc_html__( 'enable data export', 'commonsbooking' ),
				'desc' => esc_html__( 'activate to enable a button that allows the export of map data (geojson format)', 'commonsbooking' ),
				'id'   => 'enable_map_data_export',
				'type' => 'checkbox',
			),
			//Begin group Zoom
			array(
				'name' => esc_html__( 'Zoom', 'commonsbooking' ),
				'type' => 'title',
				'id'   => 'zoom_info'
			),
			array(
				'name' => esc_html__( 'min. zoom level' , 'commonsbooking' ),
				'desc' => esc_html__( 'the minimal zoom level a user can choose', 'commonsbooking' ),
				'id'   => 'zoom_min',
				'type' => 'text_small',
				'default' => '9',
				'attributes' => array(
					'type' => 'number',
					'pattern' => '\d*',
					'min' => '1',
					'max' => '19',
				)
			),
			array(
				'name' => esc_html__( 'max. zoom level' , 'commonsbooking' ),
				'desc' => esc_html__( 'the maximal zoom level a user can choose', 'commonsbooking' ),
				'id'   => 'zoom_max',
				'type' => 'text_small',
				'default' => '19',
				'attributes' => array(
					'type' => 'number',
					'pattern' => '\d*',
					'min' => '1',
					'max' => '19',
				)
			),
			array(
				'name' => esc_html__( 'start zoom level' , 'commonsbooking' ),
				'desc' => esc_html__( 'the zoom level that will be set when the map is loaded', 'commonsbooking' ),
				'id'   => 'zoom_start',
				'type' => 'text_small',
				'default' => '9',
				'attributes' => array(
					'type' => 'number',
					'pattern' => '\d*',
					'min' => '1',
					'max' => '19',
				)
			),
			array(
				'name' => esc_html__( 'enable scroll wheel zoom' , 'commonsbooking' ),
				'desc' => esc_html__( 'when activated users can zoom the map using the scroll wheel', 'commonsbooking' ),
				'id'   => 'scrollWheelZoom',
				'type' => 'checkbox',
				'default_cb' => 'commonsbooking_cmb2_set_checkbox_default_for_new_post',
			),
			//End group Zoom
			//Begin group Positioning
			array(
				'name' => esc_html__( 'Positioning', 'commonsbooking' ),
				'type' => 'title',
				'id'   => 'positioning_info'
			),
			array(
				'name' => esc_html__( 'start latitude' , 'commonsbooking' ),
				'desc' => esc_html__( 'the latitude of the map center when the map is loaded', 'commonsbooking' ),
				'id'   => 'lat_start',
				'type' => 'text_small',
				'default' => self::LATITUDE_DEFAULT,
			),
			array(
				'name' => esc_html__( 'start longitude' , 'commonsbooking' ),
				'desc' => esc_html__( 'the longitude of the map center when the map is loaded', 'commonsbooking' ),
				'id'   => 'lon_start',
				'type' => 'text_small',
				'default' => self::LONGITUDE_DEFAULT,
			),
			array(
				'name' => esc_html__( 'initial adjustment to marker bounds', 'commonsbooking' ),
				'desc' => esc_html__( 'adjust map section to bounds of shown markers automatically when map is loaded', 'commonsbooking' ),
				'id'  => 'marker_map_bounds_initial',
				'type' => 'checkbox',
				'default_cb' => 'commonsbooking_cmb2_set_checkbox_default_for_new_post',
			),
			array(
				'name' => esc_html__( 'adjustment to marker bounds on filter', 'commonsbooking' ),
				'desc' => esc_html__( 'adjust map section to bounds of shown markers automatically when filtered by users', 'commonsbooking' ),
				'id'  => 'marker_map_bounds_filter',
				'type' => 'checkbox',
				'default_cb' => 'commonsbooking_cmb2_set_checkbox_default_for_new_post',
			),
			//End group Positioning
			//Begin group Tooltip
			array(
				'name' => esc_html__( 'Marker Tooltip', 'commonsbooking' ),
				'type' => 'title',
				'id'   => 'marker_tooltip_info'
			),
			array(
				'name' => esc_html__( 'Show marker tooltip permanently', 'commonsbooking' ),
				'desc' => esc_html__( 'activate to show the marker tooltips permanently', 'commonsbooking' ),
				'id'   => 'marker_tooltip_permanent',
				'type' => 'checkbox',
			),
			//End group Tooltip
			//Begin group popup
			array(
				'name' => esc_html__( 'Marker Popup', 'commonsbooking' ),
				'type' => 'title',
				'id'   => 'marker_popup_info'
			),
			array(
				'name' => esc_html__( 'show item availability', 'commonsbooking' ),
				'desc' => esc_html__( 'activate to show the item availability in the marker popup', 'commonsbooking' ),
				'id'   => 'show_item_availability',
				'type' => 'checkbox'
			),
			array(
				'name' => esc_html__( 'Max. available days in popup', 'commonsbooking' ),
				'desc' => esc_html__( 'Set how many days are displayed on the popup (starting from today)', 'commonsbooking' ),
				'id'   => 'availability_max_days_to_show',
				'type' => 'text_small',
				'default' => '11',
				'attributes' => array(
					'type' => 'number',
					'pattern' => '\d*',
					'min' => '1',
				)
			),
			array(
				'name' => esc_html__( 'Maximum days to choose in map availabilty filter ', 'commonsbooking' ),
				'desc' => esc_html__( 'Notice: Defines the maximun days a user can choose in the availabilty filter in frontend map', 'commonsbooking' ),
				'id'   => 'availability_max_day_count',
				'default' => '14',
				'type' => 'text_small',
				'attributes' => array(
					'type' => 'number',
					'pattern' => '\d*',
				)
			),
			//End group popup
			//Begin group custom marker
			array(
				'name' => esc_html__( 'Custom Marker', 'commonsbooking' ),
				'type' => 'title',
				'id'   => 'custom_marker_info'
			),
			array(
				'name' => esc_html__( 'image file', 'commonsbooking' ),
				'desc' => esc_html__( 'the default marker icon can be replaced by a custom image', 'commonsbooking' ),
				'id'   => 'custom_marker_media',
				'type' => 'file',
				'options' => array(
					'url' => false,
				),
				'query_args' => array(
					'type' => array(
						'image/png',
					),
				),
			),
			array(
				'name' => esc_html__( 'icon width', 'commonsbooking' ),
				'desc' => 'px ' . esc_html__( 'the size of the custom marker icon image as it is shown on the map', 'commonsbooking' ),
				'id'   => 'marker_icon_width',
				'type' => 'text_small',
				'attributes' => array(
					'type' => 'number',
					'pattern' => '\d*',
					'min' => '1',
				),
			),
			array(
				'name' => esc_html__( 'icon height', 'commonsbooking' ),
				'desc' => 'px ' . esc_html__( 'the size of the custom marker icon image as it is shown on the map', 'commonsbooking' ),
				'id'   => 'marker_icon_height',
				'type' => 'text_small',
				'attributes' => array(
					'type' => 'number',
					'pattern' => '\d*',
					'min' => '1',
				),
			),
			array(
				'name' => esc_html__( 'anchor point', 'commonsbooking' ) . ' x',
				'desc' => 'px ' . esc_html__( 'the position of the anchor point of the icon image, seen from the left top corner of the icon, often it is half of the width and full height of the icon size - this point is used to place the marker on the geo coordinates', 'commonsbooking' ),
				'id'   => 'marker_icon_anchor_x',
				'type' => 'text_small',
				'attributes' => array(
					'type' => 'number',
					'pattern' => '\d*',
				),
			),
			array(
				'name' => esc_html__( 'anchor point', 'commonsbooking' ) . ' y',
				'desc' => 'px ' . esc_html__( 'the position of the anchor point of the icon image, seen from the left top corner of the icon, often it is half of the width and full height of the icon size - this point is used to place the marker on the geo coordinates', 'commonsbooking' ),
				'id'   => 'marker_icon_anchor_y',
				'type' => 'text_small',
				'attributes' => array(
					'type' => 'number',
					'pattern' => '\d*',
				),
			),
			//End group custom marker
			//Begin group cluster
			array(
				'name' => esc_html__( 'Cluster', 'commonsbooking' ),
				'type' => 'title',
				'id'   => 'cluster_info'
			),
			array(
				'name' => esc_html__( 'max. cluster radius', 'commonsbooking' ),
				'desc' => 'px ' . esc_html__( 'combine markers to a cluster within given radius - 0 for deactivation', 'commonsbooking' ),
				'id'   => 'max_cluster_radius',
				'type' => 'text_small',
				'default' => '80',
				'attributes' => array(
					'type' => 'number',
					'pattern' => '\d*',
					'min' => 0,
					'max' => 1000
				),
			),
			array (
				'name' => esc_html__( 'Custom Cluster Marker', 'commonsbooking' ),
				'desc' => esc_html__( 'the default marker icon can be replaced by a custom image', 'commonsbooking' ),
				'id'   => 'custom_marker_cluster_media',
				'type' => 'file',
				'options' => array(
					'url' => false,
				),
				'query_args' => array(
					'type' => array(
						'image/png',
					),
				),
			),
			array(
				'name' => esc_html__( 'icon width', 'commonsbooking' ),
				'desc' => 'px ' . esc_html__( 'the size of the custom marker icon image as it is shown on the map', 'commonsbooking' ),
				'id'   => 'marker_cluster_icon_width',
				'type' => 'text_small',
				'attributes' => array(
					'type' => 'number',
					'pattern' => '\d*',
					'min' => '1',
				),
			),
			array(
				'name' => esc_html__( 'icon height', 'commonsbooking' ),
				'desc' => 'px ' . esc_html__( 'the size of the custom marker icon image as it is shown on the map', 'commonsbooking' ),
				'id'   => 'marker_cluster_icon_height',
				'type' => 'text_small',
				'attributes' => array(
					'type' => 'number',
					'pattern' => '\d*',
					'min' => '1',
				),
			),
			//End group cluster
			//Begin group Appearance
			array(
				'name' => esc_html__( 'Appearance by Item Status', 'commonsbooking' ),
				'type' => 'title',
				'id'   => 'item_status_appearance_info'
			),
			array(
				'name' => esc_html__( 'appearance', 'commonsbooking' ),
				'desc' => esc_html__( 'how locations with items that are in draft status should be handled', 'commonsbooking' ),
				'id'   => 'item_draft_appearance',
				'type' => 'radio',
				'options' => array(
					'1' => esc_html__( "don't show drafts", 'commonsbooking' ),
					'2' => esc_html__( 'show only drafts', 'commonsbooking' ),
					'3' => esc_html__( 'show all together', 'commonsbooking' ),
				),
				'default' => '1',
			),
			array(
				'name' => esc_html__( 'Custom Item Draft Marker', 'commonsbooking' ),
				'desc' => esc_html__( 'the default marker icon can be replaced by a custom image', 'commonsbooking' ),
				'id'   => 'marker_item_draft_media',
				'type' => 'file',
				'options' => array(
					'url' => false,
				),
				'query_args' => array(
					'type' => array(
						'image/png',
					),
				),
			),
			array(
				'name' => esc_html__( 'icon width', 'commonsbooking' ),
				'desc' => 'px ' . esc_html__( 'the size of the custom marker icon image as it is shown on the map', 'commonsbooking' ),
				'id'   => 'marker_item_draft_icon_width',
				'type' => 'text_small',
				'attributes' => array(
					'type' => 'number',
					'pattern' => '\d*',
					'min' => '1',
				),
			),
			array(
				'name' => esc_html__( 'icon height', 'commonsbooking' ),
				'desc' => 'px ' . esc_html__( 'the size of the custom marker icon image as it is shown on the map', 'commonsbooking' ),
				'id'   => 'marker_item_draft_icon_height',
				'type' => 'text_small',
				'attributes' => array(
					'type' => 'number',
					'pattern' => '\d*',
					'min' => '1',
				),
			),
			array(
				'name' => esc_html__( 'anchor point', 'commonsbooking' ) . ' x',
				'desc' => 'px ' . esc_html__( 'the position of the anchor point of the icon image, seen from the left top corner of the icon, often it is half of the width and full height of the icon size - this point is used to place the marker on the geo coordinates', 'commonsbooking' ),
				'id'   => 'marker_item_draft_icon_anchor_x',
				'type' => 'text_small',
				'attributes' => array(
					'type' => 'number',
					'pattern' => '\d*',
				),
			),
			array(
				'name' => esc_html__( 'anchor point', 'commonsbooking' ) . ' y',
				'desc' => 'px ' . esc_html__( 'the position of the anchor point of the icon image, seen from the left top corner of the icon, often it is half of the width and full height of the icon size - this point is used to place the marker on the geo coordinates', 'commonsbooking' ),
				'id'   => 'marker_item_draft_icon_anchor_y',
				'type' => 'text_small',
				'attributes' => array(
					'type' => 'number',
					'pattern' => '\d*',
				),
			),
			//End group Appearance
			//Begin group Filters
			array(
				'name' => esc_html__( 'Filter for Users', 'commonsbooking' ),
				'type' => 'title',
				'id'   => 'filter_info'
			),
			array(
				'name' => esc_html__( 'show location distance filter', 'commonsbooking' ),
				'desc' => esc_html__( 'activate to show the location distance filter', 'commonsbooking' ),
				'id'   => 'show_location_distance_filter',
				'type' => 'checkbox',
			),
			array(
				'name' => esc_html__( 'label for location distance filter', 'commonsbooking' ),
				'desc' => esc_html__( 'alternative label for the location distance filter', 'commonsbooking' ),
				'id'   => 'label_location_distance_filter',
				'type' => 'text',
				'attributes' => array(
					'placeholder' => esc_html__( 'distance', 'commonsbooking' ),
				)
			),
			array(
				'name' => esc_html__( 'address search bounds - left bottom', 'commonsbooking' ) . ' ' . esc_html__( 'longitude', 'commonsbooking' ),
				'desc' => esc_html__( 'defines the bounds of the address search - set the longitude of the left bottom corner of the bounding box', 'commonsbooking' ),
				'id'   => 'address_search_bounds_left_bottom_lon',
				'type' => 'text_small',
			),
			array(
				'name' => esc_html__( 'address search bounds - left bottom', 'commonsbooking' ) . ' ' . esc_html__( 'latitude', 'commonsbooking' ),
				'desc' => esc_html__( 'defines the bounds of the address search - set the bottom left corner of the bounding box', 'commonsbooking' ),
				'id'   => 'address_search_bounds_left_bottom_lat',
				'type' => 'text_small',
			),
			array(
				'name' => esc_html__( 'address search bounds - right top', 'commonsbooking' ) . ' ' . esc_html__( 'longitude', 'commonsbooking' ),
				'desc' => esc_html__( 'defines the bounds of the address search - set the longitude of the right top corner of the bounding box', 'commonsbooking' ),
				'id'   => 'address_search_bounds_right_top_lon',
				'type' => 'text_small',
			),
			array(
				'name' => esc_html__( 'address search bounds - right top', 'commonsbooking' ) . ' ' . esc_html__( 'latitude', 'commonsbooking' ),
				'desc' => esc_html__( 'defines the bounds of the address search - set the latitude of the right top corner of the bounding box', 'commonsbooking' ),
				'id'   => 'address_search_bounds_right_top_lat',
				'type' => 'text_small',
			),
			array(
				'name' => esc_html__( 'show item availability filter', 'commonsbooking' ),
				'desc' => esc_html__( 'activate to show the item availability filter', 'commonsbooking' ),
				'id'   => 'show_item_availability_filter',
				'type' => 'checkbox',
			),
			array(
				'name' => esc_html__( 'label for item availability filter', 'commonsbooking' ),
				'desc' => esc_html__( 'alternative label for the item availability filter', 'commonsbooking' ),
				'id'   => 'label_item_availability_filter',
				'type' => 'text',
				'attributes' => array(
					'placeholder' => esc_html__( 'availability', 'commonsbooking' ),
				)
			),
			array(
				'name' => esc_html__( 'label for item category filter', 'commonsbooking' ),
				'desc' => esc_html__( 'alternative label for the item category filter', 'commonsbooking' ),
				'id'   => 'label_item_category_filter',
				'type' => 'text',
				'attributes' => array(
					'placeholder' => esc_html__( 'categories', 'commonsbooking' ),
				)
			),
			array(
				'name' => esc_html__( 'custom text for filter button', 'commonsbooking' ),
				'desc' => esc_html__( 'the text for the button used for filtering', 'commonsbooking' ),
				'id'   => 'custom_filterbutton_label',
				'type' => 'text',
				'attributes' => array(
					'placeholder' => esc_html__( 'filter', 'commonsbooking' ),
				)
			),
			//TODO: Add available categories & filtergroups
			//End group Filters
			//Begin group Presets
			array(
				'name' => esc_html__( 'Filter Item Presets', 'commonsbooking' ),
				'desc' => esc_html__( 'select the categories that are used to prefilter the items that are shown on the map - none for all items', 'commonsbooking' ),
				'id'   => 'cb_items_preset_categories',
				'type' => 'multicheck',
				'options' => CustomPostType::sanitizeOptions( Item::getTerms() ),
				'select_all_button' => false,
			),
			array(
				'name' => esc_html__( 'Filter Location Presets', 'commonsbooking' ),
				'desc' => esc_html__( 'select the categories that are used to prefilter the locations that are shown on the map - none for all locations', 'commonsbooking' ),
				'id'   => 'cb_locations_preset_categories',
				'type' => 'multicheck',
				'options' => CustomPostType::sanitizeOptions( Location::getTerms() ),
				'select_all_button' => false,
			),
			//End group Presets
		);
	}

	/**
	 * enforce the replacement of the original (google maps) link target on cb_item booking pages
	 * THIS FUNCTION IS NOT IN USE AND PROBABLY @deprecated
	 **/
	public static function replace_map_link_target() {
		global $post;
		$cb_item = 'cb_items';
		if ( is_object( $post ) && $post->post_type == $cb_item ) {
			//get timeframes of item
			$cb_data    = new CB_Data();
			$date_start = date( 'Y-m-d' ); // current date
			$timeframes = $cb_data->get_timeframes( $post->ID, $date_start );

			$geo_coordinates = [];
			if ( $timeframes ) {
				foreach ( $timeframes as $timeframe ) {
					$geo_coordinates[ $timeframe['id'] ] = [
						'lat' => get_post_meta( $timeframe['location_id'], 'cb-map_latitude', true ),
						'lon' => get_post_meta( $timeframe['location_id'], 'cb-map_longitude', true ),
					];
				}
			}

			wp_register_script( 'cb_map_replace_map_link_js', COMMONSBOOKING_MAP_ASSETS_URL . 'js/cb-map-replace-link.js' );

			wp_add_inline_script( 'cb_map_replace_map_link_js',
				"cb_map_timeframes_geo = " . wp_json_encode( $geo_coordinates ) . ";" );

			wp_enqueue_script( 'cb_map_replace_map_link_js' );
		}
	}

	public function getArgs() {
		$labels = array(
			'name'               => self::__( 'Maps', 'commonsbooking' ),
			'singular_name'      => self::__( 'Map', 'commonsbooking' ),
			'add_new'            => self::__( 'create CB map', 'commonsbooking' ),
			'add_new_item'       => self::__( 'create Commons Booking map', 'commonsbooking' ),
			'edit_item'          => self::__( 'edit Commons Booking map', 'commonsbooking' ),
			'new_item'           => self::__( 'create CB map', 'commonsbooking' ),
			'view_item'          => self::__( 'view CB map', 'commonsbooking' ),
			'search_items'       => self::__( 'search CB maps', 'commonsbooking' ),
			'not_found'          => self::__( 'no Commons Booking map found', 'commonsbooking' ),
			'not_found_in_trash' => self::__( 'no Commons Booking map found in the trash', 'commonsbooking' ),
			'parent_item_colon'  => self::__( 'parent CB maps', 'commonsbooking' ),
		);

		$supports = array(
			'title',
			'author',
		);

		return array(
			'labels'              => $labels,

			// Sichtbarkeit des Post Types
			'public'              => true,

			// Standard Ansicht im Backend aktivieren (Wie Artikel / Seiten)
			'show_ui'             => true,

			// Soll es im Backend Menu sichtbar sein?
			'show_in_menu'        => false,

			// Position im Menu
			'menu_position'       => 5,

			// Post Type in der oberen Admin-Bar anzeigen?
			'show_in_admin_bar'   => true,

			// in den Navigations Menüs sichtbar machen?
			'show_in_nav_menus'   => true,
			'hierarchical'        => false,
			'description'         => self::__( 'Maps to show Commons Booking Locations and their Items', 'commonsbooking' ),
			'supports'            => $supports,
			'menu_icon'           => 'dashicons-location',
			'publicly_queryable'  => false,
			'exclude_from_search' => false,
			'has_archive'         => false,
			'query_var'           => false,
			'can_export'          => false,
			'delete_with_user'    => false,
			'capability_type'     => array( self::$postType, self::$postType . 's' ),
		);
	}

	/**
	 * @param $text
	 * @param string $domain
	 * @param null $default
	 *
	 * @return mixed
	 */
	public static function __( $text, string $domain = 'default', $default = null ) {

		$translation = __( $text, $domain );

		if ( $translation == $text && isset( $default ) ) {
			$translation = $default;
		}

		return $translation;
	}
}
