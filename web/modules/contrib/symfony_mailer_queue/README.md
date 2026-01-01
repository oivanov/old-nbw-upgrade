# Symfony Mailer Queue

The *Symfony Mailer Queue* module extends the existing [Drupal Symfony Mailer](https://www.drupal.org/project/symfony_mailer) module by integrating with queue systems to send emails. This enhancement ensures that email delivery is handled asynchronously, significantly improving the performance and reliability of your Drupal site. By utilizing Drupal’s queue system, the module processes email-sending tasks in the background, allowing for better resource management and scalability.

## Features

- **Seamless Integration:** It utilizes the existing Drupal Symfony Mailer plugin system and works out of the box with the Drupal database queue.
- **Queue System:** Emails are added to a queue and processed asynchronously, ensuring quick response times for web requests.
- **Email Adjuster Configuration:** Easy-to-use configuration interface for managing individual queue settings.
- **Event System:** Dispatches requeue and failure events for other modules to subscribe to.

## How to use?

1. **Installation:** Start by installing the module via Composer. The module depends on Drupal Symfony Mailer.
2. **Configure Mailer Policies:** Go to *Configuration > System > Mailer* and add the *Queue sending* email adjuster to the desired policies. Configure the queue behavior, requeue delay, maximum retry attempts, and wait time per item.
3. **(Optional) Configure Ultimate Cron:** If processing the queue with cron and delayed requeueing, it is recommended to schedule frequent cron processing. This can be achieved with the [Ultimate Cron](https://www.drupal.org/project/ultimate_cron) module. There are two jobs to consider:
    - The *Default cron handler* of Symfony Mailer Queue performs garbage collection required to release queue items.
    - The *Queue* of Symfony Mailer Queue, which sends emails and retries failures.

## Queue Behaviors

When email processing fails, one may choose between different queue behaviors.

- **Delayed requeue:** If requeuing is delayed, the item will only be available after the specified delay or once its lease time expires. While not all queue systems support delays, the Drupal database queue does. Proper garbage collection must be configured to release items when using cron to process the queue. For queues that do not support delays, a default lease time of one minute applies.
- **Immediate requeue:** Items that are immediately requeued become available for repeated processing right away. They might even be picked up within the same queue run.
- **Suspend queue:** The email queue can be suspended, which requeues the failed item and delays the processing of other items until the next scheduled queue run.

## Developer Notes

- When using delayed requeuing, the queue which processes items is required to implement the `DelayableQueueInterface`.
- The item expiry reset may be performed during cron. In that case, the queue must implement the `QueueGarbageCollectionInterface`.
- Other modules can subscribe to the `EmailSendRequeueEvent` and `EmailSendFailureEvent` events to react with logging or further processing. Read this [blog post](https://www.simonbaese.com/blog/drupal-event-dispatcher-explained-practical-example) about the Drupal event system.
- There is a lot of movement around the mailer setup in Drupal. See the [meta issue](https://www.drupal.org/project/drupal/issues/1803948) about including Symfony Mailer in Drupal core. Or the excellent [blog series](https://www.previousnext.com.au/blog/symfony-messenger/post-1-introducing-symfony-messenger) about more advanced setups with Symfony Messenger.

## Similar projects

If your website relies on the traditional PHP mailer, explore the _Queue Mail_ or _Mail Entity Queue_ modules.

## Maintainer

- [Simon Bäse](https://www.drupal.org/u/simonbaese)

This project is sponsored by [Factorial GmbH](https://www.factorial.io). Contact us if you are looking for interesting open-source employment.
