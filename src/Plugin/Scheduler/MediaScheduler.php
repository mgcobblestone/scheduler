<?php

namespace Drupal\scheduler\Plugin\Scheduler;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\media\Entity\MediaType;
use Drupal\scheduler\SchedulerPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Node.
 *
 * @package Drupal\Scheduler\Plugin\Scheduler
 *
 * @SchedulerPlugin(
 *  id = "media_scheduler",
 *  label = @Translation("Media Scheduler Plugin"),
 *  entityType = "media",
 *  typeFieldName = "bundle",
 * )
 */
class MediaScheduler extends SchedulerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Create mothod.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation')
    );
  }

  /**
   * Publish pending media entities.
   */
  public function publish() {
    // @todo - this will likely be handled in the ScheduleManager service.
  }

  /**
   * Unpublish pending media entities.
   */
  public function unpublish() {
    // @todo - this will likely be handled in the ScheduleManager service.
  }

  /**
   * Get the available bundles for the entity type.
   *
   * @return array
   *   The list of bundles.
   */
  public function getTypes() {
    if (!\Drupal::moduleHandler()->moduleExists('media')) {
      return [];
    }
    return MediaType::loadMultiple();
  }

  /**
   * Get the form IDs for entity add/edit forms.
   *
   * @return array
   *   The list of form IDs.
   */
  public function entityFormIds() {

    if (!\Drupal::moduleHandler()->moduleExists('media')) {
      return [];
    }

    $ids = [
      'media_add_form',
      'media_edit_form',
    ];
    $types = MediaType::loadMultiple();
    /** @var \Drupal\media\Entity\MediaType $type */
    foreach ($types as $type) {
      $ids[] = 'media_' . $type->id() . '_form';
      $ids[] = 'media_' . $type->id() . '_add_form';
      $ids[] = 'media_' . $type->id() . '_edit_form';
    }
    return $ids;
  }

  /**
   * Get the list of entity type form IDs for media.
   *
   * @return array
   *   The list of form IDs.
   */
  public function entityTypeFormIds() {

    if (!\Drupal::moduleHandler()->moduleExists('media')) {
      return [];
    }

    return [
      'media_type_add_form',
      'media_type_edit_form',
    ];
  }

  /**
   * Get the bundle name for $media.
   *
   * @param \Drupal\Core\Entity\EntityInterface $media
   *   The media.
   *
   * @return string
   *   The bundle.
   */
  public function getEntityType(EntityInterface $media) {
    if (!\Drupal::moduleHandler()->moduleExists('media')) {
      return NULL;
    }

    $bundle = $media->bundle();
    return MediaType::load($bundle);
  }

  /**
   * Get list of enabled bundles for $action ([un]publish).
   *
   * @param string $action
   *   The action - publish|unpublish.
   *
   * @return array
   *   The list of bundles.
   */
  public function getEnabledTypes($action) {
    $config = \Drupal::config('scheduler.settings');
    $types = $this->getTypes();
    return array_filter($types, function ($bundle) use ($action, $config) {
      /** @var EntityTypeInterface $bundle */
      return $bundle->getThirdPartySetting('scheduler', $action . '_enable', $config->get('default_' . $action . '_enable'));
    });
  }

  /**
   * Gather IDs for all media that need to be $action'ed.
   *
   * Modules can implement hook_scheduler_media_id_list($action) and return an array
   * of media ids which will be added to the existing list.
   *
   * @param string $action
   *   The action being performed, either "publish" or "unpublish".
   *
   * @return array
   *   An array of node ids.
   */
  public function idList($action) {
    $ids = [];

    foreach (\Drupal::moduleHandler()->getImplementations('scheduler_media_id_list') as $module) {
      $function = $module . '_scheduler_media_id_list';
      $ids = array_merge($ids, $function($action));
    }

    return $ids;
  }
}
