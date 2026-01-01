<?php

namespace Drupal\symfony_mailer_queue_test\Plugin\EmailBuilder;

use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;

/**
 * Defines the email builder plug-in for queue test mails.
 *
 * @EmailBuilder(
 *   id = "symfony_mailer_queue",
 *   sub_types = { "test" = @Translation("Test email") },
 *   common_adjusters = {"email_subject", "email_body"},
 * )
 */
class QueueTestEmailBuilder extends EmailBuilderBase {

  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param mixed $to
   *   The to addresses, see Address::convert().
   */
  public function createParams(EmailInterface $email, $to = NULL): void {
    if ($to) {
      // For back-compatibility, allow $to to be NULL.
      $email->setParam('to', $to);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    if ($to = $email->getParam('to')) {
      $email->setTo($to);
    }
  }

}
