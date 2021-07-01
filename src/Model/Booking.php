<?php


namespace CommonsBooking\Model;


use CommonsBooking\CB\CB;
use CommonsBooking\Repository\BookingCodes;
use CommonsBooking\Repository\Timeframe;
use DateTime;
use Exception;

class Booking extends CustomPost {

	const START_TIMEFRAME_GRIDSIZE = "start-timeframe-gridsize";

	const END_TIMEFRAME_GRIDSIZE = "end-timeframe-gridsize";

	/**
	 * Booking states.
	 * @var string[]
	 */
	public static $bookingStates = [
		"canceled",
		"confirmed",
		"unconfirmed"
	];

	/**
	 * @return Location
	 * @throws Exception
	 */
	public function getLocation(): Location {
		$locationId = $this->getMeta( 'location-id' );
		if ( $post = get_post( $locationId ) ) {
			return new Location( $post );
		}

		return $post;
	}

	/**
	 * @return Item
	 * @throws Exception
	 */
	public function getItem(): Item {
		$itemId = $this->getMeta( 'item-id' );

		if ( $post = get_post( $itemId ) ) {
			return new Item( $post );
		}

		return $post;
	}

	/**
	 * Returns the booking code.
	 * @return mixed
	 */
	public function getBookingCode() {
		return $this->getMeta( COMMONSBOOKING_METABOX_PREFIX . 'bookingcode' );
	}


	/**
	 * Returns rendered booking code for using in email-template (booking confirmation mail)
	 * @return string
	 * @throws Exception
	 */
	public function formattedBookingCode(): string {
		$htmloutput = "";
		if (
			$this->getMeta( COMMONSBOOKING_METABOX_PREFIX . 'bookingcode' ) &&
			$this->post_status == "confirmed" && (
				$this->showBookingCodes() ||
				( $this->getBookableTimeFrame() && $this->getBookableTimeFrame()->showBookingCodes() )
			)
		) {
			// translators: %s = Booking code
			$htmloutput = '<br>' . sprintf( commonsbooking_sanitizeHTML( __( 'Your booking code is: %s', 'commonsbooking' ) ), $this->getMeta( COMMONSBOOKING_METABOX_PREFIX . 'bookingcode' ) ) . '<br>';
		}

		return $htmloutput;
	}

	/**
	 * Returns true if booking codes shall be shown in frontend.
	 * @return bool
	 */
	public function showBookingCodes(): bool {
		return $this->getMeta( "show-booking-codes" ) == "on";
	}

	/**
	 * Assings relevant meta fields from related bookable timeframe to booking.
	 * @throws Exception
	 */
	public function assignBookableTimeframeFields() {
		$timeframe        = $this->getBookableTimeFrame();
		$neededMetaFields = [
			"full-day",
			"grid",
			"start-time",
			"end-time",
			"show-booking-codes"
		];
		foreach ( $neededMetaFields as $fieldName ) {
			$fieldValue = get_post_meta(
				$timeframe->ID,
				$fieldName,
				true
			);
			if ( in_array( $fieldName, [ 'start-time', 'end-time' ] ) ) {
				$fieldValue = $this->sanitizeTimeField( $fieldName );
			}
			update_post_meta(
				$this->post->ID,
				$fieldName,
				$fieldValue
			);
		}

		// If there exists a booking code, add it.
		$bookingCode = BookingCodes::getCode(
			$timeframe->ID,
			$this->getItem()->ID,
			$this->getLocation()->ID,
			date( 'Y-m-d', $this->getMeta( 'repetition-start' ) )
		);

		// only add booking code if the booking is based on a full day timeframe
		if ( $bookingCode && $this->getMeta( 'full-day' ) == "on" ) {
			update_post_meta(
				$this->post->ID,
				COMMONSBOOKING_METABOX_PREFIX . 'bookingcode',
				$bookingCode->getCode()
			);
		}
	}

	/**
	 * Returns time from repetition-[start/end] field
	 *
	 * @param $fieldName
	 *
	 * @return string
	 */
	private function sanitizeTimeField( $fieldName ): string {
		$time       = new DateTime();
		$fieldValue = $this->getMeta( 'repetition-start' );
		if ( $fieldName == "end-time" ) {
			$fieldValue = $this->getMeta( \CommonsBooking\Model\Timeframe::REPETITION_END );
		}
		$time->setTimestamp( $fieldValue );

		return $time->format( 'H:i' );
	}

	/**
	 * Returns suitable bookable Timeframe for booking.
	 * @return null|\CommonsBooking\Model\Timeframe
	 * @throws Exception
	 */
	public function getBookableTimeFrame(): ?\CommonsBooking\Model\Timeframe {
		$locationId = $this->getMeta( 'location-id' );
		$itemId     = $this->getMeta( 'item-id' );

		$response = Timeframe::get(
			[ $locationId ],
			[ $itemId ],
			[ \CommonsBooking\Wordpress\CustomPostType\Timeframe::BOOKABLE_ID ],
			date( CB::getInternalDateFormat(), $this->getMeta( 'repetition-start' ) ),
			true
		);

		if ( count( $response ) ) {
			return array_shift( $response );
		}

		return null;
	}

