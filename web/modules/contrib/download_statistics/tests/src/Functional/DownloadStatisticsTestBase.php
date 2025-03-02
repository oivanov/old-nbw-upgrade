<?php

namespace Drupal\Tests\download_statistics\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Defines a base class for testing the Statistics module.
 */
abstract class DownloadStatisticsTestBase extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['node', 'block', 'ban', 'download_statistics'];

  /**
   * User with permissions to ban IP's.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $blockingUser;

  protected function setUp(): void {
    parent::setUp();

    // Create Basic page node type.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    }

    // Create user.
    $this->blockingUser = $this->drupalCreateUser([
      'access administration pages',
      'access site reports',
      'ban IP addresses',
      'administer blocks',
      'administer download statistics',
      'administer users',
    ]);
    $this->drupalLogin($this->blockingUser);
  }

}
