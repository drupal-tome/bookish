<?php

namespace Drupal\bookish_image\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

/**
 * Controller for previewing Bookish image effect settings.
 */
class BookishImagePreview extends ControllerBase {

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
   * Constructs a new BookishImagePreview object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   */
  public function __construct(FileSystemInterface $file_system, ImageFactory $image_factory) {
    $this->fileSystem = $file_system;
    $this->imageFactory = $image_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('image.factory')
    );
  }

  /**
   * Generates a temporary image style derivative using effect settings.
   *
   * @param \Drupal\file\FileInterface $file
   *   The image.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The contents of the preview image.
   */
  public function build(FileInterface $file, ImageStyleInterface $image_style, Request $request) {
    $new_image_data = json_decode($request->query->get('bookish_image_data', '[]'), TRUE);
    _bookish_image_update_data($file, $new_image_data);
    $uri = preg_replace('|.*://|', '', $file->getFileUri());
    if (empty($uri)) {
      throw new NotAcceptableHttpException('Provided file has no path.');
    }
    $derivative_uri = 'temporary://bookish-image-preview/' . $uri;
    $this->fileSystem->delete($derivative_uri);
    $image_style->createDerivative($file->getFileUri(), $derivative_uri);
    $image = $this->imageFactory->get($derivative_uri);
    $headers = [
      'Content-Type' => $image->getMimeType(),
      'Content-Length' => $image->getFileSize(),
    ];
    $response = new BinaryFileResponse($derivative_uri, 200, $headers, FALSE);
    $response->setCache(['max_age' => 60]);
    return $response;
  }

  /**
   * Determines access to the preview and form routes.
   *
   * @param \Drupal\file\FileInterface $file
   *   The image.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   An access result.
   */
  public static function access(FileInterface $file, ImageStyleInterface $image_style = NULL) {
    $uri = $file->getFileUri();
    $scheme = StreamWrapperManager::getScheme($uri);
    return AccessResult::allowedIf(file_exists($uri) && $file->access('download') && $scheme !== 'private')
      ->andIf(AccessResult::allowedIfHasPermission(\Drupal::currentUser(), 'use bookish image'));
  }

}
