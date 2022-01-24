<?php

namespace Drupal\bookish_image\Plugin\Field\FieldWidget;

use Drupal\bookish_image\BookishImageFormTrait;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
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

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'show_zoom' => 0,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#show_zoom'] = $this->getSetting('show_zoom');
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $element = parent::process($element, $form_state, $form);

    $unique_id = $element['#field_name'] . '-' . $element['#delta'];
    $preview_id = 'bookish-image-preview-' . $unique_id;

    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];

    if (!empty($element['#files'])) {
      /** @var \Drupal\file\FileInterface $file */
      $file = reset($element['#files']);
      $element = static::buildImageForm($element, $unique_id, $file, \Drupal::service('image.factory'), ImageStyle::load('bookish_image_thumbnail'));
      $element['bookish_image']['bookish_image_data']['zoom']['#access'] = !!$element['#show_zoom'];
    }

    $element['preview']['#prefix'] = '<div class="bookish-image-preview" id="' . $preview_id . '">';
    $element['preview']['#suffix'] = '</div>';

    return $element;
  }

  /**
   * Updates the preview element after an AJAX call.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   The render array representing the preview.
   *
   * @see \Drupal\bookish_image\BookishImageFormTrait::getAjaxSettings
   */
  public static function updatePreview(array &$form, FormStateInterface &$form_state, Request $request) {
    $form_parents = explode('/', $request->query->get('element_parents'));
    $form_parents = array_filter($form_parents, [Element::class, 'child']);
    $element = NestedArray::getValue($form, $form_parents);
    if (empty($element['#files'])) {
      return $element['preview'];
    }

    /** @var \Drupal\file\FileInterface $file */
    $file = reset($element['#files']);
    $image_data = json_decode($file->bookish_image_data->getString(), TRUE);
    $new_image_data = $form_state->getValue(array_merge($element['#parents'], [
      'bookish_image',
      'bookish_image_data',
    ]));
    $image_data = array_merge(_bookish_image_coerce_data($image_data), _bookish_image_coerce_data($new_image_data));

    $new_preview = static::getPreviewElement($file, ImageStyle::load($element['preview']['#style_name']), $image_data, \Drupal::service('image.factory'));
    $element['preview'] = array_merge($element['preview'], $new_preview);

    return $element['preview'];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['show_zoom'] = [
      '#title' => t('Show the "Zoom" slider.'),
      '#description' => t('The "Zoom" slider is not always useful, so it\'s hidden by default.'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('show_zoom'),
      '#weight' => 16,
    ];

    return $element;
  }

}
