<?php

namespace Drupal\bookish_image\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\FileInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\image\Entity\ImageStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A filter that renders an image based on a given image style.
 *
 * @Filter(
 *   id = "bookish_image_filter",
 *   title = @Translation("Support image style switching in the editor"),
 *   description = @Translation("Put this under 'Track images uploaded via a Text Editor'"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE
 * )
 */
class BookishImageFilter extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * Constructs a BookishImageFilter object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityRepositoryInterface $entity_repository, ImageFactory $image_factory = NULL) {
    $this->entityRepository = $entity_repository;
    if ($image_factory === NULL) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $image_factory argument is deprecated in drupal:9.1.0 and is required in drupal:10.0.0. See https://www.drupal.org/node/3173719', E_USER_DEPRECATED);
      $image_factory = \Drupal::service('image.factory');
    }
    $this->imageFactory = $image_factory;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.repository'),
      $container->get('image.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    if (stristr($text, 'data-bookish-image-style') !== FALSE) {
      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);
      foreach ($xpath->query('//*[@data-entity-type="file" and @data-entity-uuid and @data-bookish-image-style]') as $node) {
        $uuid = $node->getAttribute('data-entity-uuid');
        if ($node->nodeName !== 'img') {
          continue;
        }
        /** @var \Drupal\file\FileInterface $file */
        $file = $this->entityRepository->loadEntityByUuid('file', $uuid);
        if (!($file instanceof FileInterface)) {
          continue;
        }
        $image_style = ImageStyle::load($node->getAttribute('data-bookish-image-style'));
        if (!$image_style) {
          continue;
        }
        $file_uri = $file->getFileUri();
        $result->addCacheableDependency($image_style);
        $url = $image_style->buildUrl($file_uri);
        $url .= (strpos($url, '?') !== FALSE ? '&' : '?') . 't=' . time();
        $node->setAttribute('src', $url);

        $derivative_uri = $image_style->buildUri($file_uri);
        if (!file_exists($derivative_uri)) {
          $image_style->createDerivative($file_uri, $derivative_uri);
        }

        $image = $this->imageFactory->get($derivative_uri);
        $width = $image->getWidth();
        $height = $image->getHeight();
        if ($width !== NULL && $height !== NULL) {
          $node->setAttribute('width', $width);
          $node->setAttribute('height', $height);
        }
      }
      $result->setProcessedText(Html::serialize($dom));
    }

    return $result;
  }

}
