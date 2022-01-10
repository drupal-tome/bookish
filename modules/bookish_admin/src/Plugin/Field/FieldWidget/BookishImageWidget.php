<?php

namespace Drupal\bookish_admin\Plugin\Field\FieldWidget;

use Drupal\bookish_admin\BookishImageFormTrait;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\Plugin\Field\FieldWidget\ImageWidget;
use Symfony\Component\HttpFoundation\Request;

/**
 * Plugin implementation of the 'bookish_image' widget.
 *
 * @FieldWidget(
 *   id = "bookish_image",
 *   label = @Translation("Bookish image widget"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class BookishImageWidget extends ImageWidget {

  use BookishImageFormTrait;

  public static function process($element, FormStateInterface $form_state, $form) {
    $element = parent::process($element, $form_state, $form);

    $unique_id = $element['#field_name'] . '-' . $element['#delta'];
    $preview_id = 'bookish-image-preview-' . $unique_id;

    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];

    if (!empty($element['#files'])) {
      /** @var \Drupal\file\FileInterface $file */
      $file = reset($element['#files']);
      $element = static::buildImageForm($element, $unique_id, $file);
    }

    $element['preview']['#prefix'] = '<div class="bookish-image-preview" id="' . $preview_id . '">';
    $element['preview']['#suffix'] = '</div>';

    return $element;
  }

  public static function updatePreview(&$form, FormStateInterface &$form_state, Request $request) {
    $form_parents = explode('/', $request->query->get('element_parents'));
    $form_parents = array_filter($form_parents, [Element::class, 'child']);
    $element = NestedArray::getValue($form, $form_parents);
    if (empty($element['#files'])) {
      return $element['preview'];
    }

    /** @var \Drupal\file\FileInterface $file */
    $file = reset($element['#files']);
    $image_data = json_decode($file->bookish_image_data->getString(), TRUE);
    $new_image_data = $form_state->getValue(array_merge($element['#parents'], ['bookish_image', 'bookish_image_data']));
    $image_data = array_merge(_bookish_admin_coerce_data($image_data), _bookish_admin_coerce_data($new_image_data));

    $new_preview = static::getPreviewElement($file, ImageStyle::load($element['preview']['#style_name']), $image_data);
    $element['preview'] = array_merge($element['preview'], $new_preview);

    return $element['preview'];
  }

}
