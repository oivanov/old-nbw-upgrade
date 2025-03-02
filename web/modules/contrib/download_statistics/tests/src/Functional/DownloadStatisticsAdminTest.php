<?php

namespace Drupal\Tests\download_statistics\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\Traits\Core\CronRunTrait;

/**
 * Tests the Download Statistics admin.
 *
 * @group Download Statistics
 */
class DownloadStatisticsAdminTest extends BrowserTestBase {

  use CronRunTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['node', 'download_statistics'];

  /**
   * A user that has permission to administer download statistics.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $privilegedUser;

  /**
   * A page node for which to check download statistics.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $testNode;

  /**
   * The Guzzle HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  protected function setUp(): void {
    parent::setUp();

    // Create Basic page node type.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    }
    $this->privilegedUser = $this->drupalCreateUser([
      'administer download statistics',
      'view file download statistics',
      'create page content',
    ]);
    $this->drupalLogin($this->privilegedUser);
    $this->testNode = $this->drupalCreateNode(['type' => 'page', 'uid' => $this->privilegedUser->id()]);
    $this->client = \Drupal::httpClient();
  }

  /**
   * Tests that when a node is deleted, the node counter is deleted too.
   */
  public function testDeleteNode() {

    $this->drupalGet('node/' . $this->testNode->id());
    // Manually calling statistics.php, simulating ajax behavior.
    $nid = $this->testNode->id();
    $post = ['nid' => $nid];
    global $base_url;
    $stats_path = $base_url . '/' . \Drupal::service('extension.list.module')->getPath('statistics') . '/statistics.php';
    $this->client->post($stats_path, ['form_params' => $post]);

    $result = \Drupal::database()->select('download_statistics', 'n')
      ->fields('n', ['nid'])
      ->condition('n.nid', $this->testNode->id())
      ->execute()
      ->fetchAssoc();
    $this->assertEqual($result['nid'], $this->testNode->id(), 'Verifying that the node counter is incremented.');

    $this->testNode->delete();

    $result = db_select('download_statistics', 'n')
      ->fields('n', ['nid'])
      ->condition('n.nid', $this->testNode->id())
      ->execute()
      ->fetchAssoc();
    $this->assertFalse($result, 'Verifying that the node counter is deleted.');
  }

  /**
   * Tests that cron clears day counts and expired access logs.
   */
  public function testExpiredLogs() {

    \Drupal::state()->set(' download_statistics.day_timestamp', 8640000);

    $this->drupalGet('node/' . $this->testNode->id());
    // Manually calling statistics.php, simulating ajax behavior.
    $nid = $this->testNode->id();
    $post = ['nid' => $nid];
    global $base_url;
    $stats_path = $base_url . '/' . \Drupal::service('extension.list.module')->getPath('statistics') . '/statistics.php';
    $this->client->post($stats_path, ['form_params' => $post]);
    $this->drupalGet('node/' . $this->testNode->id());
    $this->client->post($stats_path, ['form_params' => $post]);
    //$this->assertText('1 view', 'Node is viewed once.');
    $this->assertSession()->pageTextContains('1 view');

    // download_statistics_cron() will subtract
    // download_statistics.settings:accesslog.max_lifetime config from
    // REQUEST_TIME in the delete query, so wait two secs here to make
    // sure the access log will be flushed for the node just hit.
    sleep(2);
    $this->cronRun();

    $this->drupalGet('admin/reports/pages');
    //$this->assertNoText('node/' . $this->testNode->id(), 'No hit URL found.');
    $this->assertSession()->pageTextNotContains('node/' . $this->testNode->id());

    $result = \Drupal::database()->select('download_statistics', 'ds')
      ->fields('ds', ['daycount'])
      ->condition('nid', $this->testNode->id(), '=')
      ->execute()
      ->fetchField();
    $this->assertFalse($result, 'Daycounter is zero.');
  }

}
