/**
 * @file
 * JQuery to set default time for Scheduler DateTime Widget.
 */

(function ($, drupalSettings) {

  'use strict';

  /**
   * Provide default time if schedulerDefaultTime is set.
   *
   * schedulerDefaultTime is defined in _scheduler_entity_form_alter when the
   * user is allowed to enter just a date. The value needs to be pre-filled here
   * to avoid the browser validation 'please fill in this field' pop-up error
   * which is produced before the date widget valueCallback() can set the value.
   * @see https://www.drupal.org/project/scheduler/issues/2913829
   */
  Drupal.behaviors.setSchedulerDefaultTime = {
    attach: function (context) {

      // Drupal.behaviors are called many times per page. Using .once() adds the
      // class onto the matched DOM element and uses this to prevent it running
      // on subsequent calls.
      const $default_time = $(context)
        .find('#edit-scheduler-settings')
        .once('default-time-done');

      if ($default_time.length && typeof drupalSettings.schedulerDefaultTime !== "undefined") {
        var operations = ["publish", "unpublish"];
        operations.forEach(function (value) {
          var element = $("input#edit-" + value + "-on-0-value-time", context);
          // Only set the time when there is no value and the field is required.
          if (element.val() === "" && element.prop("required")) {
            element.val(drupalSettings.schedulerDefaultTime);
          }
        });
      }
    }
  };
})(jQuery, drupalSettings);
