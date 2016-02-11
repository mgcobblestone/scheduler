<?php
/**
 * @file
 * Contains \Drupal\scheduler\Tests\SchedulerPastDatesTest.
 */

namespace Drupal\scheduler\Tests;

use Drupal\Component\Utility\SafeMarkup;

/**
 * Tests the options and processing when dates are entered in the past.
 *
 * @group scheduler
 */
class SchedulerPastDatesTest extends SchedulerTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
  }

  /**
   * Test the different options for past publication dates.
   */
  public function testSchedulerPastDates() {
    /** @var EntityStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Log in.
    $this->drupalLogin($this->adminUser);

    // Create an unpublished page node.
    $node = $this->drupalCreateNode(['type' => $this->nodetype->get('type'), 'status' => FALSE]);

    // Test the default behavior: an error message should be shown when the user
    // enters a publication date that is in the past.
    $edit = [
      'title[0][value]' => t('Past') . ' ' . $this->randomString(10),
      'publish_on[0][value][date]' => \Drupal::service('date.formatter')->format(strtotime('-1 day'), 'custom', 'Y-m-d'), ### @TODO should use default date part from config, not hardcode
      'publish_on[0][value][time]' => \Drupal::service('date.formatter')->format(strtotime('-1 day'), 'custom', 'H:i:s'), ### @TODO should use default time part from config, not hardcode
    ];
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, t('Save and publish'));
    $this->assertRaw(t("The 'publish on' date must be in the future"), 'An error message is shown when the publication date is in the past and the "error" behavior is chosen.');

    // Test the 'publish' behavior: the node should be published immediately.
    $this->nodetype->setThirdPartySetting('scheduler', 'publish_past_date', 'publish')->save();
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, t('Save and publish'));
    $this->assertNoRaw(t("The 'publish on' date must be in the future"), 'No error message is shown when the publication date is in the past and the "publish" behavior is chosen.');
    $this->assertRaw(t('@type %title has been updated.', ['@type' => t('Basic page'), '%title' => SafeMarkup::checkPlain($edit['title[0][value]'])]), 'The node is saved successfully when the publication date is in the past and the "publish" behavior is chosen.');

    // Reload the changed node and check that it is published.
    $node_storage->resetCache([$node->id()]);

    /** @var NodeInterface $node */
    $node = $node_storage->load($node->id());
    $this->assertTrue($node->isPublished(), 'The node has been published immediately when the publication date is in the past and the "publish" behavior is chosen.');
    $this->assertNull($node->publish_on->value, 'The node publish_on date has been removed after publishing when the "publish" behavior is chosen.');

    // Test the 'schedule' behavior: the node should be unpublished and become
    // published on the next cron run.
    $this->nodetype->setThirdPartySetting('scheduler', 'publish_past_date', 'schedule')->save();
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, t('Save and keep published'));
    $publish_time = $edit['publish_on[0][value][date]'] . ' ' . $edit['publish_on[0][value][time]']; ### @TODO should use date format from config
    $this->assertNoRaw(t("The 'publish on' date must be in the future"), 'No error message is shown when the publication date is in the past and the "schedule" behavior is chosen.');
    $this->assertRaw(t('@type %title has been updated.', ['@type' => t('Basic page'), '%title' => SafeMarkup::checkPlain($edit['title[0][value]'])]), 'The node is saved successfully when the publication date is in the past and the "schedule" behavior is chosen.');
    $this->assertRaw(t('This post is unpublished and will be published @publish_time.', ['@publish_time' => $publish_time]), 'The node is scheduled to be published when the publication date is in the past and the "schedule" behavior is chosen.');

    // Reload the node and check that it is unpublished but scheduled correctly.
    $node_storage->resetCache([$node->id()]);
    $node = $node_storage->load($node->id());
    $this->assertFalse($node->isPublished(), 'The node has been unpublished when the publication date is in the past and the "schedule" behavior is chosen.');
    $this->assertEqual(\Drupal::service('date.formatter')->format($node->publish_on->value, 'custom', 'Y-m-d H:i:s'), $publish_time, 'The node is scheduled for the required date');

    // Simulate a cron run and check that the node is published.
    scheduler_cron();
    $node_storage->resetCache([$node->id()]);
    $node = $node_storage->load($node->id());
    $this->assertTrue($node->isPublished(), 'The node with publication date in the past and the "schedule" behavior has now been published by cron.');

    // Check that an Unpublish date in the past fails validation.
    $edit = [
      'title[0][value]' => t('Unpublish in the past') . ' ' . $this->randomString(10),
      'unpublish_on[0][value][date]' => \Drupal::service('date.formatter')->format(REQUEST_TIME - 3600, 'custom', 'Y-m-d'),
      'unpublish_on[0][value][time]' => \Drupal::service('date.formatter')->format(REQUEST_TIME - 3600, 'custom', 'H:i:s'),
    ];
    $this->drupalPostForm('node/add/page', $edit, t('Save and publish'));
    $this->assertRaw(t("The 'unpublish on' date must be in the future"), 'An error message is shown when the unpublish date is in the past.');
  }
}
