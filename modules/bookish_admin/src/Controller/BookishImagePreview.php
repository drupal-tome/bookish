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
 * @todo
 */
class BookishImagePreview extends ControllerBase {

  /**
   * @todo
   */
  public function build(FileInterface $file, ImageStyleInterface $image_style, Request $request) {
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    /** @var \Drupal\Core\Image\ImageFactory $image_factory */
    $image_factory = \Drupal::service('image.factory');
    $original_image_data = json_decode($file->bookish_image_data->getString(), TRUE);
    $new_image_data = json_decode($request->query->get('bookish_image_data', []), TRUE);
    $image_data = array_merge($original_image_data, $new_image_data);
    $file->bookish_image_data = json_encode($image_data);
    // @todo Make more unique.
    $derivative_uri = 'public://bookish-image-preview/' . $file->getFileUri();
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

  public function access(FileInterface $file, ImageStyleInterface $image_style) {
    $uri = $file->getFileUri();
    $scheme = StreamWrapperManager::getScheme($uri);
    return AccessResult::allowedIf(file_exists($uri) && $file->access('download') && $scheme !== 'private');
  }

}
