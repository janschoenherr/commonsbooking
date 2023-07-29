<?php

namespace CommonsBooking\Service;

use CommonsBooking\Exception\ExportException;
use CommonsBooking\Helper\Wordpress;
use CommonsBooking\Repository\Timeframe;
use CommonsBooking\Settings\Settings;
use DateInterval;
use DatePeriod;
use Psr\Cache\InvalidArgumentException;

class TimeframeExport {

	/**
	 * The post type to export.
	 * This corresponds to the return of @see \CommonsBooking\Wordpress\CustomPostType\Timeframe::getTypes()
	 * The all option is also valid.
	 * @var string
	 */
	private string $exportType;
	private ?array $locationFields = null;
	private ?array $itemFields = null;
	private ?array $userFields = null;
	private string $exportStartDate;
	private string $exportEndDate;

	private string $exportFilename;

	private bool $exportDataComplete = false;
	private bool $isCron = false;

	private ?string $lastProcessedDate = null;
	private ?array $relevantTimeframes = null;

	/**
	 * Defines how many days will be processed in one iteration. Higher numbers increases the likelihood for a timeout.
	 */
	const ITERATION_COUNTS = 25;

	/**
	 * @param string $exportType
	 * @param string $exportStartDate
	 * @param string $exportEndDate
	 *
	 * @param array|null $locationFields
	 * @param array|null $itemFields
	 * @param array|null $userFields
	 * @param string|null $lastProcessedDate
	 * @param array|null $relevantTimeframes
	 *
	 * @throws ExportException
	 */
	public function __construct(
		string $exportType,
		string $exportStartDate,
		string $exportEndDate,
		array $locationFields = null,
		array $itemFields = null,
		array $userFields = null,
		string $lastProcessedDate = null,
		array $relevantTimeframes = null
	) {

		if ( ! array_key_exists($exportType,\CommonsBooking\Wordpress\CustomPostType\Timeframe::getTypes(true)) ){
			throw new ExportException('Post type to export not valid');
		}
		$startDateTimestamp = strtotime( $exportStartDate );
		if ( ! $startDateTimestamp ) {
			throw new ExportException( __("Invalid start date",'commonsbooking') );
		}
		$endDateTimestamp  = strtotime( $exportEndDate );
		if ( ! $endDateTimestamp ) {
			throw new ExportException( __("Invalid end date",'commonsbooking') );
		}
		if ($startDateTimestamp > $endDateTimestamp) {
			throw new ExportException(__("Start date must not be after the end date.",'commonsbooking'));
		}

		$this->exportFilename  = 'timeframe-export-' .  date('Y-m-d-H-i-s') . '.csv';

		$this->exportType = $exportType;
		$this->exportStartDate = $exportStartDate;
		$this->exportEndDate   = $exportEndDate;
		$this->locationFields  = $locationFields;
		$this->itemFields      = $itemFields;
		$this->userFields      = $userFields;
		$this->lastProcessedDate = $lastProcessedDate;
		$this->relevantTimeframes = $relevantTimeframes;
	}

	public static function ajaxExportCsv() {
		//verify nonce
		check_ajax_referer('cb_export_timeframes', 'nonce');

		$result = array(
			'success' => false, //if neither success nor error is set, the frontend will assume that the export is still running
			'error' => false,
			'message' => ''
		);

		$postData = isset( $_POST['data'] ) ? (array) $_POST['data'] : array();
		$postData = commonsbooking_sanitizeArrayorString( $postData );

		$postSettings = $postData['settings'];

		$relevantTimeframes = empty ( $postSettings['relevantTimeframes'] ) ? null : $postSettings['relevantTimeframes'];
		if ( $relevantTimeframes !== null ) {
			$relevantTimeframes = array_map('intval', $relevantTimeframes);
		}

		try {
			$exportObject        = new self(
				intval($postSettings['exportType']),
				$postSettings['exportStartDate'],
				$postSettings['exportEndDate'],
				$postSettings['locationFields'] ? self::convertInputFields($postSettings['locationFields']) : null,
				$postSettings['itemFields'] ? self::convertInputFields($postSettings['itemFields']) : null,
				$postSettings['userFields'] ? self::convertInputFields($postSettings['userFields']) : null,
				$postSettings['lastProcessedDate'] ?? null,
				$relevantTimeframes,
			);
		} catch ( ExportException $e ) {
			wp_send_json( array(
				'success' => false,
				'error' => true,
				'message' => $e->getMessage()
			) );
			return;
		}
		$exportFinished = $exportObject->getExportData();
		if ( $exportFinished ) {
			try {
				$csvString = $exportObject->getCSV();
			} catch ( ExportException $e ) {
				wp_send_json( array(
					'success' => false,
					'error' => true,
					'message' => $e->getMessage()
				) );
				return;
			}
			wp_send_json( array(
				'success' => true,
				'error' => false,
				'message' => __( 'Export finished', 'commonsbooking' ),
				'csv' => $csvString,
				'filename' => $exportObject->exportFilename
			) );
		}
		else {
			$options = array(
				'exportType' => $exportObject->exportType,
				'exportStartDate' => $exportObject->exportStartDate,
				'exportEndDate' => $exportObject->exportEndDate,
				'locationFields' => $exportObject->locationFields,
				'itemFields' => $exportObject->itemFields,
				'userFields' => $exportObject->userFields,
				'lastProcessedDate' => $exportObject->lastProcessedDate,
				'relevantTimeframes' => $exportObject->relevantTimeframes,
			);
			wp_send_json( array(
				'success' => false,
				'error' => false,
				'settings' => $options,
				'progress' => $exportObject->getProgressString()
			) );
		}
	}

