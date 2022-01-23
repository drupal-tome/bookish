<?php

namespace Drupal\bookish_image\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Updates an image in CKEditor after changing Bookish image settings.
 */
class BookishImageCKEditorCommand implements CommandInterface {

  /**
   * The UUID of the File entity.
   *
   * @var string
   */
  protected $fileUuid;

  /**
   * The new image URL.
   *
   * @var string
   */
  protected $url;

  /**
   * The selected image style.
   *
   * @var string
   */
  protected $imageStyle;

  /**
   * Constructs a BookishImageCKEditorCommand.
   *
   * @param string $file_uuid
   *   The UUID of the File entity.
   * @param string $url
   *   The new image URL.
   * @param string $image_style_name
   *   The selected image style.
   */
  public function __construct($file_uuid, $url, $image_style_name = NULL) {
    $this->fileUuid = $file_uuid;
    $this->url = $url;
    $this->imageStyle = $image_style_name;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'bookishImageCKEditor',
      'fileUuid' => $this->fileUuid,
      'url' => $this->url,
      'imageStyle' => $this->imageStyle,
    ];
  }

}
