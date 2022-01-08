<?php

namespace Drupal\bookish_admin\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
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

  /**
   * Form API callback: Processes an image_image field element.
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $element = parent::process($element, $form_state, $form);
    $element['#attached'] = ['library' => ['bookish_admin/imageWidget']];

    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];

    $preview_id = 'bookish-image-preview-' . $element['#field_name'] . '-' . $element['#delta'];
    $ajax_settings = [
      'callback' => [static::class, 'updatePreview'],
      'options' => [
        'query' => [
          'element_parents' => implode('/', $element['#array_parents']),
        ],
      ],
      'event' => 'change',
      'wrapper' => $preview_id,
      'progress' => ['type' => 'Dontdisable'],
      'effect' => 'fade',
    ];
    $element['bookish_image_data'] =[
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'bookish-image-data-container',
        ],
      ],
      '#access' => (bool) $item['fids'],
    ];
    $element['bookish_image_data']['brightness'] = [
      '#title' => t('Brightness'),
      '#type' => 'range',
      '#min' => -255,
      '#max' => 255,
      '#ajax' => $ajax_settings,
    ];

    $element['bookish_image_data']['red'] = [
      '#title' => t('Red'),
      '#type' => 'range',
      '#min' => -255,
      '#max' => 255,
      '#ajax' => $ajax_settings,
    ];

    $element['bookish_image_data']['contrast'] = [
      '#title' => t('Contrast'),
      '#type' => 'range',
      '#min' => -100,
      '#max' => 100,
      '#ajax' => $ajax_settings,
    ];

    $element['bookish_image_data']['green'] = [
      '#title' => t('Green'),
      '#type' => 'range',
      '#min' => -255,
      '#max' => 255,
      '#ajax' => $ajax_settings,
    ];

    $element['bookish_image_data']['blur'] = [
      '#title' => t('Blur'),
      '#type' => 'range',
      '#min' => 0,
      '#max' => 100,
      '#ajax' => $ajax_settings,
      '#default_value' => 0,
    ];

    $element['bookish_image_data']['blue'] = [
      '#title' => t('Blue'),
      '#type' => 'range',
      '#min' => -255,
      '#max' => 255,
      '#ajax' => $ajax_settings,
    ];

    $element['bookish_image_data']['grayscale'] = [
      '#title' => t('Grayscale'),
      '#type' => 'range',
      '#min' => 0,
      '#max' => 1,
      '#ajax' => $ajax_settings,
      '#default_value' => 0,
    ];

    if (!empty($element['#files'])) {
      $file = reset($element['#files']);
      $image_data = json_decode($file->bookish_image_data->getString(), TRUE);
      foreach (Element::children($element['bookish_image_data']) as $key) {
        if (!isset($image_data[$key])) {
          continue;
        }
        $element['bookish_image_data'][$key]['#default_value'] = $image_data[$key];
      }
    }

    $element['preview_clone'] = [
      '#type' => 'container',
      '#weight' => $element['preview']['#weight'] -1,
      '#attributes' => [
        'class' => [
          'bookish-image-preview-clone',
        ],
      ],
    ];

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
    $new_image_data = $form_state->getValue(array_merge($element['#parents'], ['bookish_image_data']));
    $image_data = array_merge($image_data, $new_image_data);
    foreach ($image_data as &$value) {
      $value = (int) $value;
    }

    $element['preview']['#theme'] = 'image';
    $element['preview']['#uri'] = Url::fromRoute('bookish_image_preview', [
      'file' => $file->id(),
      'image_style' => $element['preview']['#style_name'],
    ], [
      'query' => [
        'bookish_image_data' => json_encode($image_data),
      ],
    ])->toString();

    return $element['preview'];
  }

}
