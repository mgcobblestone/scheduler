<?php

namespace Drupal\Tests\scheduler\Functional;

use Drupal\Core\Url;

/**
 * Tests the display of date entry fields and form elements.
 *
 * @group scheduler
 */
class SchedulerFieldsDisplayTest extends SchedulerBrowserTestBase {

  /**
   * Additional module field_ui is required for the 'manage form display' test.
   *
   * @var array
   */
  protected static $modules = ['field_ui'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Give adminUser the permissions to use the field_ui 'manage form display'
    // tab for each entity type being tested.
    $this->addPermissionsToUser($this->adminUser, [
      'administer node form display',
      'administer media form display',
    ]);
  }

  /**
   * Tests date input is displayed as vertical tab or an expandable fieldset.
   *
   * This test covers _scheduler_entity_form_alter().
   *
   * @dataProvider dataStandardEntityTypes()
   */
  public function testVerticalTabOrFieldset($entityTypeId, $bundle) {
    $this->drupalLogin($this->adminUser);
    $entityType = $this->entityTypeObject($entityTypeId, $bundle);

    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Check that the dates are shown in a vertical tab by default.
    $this->drupalGet("$entityTypeId/add/$bundle");
    $assert->elementExists('xpath', '//div[contains(@class, "form-type-vertical-tabs")]//details[@id = "edit-scheduler-settings"]');

    // Check that the dates are shown as a fieldset when configured to do so,
    // and that fieldset is collapsed by default.
    $entityType->setThirdPartySetting('scheduler', 'fields_display_mode', 'fieldset')->save();
    $this->drupalGet("$entityTypeId/add/$bundle");
    $assert->elementNotExists('xpath', '//div[contains(@class, "form-type-vertical-tabs")]//details[@id = "edit-scheduler-settings"]');
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings" and not(@open = "open")]');

    // Check that the fieldset is expanded if either of the scheduling dates
    // are required.
    $entityType->setThirdPartySetting('scheduler', 'publish_required', TRUE)->save();
    $this->drupalGet("$entityTypeId/add/$bundle");
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings" and @open = "open"]');

    $entityType->setThirdPartySetting('scheduler', 'publish_required', FALSE)
      ->setThirdPartySetting('scheduler', 'unpublish_required', TRUE)->save();
    $this->drupalGet("$entityTypeId/add/$bundle");
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings" and @open = "open"]');

    // Check that the fieldset is expanded if the 'always' option is set.
    $entityType->setThirdPartySetting('scheduler', 'publish_required', FALSE)
      ->setThirdPartySetting('scheduler', 'unpublish_required', FALSE)
      ->setThirdPartySetting('scheduler', 'expand_fieldset', 'always')->save();
    $this->drupalGet("$entityTypeId/add/$bundle");
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings" and @open = "open"]');

    // Check that the fieldset is expanded if the entity already has a
    // publish-on date. This requires editing an existing scheduled entity.
    $entityType->setThirdPartySetting('scheduler', 'expand_fieldset', 'when_required')->save();
    $options = [
      'title' => 'Contains Publish-on date ' . $this->randomMachineName(10),
      'publish_on' => strtotime('+1 day'),
    ];
    $entity = $this->createEntity($entityTypeId, $bundle, $options);
    $this->drupalGet("$entityTypeId/{$entity->id()}/edit");
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings" and @open = "open"]');

    // Repeat the check with a timestamp value of zero. This is a valid date
    // so the fieldset should be opened. It will not be used much on real sites
    // but can occur when testing Rules which fail to set the date correctly and
    // we get zero. Debugging Rules is easier if the fieldset opens as expected.
    $options = [
      'title' => 'Contains Publish-on date with timestamp value zero - ' . $this->randomMachineName(10),
      'publish_on' => 0,
    ];
    $entity = $this->createEntity($entityTypeId, $bundle, $options);
    $this->drupalGet("$entityTypeId/{$entity->id()}/edit");
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings" and @open = "open"]');

    // Check that the fieldset is expanded if there is an unpublish-on date.
    $options = [
      'title' => 'Contains Unpublish-on date ' . $this->randomMachineName(10),
      'unpublish_on' => strtotime('+1 day'),
    ];
    $entity = $this->createEntity($entityTypeId, $bundle, $options);
    $this->drupalGet("$entityTypeId/{$entity->id()}/edit");
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings" and @open = "open"]');

    // Repeat with a timestamp value of zero.
    $options = [
      'title' => 'Contains Unpublish-on date with timestamp value zero - ' . $this->randomMachineName(10),
      'unpublish_on' => 0,
    ];
    $entity = $this->createEntity($entityTypeId, $bundle, $options);
    $this->drupalGet("$entityTypeId/{$entity->id()}/edit");
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings" and @open = "open"]');

    // Check that the display reverts to a vertical tab again when specifically
    // configured to do so.
    $entityType->setThirdPartySetting('scheduler', 'fields_display_mode', 'vertical_tab')->save();
    $this->drupalGet("$entityTypeId/add/$bundle");
    $assert->elementExists('xpath', '//div[contains(@class, "form-type-vertical-tabs")]//details[@id = "edit-scheduler-settings"]');
  }

