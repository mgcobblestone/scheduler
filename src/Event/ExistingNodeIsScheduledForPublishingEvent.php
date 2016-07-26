<?php

namespace Drupal\scheduler\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event that is fired when an existing node is updated/saved and it has a
 * scheduled publishing date.
 */
class ExistingNodeIsScheduledForPublishingEvent extends Event {

  const EVENT_NAME = 'scheduler_existing_node_is_scheduled_for_publishing_event';

  /**
   * The node which is being scheduled and saved.
   */
  public $node;

  /**
   * Constructs the object.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node which is being scheduled and saved.
   */
  public function __construct($node) {
    $this->node = $node;
  }

}
