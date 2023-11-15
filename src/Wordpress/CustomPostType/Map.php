<?php


namespace CommonsBooking\Wordpress\CustomPostType;


use CommonsBooking\Helper\Helper;
use CommonsBooking\Map\MapAdmin;
use CommonsBooking\Map\MapSettings;
use CommonsBooking\Map\MapShortcode;
use CommonsBooking\Repository\Item;
use CommonsBooking\Repository\Timeframe;
use Exception;
use function __;

class Map extends CustomPostType {

	/**
	 * @var string
	 */
	public static $postType = 'cb_map';

	/**
	 * Initiates needed hooks.
	 */
	public function initHooks() {
		$cb_map_settings = new MapSettings();

		// deactivated individual map settings because we don't need them righ now
		// map setting should be integrated in CB settings in the future
		//$cb_map_settings->prepare_settings();
		
		if ( $cb_map_settings->get_option( 'booking_page_link_replacement' ) ) {
			add_action( 'wp_enqueue_scripts', array( Map::class, 'replace_map_link_target' ), 11 );
		}

		// Add shortcodes
		add_shortcode( 'cb_map', array( MapShortcode::class, 'execute' ) );

		// Add actions
		add_action( 'save_post_' . self::$postType, array( MapAdmin::class, 'validate_options' ), 10, 3 );
		add_action( 'add_meta_boxes_cb_map', array( MapAdmin::class, 'add_meta_boxes' ) );
	}

	public static function getView() {
		return new \CommonsBooking\View\Map();
	}

	/**
	 * enforce the replacement of the original (google maps) link target on cb_item booking pages
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

			// Standart Ansicht im Backend aktivieren (Wie Artikel / Seiten)
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
