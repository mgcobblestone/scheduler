<?php

/**
 * @file
 * Contains \Drupal\scheduler\Plugin\RulesAction\SetUnpublishingDate.
 */

namespace Drupal\scheduler\Plugin\RulesAction;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\rules\Core\RulesActionBase;
use Drupal\Core\Url;

/**
 * Provides a 'Set date for scheduled unpublishing' action.
 *
 * @RulesAction(
 *   id = "scheduler_set_unpublishing_date_action",
 *   label = @Translation("Set date for scheduled unpublishing"),
 *   category = @Translation("Scheduler"),
 *   context = {
 *     "node" = @ContextDefinition("entity:node",
 *       label = @Translation("Node for scheduling"),
 *       description = @Translation("The node which is to have a scheduled unpublishing date set"),
 *     ),
 *     "date" = @ContextDefinition("integer",
 *       label = @Translation("The date for unpublishing"),
 *       description = @Translation("The date when Scheduler will unpublish the node"),
 *     )
 *   }
 * )
 */
class SetUnpublishingDate extends RulesActionBase {

  /**
   * Set the unpublish_on date for the node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node object to be scheduled for unpublishing.
   * @param int $date
   *   The date for publishing, a unix timestamp integer.
   */
  public function doExecute() {
    $node = $this->getContextValue('node');
    $date = $this->getContextValue('date');
    if ($node->type->entity->getThirdPartySetting('scheduler', 'unpublish_enable', SCHEDULER_DEFAULT_UNPUBLISH_ENABLE)) {
      // When this action is invoked and it operates on the node being editted
      // then hook_node_presave() and hook_node_update() will be executed
      // automatically. But if this action is being used to schedule a different
      // node then we need to call the functions directly here.
      $node->set('unpublish_on', $date)->save;
      scheduler_node_presave($node);
      scheduler_node_update($node);
    }
    else {
      $type_name = node_get_type_label($node);
      \Drupal::logger('scheduler')->warning('Rules: Scheduled unpublishing is not enabled for %type content. To prevent this message add the condition "Scheduled unpublishing is enabled" to your Rule, or enable the Scheduler options via the %type content type settings.', array('%type' => $type_name, 'link' => \Drupal::l(t('@type settings', array('@type' => $type_name)), new Url('entity.node_type.edit_form', ['node_type' => $node->getType()]))));
    }
  }
}