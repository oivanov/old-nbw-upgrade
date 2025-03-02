/**
 * @file
 * Contains the Message Banner object.
 */

 (function ($, Drupal) {

  "use strict";

  /**
   * A message banner object.
   *
   * @namespace
   */
  Drupal.messageBanner = {
    /**
     * The name of the local storage variable.
     *
     * @type {string}
     */
    localStorageVariable: 'messageBanner.userLastDismissedTime',

    /**
     * Get the last time a user dismissed the banner.
     *
     * @return {int}
     *   The timestamp the banner was dismissed.
     */
    getUserLastDismissedTime: function () {
      var _self = this;
      var userLastDismissedTime = localStorage.getItem(_self.localStorageVariable);
      return parseInt(userLastDismissedTime);
    },

    /**
     * Set the time the user dismissed the banner in local storage.
     */
    setUserLastDismissedTime: function () {
      var _self = this;
      var currentTime = Math.floor(Date.now() / 1000);
      localStorage.setItem(_self.localStorageVariable, currentTime);
    },

    /**
     * Check whether the banner should be displayed to a user.
     *
     * @param {int} bannerLastSavedTime
     *   The time the banner config was last saved.
     * @param {int} userLastDismissedTime
     *   The time the user last dismissed the message.
     *
     * @return {bool}
     *   Whether the banner should show. It will show if there is no
     *   userLastDismissedTime, or if that value is earlier (less) than the
     *   bannerLastSavedTime.
     */
    shouldShowBanner: function (bannerLastSavedTime, userLastDismissedTime) {
      if (isNaN(userLastDismissedTime)) {
        return true;
      }
      else {
        return userLastDismissedTime < bannerLastSavedTime;
      }
    },

    /**
     * Creates a new banner and prepends it to the body.
     *
     * @param {string} bannerMarkup
     *   The rendered HTML markup.
     * @param {string} bannerColor
     *   A hex code or web color.
     * @param {object} context
     *   The DOM context.
     */
    attachBannerToBody: function (bannerMarkup, bannerColor, context) {
      var $messageBanner = $(bannerMarkup);
      var $skipLink = $('.skip-link', context);

      // If there is a skip link, attach the banner after it.
      if ($skipLink.length > 0) {
        $(once('skipLink', $skipLink)).after($messageBanner);
      }
      else {
        $(once('messageBanner', 'body', context)).each(function() {
          $messageBanner.prependTo($('body', context))
        });
      }
    },

    /**
     * Check to see if we have access to local storage.
     *
     * @return {bool}
     *   True if we can access local storage, false otherwise.
     */
    testForLocalStorage: function () {
      var testValue = 'test';
      try {
        localStorage.setItem(testValue, testValue);
        localStorage.removeItem(testValue);
        return true;
      }
      catch (e) {
        return false;
      }
    }
  };
}(jQuery, Drupal));
