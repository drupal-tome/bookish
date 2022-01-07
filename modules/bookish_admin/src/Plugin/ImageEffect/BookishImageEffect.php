<?php

namespace Drupal\bookish_admin\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\image\ImageEffectBase;
use Drupal\system\Plugin\ImageToolkit\GDToolkit;

/**
 * Applies image edits made using the Bookish Image widget.
 *
 * @ImageEffect(
 *   id = "bookish_image_effect",
 *   label = @Translation("Bookish image effect"),
 *   description = @Translation("Applies image edits made using the Bookish Image widget.")
 * )
 */
class BookishImageEffect extends ImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    /** @var \Drupal\system\Plugin\ImageToolkit\GDToolkit $toolkit */
    $toolkit = $image->getToolkit();
    if (!($toolkit instanceof GDToolkit)) {
      return TRUE;
    }
    $resource = $toolkit->getResource();
    if (!$resource) {
      return TRUE;
    }
    /** @var \Drupal\file\FileInterface[] $files */
    $files = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->loadByProperties([
        'uri' => $image->getSource(),
      ]);
    if (empty($files)) {
      return TRUE;
    }
    $file = reset($files);
    $data = json_decode($file->bookish_image_data->getString(), TRUE);
    if (isset($data['brightness'])) {
      imagefilter($resource, IMG_FILTER_BRIGHTNESS, $data['brightness']);
    }
    return TRUE;
  }

}
