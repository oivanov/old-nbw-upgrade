<?php

namespace Drupal\Tests\symfony_mailer_queue\Traits;

use Drupal\Core\Queue\DelayableQueueInterface;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueGarbageCollectionInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;

/**
 * Methods to process queues during test runs.
 *
 * @property \Drupal\Core\DependencyInjection\Container $container
 */
trait DelayableQueueRunnerTrait {

  /**
   * Runs a given delayable queue until all items are processed.
   *
   * @param string $queue_name
   *   The queue to process.
   */
  protected function runQueue(string $queue_name): void {
    $queue = $this->container->get('queue')->get($queue_name);
    $worker = $this->container->get('plugin.manager.queue_worker')->createInstance($queue_name);
    while ($item = $queue->claimItem()) {
      try {
        // @phpstan-ignore-next-line
        $worker->processItem($item->data);
        $queue->deleteItem($item);
      }
      catch (DelayedRequeueException $e) {
        if ($queue instanceof DelayableQueueInterface) {
          /** @var object $item */
          $queue->delayItem($item, $e->getDelay());
        }
      }
      catch (RequeueException $e) {
        /** @var object $item */
        $queue->releaseItem($item);
      }
      catch (SuspendQueueException $e) {
        /** @var object $item */
        $queue->releaseItem($item);
        throw new SuspendQueueException('The queue is broken.');
      }
    }
  }

  /**
   * Collects garbage for a given delayable queue.
   *
   * @param string $queue_name
   *   The queue to process.
   */
  protected function garbageCollectionForQueue(string $queue_name): void {
    $queue = $this->container->get('queue')->get($queue_name);
    if ($queue instanceof QueueGarbageCollectionInterface) {
      $queue->garbageCollection();
    }
  }

}
