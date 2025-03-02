<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_opensearch\Unit\SearchAPI\Query;

use Drupal\Tests\UnitTestCase;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_opensearch\SearchAPI\Query\FacetParamBuilder;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

/**
 * Tests the facet param builder.
 *
 * @coversDefaultClass \Drupal\search_api_opensearch\SearchAPI\Query\FacetParamBuilder
 * @group search_api_opensearch
 */
class FacetParamBuilderTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * @covers ::buildFacetParams
   */
  public function testBuildFacetParams() {
    $logger = $this->prophesize(LoggerInterface::class);
    $builder = new FacetParamBuilder($logger->reveal());

    $query = $this->prophesize(QueryInterface::class);
    $query->getOption('search_api_facets', [])
      ->willReturn([
        'facet1' => [
          'field' => 'field1',
          'operator' => 'and',
        ],
        'facet2' => [
          'field' => 'field1',
          'operator' => 'or',
        ],
      ]);

    $indexFields = [
      'field1' => [],
      'field2' => [],
    ];

    $params = $builder->buildFacetParams($query->reveal(), $indexFields);

    $expected = [
      'facet1' => ['terms' => ['field' => 'field1', 'size' => '10']],
      'facet2_global' => [
        'global' => (object) NULL,
        'aggs' => [
          'facet2' =>
            ['terms' => ['field' => 'field1', 'size' => '10']],
        ],
      ],
    ];

    $this->assertNotEmpty($params);
    $this->assertEquals($expected, $params);
  }

}
