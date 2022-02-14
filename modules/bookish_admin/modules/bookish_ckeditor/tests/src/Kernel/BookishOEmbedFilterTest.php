<?php

namespace Drupal\Tests\bookish_ckeditor\Kernel;

use Drupal\bookish_ckeditor\Plugin\Filter\BookishOEmbedFilter;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;

/**
 * Tests BookishOEmbedFitler.
 *
 * @coversDefaultClass \Drupal\bookish_ckeditor\Plugin\Filter\BookishOEmbedFilter
 * @group bookish
 */
class BookishOEmbedFilterTest extends KernelTestBase {

  /**
   * @covers \Drupal\bookish_ckeditor\Plugin\Filter\BookishOEmbedFilter::process
   *
   * @dataProvider getTestProcessData
   */
  public function testProcess($text, $expected, $response) {
    $mocked_http_client = $this->createMock(ClientInterface::class);
    $mocked_http_client->expects($this->any())
      ->method('request')
      ->will($this->returnValue(new Response(200, [], $response)));
    $filter = new BookishOEmbedFilter([], '', ['provider' => ''], $mocked_http_client);
    $result = $filter->process($text, 'en');
    $this->assertMatchesRegularExpression($expected, $result->getProcessedText());
  }

  /**
   * Data provider for ::testProcess().
   *
   * @return array
   *   Test data.
   */
  public function getTestProcessData() {
    return [
      [
        '<p>Hello world</p>',
        '/<p>Hello world<\/p>/',
        '',
      ],
      [
        '<oembed url="what"></oembed>',
        '/Cannot find provider for URL/',
        '',
      ],
      [
        '<oembed url="https://www.instagram.com/p/CZnbH_QvV0J"></oembed>',
        '/This provider is not supported/',
        '',
      ],
      [
        '<oembed url="https://twitter.com/mortensonsam/status/1490426406546726913"></oembed>',
        '/Error decoding oEmbed data/',
        '',
      ],
      [
        '<oembed url="https://twitter.com/mortensonsam/status/1490426406546726913"></oembed>',
        '/Error decoding oEmbed data/',
        'Hi there',
      ],
      [
        '<oembed url="https://twitter.com/mortensonsam/status/1490426406546726913"></oembed>',
        '/The oEmbed data does not have valid HTML/',
        '{"ok": "Hi there"}',
      ],
      [
        '<oembed url="https://twitter.com/mortensonsam/status/1490426406546726913"></oembed>',
        '/srcdoc="[^"]*Hi there[^"]*".*width="200"/',
        '{"html": "<p>Hi there</p>", "width": 200}',
      ],
    ];
  }

}
