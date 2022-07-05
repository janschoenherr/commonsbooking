<?php

namespace CommonsBooking\Service;

use CommonsBooking\Map\MapShortcode;
use CommonsBooking\View\Calendar;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\CacheItem;
use const WP_DEBUG;

trait Cache {

	/**
	 * Returns cache item based on calling class, function and args.
	 *
	 * @param null $custom_id
	 *
	 * @return mixed
	 * @throws InvalidArgumentException
	 */
	public static function getCacheItem( $custom_id = null ) {
		if ( WP_DEBUG ) {
			return false;
		}

        
		try {
			/** @var CacheItem $cacheItem */
			$cacheKey  = self::getCacheId( $custom_id );
			$cacheItem = self::getCache()->getItem( $cacheKey );
			if ( $cacheItem->isHit() ) {
				return $cacheItem->get();
			}
		} catch (\Exception $exception) {}

		return false;
	}

	/**
	 * Returns cache id, based on calling class, function and args.
     * 
     * @since 2.7.2 added Plugin_Dir to Namespace to avoid conflicts on multiple instances on same server
	 *
	 * @param null $custom_id
	 *
	 * @return string
	 */
	public static function getCacheId( $custom_id = null ): string {
		$backtrace     = debug_backtrace()[2];
		$backtrace     = self::sanitizeArgsArray( $backtrace );
        $namespace     = COMMONSBOOKING_PLUGIN_DIR;
		$namespace     .= '_' . str_replace( '\\', '_', strtolower( $backtrace['class'] ) );
		$namespace     .= '_' . $backtrace['function'];
		$backtraceArgs = $backtrace['args'];
		$namespace     .= '_' . serialize( $backtraceArgs );
		if ( $custom_id ) {
			$namespace .= $custom_id;
		}

		return md5( $namespace );
	}

	/**
	 * @param $backtrace
	 *
	 * @return mixed
	 */
	private static function sanitizeArgsArray( $backtrace ) {
		if ( array_key_exists( 'args', $backtrace ) &&
		     count( $backtrace['args'] ) &&
		     is_array( $backtrace['args'][0] )
		) {
			if ( array_key_exists( 'taxonomy', $backtrace['args'][0] ) ) {
				unset( $backtrace['args'][0]['taxonomy'] );
			}
			if ( array_key_exists( 'term', $backtrace['args'][0] ) ) {
				unset( $backtrace['args'][0]['term'] );
			}
			if ( array_key_exists( 'category_slug', $backtrace['args'][0] ) ) {
				unset( $backtrace['args'][0]['category_slug'] );
			}
		}

		return $backtrace;
	}

	/**
	 * @param string $namespace
	 * @param int $defaultLifetime
	 * @param string|null $directory
	 *
	 * @return TagAwareAdapter
	 */
	public static function getCache( string $namespace = '', int $defaultLifetime = 0, string $directory = null ): TagAwareAdapter {
		return new TagAwareAdapter(
			new FilesystemAdapter( $namespace, $defaultLifetime, $directory )
		);
	}

	/**
	 * Saves cache item based on calling class, function and args.
	 *
	 * @param $value
	 * @param array $tags
	 * @param null $custom_id
	 * @param string|null $expirationString set expiration as timestamp or string 'midnight' to set expiration to 00:00 next day
	 *
	 * @return bool
	 * @throws InvalidArgumentException
	 * @throws \Psr\Cache\CacheException
	 */
	public static function setCacheItem( $value, array $tags, $custom_id = null, ?string $expirationString = null ): bool {
		// Set a default expiration to make sure, that we get rid of stale items, if there are some
		// too much space
		$expiration = 604800;

		$tags = array_map('strval', $tags);
		$tags = array_filter($tags);

		if(!count($tags)) {
			$tags = ['misc'];
		}

		// if expiration is set to 'midnight' we calculate the duration in seconds until midnight
		if ( $expirationString == 'midnight' ) {
			$datetime   = current_time( 'timestamp' );
			$expiration = strtotime( 'tomorrow', $datetime ) - $datetime;
		}

		$cache = self::getCache( '', intval( $expiration ) );
		/** @var CacheItem $cacheItem */
		$cacheKey  = self::getCacheId( $custom_id );
		$cacheItem = $cache->getItem( $cacheKey );
		$cacheItem->tag($tags);
		$cacheItem->set( $value );
		$cacheItem->expiresAfter(intval( $expiration ));

		return $cache->save( $cacheItem );
	}

