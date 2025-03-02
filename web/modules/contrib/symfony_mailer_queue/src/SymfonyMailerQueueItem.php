<?php

namespace Drupal\symfony_mailer_queue;

/**
 * Provides a data transfer object for Symfony mailer queue items.
 */
class SymfonyMailerQueueItem {

  /**
   * A unique identifier for this queue item.
   *
   * The unique identifier is necessary to make this queue item identifiable
   * after serialization. Unfortunately, the queue worker only receives the
   * queue item data and not the queue item ID. In the hypothetical scenario
   * that multiple identical emails are send out at once having non-unique
   * items would cause conflicts.
   */
  protected string $id;

  /**
   * Constructs a new SymfonyMailerQueueItem object.
   *
   * @param string $type
   *   The email type.
   * @param string $subType
   *   The email sub-type.
   * @param array $params
   *   An array of parameters for building the email.
   * @param array $config
   *   An array of configuration options specified with the email adjuster.
   *
   * @see \Drupal\symfony_mailer\EmailInterface
   */
  public function __construct(
    public readonly string $type,
    public readonly string $subType,
    public readonly array $params = [],
    public readonly array $config = [],
  ) {
    $this->id = uniqid('', TRUE);
  }

}
