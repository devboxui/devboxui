/**
 * @file
 * JavaScript behaviors for Ajax.
 */

(function ($) {

  'use strict';

  /**
   * Command to render all Turnstile captchas in the page if they are empty.
   */
  Drupal.AjaxCommands.prototype.turnstileRender = function () {
    $('.cf-turnstile').each(function () {
      // Render Turnstile widget again for empty containers.
      if ($(this).is(':empty')) {
        turnstile.render(this);
      }
    });
  };

})(jQuery);