	/**
	 * Deletes cache entries.
	 *
	 * @param array $tags
	 *
	 * @throws InvalidArgumentException
	 */
	public static function clearCache( array $tags = [] ) {
		if(!count($tags)) {
			self::getCache()->clear();
		} else {
			self::getCache()->invalidateTags($tags);
		}

		// Delete expired cache items
		self::getCache()->prune();

		set_transient("clearCacheHasBeenDone", true, 45);
	}

	/**
	 * Add js to frontend on cache clear.
	 * @return void
	 */
	public static function addWarmupAjaxToOutput() {
		if(get_transient("clearCacheHasBeenDone")) {
			delete_transient("clearCacheHasBeenDone");
			wp_register_script( 'cache_warmup', '', array("jquery"), '', true );
			wp_enqueue_script( 'cache_warmup'  );
			wp_add_inline_script(
				'cache_warmup',
				'
				jQuery.ajax({
		            url: cb_ajax_cache_warmup.ajax_url,
		            method: "POST",
		            data: {
		                _ajax_nonce: cb_ajax_cache_warmup.nonce,
		                action: "cache_warmup"
		            }
				});'
			);
		}
	}

	public static function warmupCache() {
		try {
			global $wpdb;
			$table_posts = $wpdb->prefix . 'posts';

			// First get all pages with cb shortcodes
			$sql = "SELECT post_content FROM $table_posts WHERE 
		      post_content LIKE '%cb_items%' OR
			  post_content LIKE '%cb_location%' OR
		      post_content LIKE '%cb_map%'";
			$pages = $wpdb->get_results( $sql );

			// Now extract shortcode calles incl. attributes
			$shortCodeCalls = [];
			foreach($pages as $page) {
				// Get cb_ shortcodes
				preg_match_all('/\[.*(cb\_.*)\]/i', $page->post_content, $cbShortCodes);

				// If there was found something between the brackets we continue
				if(count($cbShortCodes) > 1) {
					$cbShortCodes = $cbShortCodes[1];

					// each result will be prepared and added as shortcode call
					foreach ($cbShortCodes as $shortCode) {
						list($shortCode, $args) = self::getShortcodeAndAttributes($shortCode);
						$shortCodeCalls[][$shortCode] = $args;
					}
				}
			}

			// Filter duplicate calls
			$shortCodeCalls = array_intersect_key(
				$shortCodeCalls,
				array_unique(array_map('serialize', $shortCodeCalls))
			);

			self::runShortcodeCalls($shortCodeCalls);

			wp_send_json("cache successfully warmed up");
		} catch (\Exception $exception) {
			wp_send_json("something went wrong with cache warm up");
		}
	}

	/**
	 * Iterates throudh array and executes shortcodecalls.
	 * @param $shortCodeCalls
	 *
	 * @return void
	 */
	private static function runShortcodeCalls($shortCodeCalls) {
		foreach($shortCodeCalls as $shortcode) {
			$shortcodeFunction = array_keys($shortcode)[0];
			$attributes = $shortcode[$shortcodeFunction];

			if(array_key_exists($shortcodeFunction, self::$cbShortCodeFunctions)) {
				list($class, $function) = self::$cbShortCodeFunctions[$shortcodeFunction];
				$class::$function($attributes);
			}
		}
	}

	/**
	 * Extracts shortcode and attributes from shortcode string.
	 * @param $shortCode
	 *
	 * @return array
	 */
	private static function getShortcodeAndAttributes($shortCode) {
		$shortCodeParts = explode(' ', $shortCode);
		$shortCodeParts = array_map(
			function($part) {
				$trimmed = trim($part);
				$trimmed = str_replace("\xc2\xa0", '', $trimmed);
				return $trimmed;
			}, $shortCodeParts);

		$shortCode = array_shift($shortCodeParts);

		$args = [];
		foreach ($shortCodeParts as $part) {
			$parts = explode('=', $part);
			$key = $parts[0];
			$value = "";
			if(count($parts) > 1) {
				$value = $parts[1];
				if(preg_match('/^".*"$/', $value)) {
					$value = substr($value,1,-1);
				}
			}

			$args[$key] = $value;
		}

		return [$shortCode, $args];
	}

	private static $cbShortCodeFunctions = [
		"cb_items" => array( \CommonsBooking\View\Item::class, 'shortcode' ),
		'cb_bookings' => array( \CommonsBooking\View\Booking::class, 'shortcode' ),
		"cb_locations" => array( \CommonsBooking\View\Location::class, 'shortcode' ),
		"cb_map" => array( MapShortcode::class, 'execute' ),
		'cb_items_table' => array( Calendar::class, 'renderTable' )
	];

}