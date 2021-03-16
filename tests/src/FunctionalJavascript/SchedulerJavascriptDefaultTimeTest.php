<?php

namespace Drupal\Tests\scheduler\FunctionalJavascript;

/**
 * Tests the JavaScript functionality for default dates.
 *
 * @group scheduler
 */
class SchedulerJavascriptDefaultTimeTest extends SchedulerJavascriptTestBase {

  /**
   * The HTML5 datepicker format.
   *
   * @var string
   */
  private $datepickerFormat;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Determine whether the HTML5 date picker is expecting d/m/Y or m/d/Y
    // because this varies with the locale and cannot be set or predetermined
    // using the site timezone. This is a bit of hack but it is necessary due
    // to local testing having a different locale to drupal.org testing.
    // @see https://www.drupal.org/project/scheduler/issues/2913829 from #18.
    $this->drupalLogin($this->schedulerUser);
    $this->drupalGet('node/add/' . $this->type);
    $page = $this->getSession()->getPage();
    $title = 'Date format test ' . $this->randomString(12);
    $page->fillField('edit-title-0-value', $title);
    $page->clickLink('Scheduling options');
    // Set the date using a day and month which could be correctly interpreted
    // either way. Set the year to be next year to ensure a future date.
    // Use a time format which includes 'pm' as this may be necessary, and will
    // be ignored if the time widget wants hh:mm:ss in 24 hours format.
    $page->fillField('edit-publish-on-0-value-date', '05/02/' . (date('Y') + 1));
    $page->fillField('edit-publish-on-0-value-time', '06:00:00pm');
    $page->pressButton('Save');
    $node = $this->drupalGetNodeByTitle($title);
    // If the saved month is 2 then the format is d/m/Y, otherwise it is m/d/Y.
    $this->datepickerFormat = (date('n', $node->publish_on->value) == 2 ? 'd/m/Y' : 'm/d/Y');
  }

  /**
   * Test the default time functionality when scheduling dates are required.
   *
   * @dataProvider dataTimeWhenSchedulingIsRequired()
   */
  public function testTimeWhenSchedulingIsRequired($entityTypeId, $bundle, $field) {
    $config = $this->config('scheduler.settings');

    // This test is only relevant when the configuration allows a date only with
    // a default time specified. Testing with 'allow_date_only' = false is
    // covered in the browser test SchedulerDefaultTimeTest.
    $config->set('allow_date_only', TRUE)->save();

    // Use a default time of 19:30:20 (7:30pm and 20 seconds).
    $default_time = '19:30:20';
    $config->set('default_time', $default_time)->save();

    // Create a DateTime object to hold the scheduling date. This is better than
    // using a raw unix timestamp because it caters for daylight-saving.
    $scheduling_time = new \DateTime();
    $scheduling_time->add(new \DateInterval('P1D'))->setTime(19, 30, 20);

    $titleField = ($entityTypeId == 'media') ? 'name' : 'title';

    foreach ([TRUE, FALSE] as $required) {
      // Set the publish_on/unpublish_on required setting.
      $this->entityTypeObject($entityTypeId)->setThirdPartySetting('scheduler', $field . '_required', $required)->save();

      // Create an entity.
      $this->drupalGet("$entityTypeId/add/$bundle");
      $page = $this->getSession()->getPage();
      $title = ucfirst($field) . ($required ? ' required' : ' not required') . ', datepickerFormat = ' . $this->datepickerFormat;
      $page->fillField("edit-$titleField-0-value", $title);
      $page->clickLink('Scheduling options');
      if ($required) {
        // Fill in the date value but do nothing with the time field.
        $page->fillField('edit-' . $field . '-on-0-value-date', $scheduling_time->format($this->datepickerFormat));
      }
      $page->pressButton('Save');

      // Test that the content has saved properly.
      $this->assertSession()->pageTextContains(sprintf('%s has been created', $title));

      $entity = $this->getEntityByTitle($entityTypeId, $title);
      $this->assertNotEmpty($entity, 'The entity object should be found');
      if ($required) {
        // Check that the scheduled date and time are correct.
        $this->assertEquals($scheduling_time->getTimestamp(), (int) $entity->{$field . '_on'}->value);
      }
      else {
        // Check that no scheduled date was stored.
        $this->assertEmpty($entity->{$field . '_on'}->value);
      }
    }
  }

  /**
   * Provides data for testTimeWhenSchedulingIsRequired().
   *
   * The data in dataStandardEntityTypes() is expanded to test each entity type
   * with each of the scheduler date fields.
   *
   * @return array
   *   Each array item has the values: [entity type id, bundle id, field name].
   */
  public function dataTimeWhenSchedulingIsRequired() {
    $data = [];
    foreach ($this->dataStandardEntityTypes() as $values) {
      $data[] = array_merge($values, ['publish']);
      $data[] = array_merge($values, ['unpublish']);
    }
    return $data;
  }

}
