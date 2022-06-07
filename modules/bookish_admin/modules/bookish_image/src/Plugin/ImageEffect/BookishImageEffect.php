<?php

namespace Drupal\bookish_image\Plugin\ImageEffect;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\image\ImageEffectBase;
use Drupal\system\Plugin\ImageToolkit\GDToolkit;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;

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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('image'),
      $container->get('entity_type.manager')
    );
  }

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
    $files = $this->entityTypeManager
      ->getStorage('file')
      ->loadByProperties([
        'uri' => $image->getSource(),
      ]);
    if (empty($files)) {
      return TRUE;
    }
    $file = reset($files);
    $data = _bookish_image_coerce_data(json_decode($file->bookish_image_data->getString(), TRUE));
    if (isset($data['saturation']) && $data['saturation'] !== 0) {
      $this->saturation($resource, $data['saturation']);
    }
    if (isset($data['grayscale']) && $data['grayscale'] === 1) {
      imagefilter($resource, IMG_FILTER_GRAYSCALE);
    }
    if (isset($data['red']) && isset($data['green']) && isset($data['blue'])) {
      imagefilter($resource, IMG_FILTER_COLORIZE, $data['red'], $data['green'], $data['blue']);
    }
    if (isset($data['brightness']) && $data['brightness'] != 0) {
      imagefilter($resource, IMG_FILTER_BRIGHTNESS, $data['brightness']);
    }
    if (isset($data['contrast']) && $data['contrast'] != 0) {
      imagefilter($resource, IMG_FILTER_CONTRAST, $data['contrast']);
    }
    if (isset($data['blur']) && $data['blur'] > 0) {
      $this->blur($resource, $image->getWidth(), $image->getHeight(), $data['blur']);
    }
    if (isset($data['hue']) && $data['hue'] > 0) {
      $this->imagehue($resource, $data['hue']);
    }
    return TRUE;
  }

  /**
   * Performs a fade effect to darken shadows or lighten brightness.
   *
   * Not currently used due to inconsistent results, need to only apply changes
   * to colors in a certain range of dark/lightness.
   *
   * @param \GdImage|resource $image
   *   The image.
   * @param int $fade
   *   The amount to fade, between -100 and 100.
   */
  protected function fade($image, $fade) {
    $width = imagesx($image);
    $height = imagesy($image);

    $image_copy = imagecreatetruecolor($width, $height);
    imagecopy($image_copy, $image, 0, 0, 0, 0, $width, $height);

    imagealphablending($image_copy, FALSE);
    imagesavealpha($image_copy, TRUE);
    imagealphablending($image, FALSE);
    imagesavealpha($image, TRUE);

    $transparency = .5 * (abs($fade) / 100);
    imagefilter($image_copy, IMG_FILTER_COLORIZE, 0, 0, 0, 127 * $transparency);

    if ($fade < 0) {
      imagefilter($image, IMG_FILTER_BRIGHTNESS, -255);
    }
    else {
      $this->saturation($image, 100);
      imagefilter($image, IMG_FILTER_BRIGHTNESS, 255);
    }
    $this->imagecopymergealpha($image, $image_copy, 0, 0, 0, 0, $width, $height, 100);
  }

  /**
   * Acts like imagecopy while maintaing alpha channels.
   *
   * Credit to Sina Salek.
   *
   * @param \GdImage|resource $dst_im
   *   The source image.
   * @param \GdImage|resource $src_im
   *   The destination image.
   * @param int $dst_x
   *   The destination X coordinate.
   * @param int $dst_y
   *   The destination Y coordinate.
   * @param int $src_x
   *   The source X coordinate.
   * @param int $src_y
   *   The source Y coordinate.
   * @param int $src_w
   *   The source width.
   * @param int $src_h
   *   The source height.
   * @param int $pct
   *   The percentage.
   *
   * @see https://www.php.net/manual/en/function.imagecopymerge.php#92787
   */
  protected function imagecopymergealpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct) {
    // Creating a cut resource.
    $cut = imagecreatetruecolor($src_w, $src_h);

    // Copying relevant section from background to the cut resource.
    imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);

    // Copying relevant section from watermark to the cut resource.
    imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);

    // Insert cut resource to destination image.
    imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct);
  }

  /**
   * Performs a blur effect.
   *
   * Credit to https://stackoverflow.com/a/20264482
   *
   * @param \GdImage|resource $image
   *   The image.
   * @param int $width
   *   The image width.
   * @param int $height
   *   The image height.
   * @param int $amount
   *   The amount to blur.
   */
  protected function blur($image, $width, $height, $amount) {
    // Strength is a range making an image 0% -> 25% smaller than the original.
    $strength = (50 - (25 * ($amount / 100))) / 100;
    $s_img1 = imagecreatetruecolor($width * $strength, $height * $strength);
    imagecopyresampled($s_img1, $image, 0, 0, 0, 0, $width * $strength, $height * $strength, $width, $height);
    imagefilter($s_img1, IMG_FILTER_GAUSSIAN_BLUR);

    /* Scale result by 200% and blur again */
    $s_img2 = imagecreatetruecolor($width * ($strength * 2), $height * ($strength * 2));
    imagecopyresampled($s_img2, $s_img1, 0, 0, 0, 0, $width * ($strength * 2), $height * ($strength * 2), $width * $strength, $height * $strength);
    imagedestroy($s_img1);
    imagefilter($s_img2, IMG_FILTER_GAUSSIAN_BLUR);

    /* Scale result back to original size and blur one more time */
    imagecopyresampled($image, $s_img2, 0, 0, 0, 0, $width, $height, $width * ($strength * 2), $height * ($strength * 2));
    imagedestroy($s_img2);
    imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
  }

  /**
   * Performs a saturation effect.
   *
   * Credit to https://stackoverflow.com/q/33001508
   *
   * @param \GdImage|resource $image
   *   The image.
   * @param int $saturation_percentage
   *   The saturation percentage (-100 -> 100).
   */
  protected function saturation($image, $saturation_percentage) {
    $width = imagesx($image);
    $height = imagesy($image);

    for ($x = 0; $x < $width; $x++) {
      for ($y = 0; $y < $height; $y++) {
        $rgb = imagecolorat($image, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        $alpha = ($rgb & 0x7F000000) >> 24;
        [$h, $s, $l] = $this->rgb2hsl($r, $g, $b);
        $s = $s * (100 + $saturation_percentage) / 100;
        if ($s > 1) {
          $s = 1;
        }
        [$r, $g, $b] = $this->hsl2rgb($h, $s, $l);
        imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $r, $g, $b, $alpha));
      }
    }
  }

  /**
   * Performs a hue effect.
   *
   * Credit to https://stackoverflow.com/a/1890450
   *
   * @param \GdImage|resource $image
   *   The image.
   * @param int $angle
   *   The angle for the hue (-360 -> 360)
   */
  protected function imagehue($image, $angle) {
    if ($angle % 360 == 0) {
      return;
    }
    $width = imagesx($image);
    $height = imagesy($image);

    for ($x = 0; $x < $width; $x++) {
      for ($y = 0; $y < $height; $y++) {
        $rgb = imagecolorat($image, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        $alpha = ($rgb & 0x7F000000) >> 24;
        [$h, $s, $l] = $this->rgb2hsl($r, $g, $b);
        $h += $angle / 360;
        if ($h > 1) {
          $h--;
        }
        [$r, $g, $b] = $this->hsl2rgb($h, $s, $l);
        imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $r, $g, $b, $alpha));
      }
    }
  }

  /**
   * Converts RGB values to HSL.
   *
   * @param int $r
   *   The red value.
   * @param int $g
   *   The green value.
   * @param int $b
   *   The blue value.
   *
   * @return array
   *   An array with the HSL values.
   */
  protected function rgb2hsl($r, $g, $b) {
    // Credit to https://stackoverflow.com/a/1890450
    $var_R = ($r / 255);
    $var_G = ($g / 255);
    $var_B = ($b / 255);

    $var_Min = min($var_R, $var_G, $var_B);
    $var_Max = max($var_R, $var_G, $var_B);
    $del_Max = $var_Max - $var_Min;

    $v = $var_Max;

    if ($del_Max == 0) {
      $h = 0;
      $s = 0;
    }
    else {
      $s = $del_Max / $var_Max;

      $del_R = ((($var_Max - $var_R) / 6) + ($del_Max / 2)) / $del_Max;
      $del_G = ((($var_Max - $var_G) / 6) + ($del_Max / 2)) / $del_Max;
      $del_B = ((($var_Max - $var_B) / 6) + ($del_Max / 2)) / $del_Max;

      $h = 0;
      if ($var_R == $var_Max) {
        $h = $del_B - $del_G;
      }
      elseif ($var_G == $var_Max) {
        $h = (1 / 3) + $del_R - $del_B;
      }
      elseif ($var_B == $var_Max) {
        $h = (2 / 3) + $del_G - $del_R;
      }

      if ($h < 0) {
        $h++;
      }
      if ($h > 1) {
        $h--;
      }
    }

    return [$h, $s, $v];
  }

  /**
   * Converts HSL/HSV values to RGB.
   *
   * @param int $h
   *   The hue value.
   * @param int $s
   *   The saturation value.
   * @param int $v
   *   The lightness value.
   *
   * @return array
   *   An array with the RGB values.
   */
  protected function hsl2rgb($h, $s, $v) {
    // Credit to https://stackoverflow.com/a/1890450
    if ($s == 0) {
      $r = $g = $b = $v * 255;
    }
    else {
      $var_H = $h * 6;
      $var_i = floor($var_H);
      $var_1 = $v * (1 - $s);
      $var_2 = $v * (1 - $s * ($var_H - $var_i));
      $var_3 = $v * (1 - $s * (1 - ($var_H - $var_i)));

      if ($var_i == 0) {
        $var_R = $v;
        $var_G = $var_3;
        $var_B = $var_1;
      }
      elseif ($var_i == 1) {
        $var_R = $var_2;
        $var_G = $v;
        $var_B = $var_1;
      }
      elseif ($var_i == 2) {
        $var_R = $var_1;
        $var_G = $v;
        $var_B = $var_3;
      }
      elseif ($var_i == 3) {
        $var_R = $var_1;
        $var_G = $var_2;
        $var_B = $v;
      }
      elseif ($var_i == 4) {
        $var_R = $var_3;
        $var_G = $var_1;
        $var_B = $v;
      }
      else {
        $var_R = $v;
        $var_G = $var_1;
        $var_B = $var_2;
      }

      $r = $var_R * 255;
      $g = $var_G * 255;
      $b = $var_B * 255;
    }
    return [(int) $r, (int) $g, (int) $b];
  }

}
