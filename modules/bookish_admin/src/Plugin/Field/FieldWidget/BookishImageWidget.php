<?php

namespace Drupal\bookish_admin\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
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

  /**
   * Form API callback: Processes an image_image field element.
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $element = parent::process($element, $form_state, $form);
    $element['#attached'] = ['library' => ['bookish_admin/imageWidget']];

    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];

    $element['bookish_image'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'bookish-image-container',
        ],
      ],
    ];
    $tabs_class = 'bookish-image-tabs-' . $element['#field_name'] . '-' . $element['#delta'];
    $element['bookish_image']['tabs'] = [
      '#type' => 'radios',
      '#default_value' => 2,
      '#options' => [
        0 => t('Color'),
        2 => t('Filter'),
        1 => t('Crop'),
      ],
      '#attributes' => [
        'class' => [
          $tabs_class,
        ],
      ],
      '#prefix' => '<div class="bookish-image-tabs">',
      '#suffix' => '</div>'
    ];

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
      'progress' => ['type' => 'none'],
      'effect' => 'fade',
      'speed' => 'fast',
    ];
    $element['bookish_image']['bookish_image_data'] =[
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'bookish-image-data-container',
        ],
      ],
      '#access' => (bool) $item['fids'],
      '#states' => [
        'visible' => [
          ".$tabs_class" => ['value' => 0],
        ],
      ],
    ];
    $element['bookish_image']['bookish_image_data']['brightness'] = [
      '#title' => t('Brightness'),
      '#type' => 'range',
      '#min' => -255,
      '#max' => 255,
      '#ajax' => $ajax_settings,
    ];

    $element['bookish_image']['bookish_image_data']['contrast'] = [
      '#title' => t('Contrast'),
      '#type' => 'range',
      '#min' => -100,
      '#max' => 100,
      '#ajax' => $ajax_settings,
    ];

    $element['bookish_image']['bookish_image_data']['saturation'] = [
      '#title' => t('Saturation'),
      '#type' => 'range',
      '#min' => -100,
      '#max' => 100,
      '#ajax' => $ajax_settings,
      '#default_value' => 0,
    ];

    // $element['bookish_image']['bookish_image_data']['fade'] = [
    //   '#title' => t('Fade'),
    //   '#type' => 'range',
    //   '#min' => -100,
    //   '#max' => 100,
    //   '#ajax' => $ajax_settings,
    //   '#default_value' => 0,
    // ];

    $element['bookish_image']['bookish_image_data']['blur'] = [
      '#title' => t('Blur'),
      '#type' => 'range',
      '#min' => 0,
      '#max' => 100,
      '#ajax' => $ajax_settings,
      '#default_value' => 0,
    ];

    $element['bookish_image']['bookish_image_data']['grayscale'] = [
      '#title' => t('Grayscale'),
      '#type' => 'range',
      '#min' => 0,
      '#max' => 1,
      '#ajax' => $ajax_settings,
      '#default_value' => 0,
    ];

    $element['bookish_image']['bookish_image_data']['hue'] = [
      '#title' => t('Hue'),
      '#type' => 'range',
      '#min' => 0,
      '#max' => 360,
      '#ajax' => $ajax_settings,
      '#default_value' => 0,
    ];

    $element['bookish_image']['bookish_image_data']['red'] = [
      '#title' => t('Red'),
      '#type' => 'range',
      '#min' => -255,
      '#max' => 255,
      '#ajax' => $ajax_settings,
    ];

    $element['bookish_image']['bookish_image_data']['green'] = [
      '#title' => t('Green'),
      '#type' => 'range',
      '#min' => -255,
      '#max' => 255,
      '#ajax' => $ajax_settings,
    ];

    $element['bookish_image']['bookish_image_data']['blue'] = [
      '#title' => t('Blue'),
      '#type' => 'range',
      '#min' => -255,
      '#max' => 255,
      '#ajax' => $ajax_settings,
    ];

    $element['bookish_image']['bookish_image_data']['focal_point'] = [
      '#title' => t('Focal point'),
      '#type' => 'hidden',
      '#attributes' => [
        'class' => [
          'bookish-image-focal-point-input',
        ],
      ],
      '#ajax' => array_merge($ajax_settings, ['disable-refocus' => TRUE]),
    ];

    $element['bookish_image']['re_render_button'] = [
      '#type' => 'button',
      '#value' => t('Re-render preview'),
      '#limit_validation_errors' => [],
      '#attributes' => [
        'class' => [
          'visually-hidden',
          'bookish-image-re-render',
        ],
      ],
      '#ajax' => array_merge($ajax_settings, ['disable-refocus' => TRUE, 'event' => 'click']),
    ];

    if (!empty($element['#files'])) {
      /** @var \Drupal\file\FileInterface $file */
      $file = reset($element['#files']);
      $image_data = json_decode($file->bookish_image_data->getString(), TRUE);
      if (!is_array($image_data)) {
        $image_data = [];
      }
      foreach (Element::children($element['bookish_image']['bookish_image_data']) as $key) {
        if (!isset($image_data[$key])) {
          continue;
        }
        if ($key === 'focal_point') {
          $element['bookish_image']['bookish_image_data'][$key]['#default_value'] = implode(',', $image_data[$key]);
        } else {
          $element['bookish_image']['bookish_image_data'][$key]['#default_value'] = $image_data[$key];
        }
      }
      /** @var \Drupal\Core\Image\ImageFactory $image_factory */
      $image_factory = \Drupal::service('image.factory');
      $image = $image_factory->get($file->getFileUri());
      $element['bookish_image']['focal_point'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'bookish-image-focal-point-container',
          ],
        ],
        'thumbnail' => [
          '#theme' => 'image',
          '#uri' => $file->getFileUri(),
          '#width' => $image->getWidth(),
          '#height' => $image->getHeight(),
          '#attributes' => [
            'draggable' => 'false',
          ],
        ],
        'description' => [
          '#markup' => '<div class="form-item__description">' . t('Drupal crops images according to configured image styles. Click the image above to choose the point you want centered when cropped.') . '</div>'
        ],
        '#states' => [
          'visible' => [
            ".$tabs_class" => ['value' => 1],
          ],
        ],
      ];
    }

    $element['preview']['#prefix'] = '<div class="bookish-image-preview" id="' . $preview_id . '">';
    $element['preview']['#suffix'] = '</div>';

    $element['bookish_image']['filters'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'bookish-image-filter-container',
        ],
      ],
      '#states' => [
        'visible' => [
          ".$tabs_class" => ['value' => 2],
        ],
      ],
    ];
    $filters = [
      'Original' => [
        "brightness" => 0,
        "contrast" => 0,
        "hue" => 0,
        "saturation" => 0,
        "blur" => 0,
        "grayscale" => 0,
        "red" => 0,
        "green" => 0,
        "blue" => 0,
      ],
      'Calderwood' => [
        "brightness" => 23,
        "contrast" => -15,
        "hue" => 0,
        "saturation" => 0,
        "blur" => 0,
        "grayscale" => 0,
        "red" => 1,
        "green" => 12,
        "blue" => 15,
      ],
      'Tartan' => [
        "brightness" => 85,
        "contrast" => -4,
        "hue" => 0,
        "saturation" => -12,
        "blur" => 0,
        "grayscale" => 0,
        "red" => -31,
        "green" => -26,
        "blue" => -28,
      ],
      'Lunar' => [
        "brightness" => 36,
        "contrast" => -8,
        "hue" => 0,
        "saturation" => -49,
        "blur" => 0,
        "grayscale" => 1,
        "red" => 0,
        "green" => 0,
        "blue" => 0,
      ],
      'Sparrow' => [
        "brightness" => 28,
        "contrast" => -12,
        "hue" => 0,
        "saturation" => -28,
        "blur" => 0,
        "grayscale" => 0,
        "red" => 26,
        "green" => 15,
        "blue" => -1,
      ],
    ];
    foreach ($filters as $name => $image_data) {
      $element['bookish_image']['filters'][$name] = [
        '#type' => 'container',
        '#attributes' => [
          'data-image-data' => json_encode($image_data),
          'class' => [
            'bookish-image-filter',
          ],
        ],
        'preview' => static::getPreviewElement($file, 'bookish_image_thumbnail', $image_data),
        'title' => [
          '#markup' => '<a href="#" class="bookish-image-filter-name">' . $name . '</a>',
        ],
      ];
    }

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

    $new_preview = static::getPreviewElement($file, $element['preview']['#style_name'], $image_data);
    $element['preview'] = array_merge($element['preview'], $new_preview);

    return $element['preview'];
  }

  protected static function getPreviewElement(FileInterface $file, $style_name, $image_data) {
    /** @var \Drupal\Core\Image\ImageFactory $image_factory */
    $image_factory = \Drupal::service('image.factory');
    $image_style = ImageStyle::load($style_name);
    $derivative_uri = $image_style->buildUri($file->getFileUri());
    if (!file_exists($derivative_uri)) {
      $image_style->createDerivative($file->getFileUri(), $derivative_uri);
    }
    $image = $image_factory->get($derivative_uri);
    $url = Url::fromRoute('bookish_image_preview', [
      'file' => $file->id(),
      'image_style' => $style_name,
    ]);
    // @todo Change after https://www.drupal.org/project/drupal/issues/2630920
    // is fixed.
    $token = \Drupal::csrfToken()->get($url->getInternalPath());
    $url->setOptions(['query' => [
      'token' => $token,
      'bookish_image_data' => json_encode($image_data),
    ]]);
    return [
      '#theme' => 'image',
      '#width' => $image->getWidth(),
      '#height' => $image->getHeight(),
      '#uri' => $url->toString(),
    ];
  }

}
