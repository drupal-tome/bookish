<?php

namespace Drupal\Tests\bookish_image\Kernel;

use Drupal\bookish_image\Form\BookishImageForm;
use Drupal\Core\Form\FormState;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests BookishImageForm.
 *
 * @coversDefaultClass \Drupal\bookish_image\Form\BookishImageForm
 * @group bookish
 */
class BookishImageFormTest extends KernelTestBase {

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
   * @covers \Drupal\bookish_image\Form\BookishImageForm::build
   */
  public function testBuild() {
    $this->installEntitySchema('file');
    $this->installEntitySchema('image_style');
    $this->installConfig('system');
    $this->installConfig('bookish_image');
    $entity_type_manager = $this->container->get('entity_type.manager');
    $image_factory = $this->container->get('image.factory');
    $form = new BookishImageForm($image_factory, $entity_type_manager);

    $file = File::create([
      'filename' => 'image-test.jpg',
      'uri' => "public://image-test.jpg",
      'filemime' => 'image/jpeg',
    ]);
    $file->enforceIsNew()->setPermanent();
    file_put_contents($file->getFileUri(), file_get_contents('core/tests/fixtures/files/image-1.png'));
    $file->save();

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

    // Test that submitting the form works.
    $new_data = [
      'brightness' => 255,
    ];
    $form_state = new FormState();
    $form_state->setValue([
      'bookish_image',
      'bookish_image_data',
    ],
    $new_data);
    $form_state->addBuildInfo('args', [$file]);
    \Drupal::formBuilder()->submitForm($form, $form_state);
    $new_file = \Drupal::entityTypeManager()->getStorage('file')->load($file->id());
    $this->assertEquals(json_decode($new_file->bookish_image_data->getString(), TRUE)['brightness'], $new_data['brightness']);
  }

}
