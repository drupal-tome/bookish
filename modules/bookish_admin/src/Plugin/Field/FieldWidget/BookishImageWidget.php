<?php

namespace Drupal\bookish_admin\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
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

    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];

    $preview_id = 'bookish-image-preview-' . $element['#field_name'] . '-' . $element['#delta'];
    $element['brightness'] = [
      '#title' => t('Brightness'),
      '#type' => 'range',
      '#min' => -255,
      '#max' => 255,
      '#access' => (bool) $item['fids'],
      '#ajax' => [
        'callback' => [static::class, 'updatePreview'],
        'options' => [
          'query' => [
            'element_parents' => implode('/', $element['#array_parents']),
          ],
        ],
        'event' => 'change',
        'wrapper' => $preview_id,
      ],
    ];

    if (!empty($element['#files'])) {
      $file = reset($element['#files']);
      $image_data = json_decode($file->bookish_image_data->getString(), TRUE);
      $element['brightness']['#default_value'] = $image_data['brightness'];
    }

    $element['preview']['#prefix'] = '<div id="' . $preview_id . '">';
    $element['preview']['#suffix'] = '</div>';

    return $element;
  }

  public static function updatePreview(&$form, FormStateInterface &$form_state, Request $request) {
    $form_parents = explode('/', $request->query->get('element_parents'));
    $form_parents = array_filter($form_parents, [Element::class, 'child']);
    $element = NestedArray::getValue($form, $form_parents);
    if (!empty($element['#files'])) {
      /** @var \Drupal\file\FileInterface $file */
      $file = reset($element['#files']);
      $image_data = json_decode($file->bookish_image_data->getString(), TRUE);
      $image_data['brightness'] = $element['brightness']['#value'];
      $file->bookish_image_data = json_encode($image_data);
      /** @var \Drupal\image\ImageStyleInterface $image_style */
      $image_style = \Drupal::entityTypeManager()->getStorage('image_style')->load($element['preview']['#style_name']);
      if ($image_style) {
        /** @var \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator */
        $file_url_generator = \Drupal::service('file_url_generator');
        /** @var \Drupal\Core\File\FileSystemInterface $file_system */
        $file_system = \Drupal::service('file_system');
        // @todo Make more unique.
        $derivative_uri = 'public://bookish-image-preview/' . $file->getFileUri();
        $file_system->delete($derivative_uri);
        $image_style->createDerivative($file->getFileUri(), $derivative_uri);
        $element['preview']['#theme'] = 'image';
        $element['preview']['#uri'] = $file_url_generator->generate($derivative_uri)->toString() . '?cache_bypass=' . md5($file->bookish_image_data->getString());
      }
    }
    return $element['preview'];
  }

}