  /**
   * Tests the settings entry in the content type form display.
   *
   * This test covers scheduler_entity_extra_field_info().
   *
   * @dataProvider dataStandardEntityTypes()
   */
  public function testManageFormDisplay($entityTypeId, $bundle) {
    $this->drupalLogin($this->adminUser);
    $entityType = $this->entityTypeObject($entityTypeId, $bundle);

    // Check that the weight input field is displayed when the entity bundle is
    // enabled for scheduling. This field still exists even with tabledrag on.
    $form_display_url = Url::fromRoute("entity.entity_form_display.{$entityTypeId}.default", [$entityType->getEntityTypeId() => $bundle]);
    $this->drupalGet($form_display_url);
    $this->assertSession()->fieldExists('edit-fields-scheduler-settings-weight');

    // Check that the weight input field is not displayed when the entity bundle
    // is not enabled for scheduling.
    $this->entityTypeObject($entityTypeId, $bundle)
      ->setThirdPartySetting('scheduler', 'publish_enable', FALSE)
      ->setThirdPartySetting('scheduler', 'unpublish_enable', FALSE)->save();
    $this->drupalGet($form_display_url);
    $this->assertSession()->pageTextContains('Manage form display');
    $this->assertSession()->FieldNotExists('edit-fields-scheduler-settings-weight');
  }

  /**
   * Tests the edit form when scheduler fields have been disabled.
   *
   * This test covers _scheduler_entity_type_form_alter().
   *
   * @dataProvider dataStandardEntityTypes()
   */
  public function testDisabledFields($entityTypeId, $bundle) {
    $this->drupalLogin($this->adminUser);
    $entityType = $this->entityTypeObject($entityTypeId, $bundle);

    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // 1. Set the publish_on field to 'hidden' in the entity edit form.
    $edit = [
      'fields[publish_on][region]' => 'hidden',
    ];
    $form_display_url = Url::fromRoute("entity.entity_form_display.{$entityTypeId}.default", [$entityType->getEntityTypeId() => $bundle]);
    $this->drupalGet($form_display_url);
    $this->submitForm($edit, 'Save');

    // Check that a scheduler vertical tab is displayed.
    $this->drupalGet("$entityTypeId/add/$bundle");
    $assert->elementExists('xpath', '//div[contains(@class, "form-type-vertical-tabs")]//details[@id = "edit-scheduler-settings"]');
    // Check the publish_on field is not shown, but the unpublish_on field is.
    $this->assertSession()->FieldNotExists('publish_on[0][value][date]');
    $this->assertSession()->FieldExists('unpublish_on[0][value][date]');

    // 2. Set publish_on to be displayed but hide the unpublish_on field.
    $edit = [
      'fields[publish_on][region]' => 'content',
      'fields[unpublish_on][region]' => 'hidden',
    ];
    $this->drupalGet($form_display_url);
    $this->submitForm($edit, 'Save');

    // Check that a scheduler vertical tab is displayed.
    $this->drupalGet("$entityTypeId/add/$bundle");
    $assert->elementExists('xpath', '//div[contains(@class, "form-type-vertical-tabs")]//details[@id = "edit-scheduler-settings"]');
    // Check the publish_on field is not shown, but the unpublish_on field is.
    $this->assertSession()->FieldExists('publish_on[0][value][date]');
    $this->assertSession()->FieldNotExists('unpublish_on[0][value][date]');

    // 3. Set both fields to be hidden.
    $edit = [
      'fields[publish_on][region]' => 'hidden',
      'fields[unpublish_on][region]' => 'hidden',
    ];
    $this->drupalGet($form_display_url);
    $this->submitForm($edit, 'Save');

    // Check that no vertical tab is displayed.
    $this->drupalGet("$entityTypeId/add/$bundle");
    $assert->elementNotExists('xpath', '//div[contains(@class, "form-type-vertical-tabs")]//details[@id = "edit-scheduler-settings"]');
    // Check the neither field is displayed.
    $this->assertSession()->FieldNotExists('publish_on[0][value][date]');
    $this->assertSession()->FieldNotExists('unpublish_on[0][value][date]');
  }

