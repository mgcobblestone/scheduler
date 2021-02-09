<?php

namespace Drupal\scheduler_rules_integration\Event;

/**
 * An existing node is scheduled for unpublishing.
 *
 * This event is fired when an existing node is updated/saved and it has a
 * scheduled unpublishing date.
 */
class ExistingNodeIsScheduledForUnpublishingEvent extends NodeEventBase {

  const EVENT_NAME = 'scheduler_existing_node_is_scheduled_for_unpublishing_event';

}
