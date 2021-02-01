<?php

namespace Drupal\Tests\scheduler\Functional;

use Drupal\Tests\scheduler\Traits\SchedulerMediaSetupTrait;

/**
 * Tests the validation when editing a node.
 *
 * @group scheduler
 */
class SchedulerValidationTest extends SchedulerBrowserTestBase {

  use SchedulerMediaSetupTrait;

  /**
   * Additional modules required.
   *
   * @var array
   */
  protected static $modules = ['media'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Run the setup routine for Media entities.
    $this->SchedulerMediaSetup();
  }

  /**
   * Tests the validation when editing a node.
   *
   * The 'required' checks and 'dates in the past' checks are handled in other
   * tests. This test checks validation when the two fields interact, and covers
   * the error message text stored in the following constraint variables:
   *   $messageUnpublishOnRequiredIfPublishOnEntered
   *   $messageUnpublishOnRequiredIfPublishing
   *   $messageUnpublishOnTooEarly.
   *
   * @dataProvider dataValidationDuringEdit()
   */
  public function testValidationDuringEdit($entityType, $bundle) {
    $this->drupalLogin($this->adminUser);

    // Set unpublishing to be required for this entity type.
    $this->entityTypeObject($entityType)->setThirdPartySetting('scheduler', 'unpublish_required', TRUE)->save();

    // Create an unpublished entity.
    $entity = $this->createEntity($entityType, $bundle, ['status' => FALSE]);

    // Edit the unpublished entity and try to save a publish-on date.
    $edit = [
      'publish_on[0][value][date]' => date('Y-m-d', strtotime('+1 day', $this->requestTime)),
      'publish_on[0][value][time]' => date('H:i:s', strtotime('+1 day', $this->requestTime)),
    ];
    $this->drupalGet("$entityType/{$entity->id()}/edit");
    $this->submitForm($edit, 'Save');
    // Check that validation prevents entering a publish-on date with no
    // unpublish-on date if unpublishing is required.
    $this->assertSession()->pageTextContains("If you set a 'publish on' date then you must also set an 'unpublish on' date.");
    $this->assertSession()->pageTextNotContains(sprintf('%s %s has been updated.', $bundle, $entity->label()));

    // Create an unpublished entity.
    $entity = $this->createEntity($entityType, $bundle, ['status' => FALSE]);

    // Edit the unpublished entity and try to change the status to 'published'.
    $edit = ['status[value]' => TRUE];
    $this->drupalPostForm("$entityType/{$entity->id()}/edit", $edit, 'Save');
    // Check that validation prevents publishing the entity directly without an
    // unpublish-on date if unpublishing is required.
    $this->assertSession()->pageTextContains("Either you must set an 'unpublish on' date or save this node as unpublished.");
    $this->assertSession()->pageTextNotContains(sprintf('%s %s has been updated.', $bundle, $entity->label()));

    // Create an unpublished entity, and try to edit and save with a publish-on
    // date later than the unpublish-on date.
    $entity = $this->createEntity($entityType, $bundle, ['status' => FALSE]);
    $edit = [
      'publish_on[0][value][date]' => $this->dateFormatter->format($this->requestTime + 7200, 'custom', 'Y-m-d'),
      'publish_on[0][value][time]' => $this->dateFormatter->format($this->requestTime + 7200, 'custom', 'H:i:s'),
      'unpublish_on[0][value][date]' => $this->dateFormatter->format($this->requestTime + 1800, 'custom', 'Y-m-d'),
      'unpublish_on[0][value][time]' => $this->dateFormatter->format($this->requestTime + 1800, 'custom', 'H:i:s'),
    ];
    $this->drupalPostForm("$entityType/{$entity->id()}/edit", $edit, 'Save');
    // Check that validation prevents entering an unpublish-on date which is
    // earlier than the publish-on date.
    $this->assertSession()->pageTextContains("The 'unpublish on' date must be later than the 'publish on' date.");
    $this->assertSession()->pageTextNotContains(sprintf('%s %s has been updated.', $bundle, $entity->label()));
  }

  /**
   * Provides data for testDevelGenerate().
   *
   * @return array
   *   Each array item has the values: [entity type, bundle id].
   */
  public function dataValidationDuringEdit() {
    // The data provider does not have access to $this so we have to hard-code
    // the entity bundle id.
    $data = [
      'Content' => ['node', 'testpage'],
      'Media' => ['media', 'test_media_image'],
    ];
    return $data;
  }

}
