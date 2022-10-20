<?php

namespace CommonsBooking\Service;

use CommonsBooking\Settings\Settings;
use CommonsBooking\View\TimeframeExport;

class Scheduler {

	/**
	 * Returns array with custom time intervals.
	 * @return array[]
	 */
	public static function getIntervals(): array {
		return array(
			'ten_seconds'    => array(
				'display'  => 'Every 10 Seconds',
				'interval' => 10,
			),
			'ten_minutes'    => array(
				'display'  => 'Every 10 Minutes',
				'interval' => 600,
			),
			'five_minutes'   => array(
				'display'  => 'Every 5 Minutes',
				'interval' => 300,
			),
			'thirty_minutes' => array(
				'display'  => 'Every 30 Minutes',
				'interval' => 1800,
			)
		);
	}

	/**
	 * Inits custom intervals.
	 *
	 * @param $schedules
	 *
	 * @return array
	 */
	public static function initIntervals( $schedules ): array {
		return array_merge( $schedules, self::getIntervals() );
	}

	/**
	 * Inits scheduler hooks.
	 */
	public static function initHooks() {
		// Add custom cron intervals
		add_filter( 'cron_schedules', array( self::class, 'initIntervals' ) );

		// Init booking cleanup job
		add_action( 'cb_cron_hook', array( \CommonsBooking\Service\Booking::class, 'cleanupBookings' ) );
		if ( ! wp_next_scheduled( 'cb_cron_hook' ) ) {
			wp_schedule_event( time(), 'ten_minutes', 'cb_cron_hook' );
		}

		// Init booking reminder job
        if (Settings::getOption('commonsbooking_options_reminder', 'pre-booking-reminder-activate') == 'on') {
            add_action( 'cb_reminder_cron_hook', array( \CommonsBooking\Service\Booking::class, 'sendReminderMessage' ) );
            if ( ! wp_next_scheduled( 'cb_reminder_cron_hook' ) ) {
                $startTime = Settings::getOption( 'commonsbooking_options_reminder',
                    'pre-booking-time' );

                wp_schedule_event(
                    self::getReminderStarttimestamp( $startTime ),
                    'daily',
                    'cb_reminder_cron_hook'
                );
            }
		}

		// Init booking feedback job
        if (Settings::getOption('commonsbooking_options_reminder', 'post-booking-notice-activate') == 'on') {
            add_action( 'cb_feedback_cron_hook', array( \CommonsBooking\Service\Booking::class, 'sendFeedbackMessage' ) );
            if ( ! wp_next_scheduled( 'cb_feedback_cron_hook' ) ) {
                wp_schedule_event(
                    strtotime( "tomorrow midnight" ) + 1, // tomorrow at 00:00:01
                    'daily',
                    'cb_feedback_cron_hook'
                );
            }
        }

		// Init timeframe export job
		self::initTimeFrameExport();
	}

	/**
	 * Returns timestamp based on starttime. If calculated timestamp is in past, it returns timestamp
	 * for tomorrow.
	 *
	 * @param $startTime
	 *
	 * @return false|int
	 */
	private static function getReminderStarttimestamp( $startTime ) {
		$startTimestamp = strtotime( "today +$startTime hours" );
		if ( $startTimestamp < current_time('timestamp') ) {
			$startTimestamp = strtotime( "tomorrow +$startTime hours" );
		}

		return $startTimestamp;
	}

	/**
	 * Add cronjob for csv timeframe export
	 * @throws \Exception
	 */
	public static function initTimeFrameExport() {
		$cronExport = Settings::getOption( 'commonsbooking_options_export', 'export-cron' );
		if ( $cronExport == 'on' ) {
			$exportPath     = Settings::getOption( 'commonsbooking_options_export', 'export-filepath' );
			$exportInterval = Settings::getOption( 'commonsbooking_options_export', 'export-interval' );

			$cbCronHook = 'cb_cron_export';
			add_action( $cbCronHook, function () use ( $exportPath ) {
				TimeframeExport::exportCsv( $exportPath );
			} );

			if ( ! wp_next_scheduled( $cbCronHook ) ) {
				wp_schedule_event( time(), $exportInterval, $cbCronHook );
			}
		}
	}

	/**
	 * Remove events
	 */
	public static function unscheduleEvents() {
		$cbCronHooks = [
			'cb_cron_hook',
			'cb_reminder_cron_hook',
			'cb_feedback_cron_hook',
			'cb_cron_export'
		];

		foreach ( $cbCronHooks as $cbCronHook ) {
			$timestamp = wp_next_scheduled( $cbCronHook );
			wp_unschedule_event( $timestamp, $cbCronHook );
		}
	}

}