	/**
	 * @return string
	 */
	public function formattedBookingDate(): string {
		$date_format = get_option( 'date_format' );

		$startdate = date_i18n( $date_format, $this->getMeta( 'repetition-start' ) );
		$enddate   = date_i18n( $date_format, $this->getMeta( \CommonsBooking\Model\Timeframe::REPETITION_END ) );

		if ( $startdate == $enddate ) {
			/* translators: %s = date in wordpress defined format */
			return sprintf( sanitize_text_field( __( ' on %s ', 'commonsbooking' ) ), $startdate );
		} else {
			/* translators: %1 = startdate, %2 = enddate in wordpress defined format */
			return sprintf( sanitize_text_field( __( ' from %1$s until %2$s ', 'commonsbooking' ) ), $startdate, $enddate );
		}
	}


	/**
	 * pickupDatetime
	 *
	 * renders the pickup date and time information and returns a formatted string
	 * this is used in templates/booking-single.php and in email-templates (configuration via admin options)
	 *
	 * @return string
	 */
	public function pickupDatetime(): string {

		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		$date_start = date_i18n( $date_format, $this->getMeta( 'repetition-start' ) );
		$time_start = date_i18n( $time_format, $this->getMeta( 'repetition-start' ) );

		$grid     = $this->getMeta( 'grid' );
		$full_day = $this->getMeta( 'full-day' );

		if ( $full_day == "on" ) {
			return $date_start;
		}

		if ( $grid == 0 ) { // if grid is set to slot duration
			$time_end = date_i18n( $time_format, strtotime( $this->getMeta( 'repetition-start' ) ) );

			// If we have the grid size, we use it to calculate right time end
			$timeframeGridSize = $this->getMeta( Booking::START_TIMEFRAME_GRIDSIZE );
			if ( $timeframeGridSize ) {
				$grid = $timeframeGridSize;
			}
		}

		if ( $grid > 0 ) { // if grid is set to hourly (grid = 1) or a multiple of an hour
			$time_end = date_i18n( $time_format, $this->getMeta( 'repetition-start' ) + ( 60 * 60 * $grid ) );
		}

		return $date_start . ' ' . $time_start . ' - ' . $time_end;
	}

	/**
	 * pickupDatetime
	 *
	 * renders the return date and time information and returns a formatted string
	 * this is used in templates/booking-single.php and in email-templates (configuration via admin options)
	 *
	 * @return string
	 */

	public function returnDatetime(): string {
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		$date_end = date_i18n( $date_format, $this->getMeta( \CommonsBooking\Model\Timeframe::REPETITION_END ) );
		$time_end = date_i18n( $time_format, $this->getMeta( \CommonsBooking\Model\Timeframe::REPETITION_END ) + 60 ); // we add 60 seconds because internal timestamp is set to hh:59

		$grid     = $this->getMeta( 'grid' );
		$full_day = $this->getMeta( 'full-day' );

		if ( $full_day == "on" ) {
			return $date_end;
		}

		if ( $grid == 0 ) { // if grid is set to slot duration
			$time_start = date_i18n( $time_format, strtotime( $this->getMeta( 'start-time' ) ) );

			// If we have the grid size, we use it to calculate right time start
			$timeframeGridSize = $this->getMeta( Booking::END_TIMEFRAME_GRIDSIZE );
			if ( $timeframeGridSize ) {
				$grid = $timeframeGridSize;
			}
		}

		if ( $grid > 0 ) { // if grid is set to hourly (grid = 1) or a multiple of an hour
			$time_start = date_i18n( $time_format, $this->getMeta( \CommonsBooking\Model\Timeframe::REPETITION_END ) + 1 - ( 60 * 60 * $grid ) );
		}

		return $date_end . ' ' . $time_start . ' - ' . $time_end;
	}

	public function getStartDate() {
		return $this->getMeta( 'repetition-start' );
	}

	public function getEndDate() {
		return $this->getMeta( \CommonsBooking\Model\Timeframe::REPETITION_END );
	}

	/**
	 * Returns comment text.
	 * @return string
	 */
	public function returnComment(): string {
		return commonsbooking_sanitizeHTML( $this->getMeta( 'comment' ) );
	}


	/**
	 * show booking notice
	 *
	 * @return string
	 */
	public function bookingNotice(): ?string {

		$currentStatus = $this->post->post_status;

		if ( $currentStatus == "unconfirmed" ) {
			$noticeText = commonsbooking_sanitizeHTML( __( 'Please check your booking and click confirm booking', 'commonsbooking' ) );
		} else if ( $currentStatus == "confirmed" ) {
			$noticeText = commonsbooking_sanitizeHTML( __( 'Your booking is confirmed. A confirmation mail has been sent to you.', 'commonsbooking' ) );
		}

		if ( $currentStatus == "canceled" ) {
			$noticeText = commonsbooking_sanitizeHTML( __( 'Your booking has been canceled.', 'commonsbooking' ) );
		}

		if ( isset( $noticeText ) ) {
			return sprintf( '<div class="cb-notice cb-booking-notice cb-status-%s">%s</div>', $currentStatus, $noticeText );
		}

		return null;
	}

	/**
	 * Return HTML Link to booking
	 * @TODO: optimize booking link to support different permalink settings or set individual slug (e.g. booking instead of cb_timeframe)
	 *
	 * @return string
	 */
	public function bookingLink(): string {
		return sprintf( '<a href="%1$s">%2$s</a>', add_query_arg( $this->post->post_type, $this->post->post_name, home_url( '/' ) ), esc_html__( 'Link to your booking', 'commonsbooking' ) );

	}

}
