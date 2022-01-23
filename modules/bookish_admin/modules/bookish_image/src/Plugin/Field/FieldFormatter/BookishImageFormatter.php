<?php

namespace Drupal\bookish_image\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;

/**
 * Plugin implementation of the 'bookish_image' formatter.
 *
 * @FieldFormatter(
 *   id = "bookish_image",
 *   label = @Translation("Bookish iamge"),
 *   field_types = {
 *     "image"
 *   },
 *   quickedit = {
 *     "editor" = "image"
 *   }
 * )
 */
class BookishImageFormatter extends ImageFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    $image_style = ImageStyle::load($this->getSetting('image_style'));
    $files = $this->getEntitiesToView($items, $langcode);
    /** @var \Drupal\Core\Image\ImageFactory $image_factory */
    $image_factory = \Drupal::service('image.factory');
    /** @var \Drupal\Core\File\FileSystem $file_system */
    $file_system = \Drupal::service('file_system');
    foreach ($elements as $delta => $element) {
      if (!isset($files[$delta])) {
        continue;
      }
      /** @var \Drupal\file\FileInterface $file */
      $file = $files[$delta];
      $derivative_uri = $image_style->buildUri($file->getFileUri());
      if (!file_exists($derivative_uri)) {
        $image_style->createDerivative($file->getFileUri(), $derivative_uri);
      }
      if (!file_exists($derivative_uri)) {
        continue;
      }
      $image = $image_factory->get($derivative_uri);
      if (!$image) {
        continue;
      }
      $image->scale(42);
      $temp_name = $file_system->tempnam('temporary://bookish_image', 'bookish_image_');
      $image->save($temp_name);
      $type = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
      $data = file_get_contents($temp_name);
      $file_system->delete($temp_name);
      $data_uri = 'data:image/' . $type . ';base64,' . base64_encode($data);

      $element['#item_attributes']['class'][] = 'bookish-image-blur-image';
      $elements[$delta] = [
        'blur_container' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['bookish-image-blur-container'],
          ],
          'blur' => [
            '#type' => 'container',
            '#attributes' => [
              'style' => "background-image:url($data_uri);",
              'class' => ['bookish-image-blur-blur'],
            ],
          ],
          'image' => $element,
          '#attached' => ['library' => ['bookish_image/imageBlur']],
        ],
      ];
    }
    return $elements;
  }

}
