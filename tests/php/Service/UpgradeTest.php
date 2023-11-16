<?php

namespace CommonsBooking\Tests\Service;

use CommonsBooking\Model\Timeframe;
use CommonsBooking\Service\Upgrade;
use CommonsBooking\Tests\Wordpress\CustomPostTypeTest;
use CommonsBooking\Wordpress\CustomPostType\Map;
use SlopeIt\ClockMock\ClockMock;

class UpgradeTest extends CustomPostTypeTest
{

    private static bool $functionHasRun = false;

    public function testFixBrokenICalTitle()
    {
		\CommonsBooking\Settings\Settings::updateOption(
			'commonsbooking_options_templates',
			'emailtemplates_mail-booking_ics_event-title',
		'Booking for {{item:post_name}}'
		);
		\CommonsBooking\Settings\Settings::updateOption(
			COMMONSBOOKING_PLUGIN_SLUG . '_options_advanced-options',
			'event_title',
			'Booking for {{item:post_name}}'
		);
		Upgrade::fixBrokenICalTitle();
		$this->assertEquals('Booking for {{item:post_title}}', \CommonsBooking\Settings\Settings::getOption('commonsbooking_options_templates', 'emailtemplates_mail-booking_ics_event-title'));
		$this->assertEquals('Booking for {{item:post_title}}', \CommonsBooking\Settings\Settings::getOption(COMMONSBOOKING_PLUGIN_SLUG . '_options_advanced-options', 'event_title'));
    }

    public function testIsMajorUpdate()
    {
		$majorUpdate = new Upgrade('2.5.0', '2.6.0');
		$this->assertTrue($majorUpdate->isMajorUpdate());
		$minorUpdate = new Upgrade('2.5.0', '2.5.1');
		$this->assertFalse($minorUpdate->isMajorUpdate());
		$majorestUpdate = new Upgrade('2.5.0', '3.0.0');
		$this->assertTrue($majorestUpdate->isMajorUpdate());
		$downgrade = new Upgrade('2.6.0', '2.5.0');
		$this->assertFalse($downgrade->isMajorUpdate());
    }

	/**
	 * This will test if the upgrade tasks are run correctly.
	 * The test function should only run, when upgrading on or over version 2.5.2.
	 * It should for example not run when upgrading from 2.5.2 to 2.5.3.
	 *
	 * @dataProvider provideUpgradeConditions
	 */
	public function testRunUpgradeTasks($previousVersion, $currentVersion, $shouldRunFunction) {
		$upgrade = new Upgrade($previousVersion, $currentVersion);
		$upgrade->runUpgradeTasks();
		$this->assertEquals($shouldRunFunction, self::$functionHasRun);
	}

	/**
	 * The set_up defines a fake upgrade task that should only run when upgrading on or over version 2.5.2.
	 * The data provider will provide different upgrade conditions and the test will check if the function has run or not.
	 * true means, that the function is expected to run under these conditions, false means it is not expected to run.
	 *
	 * @return array[]
	 */
	public function provideUpgradeConditions() {
		return array(
			"Upgrade directly on version with new function (major)" => ["2.4.0", "2.5.2", true],
			"Upgrade past version with new function (major)" => ["2.4.0", "2.6.0", true],
			"Direct minor upgrade on same version" => ["2.5.1", "2.5.2", true],
			"Direct minor upgrade on version without new function" => ["2.5.0", "2.5.1", false], //This is a weird case that should not happen, usually the function would not be added before it is needed
			"Direct minor upgrade past version with new function" => ["2.5.2", "2.5.3", false],
			"Direct minor upgrade past version with new function (major)" => ["2.5.2", "2.6.0", false],
			"Downgrade from previous versions" => ["2.5.3", "2.5.2", false],
		);
	}

	public static function fakeUpdateFunction()
	{
		self::$functionHasRun = true;
	}

	public function testRunTasksAfterUpdate() {
		$olderVersion = '2.5.0';
		update_option(Upgrade::VERSION_OPTION, $olderVersion);
		Upgrade::runTasksAfterUpdate();
		$this->assertEquals(COMMONSBOOKING_VERSION, get_option(Upgrade::VERSION_OPTION));
	}

	public function testRun() {
		$upgrade = new Upgrade('2.5.0', '2.6.0');
		$this->assertTrue($upgrade->run());
		$this->assertEquals('2.6.0', get_option(Upgrade::VERSION_OPTION));

		$upgrade = new Upgrade('2.5.0', '2.5.1');
		$this->assertTrue($upgrade->run());
		$this->assertEquals('2.5.1', get_option(Upgrade::VERSION_OPTION));

		//new installation
		$upgrade = new Upgrade('', '2.5.0');
		$this->assertTrue($upgrade->run());
		$this->assertEquals('2.5.0', get_option(Upgrade::VERSION_OPTION));

		//no version change
		$upgrade = new Upgrade('2.5.0', '2.5.0');
		$this->assertFalse($upgrade->run());
	}

