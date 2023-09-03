<?php

namespace CommonsBooking\Tests\Repository;

use CommonsBooking\Repository\Timeframe;
use CommonsBooking\Tests\Wordpress\CustomPostTypeTest;

class TimeframeTest extends CustomPostTypeTest {

	protected int $repetition_start;
	protected int $repetition_end;

	protected string $formattedDate;

	protected int $timeframeWithEndDate;
	protected int $timeframeWithoutEndDate;
	protected int $timeframeDailyRepetition;
	protected int $timeframeWeeklyRepetition;
	protected int $timeframeManualRepetition;

	/**
	 * The tests are designed in a way, that all timeframes should lie in the CURRENT_DATE plus 10 days.
	 * The only exception is the manual repetition timeframe, which is only valid for today and in a week.
	 * all apply to the location with id $this->locationId and the item with id $this->itemId
	 * @var array|int|\WP_Error
	 */
	protected array $allTimeframes;

	protected function setUp(): void {
		parent::setUp();
	}

	/**
	 * Create a completely seperate item, location and timeframe.
	 * @return void
	 */
	private function createOtherTimeframe( $start = $this->repetition_start, $end = $this->repetition_end ) {
		$this->otherItemId      = $this->createItem( "Other Item" );
		$this->otherLocationId  = $this->createLocation( "Other Location" );
		$this->otherTimeframeId = $this->createTimeframe(
			$this->otherLocationId,
			$this->otherItemId,
			$start,
			$end
		);
	}

	private function createOtherTFwithItemAtFirstLocation( $start = $this->repetition_start, $end = $this->repetition_end ) {
		$this->otherItemId      = $this->createItem( "Other Item" );
		$this->otherTimeframeId = $this->createTimeframe(
			$this->locationId,
			$this->otherItemId,
			$start,
			$end
		);
	}

	protected function tearDown(): void {
		parent::tearDown();
	}

	public function testGetInRange_withEndDate() {
		$this->repetition_start = strtotime(self::CURRENT_DATE);
		$this->repetition_end = strtotime('+10 days', $this->repetition_start);

		// Timeframe with enddate
		$this->timeframeWithEndDate = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			$this->repetition_start,
			$this->repetition_end
		);
		$this->allTimeframes[] = $this->timeframeWithEndDate;
		$inRangeTimeFrames = Timeframe::getInRange( $this->repetition_start, $this->repetition_end);
		$postIds           = array_map( function ( $timeframe ) {
			return $timeframe->ID;
		}, $inRangeTimeFrames );
		$this->assertContains( $this->timeframeId, $postIds );
		$this->assertEquals( 1, count( $inRangeTimeFrames ) );

		// Create a completely seperate item, location and timeframe. This should now also be in the range.
		$this->createOtherTimeframe();
		$inRangeTimeFrames = Timeframe::getInRange( $this->repetition_start, $this->repetition_end );
		$this->assertEquals( 2, count( $inRangeTimeFrames ) );
		$postIds = array_map( function ( $timeframe ) {
			return $timeframe->ID;
		}, $inRangeTimeFrames );
		$this->assertContains( $this->otherTimeframeId, $postIds );

		//different location, same item, should be in range
		$this->createOtherTFwithItemAtFirstLocation();
		$inRangeTimeFrames = Timeframe::getInRange( $this->repetition_start, $this->repetition_end );
		$this->assertEquals( 3, count( $inRangeTimeFrames ) );
		$postIds = array_map( function ( $timeframe ) {
			return $timeframe->ID;
		}, $inRangeTimeFrames );
		$this->assertContains( $this->otherTimeframeId, $postIds );

		//item and location are the same, but timeframe is not in range because it ends before the start of the range
		$earlierStart = new \DateTime();
		$earlierStart->setTimestamp( $this->repetition_start );
		$earlierStart->modify( '-10 day' );

		$earlierEnd = clone $earlierStart;
		$earlierEnd->modify( '+5 day' );

