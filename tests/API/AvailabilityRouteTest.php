<?php

namespace CommonsBooking\Tests\API;

use SlopeIt\ClockMock\ClockMock;

class AvailabilityRouteTest extends RouteTest {

	protected $ENDPOINT = '/commonsbooking/v1/availability';

	public function testsAvailabilitySuccess() {

		ClockMock::freeze( new \DateTime( self::CURRENT_DATE ) );

		$request = new \WP_REST_Request( 'GET', $this->ENDPOINT );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 2, count( $response->get_data()->availability ) );

		// Checks availability for the first day
		$this->assertEquals( $this->locationId, $response->get_data()->availability[0]->locationId );
		$this->assertEquals( $this->itemId, $response->get_data()->availability[0]->itemId );
		$this->assertEquals( self::CURRENT_DATE . 'T00:00:00+00:00', $response->get_data()->availability[0]->start );
		$this->assertEquals( self::CURRENT_DATE . 'T23:59:59+00:00', $response->get_data()->availability[0]->end );

		ClockMock::reset();
	}
}
