<?php

namespace Drupal\symfony_mailer_queue;

/**
 * Interface for queueable emails.
 */
interface QueueableEmailInterface {

  /**
   * Sets the queued flag for the email.
   */
  public function markInQueue(): void;

  /**
   * Gets whether the email is in the queue or not.
   */
  public function isInQueue(): bool;

}