		$this->timeframeId = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			$earlierStart->getTimestamp(),
			$earlierEnd->getTimestamp()
		);
		$inRangeTimeFrames = Timeframe::getInRange( $this->repetition_start, $this->repetition_end );
		$this->assertEquals( 3, count( $inRangeTimeFrames ) );
		$postIds = array_map( function ( $timeframe ) {
			return $timeframe->ID;
		}, $inRangeTimeFrames );
		$this->assertNotContains( $this->timeframeId, $postIds );
	}

	public function testGetInRange_withoutEndDate() {
		// Timeframe without enddate
		$this->timeframeWithoutEndDate = $this->createTimeframe(
		$this->timeframeId = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			$this->repetition_start,
			null
		);
		$this->allTimeframes[] = $this->timeframeWithoutEndDate;

		//timeframe with daily repetition
		$this->timeframeDailyRepetition = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			$this->repetition_start,
			$this->repetition_end,
			\CommonsBooking\Wordpress\CustomPostType\Timeframe::BOOKABLE_ID,
			'on',
			'd'
		);
		$this->allTimeframes[] = $this->timeframeDailyRepetition;

		//timeframe with weekly repetition from monday to friday
		$this->timeframeWeeklyRepetition = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			$this->repetition_start,
			$this->repetition_end,
			\CommonsBooking\Wordpress\CustomPostType\Timeframe::BOOKABLE_ID,
			'on',
			'w',
			0,
			'08:00 AM',
			'12:00 PM',
			'publish',
			["1","2","3","4","5"]
		);
		$this->allTimeframes[] = $this->timeframeWeeklyRepetition;

		$dateInAWeek = date('Y-m-d', strtotime('+1 week', $this->repetition_start));
		//timeframe with manual repetition for today and in a week
		$this->timeframeManualRepetition = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			$this->repetition_start,
			$this->repetition_end,
			\CommonsBooking\Wordpress\CustomPostType\Timeframe::BOOKABLE_ID,
			'on',
			'manual',
			0,
			'08:00 AM',
			'12:00 PM',
			'publish',
			[],
			"{$this->dateFormatted},{$dateInAWeek}"
		);
		$this->allTimeframes[] = $this->timeframeManualRepetition;

		asort($this->allTimeframes);
	}

	public function testGetInRange() {
		$inRangeTimeFrames = Timeframe::getInRange($this->repetition_start, $this->repetition_end);
		//All timeframes should be in range
		$this->assertEquals(count($this->allTimeframes),count($inRangeTimeFrames) );
		$postIds = array_map(function($timeframe) {
		$inRangeTimeFrames = Timeframe::getInRange( $this->repetition_start, $this->repetition_end );
		$postIds           = array_map( function ( $timeframe ) {
			return $timeframe->ID;
		}, $inRangeTimeFrames);
		asort($postIds);
		$this->assertEquals($this->allTimeframes, $postIds);
		}, $inRangeTimeFrames );
		$this->assertContains( $this->timeframeId, $postIds );
		$this->assertEquals( 1, count( $inRangeTimeFrames ) );
	}

	public function testGetForItem() {
		// Timeframe with enddate
		$this->timeframeId = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			$this->repetition_start,
			$this->repetition_end
		);
		$inItemTimeframes  = Timeframe::get(
			[],
			[ $this->itemId ],
		);
		$this->assertEquals( 1, count( $inItemTimeframes ) );
		$this->assertEquals( $this->timeframeId, $inItemTimeframes[0]->ID );

		//test for one item that is first at one location and then at another location, should get both timeframes
		$otherLocationId = $this->createLocation( "Other Location" );
		$earlierStart    = new \DateTime();
		$earlierStart->setTimestamp( $this->repetition_start );
		$earlierStart->modify( '-10 day' );

		$earlierEnd = clone $earlierStart;
		$earlierEnd->modify( '+5 day' );
		$otherTimeframeId = $this->createTimeframe(
			$otherLocationId,
			$this->itemId,
			$earlierStart->getTimestamp(),
			$earlierEnd->getTimestamp()
		);
		$inItemTimeframes = Timeframe::get(
			[],
			[ $this->itemId ],
		);
		$this->assertEquals(count($this->allTimeframes),count($inItemTimeframes));
		$postIds = array_map(function($timeframe) {
		$this->assertEquals( 2, count( $inItemTimeframes ) );
		$postIds = array_map( function ( $timeframe ) {
			return $timeframe->ID;
		}, $inItemTimeframes);
		asort($postIds);
		$this->assertEquals($this->allTimeframes, $postIds);
		}, $inItemTimeframes );
		$this->assertContains( $this->timeframeId, $postIds );
		$this->assertContains( $otherTimeframeId, $postIds );
	}

	/**
	 * Tests for timeframes which have more than one assigned item or location
	 * @return void
	 */
	public function testGetMultiTimeframe() {
		$otherItem = $this->createItem( "Other Item" );
		$otherLocation = $this->createLocation( "Other Location" );
		// Timeframe just for original item and location
		$this->timeframeId = $this->createBookableTimeFrameIncludingCurrentDay();
		$holidayTF = $this->createHolidayTimeframeForAllItemsAndLocations();
		//from first item
		$inItemTimeframes = Timeframe::get(
			[],
			[ $this->itemId ],
		);
		$this->assertEquals( 2, count( $inItemTimeframes ) );
		$postIds = array_map( function ( $timeframe ) {
			return $timeframe->ID;
		}, $inItemTimeframes );
		$this->assertContains( $this->timeframeId, $postIds );
		$this->assertContains( $holidayTF, $postIds );

		//from second item
		$inItemTimeframes = Timeframe::get(
			[],
			[ $otherItem ],
		);
		$this->assertEquals( 1, count( $inItemTimeframes ) );
		$this->assertEquals( $holidayTF, $inItemTimeframes[0]->ID );

		//from first location
		$inLocationTimeframes = Timeframe::get(
			[ $this->locationId ],
		);
		$this->assertEquals( 2, count( $inLocationTimeframes ) );
		$postIds = array_map( function ( $timeframe ) {
			return $timeframe->ID;
		}, $inLocationTimeframes );
		$this->assertContains( $this->timeframeId, $postIds );
		$this->assertContains( $holidayTF, $postIds );

		//from second location
		$inLocationTimeframes = Timeframe::get(
			[ $otherLocation ],
		);
		$this->assertEquals( 1, count( $inLocationTimeframes ) );
		$this->assertEquals( $holidayTF, $inLocationTimeframes[0]->ID );
	}

	public function testGetForLocation() {
		// Timeframe with enddate
		$this->timeframeId    = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			$this->repetition_start,
			$this->repetition_end
		);
		$inLocationTimeframes = Timeframe::get(
			[ $this->locationId ],
		);
		$this->assertEquals(count($this->allTimeframes),count($inLocationTimeframes));
		$postIds = array_map(function($timeframe) {
		$this->assertEquals( 1, count( $inLocationTimeframes ) );
		$this->assertEquals( $this->timeframeId, $inLocationTimeframes[0]->ID );

		//test for one location that has two items, should get both timeframes
		$this->createOtherTFwithItemAtFirstLocation();
		$inLocationTimeframes = Timeframe::get(
			[ $this->locationId ],
		);
		$this->assertEquals( 2, count( $inLocationTimeframes ) );
		$postIds = array_map( function ( $timeframe ) {
			return $timeframe->ID;
		}, $inLocationTimeframes);
		asort($postIds);
		$this->assertEquals($this->allTimeframes, $postIds);
		}, $inLocationTimeframes );
		$this->assertContains( $this->timeframeId, $postIds );
		$this->assertContains( $this->otherTimeframeId, $postIds );
	}

	public function testGetForLocationAndItem() {
		// Timeframe with enddate
		$this->timeframeId           = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			$this->repetition_start,
			$this->repetition_end
		);
		$inLocationAndItemTimeframes = Timeframe::get(
			[ $this->locationId ],
			[ $this->itemId ],
		);
		$this->assertEquals(count($this->allTimeframes),count($inLocationAndItemTimeframes));
		$postIds = array_map(function($timeframe) {
			return $timeframe->ID;
		}, $inLocationAndItemTimeframes);
		asort($postIds);
		$this->assertEquals($this->allTimeframes, $postIds);
		$this->assertEquals( 1, count( $inLocationAndItemTimeframes ) );
		$this->assertEquals( $this->timeframeId, $inLocationAndItemTimeframes[0]->ID );

		//test for one location that has two items and completely separate item/location combo should still only get the specific timeframe
		$this->createOtherTFwithItemAtFirstLocation();
		$inLocationAndItemTimeframes = Timeframe::get(
			[ $this->locationId ],
			[ $this->itemId ],
		);
		$this->assertEquals( 1, count( $inLocationAndItemTimeframes ) );
		$this->assertEquals( $this->timeframeId, $inLocationAndItemTimeframes[0]->ID );
	}

	public function testGetPostIdsByType_singleItem() {
		// Timeframe with enddate
		$this->timeframeId = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			$this->repetition_start,
			$this->repetition_end
		);
		$postIds           = Timeframe::getPostIdsByType(
			[ \CommonsBooking\Wordpress\CustomPostType\Timeframe::BOOKABLE_ID ],
			[ $this->itemId ],
		);
		$this->assertEquals( 1, count( $postIds ) );
		$this->assertEquals( $this->timeframeId, $postIds[0] );

		//test for one item that is first at one location and then at another location, should get both timeframes
		$otherLocationId = $this->createLocation( "Other Location" );
		$earlierStart    = new \DateTime();
		$earlierStart->setTimestamp( $this->repetition_start );
		$earlierStart->modify( '-10 day' );
		$earlierEnd = clone $earlierStart;
		$earlierEnd->modify( '+5 day' );
		$otherTimeframeId = $this->createTimeframe(
			$otherLocationId,
			$this->itemId,
			$earlierStart->getTimestamp(),
			$earlierEnd->getTimestamp()
		);
		$postIds          = Timeframe::getPostIdsByType(
			[ \CommonsBooking\Wordpress\CustomPostType\Timeframe::BOOKABLE_ID ],
			[ $this->itemId ]
		);
		$this->assertEquals( 2, count( $postIds ) );
		$postIds = array_map( 'intval', $postIds ); //the assertContains can not handle string/int comparison
		$this->assertContains( $this->timeframeId, $postIds );
		$this->assertContains( $otherTimeframeId, $postIds );
	}

	public function testGetPostIdsByType_singleLocation() {
		// Timeframe with enddate
		$this->timeframeId = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			$this->repetition_start,
			$this->repetition_end
		);
		$postIds           = Timeframe::getPostIdsByType(
			[ \CommonsBooking\Wordpress\CustomPostType\Timeframe::BOOKABLE_ID ],
			[],
			[ $this->locationId ]
		);
		$this->assertEquals( 1, count( $postIds ) );
		$this->assertEquals( $this->timeframeId, $postIds[0] );

		//test for one location that has two items, should get both timeframes
		$this->createOtherTFwithItemAtFirstLocation();
		$postIds = Timeframe::getPostIdsByType(
			[ \CommonsBooking\Wordpress\CustomPostType\Timeframe::BOOKABLE_ID ],
			[],
			[ $this->locationId ]
		);
		$postIds = array_map( 'intval', $postIds ); //the assertContains can not handle string/int comparison
		$this->assertEquals( 2, count( $postIds ) );
		$this->assertContains( $this->timeframeId, $postIds );
		$this->assertContains( $this->otherTimeframeId, $postIds );
	}

	public function testGetPostIdsByType_singleLocationAndItem() {
		// Timeframe with enddate
		$this->timeframeId = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			$this->repetition_start,
			$this->repetition_end
		);
		$postIds           = Timeframe::getPostIdsByType(
			[ \CommonsBooking\Wordpress\CustomPostType\Timeframe::BOOKABLE_ID ],
			[ $this->itemId ],
			[ $this->locationId ]
		);
		$this->assertEquals( 1, count( $postIds ) );
		$this->assertEquals( $this->timeframeId, $postIds[0] );

		//test for one location that has two items and completely separate item/location combo should still only get the specific timeframe
		$this->createOtherTFwithItemAtFirstLocation();
		$postIds = Timeframe::getPostIdsByType(
			[ \CommonsBooking\Wordpress\CustomPostType\Timeframe::BOOKABLE_ID ],
			[ $this->itemId ],
			[ $this->locationId ]
		);
		$this->assertEquals( 1, count( $postIds ) );
		$this->assertEquals( $this->timeframeId, $postIds[0] );
	}

	public function testGetPostIdsByType_oneLocationMultiItem() {
		$otherItemId = $this->createItem( "Other Item" );
		// Timeframe with enddate and two items
		$this->timeframeId = $this->createTimeframe(
			$this->locationId,
			[$this->itemId, $otherItemId],
			$this->repetition_start,
			$this->repetition_end
		);
		$fromFirstItem     = Timeframe::getPostIdsByType(
			[ \CommonsBooking\Wordpress\CustomPostType\Timeframe::BOOKABLE_ID ],
			[ $this->itemId ],
			[ $this->locationId ]
		);
		$this->assertEquals( 1, count( $fromFirstItem ) );
		$this->assertEquals( $this->timeframeId, $fromFirstItem[0] );

		$fromSecondItem     = Timeframe::getPostIdsByType(
			[ \CommonsBooking\Wordpress\CustomPostType\Timeframe::BOOKABLE_ID ],
			[ $otherItemId ],
			[ $this->locationId ]
		);
		$this->assertEquals( 1, count( $fromSecondItem ) );
		$this->assertEquals( $this->timeframeId, $fromSecondItem[0] );

		$fromBothItems     = Timeframe::getPostIdsByType(
			[ \CommonsBooking\Wordpress\CustomPostType\Timeframe::BOOKABLE_ID ],
			[ $this->itemId, $otherItemId ],
			[ $this->locationId ]
		);
		$this->assertEquals( 1, count( $fromBothItems ) );
		$this->assertEquals( $this->timeframeId, $fromBothItems[0] );
	}

	/**
	 * This test is tricky because it only makes sense for holiday timeframes.
	 * Otherwise, this configuration would create a conflict.
	 *
	 * @return void
	 */
	public function testGetPostIdsByType_multiLocationMultiItem() {
		// Timeframe with enddate and one item
		$this->timeframeId = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			$this->repetition_start,
			$this->repetition_end,
		);
		$this->createOtherTimeframe();

		//create holiday applicable for both
		$holidayId = $this->createTimeframe(
			[$this->locationId, $this->otherLocationId],
			[$this->itemId, $this->otherItemId],
			$this->repetition_start,
			$this->repetition_end,
			\CommonsBooking\Wordpress\CustomPostType\Timeframe::HOLIDAYS_ID
		);

		$holidayFromFirstItemAndLoc = Timeframe::getPostIdsByType(
			[ \CommonsBooking\Wordpress\CustomPostType\Timeframe::HOLIDAYS_ID ],
			[ $this->itemId ],
			[ $this->locationId ]
		);
		$this->assertEquals( 1, count( $holidayFromFirstItemAndLoc ) );
		$this->assertEquals( $holidayId, $holidayFromFirstItemAndLoc[0] );

		$holidayFromSecondItemAndLoc = Timeframe::getPostIdsByType(
			[ \CommonsBooking\Wordpress\CustomPostType\Timeframe::HOLIDAYS_ID ],
			[ $this->otherItemId ],
			[ $this->otherLocationId ]
		);
		$this->assertEquals( 1, count( $holidayFromSecondItemAndLoc ) );
		$this->assertEquals( $holidayId, $holidayFromSecondItemAndLoc[0] );

	}

	public function testGetForSpecificDate() {
		$inSpecificDate = Timeframe::get(
			[$this->locationId],
			[$this->itemId],
			[],
			$this->dateFormatted
		);
		$this->assertEquals(count($this->allTimeframes),count($inSpecificDate));
		$postIds = array_map(function($timeframe) {
			return $timeframe->ID;
		}, $inSpecificDate);
		asort($postIds);
		$this->assertEquals($this->allTimeframes, $postIds);

		$inOneWeek = Timeframe::get(
			[$this->locationId],
			[$this->itemId],
			[],
			date('Y-m-d', strtotime('+1 week', $this->repetition_start))
		);
		//it should contain everything
		$this->assertEquals(count($this->allTimeframes),count($inOneWeek));
		$postIds = array_map(function($timeframe) {
			return $timeframe->ID;
		}, $inOneWeek);
		asort($postIds);
		$this->assertEquals($this->allTimeframes, $postIds);

		$tomorrow = Timeframe::get(
			[$this->locationId],
			[$this->itemId],
			[],
			date('Y-m-d', strtotime('+1 day', $this->repetition_start))
		);
		//it should contain everything except the manual repetition
		$this->assertEquals(count($this->allTimeframes) - 1,count($tomorrow));
		$postIds = array_map(function($timeframe) {
			return $timeframe->ID;
		}, $tomorrow);
		asort($postIds);
		$this->assertEquals(array_diff($this->allTimeframes, [$this->timeframeManualRepetition]), $postIds);
	}

}
