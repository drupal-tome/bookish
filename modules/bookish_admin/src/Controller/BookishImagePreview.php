<?php

namespace Drupal\bookish_admin\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for previewing Bookish image effect settings.
 */
class BookishImagePreview extends ControllerBase {

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
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    /** @var \Drupal\Core\Image\ImageFactory $image_factory */
    $image_factory = \Drupal::service('image.factory');
    $original_image_data = json_decode($file->bookish_image_data->getString(), TRUE);
    $new_image_data = json_decode($request->query->get('bookish_image_data', []), TRUE);
    $image_data = array_merge(_bookish_admin_coerce_data($original_image_data), _bookish_admin_coerce_data($new_image_data));
    $file->bookish_image_data = json_encode($image_data);
    $derivative_uri = 'temporary://bookish-image-preview/' . preg_replace('|.*://|', '', $file->getFileUri());
    $file_system->delete($derivative_uri);
    $image_style->createDerivative($file->getFileUri(), $derivative_uri);
    $image = $image_factory->get($derivative_uri);
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