	public static function cronExport($exportPath) {
		$timerange = Settings::getOption( 'commonsbooking_options_export', 'export-timerange' );
		$start     = date( 'd.m.Y' );
		$end       = date( 'd.m.Y', strtotime( '+' . $timerange . ' day' ) );
		$configuredType = Settings::getOption( 'commonsbooking_options_export', 'export-type' );
		$configuredLocationFields = Settings::getOption( 'commonsbooking_options_export', \CommonsBooking\View\TimeframeExport::LOCATION_FIELD );
		$configuredItemFields = Settings::getOption( 'commonsbooking_options_export', \CommonsBooking\View\TimeframeExport::ITEM_FIELD );
		$configuredUserFields = Settings::getOption( 'commonsbooking_options_export', \CommonsBooking\View\TimeframeExport::USER_FIELD );
		if ( $configuredType && $configuredType != 'all' ) {
			$type = intval( $configuredType );
		}
		else {
			$type = 0;
		}
		$exportObject = new self(
			$type,
			$start,
			$end,
			$configuredLocationFields ? self::convertInputFields($configuredLocationFields) : null,
			$configuredItemFields ? self::convertInputFields($configuredItemFields) : null,
			$configuredUserFields ? self::convertInputFields($configuredUserFields) : null,
		);
		$exportObject->setCron();
		try {
			$exportObject->getExportData();
			$exportObject->getCSV( $exportPath );
		} catch ( ExportException $e ) {
			$file = fopen( $exportPath, 'w' );
			fwrite( $file, $e->getMessage() );
			fclose( $file );
		}
	}

	/**
	 * Will get the corresponding CSV data for the TimeframeExport object as string.
	 * When cron is set, the export will be saved to the configured export path.
	 *
	 * @throws ExportException
	 */
	public function getCSV( $exportPath = null ) : string {
		$inputFields = [
			'location' => $this->locationFields,
			'item'     => $this->itemFields,
			'user'     => $this->userFields,
		];

		if (! $this->exportDataComplete) {
			throw new ExportException(__("Export data is not complete. Please complete the process before trying to export.",'commonsbooking'));
		}

		if ( $this->relevantTimeframes === null ) {
			throw new ExportException(__("No data was found for the selected time period",'commonsbooking'));
		}

		if ( $this->isCron ) {
			if ( $exportPath === null ) {
				throw new ExportException(__("You need to set an export path to execute the export",'commonsbooking'));
			}
			$output = fopen( $exportPath, 'w' );
		}
		else {
			// create a file pointer to memory so that we can save it as a string and return it
			$output = fopen('php://memory', 'r+');
		}

		$headline = false;

		foreach ( $this->relevantTimeframes as $timeframeID ) {
			$timeframePost = new \CommonsBooking\Model\Timeframe($timeframeID);
			$timeframeData = self::getTimeframeData( $timeframePost );

			if ( ! $headline ) {
				$headline    = true;
				$headColumns = array_keys( $timeframeData );

				// Iterate through in put fields
				foreach ( $inputFields as $type => $fields ) {
					if ( $fields === null ) {
						continue;
					}
					$columnNames = $fields;
					array_walk( $columnNames, function ( &$item ) use ( $type ) {
						$item = $type . ': ' . $item;
					} );
					$headColumns = array_merge( $headColumns, $columnNames );
				}

				// output the column headings
				fputcsv( $output, $headColumns, ";" );
			}

			// output the column values
			$valueColumns = array_values( $timeframeData );

			// Get values for user defined input fields.
			foreach ( $inputFields as $type => $fields ) {
				if ( $fields === null ) {
					continue;
				}
				// Location fields
				if ( $type == 'location' ) {
					$location = $timeframePost->getLocation();
					foreach ( $fields as $field ) {
						$valueColumns[] = $location->getFieldValue( $field );
					}
				}

				// Item fields
				if ( $type == 'item' ) {
					$item = $timeframePost->getItem();
					foreach ( $fields as $field ) {
						$valueColumns[] = $item->getFieldValue( $field );
					}
				}

				// User fields
				if ( $type == 'user' ) {
					$user = $timeframePost->getUserData();
					foreach ( $fields as $field ) {
						$valueColumns[] = $user->get( $field );
					}
				}
			}

			fputcsv( $output, $valueColumns, ";" );
		}
		if ( $this->isCron ) {
			fclose( $output );
			exit;
		}
		else {
			rewind( $output );

			return rtrim( stream_get_contents( $output ) );
		}
	}

