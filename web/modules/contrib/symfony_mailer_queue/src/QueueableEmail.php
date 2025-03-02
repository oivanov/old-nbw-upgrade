<?php

namespace Drupal\symfony_mailer_queue;

use Drupal\symfony_mailer\Email;

/**
 * Defines the email class for queueable emails.
 */
class QueueableEmail extends Email implements QueueableEmailInterface {

  /**
   * Whether the email is in the queue or not.
   *
   * @var bool
   */
  protected $isInQueue = FALSE;

  /**
   * {@inheritdoc}
   */
  public function markInQueue(): void {
    $this->isInQueue = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isInQueue(): bool {
    return $this->isInQueue;
  }

}
