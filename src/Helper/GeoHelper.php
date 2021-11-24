<?php


namespace CommonsBooking\Helper;


use Geocoder\Exception\Exception;
use Geocoder\Location;
use Geocoder\Provider\Nominatim\Nominatim;
use Geocoder\Query\GeocodeQuery;
use Geocoder\StatefulGeocoder;
use Http\Client\Curl\Client;

class GeoHelper {

	/**
	 * @param $addressString
	 *
	 * @return ?Location
	 * @throws Exception
	 */
	public static function getAddressData( $addressString ): ?Location {
		$defaultUserAgent = "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:47.0) Gecko/20100101 Firefox/47.0";

		$provider = Nominatim::withOpenStreetMapServer(
			new Client(),
			array_key_exists('HTTP_USER_AGENT', $_SERVER) ? $_SERVER['HTTP_USER_AGENT'] : $defaultUserAgent
		);
		$geoCoder = new StatefulGeocoder( $provider, 'en' );

		$addresses = $geoCoder->geocodeQuery( GeocodeQuery::create( $addressString ) );
		if ( ! $addresses->isEmpty() ) {
			return $addresses->first();
		}

		return null;
	}

}