	/**
	 * Gets export fields array from the comma separated string in the settings.
	 *
	 * @param $inputName
	 *
	 * @return false|string[]
	 */
	protected static function convertInputFields( $inputString ) {
		return array_filter( explode( ',', sanitize_text_field($inputString) ) );
	}

	public function getProgressString() : string {
		if ( $this->lastProcessedDate === null ) {
			return '';
		}
		$startDt = new \DateTime( $this->exportStartDate );
		$endDt   = new \DateTime( $this->exportEndDate );
		$progressDt = new \DateTime( $this->lastProcessedDate );
		$totalDays = $startDt->diff( $endDt )->days;
		$progressDays = $startDt->diff( $progressDt )->days;
		return sprintf( __( 'Processed %d of %d days', 'commonsbooking' ), $progressDays, $totalDays );
	}

	/**
	 * Returns data for export.
	 * This is the slowest function
	 *
	 * @param false $isCron
	 *
	 * @return bool - False if all days have been processed, True if there are still days left that have to be processed
	 * @throws InvalidArgumentException
	 */
	public function getExportData( ): bool {
		if ( ! $this->isCron && $this->lastProcessedDate === null){
			$start = $this->exportStartDate;
		}
		else {
			$start = $this->lastProcessedDate;
		}
		$end = $this->exportEndDate;

		// Timerange
		$period = self::getPeriod( $start, $end );

		// Types
		$type = self::getType();

		$dayCounter = 0;
		foreach ( $period as $dt ) {
			$dayCounter++;
			if (! $this->isCron && $dayCounter >= self::ITERATION_COUNTS) {
				//if we have to break, we later begin again at the last processed date
				$this->lastProcessedDate = $dt->format( "Y-m-d" );
				return false;
			}
			$dayTimeframes = Timeframe::get(
				[],
				[],
				$type ? [$type] : [],
				$dt->format( "Y-m-d" ),
				false,
				null,
				[ 'canceled', 'confirmed', 'unconfirmed', 'publish', 'inherit' ]
			);
			foreach ( $dayTimeframes as $timeframe ) {
				$this->relevantTimeframes[] = $timeframe->ID;
			}
		}
		$this->exportDataComplete = true;
		return true;
	}

	/**
	 * Will get a DatePeriod object from two datestring
	 * @param string $start Start date as datestring
	 * @param string $end End date as datestring
	 *
	 * @return DatePeriod
	 */
	protected static function getPeriod( $start, $end ): DatePeriod {
		// Timerange
		$begin = Wordpress::getUTCDateTime( $start );
		$end   = Wordpress::getUTCDateTime( $end );

		$interval = DateInterval::createFromDateString( '1 day' );

		return new DatePeriod( $begin, $interval, $end );
	}

	/**
	 * Returns selected timeframe type id.
	 * @return int
	 */
	protected static function getType(): int {
		$type = 0;

		// Backend download
		if ( array_key_exists( 'export-type', $_REQUEST ) && $_REQUEST['export-type'] !== 'all' ) {
			$type = intval( $_REQUEST['export-type'] );
		} else {
			//cron download
			$configuredType = Settings::getOption( 'commonsbooking_options_export', 'export-type' );
			if ( $configuredType && $configuredType != 'all' ) {
				$type = intval( $configuredType );
			}
		}

		return $type;
	}

