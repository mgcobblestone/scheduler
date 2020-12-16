<?php

namespace Drupal\scheduler;


/**
 * Provides an interface for scheduler managers.
 */
interface SchedulerManagerInterface {

  /**
   * Publish scheduled entity.
   *
   * @return bool
   *   TRUE if any entity has been published, FALSE otherwise.
   */
  public function publish();

  /**
   * Unpublish scheduled entity.
   *
   * @return bool
   *   TRUE if any entity has been unpublished, FALSE otherwise.
   */
  public function unpublish();

}
