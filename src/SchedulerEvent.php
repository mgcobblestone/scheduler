<?php

namespace Drupal\scheduler;

use Drupal\Core\Entity\EntityInterface;

/**
 * Wraps a scheduler event for event listeners.
 */
class SchedulerEvent extends EventBase {

  /**
   * Gets entity object.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The node object that caused the event to fire.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Sets the node object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   The node object that caused the event to fire.
   */
  public function setEntity(EntityInterface $entity) {
    $this->entity = $entity;
  }

}