	/**
	 * Prepares timeframe data array.
	 *
	 * @param \CommonsBooking\Model\Timeframe $timeframePost
	 *
	 * @return array
	 */
	protected static function getTimeframeData( \CommonsBooking\Model\Timeframe $timeframePost ): array {
		$timeframeData = self::getRelevantTimeframeFields( $timeframePost );

		// Timeframe typ
		$timeframeTypeId       = $timeframePost->getFieldValue( 'type' );
		$timeframetypes        = \CommonsBooking\Wordpress\CustomPostType\Timeframe::getTypes();
		$timeframeData['type'] = array_key_exists( $timeframeTypeId, $timeframetypes ) ?
			$timeframetypes[ $timeframeTypeId ] : __( 'Unknown', 'commonsbooking' );

		if ( $timeframeTypeId == \CommonsBooking\Wordpress\CustomPostType\Timeframe::BOOKING_ID ) {
			$booking = new \CommonsBooking\Model\Booking( $timeframePost->ID );
		}

		// Repetition option
		$repetitions                           = \CommonsBooking\Wordpress\CustomPostType\Timeframe::getTimeFrameRepetitions();
		$repetitionId                          = $timeframePost->getFieldValue( "timeframe-repetition" );
		$timeframeData["timeframe-repetition"] = array_key_exists( $repetitionId, $repetitions ) ?
			$repetitions[ $repetitionId ] : __( 'Unknown', 'commonsbooking' );

		// Grid option
		$gridOptions           = \CommonsBooking\Wordpress\CustomPostType\Timeframe::getGridOptions();
		$gridOptionId          = $timeframePost->getGrid();
		$timeframeData["grid"] = array_key_exists( $gridOptionId, $gridOptions ) ?
			$gridOptions[ $gridOptionId ] : __( 'Unknown', 'commonsbooking' );

		// get corresponding item title
		$item = $timeframePost->getItem();
		if ($item != null){
			$item_title = $item->post_title;
		}
		else {
			$item_title = __( 'Unknown', 'commonsbooking' );
		}

		// get corresponding location title
		$location = $timeframePost->getLocation();
		if ($location != null){
			$location_title = $location->post_title;
		}
		else {
			$location_title = __( 'Unknown', 'commonsbooking' );
		}

		// populate simple meta fields
		$timeframeData[ \CommonsBooking\Model\Timeframe::META_MAX_DAYS ]  = $timeframePost->getFieldValue( \CommonsBooking\Model\Timeframe::META_MAX_DAYS );
		$timeframeData["full-day"]            = $timeframePost->getFieldValue( "full-day" );
		$timeframeData[\CommonsBooking\Model\Timeframe::REPETITION_START] =
			$timeframePost->getStartDate() ?
				date( 'c', $timeframePost->getStartDate() ) : '';
		$timeframeData[\CommonsBooking\Model\Timeframe::REPETITION_END] =
			$timeframePost->getEndDate() ?
				date( 'c', $timeframePost->getEndDate() ) : '';
		$timeframeData["start-time"]          = $timeframePost->getStartTime();
		$timeframeData["end-time"]            = $timeframePost->getEndTime();
		$timeframeData["pickup"]              = isset( $booking ) ? $booking->pickupDatetime() : "";
		$timeframeData["return"]              = isset( $booking ) ? $booking->returnDatetime() : "";
		$timeframeData["booking-code"]        = $timeframePost->getFieldValue( "_cb_bookingcode" );
		$timeframeData["location-post_title"] = $location_title;
		$timeframeData["item-post_title"]     = $item_title;
		$timeframeData["user-firstname"]      = $timeframePost->getUserData()->first_name;
		$timeframeData["user-lastname"]       = $timeframePost->getUserData()->last_name;
		$timeframeData["user-login"]          = $timeframePost->getUserData()->user_login;
		$timeframeData["comment"]             = $timeframePost->getFieldValue('comment');

		return $timeframeData;
	}

	/**
	 * Removes not relevant fields from timeframedata.
	 *
	 * @param $timeframe
	 *
	 * @return array
	 */
	protected static function getRelevantTimeframeFields( $timeframe ) {
		$postArray               = get_object_vars( $timeframe->getPost() );
		$relevantTimeframeFields = [
			'ID',
			'post_title',
			"post_author",
			"post_date",
			"post_date_gmt",
			"post_content",
			"comment",
			"post_excerpt",
			"post_status",
			"post_name"
		];

		return array_filter(
			$postArray,
			function ( $key ) use ( $relevantTimeframeFields ) {
				return in_array( $key, $relevantTimeframeFields );
			},
			ARRAY_FILTER_USE_KEY
		);
	}

	public function setCron(): void {
		$this->isCron = true;
	}


}