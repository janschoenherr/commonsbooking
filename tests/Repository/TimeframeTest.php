<?php

namespace CommonsBooking\Tests\Repository;

use CommonsBooking\Repository\Timeframe;
use CommonsBooking\Tests\Wordpress\CustomPostTypeTest;

class TimeframeTest extends CustomPostTypeTest {

	const REPETITION_START = '1623801600';

	const REPETITION_END = '1661472000';

	protected int $timeframeId;
	protected int $timeframe2Id;

	protected function setUp() : void {
		parent::setUp();

		// Timeframe with enddate
		$this->timeframeId = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			self::REPETITION_START,
			self::REPETITION_END
		);

		// Timeframe without enddate
		$this->timeframe2Id = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			self::REPETITION_START,
			null
		);
	}

	protected function tearDown() : void {
		parent::tearDown();
	}

	public function testGetInRange() {
		//get for range without endtime (not implemented yet)
		/*
		$inRangeTimeFrames = Timeframe::getInRange(self::REPETITION_START);
		$this->assertEquals(2, count($inRangeTimeFrames));
		$postIds = array_map(function($timeframe) {
			return $timeframe->ID;
		}, $inRangeTimeFrames);
		$this->assertContains($this->timeframeId, $postIds);
		$this->assertContains($this->timeframe2Id, $postIds);
		*/
		//get for range with specific enddate
		$inRangeTimeFrames = Timeframe::getInRange(self::REPETITION_START, self::REPETITION_END);
		$this->assertEquals(2, count($inRangeTimeFrames));
		$postIds = array_map(function($timeframe) {
			return $timeframe->ID;
		}, $inRangeTimeFrames);
		$this->assertContains($this->timeframeId, $postIds);
		$this->assertContains($this->timeframe2Id, $postIds);
	}

	public function testGetForItem() {
		$inItemTimeframes = Timeframe::get(
			[],
			[$this->itemId],
		);
		$this->assertEquals(2,count($inItemTimeframes));
		$postIds = array_map(function($timeframe) {
			return $timeframe->ID;
		}, $inItemTimeframes);
		$this->assertContains($this->timeframeId, $postIds);
		$this->assertContains($this->timeframe2Id, $postIds);
	}

	public function testGetForLocation() {
		$inLocationTimeframes = Timeframe::get(
			[$this->locationId],
		);
		$this->assertEquals(2,count($inLocationTimeframes));
		$postIds = array_map(function($timeframe) {
			return $timeframe->ID;
		}, $inLocationTimeframes);
		$this->assertContains($this->timeframeId, $postIds);
		$this->assertContains($this->timeframe2Id, $postIds);
	}

	public function testGetForLocationAndItem() {
		$inLocationAndItemTimeframes = Timeframe::get(
			[$this->locationId],
			[$this->itemId],
		);
		$this->assertEquals(2,count($inLocationAndItemTimeframes));
		$postIds = array_map(function($timeframe) {
			return $timeframe->ID;
		}, $inLocationAndItemTimeframes);
		$this->assertContains($this->timeframeId, $postIds);
		$this->assertContains($this->timeframe2Id, $postIds);
	}

	public function testGetInRangePaginated() {
		/*
		$originalTimeframes = Timeframe::getInRangePaginated(
			self::REPETITION_START,
			self::REPETITION_END,
		);
		$this->assertTrue($originalTimeframes['done']);
		$this->assertEquals(1, $originalTimeframes['totalPages']);
		$this->assertEquals(2, count($originalTimeframes['posts']));
		$postIds = array_map(function($timeframe) {
			return $timeframe->ID;
		}, $originalTimeframes['posts']);
		$this->assertEqualsCanonicalizing([$this->timeframeId, $this->timeframe2Id], $postIds);
*/
		//create a bunch of bookings to test pagination properly
		$bookingIds = [];
		for($i = 0; $i < 21; $i++) {
			$bookingIds[] = $this->createBooking(
				$this->locationId,
				$this->itemId,
				strtotime("+ {10 + $i} days", strtotime(self::CURRENT_DATE)),
				strtotime("+ {11 + $i} days", strtotime(self::CURRENT_DATE)),
			);
		}
		$firstPage = Timeframe::getInRangePaginated(
			strtotime("+ 10 days", strtotime(self::CURRENT_DATE)),
			strtotime("+ 32 days", strtotime(self::CURRENT_DATE)),
			1,
			10,
			[ \CommonsBooking\Wordpress\CustomPostType\Timeframe::BOOKING_ID ],
		);
		$this->assertEquals(10, count($firstPage['posts']));
		$this->assertEquals(3, $firstPage['totalPages']);
		$this->assertFalse($firstPage['done']);

		$secondPage = Timeframe::getInRangePaginated(
			strtotime("+ 10 days", strtotime(self::CURRENT_DATE)),
			strtotime("+ 32 days", strtotime(self::CURRENT_DATE)),
			2,
			10,
			[ \CommonsBooking\Wordpress\CustomPostType\Timeframe::BOOKING_ID ],
		);
		$this->assertFalse($secondPage['done']);
		$this->assertEquals(3, $secondPage['totalPages']);
		$this->assertEquals(10, count($secondPage['posts']));

		$thirdPage = Timeframe::getInRangePaginated(
			strtotime("+ 10 days", strtotime(self::CURRENT_DATE)),
			strtotime("+ 32 days", strtotime(self::CURRENT_DATE)),
			3,
			10,
			[ \CommonsBooking\Wordpress\CustomPostType\Timeframe::BOOKING_ID ],
		);
		$this->assertTrue($thirdPage['done']);
		$this->assertEquals(3, $thirdPage['totalPages']);
		$this->assertEquals(3, count($thirdPage['posts']));

		//make sure, that no booking is in more than one page
		$this->assertEmpty(array_intersect($firstPage['posts'], $secondPage['posts'], $thirdPage['posts']));

		//make sure, that all bookings are in one of the pages
		$merged = array_merge($firstPage['posts'], $secondPage['posts'], $thirdPage['posts']);
		$this->assertEquals(23, count($merged));
		$this->assertEqualsCanonicalizing($bookingIds, array_map(function($booking) {
			return $booking->ID;
		}, $merged));
	}
}
