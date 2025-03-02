<?php

namespace Drupal\add_content_modal\Controller;

/**
 * @file
 * Controller for Modal
 */

Use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;

class ModalController extends ControllerBase {

  /**
   * Close modal dialog callback.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function closeModalForm(){
    $command = new CloseModalDialogCommand();
    $response = new AjaxResponse();
    $response->addCommand($command);
    return $response;
  }

}
