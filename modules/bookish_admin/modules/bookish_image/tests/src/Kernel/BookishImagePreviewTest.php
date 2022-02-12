<?php

namespace Drupal\Tests\bookish_image\Kernel;

use Drupal\bookish_image\Controller\BookishImagePreview;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests BookishImagePreview.
 *
 * @coversDefaultClass \Drupal\bookish_ckeditor\Controller\BookishImagePreview
 * @group bookish
 */
class BookishImagePreviewTest extends KernelTestBase {

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
   * @covers \Drupal\bookish_ckeditor\Controller\BookishImagePreview::build
   */
  public function testBuild() {
    $this->installConfig('system');
    $this->installEntitySchema('file');
    $this->installEntitySchema('image_style');
    $file_system = $this->container->get('file_system');
    $image_factory = $this->container->get('image.factory');
    $controller = new BookishImagePreview($file_system, $image_factory);

    $request = new Request();

    $file = File::create([
      'filename' => 'image-test.jpg',
      'uri' => "public://image-test.jpg",
      'filemime' => 'image/jpeg',
    ]);
    $file->enforceIsNew()->setPermanent();
    file_put_contents($file->getFileUri(), file_get_contents('core/tests/fixtures/files/image-1.png'));
    $file->save();

    // Re-load the file to make sure it's pointing to the cached one.
    $file = \Drupal::entityTypeManager()->getStorage('file')->load($file->id());

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

    $controller->build($file, $image_style, $request);
    $this->assertFileExists('temporary://bookish-image-preview/image-test.jpg');
    $old_hash = sha1_file('temporary://bookish-image-preview/image-test.jpg');

    $request->query->set('bookish_image_data', json_encode(['brightness' => 255]));
    $controller->build($file, $image_style, $request);
    $new_hash = sha1_file('temporary://bookish-image-preview/image-test.jpg');
    $this->assertNotEquals($old_hash, $new_hash);

    // Test exception.
    $file = File::create();
    $image_style = ImageStyle::create();
    $request = new Request();
    $this->expectException('\Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException');
    $controller->build($file, $image_style, $request);
  }

}
