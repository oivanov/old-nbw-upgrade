<?php

/**
 * @file
 * Hooks provided by the Message Banner module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the colors available for message banners.
 *
 * The module provides a list of default colors but these can be extended with
 * brand colors or similar using this hook. Colors should be defined in the
 * format:
 *
 * @code
 *   $colors = [
 *     'badass' => t('A badass green'),
 *   ];
 * @endcode
 *
 * Where the key is a string that can be used as a class, and the value is a
 * human-readable option.
 *
 * This will add the class to the message banner, which can be used to style the
 * banner appropriately for your theme.
 *
 * @param array $colors
 *   The default color list.
 *
 * @ingroup message_banner
 */
function hook_message_banner_colors_alter(array &$colors) {
  // Define a new color.
  $colors['badass'] = t('A badass green');

  // Replace an existing color.
  unset($colors['default--white']);
  $colors['overridden-white'] = t('White');
}

/**
 * @} End of "addtogroup hooks".
 */
