<?php

namespace Drupal\bookish_image\Plugin\ImageEffect;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\image\Plugin\ImageEffect\ResizeImageEffect;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;

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
    $scale = 1;
    if (isset($data['zoom']) && $data['zoom'] !== 0) {
      $new_scale = 1 + (2 * ($data['zoom'] / 100));
      $new_width = $image->getWidth() * $new_scale;
      $new_height = $image->getHeight() * $new_scale;
      if ($new_width > $this->configuration['width'] && $new_height > $this->configuration['height']) {
        $scale = $new_scale;
        $image->scale($new_width, $new_height, TRUE);
      }
      else {
        if ($image->getWidth() < $image->getHeight()) {
          $scale = $this->configuration['width'] / $image->getWidth();
          $image->scale($image->getWidth() * $scale, NULL, TRUE);
        }
        else {
          $scale = $this->configuration['height'] / $image->getHeight();
          $image->scale(NULL, $image->getHeight() * $scale, TRUE);
        }
      }
    }
    if (isset($data['focal_point'])) {
      $x = floor(($data['focal_point'][0] * $scale) - ($this->configuration['width'] / 2));
      $y = floor(($data['focal_point'][1] * $scale) - ($this->configuration['height'] / 2));
      if ($x < 0) {
        $x = 0;
      }
      if ($y < 0) {
        $y = 0;
      }
    }
    else {
      $x = floor($this->configuration['width'] / 2);
      $y = floor($this->configuration['height'] / 2);
    }
    $overflowX = ($x + $this->configuration['width']) - $image->getWidth();
    if ($overflowX > 0) {
      $x -= $overflowX;
    }
    $overflowY = ($y + $this->configuration['height']) - $image->getHeight();
    if ($overflowY > 0) {
      $y -= $overflowY;
    }
    if (!$image->crop($x, $y, $this->configuration['width'], $this->configuration['height'])) {
      $this->logger->error('Bookish image crop failed using the %toolkit toolkit on %path (%mimetype, %dimensions)', [
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
