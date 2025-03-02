/**
 * @file
 * Custom code for loading the Gatsby preview in a new window.
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.gatsby_preview_window = {
    attach: function (context, settings) {
      if (context == document && settings.gatsby.preview_url != undefined) {
        var window_name =
          window.location.host.replaceAll(".", "-") +
          "--" +
          settings.gatsby.entity_type +
          "--" +
          settings.gatsby.entity_id;

        window.open(settings.gatsby.preview_url, window_name);
        $('#edit-moderation-state-0-state').val('draft');
      }
    }
  };

})(jQuery, Drupal);
