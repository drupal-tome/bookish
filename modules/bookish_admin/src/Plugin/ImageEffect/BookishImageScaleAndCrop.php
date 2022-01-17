<?php

namespace Drupal\bookish_admin\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\image\Plugin\ImageEffect\ResizeImageEffect;

/**
 * Applies the scale and crop effect taking the focal point into account.
 *
 * @ImageEffect(
 *   id = "bookish_image_scale_and_crop",
 *   label = @Translation("Bookish image scale and crop"),
 *   description = @Translation("Applies a scale and crop taking the focal point into account.")
 * )
 */
class BookishImageScaleAndCrop extends ResizeImageEffect {

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    $width = $this->configuration['width'];
    $height = $this->configuration['height'];
    $scale = max($width / $image->getWidth(), $height / $image->getHeight());

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
      $x = floor(($data['focal_point'][0] * $scale) - ($width / 2));
      $y = floor(($data['focal_point'][1] * $scale) - ($height / 2));
      if ($x < 0) {
        $x = 0;
      }
      if ($y < 0) {
        $y = 0;
      }
    }
    else {
      $x = floor($width / 2);
      $y = floor($height / 2);
    }
    $overflowX = ($x + $width) - ($image->getWidth() * $scale);
    if ($overflowX > 0) {
      $x -= $overflowX;
    }
    $overflowY = ($y + $height) - ($image->getHeight() * $scale);
    if ($overflowY > 0) {
      $y -= $overflowY;
    }
    if (!$image->apply('scale_and_crop', [
      'x' => $x,
      'y' => $y,
      'width' => $width,
      'height' => $height,
    ])) {
      $this->logger->error('Bookish image scale and crop failed using the %toolkit toolkit on %path (%mimetype, %dimensions)', [
        '%toolkit' => $image->getToolkitId(),
        '%path' => $image->getSource(),
        '%mimetype' => $image->getMimeType(),
        '%dimensions' => $image->getWidth() . 'x' . $image->getHeight(),
      ]);
      return FALSE;
    }
    return TRUE;
  }

}
