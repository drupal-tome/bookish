<?php

namespace Drupal\bookish_image\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\responsive_image\Plugin\Field\FieldFormatter\ResponsiveImageFormatter;
use Drupal\responsive_image\ResponsiveImageStyleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'bookish_responsive_image' formatter.
 *
 * @FieldFormatter(
 *   id = "bookish_responsive_image",
 *   label = @Translation("Bookish responsive image"),
 *   field_types = {
 *     "image"
 *   },
 *   quickedit = {
 *     "editor" = "image"
 *   }
 * )
 */
class BookishResponsiveImageFormatter extends ResponsiveImageFormatter {

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
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityStorageInterface $responsive_image_style_storage, EntityStorageInterface $image_style_storage, LinkGeneratorInterface $link_generator, AccountInterface $current_user, FileSystemInterface $file_system, ImageFactory $image_factory) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $responsive_image_style_storage, $image_style_storage, $link_generator, $current_user);
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
      $container->get('entity_type.manager')->getStorage('responsive_image_style'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('link_generator'),
      $container->get('current_user'),
      $container->get('file_system'),
      $container->get('image.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    $files = $this->getEntitiesToView($items, $langcode);
    /** @var \Drupal\Core\Render\Renderer $renderer */
    $renderer = \Drupal::service('renderer');
    foreach ($elements as $delta => $element) {
      if (!isset($files[$delta])) {
        continue;
      }
      /** @var \Drupal\file\FileInterface $file */
      $file = $files[$delta];
      $original_image = $this->imageFactory->get($file->getFileUri());
      if (!$original_image) {
        continue;
      }

      $clone = $element;
      $clone['#item_attributes']['class'][] = 'bookish-image-blur-blur';
      $clone_rendered = (string) $renderer->render($clone);

      $element['#item_attributes']['loading'] = 'lazy';
      $element['#item_attributes']['class'][] = 'bookish-image-blur-image';
      $rendered = (string) $renderer->render($element);

      // Find all image URLs in rendered string and make thumbnails.
      // Note: While gross, trying to generate a valid <picture> tag using
      // responsive_image with data URIs is surprisingly hard without
      // re-implementing its template_preprocess functions.
      $clone_rendered = preg_replace_callback('/(?<=srcset=")[^"]+(?=[\s"])|(?<=src=")[^"]+(?=[\s"])/', function ($matches) use ($file) {
        $url = $matches[0];
        if (strpos($url, 'data:') === 0) {
          return $url;
        }

        $uri = $file->getFileUri();
        if (preg_match('|(?<=/styles/)[^/]+|', $url, $matches2)) {
          $image_style_id = $matches2[0];
          $image_style = ImageStyle::load($image_style_id);

          $derivative_uri = $image_style->buildUri($file->getFileUri());
          if (!file_exists($derivative_uri)) {
            $image_style->createDerivative($file->getFileUri(), $derivative_uri);
          }
          if (file_exists($derivative_uri)) {
            $uri = $derivative_uri;
          }
        }

        $image = $this->imageFactory->get($uri);
        if (!$image) {
          return $url;
        }

        $image->scale(42);
        $temp_name = $this->fileSystem->tempnam('temporary://bookish_image', 'bookish_image_');
        $image->save($temp_name);
        $type = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
        $data = file_get_contents($temp_name);
        $this->fileSystem->delete($temp_name);
        $data_uri = 'data:image/' . $type . ';base64,' . base64_encode($data);
        return $data_uri;
      }, $clone_rendered);

      // Add width and height elements to <source> tags to support the blur-in.
      // @see https://github.com/whatwg/html/issues/4968
      $rendered = preg_replace_callback('/srcset="[^"]+"/', function ($matches) use ($original_image, $file) {
        $url = $matches[0];
        if (preg_match('|(?<=/styles/)[^/]+|', $url, $matches2)) {
          $image_style_id = $matches2[0];
        }
        else {
          $image_style_id = ResponsiveImageStyleInterface::ORIGINAL_IMAGE;
        }
        $dimensions = responsive_image_get_image_dimensions($image_style_id, [
          'width' => $original_image->getWidth(),
          'height' => $original_image->getHeight(),
        ], $file->getFileUri());
        return $url . ' width="' . (int) $dimensions['width'] . '" height="' . (int) $dimensions['height'] . '"';
      }, $rendered);

      $elements[$delta] = [
        'blur_container' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'bookish-image-blur-container',
              'bookish-responsive-image-blur-container',
            ],
          ],
          'blur' => [
            '#markup' => Markup::create($clone_rendered),
          ],
          'image' => [
            '#markup' => Markup::create($rendered),
          ],
          '#attached' => ['library' => ['bookish_image/imageBlur']],
        ],
      ];
    }
    return $elements;
  }

}
