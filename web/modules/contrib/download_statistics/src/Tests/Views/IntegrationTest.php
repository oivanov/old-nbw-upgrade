<?php

namespace Drupal\download_statistics\Tests\Views;

use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests basic integration of views data from the download_statistics module.
 *
 * @group download_statistics
 *
 * @deprecated in download_statistics:1.1.0 and is removed from
 *   download_statistics:2.0.0. Use
 *   \Drupal\Tests\download_statistics\Functional\Views\IntegrationTest instead.
 */
class IntegrationTest extends ViewTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['download_statistics', 'download_statistics_test_views', 'node'];

  /**
   * Stores the user object that accesses the page.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * Stores the user object that cannot see download statistics.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $deniedUser;

  /**
   * Stores the node object which is used by the test.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_download_statistics_integration'];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp();

    ViewTestData::createTestViews(get_class($this), ['download_statistics_test_views']);

    // Create a new user for viewing nodes and statistics.
    $this->webUser = $this->drupalCreateUser(['access content', 'view file download statistics']);

    // Create a new user for viewing nodes only.
    $this->deniedUser = $this->drupalCreateUser(['access content']);

    $this->drupalCreateContentType(['type' => 'page']);
    $this->node = $this->drupalCreateNode(['type' => 'page']);

  }

  /**
   * Tests the integration of the {download_statistics} table in views.
   */
  public function testDownloadCounterIntegration() {
    $this->drupalLogin($this->webUser);

    $this->drupalGet('node/' . $this->node->id());
    $this->drupalGet('test_download_statistics_integration');

    $this->drupalLogout();
    $this->drupalLogin($this->deniedUser);
    $this->drupalGet('test_download_statistics_integration');
    $this->assertSession()->statusCodeEquals(200);
  }

}
