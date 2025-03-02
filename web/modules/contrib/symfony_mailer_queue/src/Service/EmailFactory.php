<?php

namespace Drupal\symfony_mailer_queue\Service;

use Drupal\symfony_mailer\EmailFactory as SymfonyMailerEmailFactory;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer_queue\QueueableEmail;

/**
 * Provides a factory for creating queueable email objects.
 */
class EmailFactory extends SymfonyMailerEmailFactory {

  /**
   * {@inheritdoc}
   */
  public function newTypedEmail(string $type, string $sub_type, ...$params): EmailInterface {
    // @phpstan-ignore-next-line Following parent implementation.
    $email = QueueableEmail::create(\Drupal::getContainer(), $type, $sub_type); // phpcs:ignore
    return $this->initEmail($email, ...$params);
  }

}