  /**
   * Test the option to hide the seconds on the time input fields.
   */
  public function testHideSeconds() {
    $this->drupalLogin($this->schedulerUser);
    $config = $this->config('scheduler.settings');

    // Check that the default is to show the seconds on the input fields.
    $this->drupalGet('node/add/' . $this->type);
    $publish_time_field = $this->xpath('//input[@id="edit-publish-on-0-value-time"]');
    $unpublish_time_field = $this->xpath('//input[@id="edit-unpublish-on-0-value-time"]');
    $this->assertEquals(1, $publish_time_field[0]->getAttribute('step'), 'The input time step for publish-on is 1, so the seconds will be visible and usable.');
    $this->assertEquals(1, $unpublish_time_field[0]->getAttribute('step'), 'The input time step for unpublish-on is 1, so the seconds will be visible and usable.');

    // Set the config option to hide the seconds and thus set the input fields
    // to the granularity of one minute.
    $config->set('hide_seconds', TRUE)->save();

    // Get the node-add page and check the input fields.
    $this->drupalGet('node/add/' . $this->type);
    $publish_time_field = $this->xpath('//input[@id="edit-publish-on-0-value-time"]');
    $unpublish_time_field = $this->xpath('//input[@id="edit-unpublish-on-0-value-time"]');
    $this->assertEquals(60, $publish_time_field[0]->getAttribute('step'), 'The input time step for publish-on is 60, so the seconds will be hidden and not usable.');
    $this->assertEquals(60, $unpublish_time_field[0]->getAttribute('step'), 'The input time step for unpublish-on is 60, so the seconds will be hidden and not usable.');
    // @todo How can we check that the seconds element is not shown?

    // Save with both dates entered, including seconds in the times.
    $edit = [
      'title[0][value]' => 'Hide the seconds',
      'body[0][value]' => $this->randomString(30),
      'publish_on[0][value][date]' => date('Y-m-d', strtotime('+1 day', $this->requestTime)),
      'publish_on[0][value][time]' => '01:02:03',
      'unpublish_on[0][value][date]' => date('Y-m-d', strtotime('+1 day', $this->requestTime)),
      'unpublish_on[0][value][time]' => '04:05:06',
    ];
    $this->submitForm($edit, 'Save');
    $node = $this->drupalGetNodeByTitle('Hide the seconds');

    // Edit and check that the seconds have been set to zero.
    $this->drupalGet("node/{$node->id()}/edit");
    $this->assertSession()->FieldValueEquals('publish_on[0][value][time]', '01:02:00');
    $this->assertSession()->FieldValueEquals('unpublish_on[0][value][time]', '04:05:00');

  }

}
