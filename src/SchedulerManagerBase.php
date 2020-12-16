<?php

namespace Drupal\scheduler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Defines a scheduler manager.
 */
class SchedulerManagerBase {

  use StringTranslationTrait;

  /**
   * Date formatter service object.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Scheduler Logger service object.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Module handler service object.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Entity Type Manager service object.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Entity Type Manager service object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Config Factory service object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a SchedulerManager object.
   */
  public function __construct(DateFormatterInterface $dateFormatter, LoggerInterface $logger, ModuleHandlerInterface $moduleHandler, EntityFieldManagerInterface $entityFieldManager, EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory, EventDispatcherInterface $eventDispatcher, TimeInterface $time) {
    $this->dateFormatter = $dateFormatter;
    $this->logger = $logger;
    $this->moduleHandler = $moduleHandler;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
    $this->eventDispatcher = $eventDispatcher;
    $this->time = $time;
  }

  /**
   * Checks whether a scheduled action on a media entity is allowed.
   *
   * This provides a way for other modules to prevent scheduled publishing or
   * unpublishing, by implementing hook_scheduler_allow_publishing() or
   * hook_scheduler_allow_unpublishing().
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The node on which the action is to be performed.
   * @param string $action
   *   The action that needs to be checked. Can be 'publish' or 'unpublish'.
   *
   * @return bool
   *   TRUE if the action is allowed, FALSE if not.
   *
   * @see hook_scheduler_allow_publishing()
   * @see hook_scheduler_allow_unpublishing()
   */
  public function isAllowed(EntityInterface $entity, $action) {
    // Default to TRUE.
    $result = TRUE;
    // Check that other modules allow the action.
    $hook = 'scheduler_allow_' . $action . 'ing';
    foreach ($this->moduleHandler->getImplementations($hook) as $module) {
      $function = $module . '_' . $hook;
      $result &= $function($entity);
    }

    return $result;
  }

  /**
   * Run the lightweight cron.
   *
   * The Scheduler part of the processing performed here is the same as in the
   * normal Drupal cron run. The difference is that only scheduler_cron() is
   * executed, no other modules hook_cron() functions are called.
   *
   * This function is called from the external crontab job via url
   * /scheduler/cron/{access key} or it can be run interactively from the
   * Scheduler configuration page at /admin/config/content/scheduler/cron.
   * It is also executed when running Scheduler Cron via drush.
   *
   * @param array $options
   *   Options passed from drush command or admin form.
   */
  public function runLightweightCron(array $options = []) {
    // When calling via drush the log messages can be avoided by using --nolog.
    $log = $this->setting('log') && empty($options['nolog']);
    if ($log) {
      if (array_key_exists('nolog', $options)) {
        $trigger = 'drush command';
      }
      elseif (array_key_exists('admin_form', $options)) {
        $trigger = 'admin user form';
      }
      else {
        $trigger = 'url';
      }
      $this->logger->notice('Lightweight cron run activated by @trigger.', ['@trigger' => $trigger]);
    }
    scheduler_cron();
    if (ob_get_level() > 0) {
      $handlers = ob_list_handlers();
      if (isset($handlers[0]) && $handlers[0] == 'default output handler') {
        ob_clean();
      }
    }
    if ($log) {
      $link = Link::fromTextAndUrl($this->t('settings'), Url::fromRoute('scheduler.cron_form'));
      $this->logger->notice('Lightweight cron run completed.', ['link' => $link->toString()]);
    }
  }

  /**
   * Helper method to access the settings of this module.
   *
   * @param string $key
   *   The key of the configuration.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The value of the configuration item requested.
   */
  protected function setting($key) {
    return $this->configFactory->get('scheduler.settings')->get($key);
  }

}
