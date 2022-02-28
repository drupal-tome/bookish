<?php

namespace Drupal\bookish_image\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'bookish_image' formatter.
 *
 * @FieldFormatter(
 *   id = "bookish_image",
 *   label = @Translation("Bookish image"),
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
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $current_user, EntityStorageInterface $image_style_storage, FileUrlGeneratorInterface $file_url_generator, FileSystemInterface $file_system, ImageFactory $image_factory) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $current_user, $image_style_storage, $file_url_generator);
    $this->fileSystem = $file_system;
    $this->imageFactory = $image_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('file_url_generator'),
      $container->get('file_system'),
      $container->get('image.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    $image_style = ImageStyle::load($this->getSetting('image_style'));
    $files = $this->getEntitiesToView($items, $langcode);
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
      $image = $this->imageFactory->get($derivative_uri);
      if (!$image) {
        continue;
      }
      $image->scale(42);
      $temp_name = $this->fileSystem->tempnam('temporary://bookish_image', 'bookish_image_');
      $image->save($temp_name);
      $type = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
      $data = file_get_contents($temp_name);
      $this->fileSystem->delete($temp_name);
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
