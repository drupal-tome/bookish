<?php

namespace Drupal\bookish_admin;

use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\ImageStyleInterface;

trait BookishImageFormTrait {

  protected static function buildImageForm($element, $unique_id, FileInterface $file) {
    $tabs_class = 'bookish-image-tabs-' . $unique_id;
    $preview_id = 'bookish-image-preview-' . $unique_id;

    $element['#attached']['library'][] = 'bookish_admin/imageWidget';

    $element['bookish_image'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'bookish-image-container',
        ],
      ],
      '#tree' => TRUE,
    ];

    $element['bookish_image']['tabs'] = [
      '#type' => 'radios',
      '#default_value' => 2,
      '#options' => [
        2 => t('Filter'),
        0 => t('Adjust'),
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
          'bookish-image-re-render',
        ],
      ],
      '#ajax' => array_merge($ajax_settings, ['disable-refocus' => TRUE, 'event' => 'click']),
    ];

    $image_data = _bookish_admin_coerce_data(json_decode($file->bookish_image_data->getString(), TRUE));
    /** @var \Drupal\Core\Image\ImageFactory $image_factory */
    $image_factory = \Drupal::service('image.factory');
    $image = $image_factory->get($file->getFileUri());
    $element['bookish_image']['bookish_image_data']['focal_point']['#default_value'] = implode(',', [
      floor($image->getWidth() / 2),
      floor($image->getHeight() / 2),
    ]);
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
      'Reinas' => [
        "brightness" => 45,
        "contrast" => 9,
        "hue" => 0,
        "saturation" => -50,
        "blur" => 0,
        "grayscale" => 0,
        "red" => 34,
        "green" => 18,
        "blue" => 0,
      ],
      'Pluto' => [
        "brightness" => 26,
        "contrast" => -7,
        "hue" => 0,
        "saturation" => 11,
        "blur" => 0,
        "grayscale" => 0,
        "red" => 0,
        "green" => 0,
        "blue" => 0,
      ],
      'Restful' => [
        "brightness" => -28,
        "contrast" => 0,
        "hue" => 0,
        "saturation" => -31,
        "blur" => 0,
        "grayscale" => 0,
        "red" => 42,
        "green" => 23,
        "blue" => 0,
      ],
      'Leche' => [
        "brightness" => -39,
        "contrast" => -7,
        "hue" => 0,
        "saturation" => -34,
        "blur" => 0,
        "grayscale" => 0,
        "red" => 53,
        "green" => 47,
        "blue" => 34,
      ],
      'Wolfgang' => [
        "brightness" => 7,
        "contrast" => -7,
        "hue" => 0,
        "saturation" => 13,
        "blur" => 0,
        "grayscale" => 0,
        "red" => 0,
        "green" => 0,
        "blue" => -7,
      ],
      'Oden' => [
        "brightness" => -12,
        "contrast" => 0,
        "hue" => 0,
        "saturation" => -15,
        "blur" => 0,
        "grayscale" => 0,
        "red" => 20,
        "green" => 12,
        "blue" => 12,
      ],
      'Felicity' => [
        "brightness" => 12,
        "contrast" => -5,
        "hue" => 0,
        "saturation" => 0,
        "blur" => 0,
        "grayscale" => 0,
        "red" => -18,
        "green" => -4,
        "blue" => -15,
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
        'bookish_preview' => static::getPreviewElement($file, ImageStyle::load('bookish_image_thumbnail'), $image_data),
        'title' => [
          '#markup' => '<a href="#" class="bookish-image-filter-name">' . $name . '</a>',
        ],
      ];
    }

    return $element;
  }

  protected static function getPreviewElement(FileInterface $file, ImageStyleInterface $image_style, $image_data) {
    /** @var \Drupal\Core\Image\ImageFactory $image_factory */
    $image_factory = \Drupal::service('image.factory');
    $derivative_uri = $image_style->buildUri($file->getFileUri());
    if (!file_exists($derivative_uri)) {
      $image_style->createDerivative($file->getFileUri(), $derivative_uri);
    }
    $image = $image_factory->get($derivative_uri);
    $url = Url::fromRoute('bookish_image_preview', [
      'file' => $file->id(),
      'image_style' => $image_style->getName(),
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
