<?php

namespace Drupal\symfony_mailer_queue\Plugin\QueueWorker;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer_queue\Event\EmailSendFailureEvent;
use Drupal\symfony_mailer_queue\Event\EmailSendRequeueEvent;
use Drupal\symfony_mailer_queue\QueueableEmailInterface;
use Drupal\symfony_mailer_queue\SymfonyMailerQueueItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Sends emails from queue and retries previous failures.
 *
 * @QueueWorker(
 *   id = \Drupal\symfony_mailer_queue\Plugin\QueueWorker\SymfonyMailerQueueWorker::QUEUE_NAME,
 *   title = @Translation("Sends emails and retries failures"),
 *   cron = {"time" = 60}
 * )
 */
class SymfonyMailerQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The name of the queue.
   */
  public const QUEUE_NAME = 'symfony_mailer_queue';

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The email factory.
   *
   * @var \Drupal\symfony_mailer\EmailFactoryInterface
   */
  protected EmailFactoryInterface $emailFactory;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The expirable key-value storage.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface
   */
  protected KeyValueExpirableFactoryInterface $keyValue;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults) {
    $instance = new static(...$defaults);
    $instance->configFactory = $container->get('config.factory');
    $instance->emailFactory = $container->get('email_factory');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->keyValue = $container->get('keyvalue.expirable');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($item): void {

    // Only process Symfony mailer queue items.
    if (!$item instanceof SymfonyMailerQueueItem) {
      return;
    }

    // Attempt to send the email. Considered done when successfully delivered.
    $email = $this->emailFactory->newTypedEmail(
      $item->type,
      $item->subType,
      ...$item->params,
    );
    if (!$email instanceof QueueableEmailInterface) {
      throw new \LogicException('Attempted to process a non-queueable email.');
    }
    $email->markInQueue();
    // @phpstan-ignore-next-line Dependent typing is insufficient.
    if ($email->send() ?? FALSE) {
      $send_wait_time = (int) ($item->config['send_wait_time'] ?? 0);
      sleep($send_wait_time);
      return;
    }

    // Retrieve and increase number of attempts when email delivery failed.
    $collection = $this->keyValue->get(static::QUEUE_NAME);
    $fingerprint = md5(serialize($item));
    $attempts = $collection->get($fingerprint, 0);
    $attempts++;

    // Fail ultimately when attempts are exhausted. Dispatch email send failure
    // event to allow other modules to react.
    $maximum_attempts = (int) ($item->config['maximum_attempts'] ?? 5);
    if ($attempts > $maximum_attempts) {
      $event = new EmailSendFailureEvent($item);
      $this->eventDispatcher->dispatch($event);
      return;
    }

    // Store attempts in the key-value storage keyed by an item fingerprint and
    // requeue item. Dispatch email send requeue event to allow other modules
    // to react. If the immediate requeue behavior is selected, release the
    // item. If the the queue is suspended the item will be released. The failed
    // item and other items are processed in a future queue run. Otherwise,
    // requeue the item with configured delay.
    $collection->setWithExpire($fingerprint, $attempts, 86400);
    $event = new EmailSendRequeueEvent($item);
    $this->eventDispatcher->dispatch($event);
    $queue_behavior = $item->config['queue_behavior'] ?? '';
    if ($queue_behavior === 'requeue') {
      throw new RequeueException();
    }
    if ($queue_behavior === 'suspend') {
      throw new SuspendQueueException();
    }
    $requeue_delay = (int) ($item->config['requeue_delay'] ?? 0);
    throw new DelayedRequeueException($requeue_delay);
  }

}
