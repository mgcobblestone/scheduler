<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests entity types which are not enabled for scheduling.
 *
 * @group scheduler
 */
class SchedulerNonEnabledTypeTest extends SchedulerBrowserTestBase {

  /**
   * Tests the publish_enable and unpublish_enable entity type settings.
   *
   * @dataProvider dataNonEnabledType()
   */
  public function testNonEnabledType($id, $entityTypeProperty, $description, $publishing_enabled, $unpublishing_enabled) {
    $this->drupalLogin($this->adminUser);
    $entityType = $this->$entityTypeProperty;
    $type = $entityType->getEntityType()->getBundleOf();
    $bundle = $entityType->id();
    $storage = $this->entityStorageObject($type);
    $titleField = ($type == 'media') ? 'name' : 'title';

    // The 'default' case specifically checks the behavior of the unchanged
    // settings, so only change these when not running the default test.
    if ($description != 'Default') {
      $entityType->setThirdPartySetting('scheduler', 'publish_enable', $publishing_enabled)
        ->setThirdPartySetting('scheduler', 'unpublish_enable', $unpublishing_enabled)
        ->save();
    }

    // When publishing and/or unpublishing are not enabled but the 'required'
    // setting remains on, the entity must be able to be saved without a date.
    $entityType->setThirdPartySetting('scheduler', 'publish_required', !$publishing_enabled)->save();
    $entityType->setThirdPartySetting('scheduler', 'unpublish_required', !$unpublishing_enabled)->save();

    // Allow dates in the past to be valid on saving the entity, to simplify the
    // testing process.
    $entityType->setThirdPartySetting('scheduler', 'publish_past_date', 'schedule')->save();

    // Create a new entity via the add/bundle url, and check that the correct
    // fields are displayed on the form depending on the enabled settings.
    $this->drupalGet("$type/add/$bundle");
    if ($publishing_enabled) {
      $this->assertSession()->fieldExists('publish_on[0][value][date]');
    }
    else {
      $this->assertSession()->fieldNotExists('publish_on[0][value][date]');
    }

    if ($unpublishing_enabled) {
      $this->assertSession()->fieldExists('unpublish_on[0][value][date]');
    }
    else {
      $this->assertSession()->fieldNotExists('unpublish_on[0][value][date]');
    }

    // Fill in the title field and check that the entity can be saved OK.
    $title = $id . 'a - ' . $description;
    $this->submitForm(["{$titleField}[0][value]" => $title], 'Save');
    $string = sprintf('%s %s has been created.', $entityType->get('name'), $title);
    $this->assertSession()->pageTextContains($string);

    // Create an unpublished entity with a publishing date, which mimics what
    // could be done by a third-party module, or a by-product of the entity type
    // being enabled for publishing then being disabled before it got published.
    $title = $id . 'b - ' . $description;
    $values = [
      "$titleField" => $title,
      'status' => FALSE,
      'publish_on' => $this->requestTime - 120,
    ];
    $entity = $this->createEntity($type, $bundle, $values);

    // Check that the entity can be edited and saved OK.
    $this->drupalGet("$type/{$entity->id()}/edit");
    $this->submitForm([], 'Save');
    $string = sprintf('%s %s has been updated.', $entityType->get('name'), $title);
    $this->assertSession()->pageTextContains($string);

    // Run cron and display the dblog.
    $this->cronRun();
    $this->drupalGet('admin/reports/dblog');

    // Reload the entity.
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());
    // Check if the entity has been published or remains unpublished.
    if ($publishing_enabled) {
      $this->assertTrue($entity->isPublished(), "The unpublished entity '$title' should now be published");
    }
    else {
      $this->assertFalse($entity->isPublished(), "The unpublished entity '$title' should remain unpublished");
    }

    // Do the same for unpublishing - create a published entity with an
    // unpublishing date in the future, to be valid for editing and saving.
    $title = $id . 'c - ' . $description;
    $values = [
      "$titleField" => $title,
      'status' => TRUE,
      'unpublish_on' => $this->requestTime + 180,
    ];
    $entity = $this->createEntity($type, $bundle, $values);

    // Check that the entity can be edited and saved.
    $this->drupalGet("$type/{$entity->id()}/edit");
    $this->submitForm([], 'Save');
    $string = sprintf('%s %s has been updated.', $entityType->get('name'), $title);
    $this->assertSession()->pageTextContains($string);

    // Create a published entity with a date in the past, then run cron.
    $title = $id . 'd - ' . $description;
    $values = [
      "$titleField" => $title,
      'status' => TRUE,
      'unpublish_on' => $this->requestTime - 120,
    ];
    $entity = $this->createEntity($type, $bundle, $values);
    $this->cronRun();
    $this->drupalGet('admin/reports/dblog');

    // Reload the entity.
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());
    // Check if the entity has been unpublished or remains published.
    if ($unpublishing_enabled) {
      $this->assertFalse($entity->isPublished(), "The published entity '$title' should now be unpublished");
    }
    else {
      $this->assertTrue($entity->isPublished(), "The published entity '$title' should remain published");
    }

    // Display the full content list and the scheduled list. Calls to these
    // pages are for information and debug only.
    switch ($type) {
      case 'node':
        $this->drupalGet('admin/content');
        $this->drupalGet('admin/content/scheduled');
        break;

      case 'media':
        $this->drupalGet('admin/content/media');
        $this->drupalGet('admin/content/media/scheduled');
        break;
    }
  }

  /**
   * Provides data for testNonEnabledType().
   *
   * @return array
   *   Each item in the test data array has the follow elements:
   *     id                     - (int) a sequential id for use in titles
   *     entityTypePropertyName - (string) the name of the property of the
   *                                       entity type to be tested
   *     description            - (string) describing the scenario being checked
   *     publishing_enabled     - (bool) whether publishing is enabled
   *     unpublishing_enabled   - (bool) whether unpublishing is enabled
   */
  public function dataNonEnabledType() {
    $data = [
      // By default check that the scheduler date fields are not displayed.
      0 => [0, 'nonSchedulerNodeType', 'Default', FALSE, FALSE],

      // Explicitly disable this content type for both settings.
      1 => [1, 'nonSchedulerNodeType', 'Disabling both settings', FALSE, FALSE],

      // Turn on scheduled publishing only.
      2 => [2, 'nonSchedulerNodeType', 'Enabling publishing only', TRUE, FALSE],

      // Turn on scheduled unpublishing only.
      3 => [3, 'nonSchedulerNodeType', 'Enabling unpublishing only', FALSE, TRUE],

      // For completeness turn on both scheduled publishing and unpublishing.
      4 => [4, 'nonSchedulerNodeType', 'Enabling both publishing and unpublishing', TRUE, TRUE],

      // Repeat the above cases for media audio.
      5 => [5, 'nonSchedulerMediaType', 'Default', FALSE, FALSE],
      6 => [6, 'nonSchedulerMediaType', 'Disabling both settings', FALSE, FALSE],
      7 => [7, 'nonSchedulerMediaType', 'Enabling publishing only', TRUE, FALSE],
      8 => [8, 'nonSchedulerMediaType', 'Enabling unpublishing only', FALSE, TRUE],
      9 => [9, 'nonSchedulerMediaType', 'Enabling both publishing and unpublishing', TRUE, TRUE],
    ];

    // Use unset($data[n]) to remove a temporarily unwanted item, use
    // return [$data[n]] to selectively test just one item, or have the default
    // return $data to test everything.
    return $data;

  }

}
