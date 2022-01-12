<?php

namespace Drupal\bookish_admin\Ajax;

use Drupal\Core\Ajax\CommandInterface;

class BookishImageCKEditorCommand implements CommandInterface {

  protected $file_uuid;

  protected $url;

  public function __construct($file_uuid, $url) {
    $this->file_uuid = $file_uuid;
    $this->url = $url;
  }

  public function render() {
    return [
      'command' => 'bookishImageCKEditor',
      'fileUuid' => $this->file_uuid,
      'url' => $this->url,
    ];
  }

}
