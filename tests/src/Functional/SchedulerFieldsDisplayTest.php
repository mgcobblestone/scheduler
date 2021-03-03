<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests the display of the date entry fields (vertical tab, fieldset).
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

    // Give adminUser permission to use field_ui module 'node form display' tab.
    $this->addPermissionsToUser($this->adminUser, [
      'administer node form display',
    ]);
  }

  /**
   * Tests date input is displayed as vertical tab or an expandable fieldset.
   *
   * This test covers scheduler_form_node_form_alter().
   */
  public function testVerticalTabOrFieldset() {
    $this->drupalLogin($this->adminUser);

    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Check that the dates are shown in a vertical tab by default.
    $this->drupalGet('node/add/' . $this->type);
    $assert->elementExists('xpath', '//div[contains(@class, "form-type-vertical-tabs")]//details[@id = "edit-scheduler-settings"]');

    // Check that the dates are shown as a fieldset when configured to do so,
    // and that fieldset is collapsed by default.
    $this->nodetype->setThirdPartySetting('scheduler', 'fields_display_mode', 'fieldset')->save();
    $this->drupalGet('node/add/' . $this->type);
    $assert->elementNotExists('xpath', '//div[contains(@class, "form-type-vertical-tabs")]//details[@id = "edit-scheduler-settings"]');
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings" and not(@open = "open")]');

    // Check that the fieldset is expanded if either of the scheduling dates
    // are required.
    $this->nodetype->setThirdPartySetting('scheduler', 'publish_required', TRUE)->save();
    $this->drupalGet('node/add/' . $this->type);
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings" and @open = "open"]');

    $this->nodetype->setThirdPartySetting('scheduler', 'publish_required', FALSE)
      ->setThirdPartySetting('scheduler', 'unpublish_required', TRUE)->save();
    $this->drupalGet('node/add/' . $this->type);
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings" and @open = "open"]');

    // Check that the fieldset is expanded if the 'always' option is set.
    $this->nodetype->setThirdPartySetting('scheduler', 'publish_required', FALSE)
      ->setThirdPartySetting('scheduler', 'unpublish_required', FALSE)
      ->setThirdPartySetting('scheduler', 'expand_fieldset', 'always')->save();
    $this->drupalGet('node/add/' . $this->type);
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings" and @open = "open"]');

    // Check that the fieldset is expanded if the node already has a publish-on
    // date. This requires editing an existing scheduled node.
    $this->nodetype->setThirdPartySetting('scheduler', 'expand_fieldset', 'when_required')->save();
    $options = [
      'title' => 'Contains Publish-on date ' . $this->randomMachineName(10),
      'type' => $this->type,
      'publish_on' => strtotime('+1 day'),
    ];
    $node = $this->drupalCreateNode($options);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings" and @open = "open"]');

    // Repeat the check with a timestamp value of zero. This is a valid date
    // so the fieldset should be opened. It will not be used much on real sites
    // but can occur when testing Rules which fail to set the date correctly and
    // we get zero. Debugging Rules is easier if the fieldset opens as expected.
    $options = [
      'title' => 'Contains Publish-on date with timestamp value zero - ' . $this->randomMachineName(10),
      'type' => $this->type,
      'publish_on' => 0,
    ];
    $node = $this->drupalCreateNode($options);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings" and @open = "open"]');

    // Check that the fieldset is expanded if the node has an unpublish-on date.
    $options = [
      'title' => 'Contains Unpublish-on date ' . $this->randomMachineName(10),
      'type' => $this->type,
      'unpublish_on' => strtotime('+1 day'),
    ];
    $node = $this->drupalCreateNode($options);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings" and @open = "open"]');

    // Repeat with a timestamp value of zero.
    $options = [
      'title' => 'Contains Unpublish-on date with timestamp value zero - ' . $this->randomMachineName(10),
      'type' => $this->type,
      'unpublish_on' => 0,
    ];
    $node = $this->drupalCreateNode($options);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings" and @open = "open"]');

    // Check that the display reverts to a vertical tab again when specifically
    // configured to do so.
    $this->nodetype->setThirdPartySetting('scheduler', 'fields_display_mode', 'vertical_tab')->save();
    $this->drupalGet('node/add/' . $this->type);
    $assert->elementExists('xpath', '//div[contains(@class, "form-type-vertical-tabs")]//details[@id = "edit-scheduler-settings"]');
  }

  /**
   * Tests the settings entry in the content type form display.
   *
   * This test covers scheduler_entity_extra_field_info().
   */
  public function testManageFormDisplay() {
    $this->drupalLogin($this->adminUser);

    // Check that the weight input field is displayed when the content type is
    // enabled for scheduling. This field still exists even with tabledrag on.
    $this->drupalGet('admin/structure/types/manage/' . $this->type . '/form-display');
    $this->assertSession()->fieldExists('edit-fields-scheduler-settings-weight');

    // Check that the weight input field is not displayed when the content type
    // is not enabled for scheduling.
    $this->nodetype->setThirdPartySetting('scheduler', 'publish_enable', FALSE)
      ->setThirdPartySetting('scheduler', 'unpublish_enable', FALSE)->save();
    $this->drupalGet('admin/structure/types/manage/' . $this->type . '/form-display');
    $this->assertSession()->FieldNotExists('edit-fields-scheduler-settings-weight');
  }

  /**
   * Tests the edit form when scheduler fields have been disabled.
   *
   * This test covers scheduler_form_node_form_alter().
   */
  public function testDisabledFields() {
    $this->drupalLogin($this->adminUser);

    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // 1. Set the publish_on field to 'hidden' in the node edit form.
    $edit = [
      'fields[publish_on][region]' => 'hidden',
    ];
    $this->drupalPostForm('admin/structure/types/manage/' . $this->type . '/form-display', $edit, 'Save');

    // Check that a scheduler vertical tab is displayed.
    $this->drupalGet('node/add/' . $this->type);
    $assert->elementExists('xpath', '//div[contains(@class, "form-type-vertical-tabs")]//details[@id = "edit-scheduler-settings"]');
    // Check the publish_on field is not shown, but the unpublish_on field is.
    $this->assertSession()->FieldNotExists('publish_on[0][value][date]');
    $this->assertSession()->FieldExists('unpublish_on[0][value][date]');

    // 2. Set publish_on to be displayed but hide the unpublish_on field.
    $edit = [
      'fields[publish_on][region]' => 'content',
      'fields[unpublish_on][region]' => 'hidden',
    ];
    $this->drupalPostForm('admin/structure/types/manage/' . $this->type . '/form-display', $edit, 'Save');

    // Check that a scheduler vertical tab is displayed.
    $this->drupalGet('node/add/' . $this->type);
    $assert->elementExists('xpath', '//div[contains(@class, "form-type-vertical-tabs")]//details[@id = "edit-scheduler-settings"]');
    // Check the publish_on field is not shown, but the unpublish_on field is.
    $this->assertSession()->FieldExists('publish_on[0][value][date]');
    $this->assertSession()->FieldNotExists('unpublish_on[0][value][date]');

    // 3. Set both fields to be hidden.
    $edit = [
      'fields[publish_on][region]' => 'hidden',
      'fields[unpublish_on][region]' => 'hidden',
    ];
    $this->drupalPostForm('admin/structure/types/manage/' . $this->type . '/form-display', $edit, 'Save');

    // Check that no vertical tab is displayed.
    $this->drupalGet('node/add/' . $this->type);
    $assert->elementNotExists('xpath', '//div[contains(@class, "form-type-vertical-tabs")]//details[@id = "edit-scheduler-settings"]');
    // Check the neither field is displayed.
    $this->assertSession()->FieldNotExists('publish_on[0][value][date]');
    $this->assertSession()->FieldNotExists('unpublish_on[0][value][date]');
  }

}
