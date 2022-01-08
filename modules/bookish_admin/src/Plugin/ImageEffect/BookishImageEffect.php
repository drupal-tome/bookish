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
    if (isset($data['hue']) && $data['hue'] > 0) {
      $this->imagehue($resource, $data['hue']);
    }
    if (isset($data['saturation']) && $data['saturation'] > 0) {
      $this->saturation($resource, $data['saturation']);
    }
    return TRUE;
  }

  protected function blur($image, $width, $height, $amount) {
    // Credit to https://stackoverflow.com/a/20264482
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

  protected function saturation($image, $saturation_percentage) {
    // Credit to https://stackoverflow.com/q/33001508
    $width = imagesx($image);
    $height = imagesy($image);

    for($x = 0; $x < $width; $x++) {
        for($y = 0; $y < $height; $y++) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;            
            $alpha = ($rgb & 0x7F000000) >> 24;
            list($h, $s, $l) = $this->rgb2hsl($r, $g, $b);         
            $s = $s * (100 + $saturation_percentage ) /100;
            if($s > 1) $s = 1;
            list($r, $g, $b) = $this->hsl2rgb($h, $s, $l);            
            imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $r, $g, $b, $alpha));
        }
    }
  }

  protected function imagehue($image, $angle) {
    // Credit to https://stackoverflow.com/a/1890450
      if($angle % 360 == 0) return;
      $width = imagesx($image);
      $height = imagesy($image);

      for($x = 0; $x < $width; $x++) {
          for($y = 0; $y < $height; $y++) {
              $rgb = imagecolorat($image, $x, $y);
              $r = ($rgb >> 16) & 0xFF;
              $g = ($rgb >> 8) & 0xFF;
              $b = $rgb & 0xFF;            
              $alpha = ($rgb & 0x7F000000) >> 24;
              list($h, $s, $l) = $this->rgb2hsl($r, $g, $b);
              $h += $angle / 360;
              if($h > 1) $h--;
              list($r, $g, $b) = $this->hsl2rgb($h, $s, $l);            
              imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $r, $g, $b, $alpha));
          }
      }
  }

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
    } else {
       $s = $del_Max / $var_Max;
 
       $del_R = ( ( ( $var_Max - $var_R ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;
       $del_G = ( ( ( $var_Max - $var_G ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;
       $del_B = ( ( ( $var_Max - $var_B ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;
 
       if      ($var_R == $var_Max) $h = $del_B - $del_G;
       else if ($var_G == $var_Max) $h = ( 1 / 3 ) + $del_R - $del_B;
       else if ($var_B == $var_Max) $h = ( 2 / 3 ) + $del_G - $del_R;
 
       if ($h < 0) $h++;
       if ($h > 1) $h--;
    }
 
    return array($h, $s, $v);
 }
 
 protected function hsl2rgb($h, $s, $v) {
    // Credit to https://stackoverflow.com/a/1890450
     if($s == 0) {
         $r = $g = $B = $v * 255;
     } else {
         $var_H = $h * 6;
         $var_i = floor( $var_H );
         $var_1 = $v * ( 1 - $s );
         $var_2 = $v * ( 1 - $s * ( $var_H - $var_i ) );
         $var_3 = $v * ( 1 - $s * (1 - ( $var_H - $var_i ) ) );
 
         if       ($var_i == 0) { $var_R = $v     ; $var_G = $var_3  ; $var_B = $var_1 ; }
         else if  ($var_i == 1) { $var_R = $var_2 ; $var_G = $v      ; $var_B = $var_1 ; }
         else if  ($var_i == 2) { $var_R = $var_1 ; $var_G = $v      ; $var_B = $var_3 ; }
         else if  ($var_i == 3) { $var_R = $var_1 ; $var_G = $var_2  ; $var_B = $v     ; }
         else if  ($var_i == 4) { $var_R = $var_3 ; $var_G = $var_1  ; $var_B = $v     ; }
         else                   { $var_R = $v     ; $var_G = $var_1  ; $var_B = $var_2 ; }
 
         $r = $var_R * 255;
         $g = $var_G * 255;
         $B = $var_B * 255;
     }    
     return array($r, $g, $B);
 }

}
