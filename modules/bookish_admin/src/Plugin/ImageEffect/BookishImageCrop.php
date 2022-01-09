<?php

namespace Drupal\bookish_admin\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\image\Plugin\ImageEffect\ResizeImageEffect;

/**
 * Applies the crop effect taking the focal point into account.
 *
 * @ImageEffect(
 *   id = "bookish_image_crop",
 *   label = @Translation("Bookish image crop"),
 *   description = @Translation("Applies a crop taking the focal point into account.")
 * )
 */
class BookishImageCrop extends ResizeImageEffect {

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
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
    $data = _bookish_admin_coerce_data(json_decode($file->bookish_image_data->getString(), TRUE));
    if (isset($data['focal_point'])) {
      $x = floor($data['focal_point'][0] - ($this->configuration['width'] / 2));
      $y = floor($data['focal_point'][1] - ($this->configuration['height'] / 2));
    } else {
      $x = 0;
      $y = 0;
    }
    if (!$image->crop($x, $y, $this->configuration['width'], $this->configuration['height'])) {
      $this->logger->error('Bookish image crop failed using the %toolkit toolkit on %path (%mimetype, %dimensions)', ['%toolkit' => $image->getToolkitId(), '%path' => $image->getSource(), '%mimetype' => $image->getMimeType(), '%dimensions' => $image->getWidth() . 'x' . $image->getHeight()]);
      return FALSE;
    }
    return TRUE;
  }

}
