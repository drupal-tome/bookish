<?php

namespace Drupal\bookish_admin\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\image\ImageEffectBase;
use Drupal\system\Plugin\ImageToolkit\GDToolkit;
use GdImage;

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
    if (isset($data['grayscale']) && $data['grayscale'] === 1) {
      imagefilter($resource, IMG_FILTER_GRAYSCALE);
    }
    if (isset($data['red']) && isset($data['green']) && isset($data['blue'])) {
      imagefilter($resource, IMG_FILTER_COLORIZE, $data['red'], $data['green'], $data['blue']);
    }
    if (isset($data['brightness'])) {
      imagefilter($resource, IMG_FILTER_BRIGHTNESS, $data['brightness']);
    }
    if (isset($data['contrast'])) {
      imagefilter($resource, IMG_FILTER_CONTRAST, $data['contrast']);
    }
    if (isset($data['blur']) && $data['blur'] > 0) {
      $this->blur($resource, $image->getWidth(), $image->getHeight(), $data['blur']);
    }
    return TRUE;
  }

  protected function blur($image, $width, $height, $amount) {
    // Credit to @r3mainer in https://stackoverflow.com/a/20264482
    // Strength is a range from making an image 0% -> 25% smaller than the original.
    $strength = (50 - (25 * ($amount/100))) / 100;
    $s_img1 = imagecreatetruecolor($width * $strength, $height * $strength);
    imagecopyresampled($s_img1, $image, 0, 0, 0, 0, $width * $strength, $height * $strength, $width, $height);
    imagefilter($s_img1, IMG_FILTER_GAUSSIAN_BLUR);
  
    /* Scale result by 200% and blur again */
    $s_img2 = imagecreatetruecolor($width * ($strength*2), $height * ($strength*2));
    imagecopyresampled($s_img2, $s_img1, 0, 0, 0, 0, $width * ($strength*2), $height * ($strength*2), $width * $strength, $height * $strength);
    imagedestroy($s_img1);
    imagefilter($s_img2, IMG_FILTER_GAUSSIAN_BLUR);
  
    /* Scale result back to original size and blur one more time */
    imagecopyresampled($image, $s_img2, 0, 0, 0, 0, $width, $height, $width * ($strength*2), $height * ($strength*2));
    imagedestroy($s_img2);
    imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
  }

}
