<?php

namespace Drupal\bookish_admin\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
class BookishImageWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ElementInfoManagerInterface $element_info) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->elementInfo = $element_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['third_party_settings'], $container->get('element_info'));
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['container'] = [
      '#type' => 'fieldset',
      '#title' => $element['#title'],
      '#description' => $element['#description'],
      '#id' => 'bookish-image-widget-' . $this->fieldDefinition->getName() . '-' . $delta,
    ];
    if ($this->fieldDefinition->getFieldStorageDefinition()->isMultiple()) {
      $element['container']['#type'] = 'container';
    }
    $element_info = $this->elementInfo->getInfo('managed_file');
    $element['container']['fids'] = [
      '#type' => 'managed_file',
      '#upload_location' => $items[$delta]->getUploadLocation(),
      '#upload_validators' => $items[$delta]->getUploadValidators(),
      '#process' => array_merge($element_info['#process'], [[static::class, 'process']]),
      '#default_value' => [$items[$delta]->getValue()['target_id']],
    ];
    $element['container']['lol']['#markup'] = print_r($items[$delta]->getValue(), TRUE);
    return $element;
  }

  public static function process($element, FormStateInterface $form_state, $form) {
    $parents = array_slice($element['#array_parents'], 0, -1);
    $new_options = [
      'query' => [
        'element_parents' => implode('/', $parents),
      ],
    ];
    $field_element = NestedArray::getValue($form, $parents);
    $new_wrapper = $field_element['#id'];
    foreach (Element::children($element) as $key) {
      if (isset($element[$key]['#ajax'])) {
        $element[$key]['#ajax']['options'] = $new_options;
        $element[$key]['#ajax']['wrapper'] = $new_wrapper;
      }
    }
    unset($element['#prefix'], $element['#suffix']);

    return $element;
  }

  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $i => $value) {
      if (empty($value['container']['fids'])) {
        continue;
      }
      $values[$i]['target_id'] = $value['container']['fids'][0];
    }
    return $values;
  }

}
