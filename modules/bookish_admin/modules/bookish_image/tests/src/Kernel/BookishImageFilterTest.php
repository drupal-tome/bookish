<?php

namespace Drupal\Tests\bookish_image\Kernel;

use Drupal\bookish_image\Plugin\Filter\BookishImageFilter;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests BookishOEmbedFitler.
 *
 * @coversDefaultClass \Drupal\bookish_image\Plugin\Filter\BookishImageFilter
 * @group bookish
 */
class BookishImageFilterTest extends KernelTestBase {

  /**
   * The file UUID.
   *
   * @var string
   */
  protected $uuid;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'bookish_image',
    'system',
    'file',
    'user',
    'image',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installEntitySchema('image_style');
    $this->installConfig('system');
    $this->installConfig('bookish_image');

    $file = File::create([
      'filename' => 'image-test.jpg',
      'uri' => "public://image-test.jpg",
      'filemime' => 'image/jpeg',
    ]);
    $file->enforceIsNew()->setPermanent();
    file_put_contents($file->getFileUri(), file_get_contents('core/tests/fixtures/files/image-1.png'));
    $file->save();
    $this->uuid = $file->uuid();

    $image_style = ImageStyle::create([
      'name' => 'test',
      'label' => 'test',
    ]);
    $effect = [
      'id' => 'bookish_image_effect',
      'weight' => 0,
    ];
    $image_style->addImageEffect($effect);
    $image_style->save();
  }

  /**
   * @covers \Drupal\bookish_image\Plugin\Filter\BookishImageFilter::process
   *
   * @dataProvider getTestProcessData
   */
  public function testProcess($text, $expected) {
    $text = str_replace('uuid_placeholder', $this->uuid, $text);
    $expected = str_replace('uuid_placeholder', $this->uuid, $expected);
    $filter = BookishImageFilter::create($this->container, [], '', ['provider' => 'bookish_image']);
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
      ],
      [
        '<img src="foo" data-entity-type="file" data-entity-uuid="invalid" data-bookish-image-style="wrong" />',
        '/<img src="foo" data-entity-type="file" data-entity-uuid="invalid" data-bookish-image-style="wrong" \/>/',
      ],
      [
        '<img src="foo" data-entity-type="file" data-entity-uuid="uuid_placeholder" data-bookish-image-style="wrong" />',
        '/<img src="foo" data-entity-type="file" data-entity-uuid="uuid_placeholder" data-bookish-image-style="wrong" \/>/',
      ],
      [
        '<img src="foo" data-entity-type="file" data-entity-uuid="uuid_placeholder" data-bookish-image-style="test" />',
        '/<img src="[^"]*\/styles\/test\/public\/image-test\.jpg[^"]*" data-entity-type="file" data-entity-uuid="uuid_placeholder" data-bookish-image-style="test" width="[^"]*" height="[^"]*" \/>/',
      ],
    ];
  }

}