	public function testSetAdvanceBookingDaysDefault() {
		//create timeframe without advance booking days
		$timeframeId = $this->createBookableTimeFrameIncludingCurrentDay();
		update_post_meta($timeframeId, \CommonsBooking\Model\Timeframe::META_TIMEFRAME_ADVANCE_BOOKING_DAYS, '');
		Upgrade::setAdvanceBookingDaysDefault();
		$this->assertEquals(\CommonsBooking\Wordpress\CustomPostType\Timeframe::ADVANCE_BOOKING_DAYS, get_post_meta($timeframeId, \CommonsBooking\Model\Timeframe::META_TIMEFRAME_ADVANCE_BOOKING_DAYS, true));
	}

	public function testRemoveBreakingPostmeta() {
		ClockMock::freeze(new \DateTime(self::CURRENT_DATE));
		//Create timeframe that should still be valid after the cleanup
		$validTF = new Timeframe($this->createBookableTimeFrameStartingInAWeek());
		$this->assertTrue($validTF->isValid());

		//create holiday with ADVANCE_BOOKING_DAYS setting (the function does this by default)
		$holiday = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			strtotime('+1 week', strtotime(self::CURRENT_DATE)),
			strtotime('+2 weeks', strtotime(self::CURRENT_DATE)),
		);
		Upgrade::removeBreakingPostmeta();
		$this->assertEmpty(get_post_meta($holiday, 'advance_booking_days', true));
	}

	protected function setUp(): void {
		parent::setUp();
		//This replaces the original update tasks with a internal test function that just sets a variable to true
		$testTasks = new \ReflectionProperty('\CommonsBooking\Service\Upgrade', 'upgradeTasks');
		$testTasks->setAccessible(true);
		$testTasks->setValue(
			[
				'2.5.2' => [
					[self::class, 'fakeUpdateFunction' ]
				]
			]
		);
	}

	public function testMigrateMapSettings() {
		$mapOptions = array (
			'base_map' => 1,
			'show_scale' => true,
			'map_height' => 400,
			'custom_no_locations_message' => '',
			'custom_filterbutton_label' => '',
			'zoom_min' => 9,
			'zoom_max' => 19,
			'scrollWheelZoom' => true,
			'zoom_start' => 9,
			'lat_start' => 50.937531,
			'lon_start' => 6.960279,
			'marker_map_bounds_initial' => true,
			'marker_map_bounds_filter' => true,
			'max_cluster_radius' => 80,
			'marker_tooltip_permanent' => false,
			'custom_marker_media_id' => 0,
			'marker_icon_width' => 0.0,
			'marker_icon_height' => 0.0,
			'marker_icon_anchor_x' => 0.0,
			'marker_icon_anchor_y' => 0.0,
			'show_location_contact' => false,
			'label_location_contact' => '',
			'show_location_opening_hours' => false,
			'label_location_opening_hours' => '',
			'show_item_availability' => false,
			'custom_marker_cluster_media_id' => 0,
			'marker_cluster_icon_width' => 0.0,
			'marker_cluster_icon_height' => 0.0,
			'address_search_bounds_left_bottom_lon' => NULL,
			'address_search_bounds_left_bottom_lat' => NULL,
			'address_search_bounds_right_top_lon' => NULL,
			'address_search_bounds_right_top_lat' => NULL,
			'show_location_distance_filter' => false,
			'label_location_distance_filter' => '',
			'show_item_availability_filter' => false,
			'label_item_availability_filter' => '',
			'label_item_category_filter' => '',
			'item_draft_appearance' => '1',
			'marker_item_draft_media_id' => 0,
			'marker_item_draft_icon_width' => 0.0,
			'marker_item_draft_icon_height' => 0.0,
			'marker_item_draft_icon_anchor_x' => 0.0,
			'marker_item_draft_icon_anchor_y' => 0.0,
			'cb_items_available_categories' =>
				array (
				),
			'cb_items_preset_categories' =>
				array (
				),
			'cb_locations_preset_categories' =>
				array (
				),
			'availability_max_days_to_show' => 11,
			'availability_max_day_count' => 14,
		);
		$oldMapId = wp_insert_post( [
			'post_title'  => 'Map',
			'post_type'   => Map::$postType,
			'post_status' => 'publish'
		] );

		update_post_meta( $oldMapId, 'cb_map_options', $mapOptions );

		Upgrade::migrateMapSettings();
		//each option should now have it's own meta entry
		foreach ($mapOptions as $key => $value) {
			$this->assertEquals($value, get_post_meta($oldMapId, $key, true));
		}
		wp_delete_post($oldMapId, true);
	}

	protected function tearDown(): void {
		self::$functionHasRun = false;
		//resets version back to current version
		update_option(\CommonsBooking\Service\Upgrade::VERSION_OPTION, COMMONSBOOKING_VERSION);
		parent::tearDown();
	}
}
