<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests the components of the Scheduler interface which use the Date module.
 *
 * @group scheduler
 */
class SchedulerDefaultTimeTest extends SchedulerBrowserTestBase {

  /**
   * Test the default time functionality.
   */
  public function testDefaultTime() {
    $this->drupalLogin($this->adminUser);
    // Show the timecheck report.
    $this->drupalGet('admin/reports/status');

    // Check that the correct default time is added to the scheduled date.
    // For testing we use an offset of 6 hours 30 minutes (23400 seconds).
    $this->seconds = 23400;
    // If the test happens to be run at a time when '+1 day' puts the calculated
    // publishing date into a different daylight-saving period then formatted
    // time can be an hour different. To avoid these failures we use a fixed
    // string when asserting the message and looking for field values.
    // @see https://www.drupal.org/node/2809627
    $this->seconds_formatted = '06:30:00';
    // In $edit use '6:30' not '06:30:00' to test flexibility.
    $edit = [
      'date_format' => 'Y-m-d H:i:s',
      'allow_date_only' => TRUE,
      'default_time' => '6:30',
    ];
    $this->drupalPostForm('admin/config/content/scheduler', $edit, t('Save configuration'));

    // Verify that the values have been saved correctly.
    $this->assertTrue($this->config('scheduler.settings')->get('allow_date_only'), 'The config setting for allow_date_only is stored correctly.');
    $this->assertEqual($this->config('scheduler.settings')->get('default_time'), $this->seconds_formatted, 'The config setting for default_time is stored correctly.');

    // Check that it is not possible to enter a date format without a time if
    // the 'date only' option is not enabled.
    $edit = [
      'date_format' => 'Y-m-d',
      'allow_date_only' => FALSE,
    ];
    $this->drupalPostForm('admin/config/content/scheduler', $edit, t('Save configuration'));
    $this->assertRaw(t('You must either include a time within the date format or enable the date-only option.'), 'It is not possible to enter a date format without a time if the "date only" option is not enabled.');

    // Try to save an invalid time value.
    $edit = [
      'allow_date_only' => TRUE,
      'default_time' => '123',
    ];
    $this->drupalPostForm('admin/config/content/scheduler', $edit, t('Save configuration'));
    // Verify that an error is displayed and the value has not been saved.
    $this->assertEqual($this->config('scheduler.settings')->get('default_time'), $this->seconds_formatted, 'The config setting for default_time has not changed.');
    $this->assertText('The default time should be in the format HH:MM:SS', 'When an invalid default time is entered the correct error message is displayed.');

    // Check that the default time works correctly for a user creating content.
    $this->drupalLogin($this->schedulerUser);

    // We cannot easily test the exact validation messages as they contain the
    // REQUEST_TIME, which can be one or more seconds in the past. Best we can
    // do is check the fixed part of the message as it is when passed to t() in
    // Datetime::validateDatetime. This will only work in English.
    $publish_validation_message = 'The Publish on date is invalid.';
    $unpublish_validation_message = 'The Unpublish on date is invalid.';

    // First test with the "date only" functionality disabled.
    $this->config('scheduler.settings')->set('allow_date_only', FALSE)->save();

    // Test that entering a time is required.
    $edit = [
      'title[0][value]' => 'No time ' . $this->randomString(15),
      'publish_on[0][value][date]' => \Drupal::service('date.formatter')->format(strtotime('+1 day', REQUEST_TIME), 'custom', 'Y-m-d'),
      'unpublish_on[0][value][date]' => \Drupal::service('date.formatter')->format(strtotime('+2 day', REQUEST_TIME), 'custom', 'Y-m-d'),
    ];
    // Create a node and check that the expected error messages are shown.
    $this->drupalPostForm('node/add/' . $this->type, $edit, t('Save'));
    $this->assertSession()->pageTextContains($publish_validation_message, 'By default it is required to enter a time when scheduling content for publication.');
    $this->assertSession()->pageTextContains($unpublish_validation_message, 'By default it is required to enter a time when scheduling content for unpublication.');

    // Allow the user to enter only a date with no time.
    $this->config('scheduler.settings')->set('allow_date_only', TRUE)->save();

    // Create a node and check that the expected error messages are not shown.
    $this->drupalPostForm('node/add/' . $this->type, $edit, t('Save'));
    $this->assertSession()->pageTextNotContains($publish_validation_message, 'If the default time option is enabled the user can skip the time when scheduling content for publication.');
    $this->assertSession()->pageTextNotContains($unpublish_validation_message, 'If the default time option is enabled the user can skip the time when scheduling content for unpublication.');

    // Check that the publish-on information is shown after saving.
    $publish_time = $edit['publish_on[0][value][date]'] . ' ' . $this->seconds_formatted;
    $args = ['@publish_time' => $publish_time];
    $this->assertRaw(t('This post is unpublished and will be published @publish_time.', $args), 'The user is informed that the content will be published on the requested date, on the default time.');

    // Check that the default time has been added to the scheduler form on edit.
    // Protect in case the node was not created. The checks will still fail.
    if ($node = $this->drupalGetNodeByTitle($edit['title[0][value]'])) {
      $this->drupalGet('node/' . $node->id() . '/edit');
    }
    $this->assertFieldByName('publish_on[0][value][time]', $this->seconds_formatted, 'The default time offset has been added to the date field when scheduling content for publication.');
    $this->assertFieldByName('unpublish_on[0][value][time]', $this->seconds_formatted, 'The default time offset has been added to the date field when scheduling content for unpublication.');
  }

}
