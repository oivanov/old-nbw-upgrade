<?php

namespace Drupal\Tests\symfony_mailer_queue\Traits;

use Drupal\Component\Datetime\TimeInterface;

/**
 * Provides mock for increasing time.
 *
 * @property \Drupal\Core\DependencyInjection\Container $container
 */
trait IncreasingTimeMockTrait {

  /**
   * Mocks a increasing time service.
   */
  protected function mockIncreasingTime(): TimeInterface {

    $current_time = 0;
    $time_callback = static function () use (&$current_time): int {
      $current_time++;
      return $current_time;
    };

    $time = $this->createMock(TimeInterface::class);
    $time->expects($this->any())
      ->method('getRequestTime')
      ->willReturnCallback($time_callback);
    $time->expects($this->any())
      ->method('getCurrentTime')
      ->willReturnCallback($time_callback);

    return $time;
  }

}
