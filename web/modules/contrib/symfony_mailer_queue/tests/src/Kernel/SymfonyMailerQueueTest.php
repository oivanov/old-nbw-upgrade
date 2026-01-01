<?php

declare(strict_types=1);

namespace Drupal\Tests\symfony_mailer_queue\Kernel;

use Drupal\Core\Queue\DelayableQueueInterface;
use Drupal\Core\Queue\QueueGarbageCollectionInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer_queue\Event\EmailSendFailureEvent;
use Drupal\symfony_mailer_queue\Event\EmailSendRequeueEvent;
use Drupal\symfony_mailer_queue\Plugin\QueueWorker\SymfonyMailerQueueWorker;
use Drupal\symfony_mailer_queue\QueueableEmailInterface;
use Drupal\symfony_mailer_test\MailerTestTrait;
use Drupal\Tests\symfony_mailer_queue\Traits\DelayableQueueRunnerTrait;
use Drupal\Tests\symfony_mailer_queue\Traits\IncreasingTimeMockTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Queues emails for sending and tests success and failure behavior.
 *
 * @group symfony_mailer_queue
 */
class SymfonyMailerQueueTest extends KernelTestBase implements EventSubscriberInterface {

  use DelayableQueueRunnerTrait;
  use IncreasingTimeMockTrait;
  use MailerTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'symfony_mailer',
    'symfony_mailer_test',
    'symfony_mailer_queue',
    'symfony_mailer_queue_test',
    'system',
    'user',
  ];

  /**
   * Track caught failure events in a property for testing.
   */
  protected array $failureEvents = [];

  /**
   * Track caught requeue events in a property for testing.
   */
  protected array $requeueEvents = [];

  /**
   * The email factory.
   */
  protected EmailFactoryInterface $emailFactory;

  /**
   * The Symfony mailer queue.
   */
  protected QueueInterface $queue;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {

    parent::setUp();

    $this->installConfig([
      'symfony_mailer',
      'symfony_mailer_queue',
      'symfony_mailer_queue_test',
    ]);
    $this->installEntitySchema('user');

    $this->emailFactory = $this->container->get('email_factory');
    $this->config('system.site')
      ->set('name', 'Example')
      ->set('mail', 'sender@example.com')
      ->save();

    /** @var \Drupal\Core\Queue\QueueFactory $queue_factory */
    $queue_factory = $this->container->get('queue');
    $this->queue = $queue_factory->get(SymfonyMailerQueueWorker::QUEUE_NAME);
    $this->queue->createQueue();
    $this->assertInstanceOf(DelayableQueueInterface::class, $this->queue);
    $this->assertInstanceOf(QueueGarbageCollectionInterface::class, $this->queue);
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {

    parent::register($container);

    // Register test class as an event subscriber.
    $container->register('symfony_mailer_queue_test.queue_events', self::class)
      ->addTag('event_subscriber');
    $container->set('symfony_mailer_queue_test.queue_events', $this);

    // Mock time service to simulate increasing time. This is necessary to
    // process subsequent queue runs correctly.
    $container->set('datetime.time', $this->mockIncreasingTime());
  }

  /**
   * Tests the email queue with success.
   *
   * @param string $queue_behavior
   *   The queue behavior.
   * @param string $subject
   *   The expected email subject.
   *
   * @dataProvider emailQueueSuccessProvider
   */
  public function testEmailQueueSuccess(string $queue_behavior, string $subject): void {

    $email = $this->emailFactory->newTypedEmail('symfony_mailer_queue', $queue_behavior, 'test@example.com');
    $this->assertInstanceOf(QueueableEmailInterface::class, $email);

    $email->send();
    $this->assertEquals(1, $this->queue->numberOfItems());
    $this->runQueue(SymfonyMailerQueueWorker::QUEUE_NAME);
    $this->assertCount(0, $this->requeueEvents);
    $this->assertCount(0, $this->failureEvents);
    $this->assertEquals(0, $this->queue->numberOfItems());

    $this->readMail();
    $this->assertNoError();
    $this->assertSubject($subject);
  }

  /**
   * Data provider for testEmailQueueSuccess.
   *
   * @return array
   *   A list of test scenarios.
   */
  public static function emailQueueSuccessProvider(): array {

    $cases['delayed'] = [
      'queue_behavior' => 'delayed',
      'subject' => 'Symfony Mailer Queue Delayed Test',
    ];

    $cases['requeue'] = [
      'queue_behavior' => 'requeue',
      'subject' => 'Symfony Mailer Queue Requeue Test',
    ];

    $cases['suspend'] = [
      'queue_behavior' => 'suspend',
      'subject' => 'Symfony Mailer Queue Suspend Test',
    ];

    return $cases;
  }

  /**
   * Tests the email queue with send failures.
   */
  public function testEmailQueueFailure(): void {

    $queue_name = SymfonyMailerQueueWorker::QUEUE_NAME;

    // The email fails because no recipient is set.
    $email = $this->emailFactory->newTypedEmail('symfony_mailer_queue', 'requeue');
    $error_message = 'An email must have a "To", "Cc", or "Bcc" header.';
    $this->assertInstanceOf(QueueableEmailInterface::class, $email);
    $email->send();
    $email->send();

    $this->assertEquals(2, $this->queue->numberOfItems());
    $this->runQueue($queue_name);
    $this->assertCount(4, $this->requeueEvents);
    $this->assertCount(2, $this->failureEvents);
    for ($i = 1; $i <= 6; $i++) {
      $this->readMail($i === 6);
      $this->assertError($error_message);
    }
    $this->assertEquals(0, $this->queue->numberOfItems());
  }

  /**
   * Tests the email delayed queue with send failures.
   */
  public function testEmailDelayedQueueFailure(): void {

    $queue_name = SymfonyMailerQueueWorker::QUEUE_NAME;

    // The email fails because no recipient is set.
    $email = $this->emailFactory->newTypedEmail('symfony_mailer_queue', 'delayed');
    $error_message = 'An email must have a "To", "Cc", or "Bcc" header.';
    $this->assertInstanceOf(QueueableEmailInterface::class, $email);
    $email->send();
    $email->send();

    $this->assertEquals(2, $this->queue->numberOfItems());
    $this->runQueue($queue_name);
    $this->readMail(FALSE);
    $this->assertError($error_message);
    $this->readMail();
    $this->assertError($error_message);
    $this->assertCount(2, $this->requeueEvents);
    $this->assertCount(0, $this->failureEvents);
    $this->garbageCollectionForQueue($queue_name);

    $this->assertEquals(2, $this->queue->numberOfItems());
    $this->runQueue($queue_name);
    $this->readMail(FALSE);
    $this->assertError($error_message);
    $this->readMail();
    $this->assertError($error_message);
    $this->assertCount(4, $this->requeueEvents);
    $this->assertCount(0, $this->failureEvents);
    $this->garbageCollectionForQueue($queue_name);

    $this->assertEquals(2, $this->queue->numberOfItems());
    $this->runQueue($queue_name);
    $this->readMail(FALSE);
    $this->assertError($error_message);
    $this->readMail();
    $this->assertError($error_message);
    $this->assertCount(4, $this->requeueEvents);
    $this->assertCount(2, $this->failureEvents);

    $this->assertEquals(0, $this->queue->numberOfItems());
  }

  /**
   * Tests the email suspend queue with send failures.
   */
  public function testEmailSuspendQueueFailure(): void {

    $this->expectException(SuspendQueueException::class);
    $queue_name = SymfonyMailerQueueWorker::QUEUE_NAME;

    // The email fails because no recipient is set.
    $email = $this->emailFactory->newTypedEmail('symfony_mailer_queue', 'suspend');
    $error_message = 'An email must have a "To", "Cc", or "Bcc" header.';
    $this->assertInstanceOf(QueueableEmailInterface::class, $email);
    $email->send();
    $email->send();

    $this->assertEquals(2, $this->queue->numberOfItems());
    $this->runQueue($queue_name);
    $this->readMail();
    $this->assertError($error_message);
    $this->assertCount(1, $this->requeueEvents);
    $this->assertCount(0, $this->failureEvents);

    $this->assertEquals(2, $this->queue->numberOfItems());
    $this->runQueue($queue_name);
    $this->readMail();
    $this->assertError($error_message);
    $this->assertCount(2, $this->requeueEvents);
    $this->assertCount(0, $this->failureEvents);

    $this->assertEquals(2, $this->queue->numberOfItems());
    $this->runQueue($queue_name);
    $this->readMail();
    $this->assertError($error_message);
    $this->assertCount(2, $this->requeueEvents);
    $this->assertCount(1, $this->failureEvents);

    $this->assertEquals(1, $this->queue->numberOfItems());
    $this->runQueue($queue_name);
    $this->readMail();
    $this->assertError($error_message);
    $this->assertCount(2, $this->requeueEvents);
    $this->assertCount(2, $this->failureEvents);

    $this->assertEquals(0, $this->queue->numberOfItems());
  }

  /**
   * Pushes email send failure events into property for testing.
   */
  public function catchFailureEvents(EmailSendFailureEvent $event): void {
    $this->failureEvents[] = $event;
  }

  /**
   * Pushes email send requeue events into property for testing.
   */
  public function catchRequeueEvents(EmailSendRequeueEvent $event): void {
    $this->requeueEvents[] = $event;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EmailSendFailureEvent::class => 'catchFailureEvents',
      EmailSendRequeueEvent::class => 'catchRequeueEvents',
    ];
  }

}
