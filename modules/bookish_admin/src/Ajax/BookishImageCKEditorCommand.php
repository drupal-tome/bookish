<?php

namespace Drupal\bookish_admin\Ajax;

use Drupal\Core\Ajax\CommandInterface;

class BookishImageCKEditorCommand implements CommandInterface {

  protected $file_uuid;

  protected $url;

  protected $image_style_name;

  public function __construct($file_uuid, $url, $image_style_name = NULL) {
    $this->file_uuid = $file_uuid;
    $this->url = $url;
    $this->image_style_name = $image_style_name;
  }

  public function render() {
    return [
      'command' => 'bookishImageCKEditor',
      'fileUuid' => $this->file_uuid,
      'url' => $this->url,
      'imageStyle' => $this->image_style_name,
    ];
  }

}
