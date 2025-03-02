<?php

namespace Drupal\add_content_modal\Controller;

/**
 * @file
 * Controller for Modal
 */

use Drupal\add_content_modal\Form\SettingsForm;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;

class DialogController extends ControllerBase {

  /**
   * Close modal dialog callback.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function closeDialogForm() {
    $typeDialog = $this->config(SettingsForm::SETTINGSNAME)->get('type_of_dialog');

    $response = new AjaxResponse();

    switch ($typeDialog) {
      case 'dialog':
        $response->addCommand(new CloseDialogCommand('#drupal-off-canvas'));

        break;

      default:
        $response->addCommand(new CloseModalDialogCommand());
    }

    return $response;
  }

}
