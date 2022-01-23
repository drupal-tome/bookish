<?php

namespace Drupal\bookish_tags\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'bookish_tags_widget' widget.
 *
 * @FieldWidget(
 *   id = "bookish_tags_widget",
 *   label = @Translation("Bookish tags"),
 *   description = @Translation("An autocomplete widget that uses Tagify."),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = TRUE
 * )
 */
class BookishTagsWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $entity = $items->getEntity();
    /** @var \Drupal\taxonomy\TermInterface[] $referenced_entities */
    $referenced_entities = $items->referencedEntities();
    $default_value = [];

    foreach ($referenced_entities as $entity) {
      $default_value[] = [
        'value' => $entity->getName(),
        'entity_id' => $entity->id(),
      ];
    }

    $selection_settings = $this->getFieldSetting('handler_settings') + [
      'match_operator' => 'CONTAINS',
      'match_limit' => 10,
    ];
    $target_type = $this->getFieldSetting('target_type');
    $selection_handler = $this->getFieldSetting('handler');
    $data = serialize($selection_settings) . $target_type . $selection_handler;
    $selection_settings_key = Crypt::hmacBase64($data, Settings::getHashSalt());

    $key_value_storage = \Drupal::keyValue('entity_autocomplete');
    if (!$key_value_storage->has($selection_settings_key)) {
      $key_value_storage->set($selection_settings_key, $selection_settings);
    }

    $element += [
      '#type' => 'textfield',
      '#default_value' => json_encode($default_value),
      '#maxlength' => NULL,
      '#attached' => ['library' => ['bookish_tags/tagify']],
      '#attributes' => [
        'class' => ['bookish-tags-widget'],
        'data-autocomplete-url' => Url::fromRoute('system.entity_autocomplete', [
          'target_type' => $target_type,
          'selection_handler' => $selection_handler,
          'selection_settings_key' => $selection_settings_key,
        ])->toString(),
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues($values, array $form, FormStateInterface $form_state) {
    if (!is_string($values)) {
      return [];
    }
    $target_type = $this->getFieldSetting('target_type');
    $selection_settings = $this->getFieldSetting('handler_settings') + [
      'match_operator' => 'CONTAINS',
      'match_limit' => 10,
      'target_type' => $target_type,
    ];
    $bundle = $this->getAutocreateBundle();
    $uid = \Drupal::currentUser()->id();
    /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface $handler */
    $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance($selection_settings);
    $data = json_decode($values, TRUE);
    $items = [];
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    foreach ($data as $current) {
      // Find if a tag already exists, to avoid duplicates.
      $terms = $term_storage->loadByProperties([
        'name' => $current['value'],
      ]);
      if (!empty($terms)) {
        reset($terms);
        $current['entity_id'] = key($terms);
      }
      if (!empty($current['entity_id'])) {
        $items[] = ['target_id' => $current['entity_id']];
      }
      else {
        $entity = $handler->createNewEntity($target_type, $bundle, $current['value'], $uid);
        $items[] = ['entity' => $entity];
      }
    }
    return $items;
  }

  /**
   * Returns the name of the bundle which will be used for autocreated entities.
   *
   * @return string
   *   The bundle name. If autocreate is not active, NULL will be returned.
   */
  protected function getAutocreateBundle() {
    $bundle = NULL;
    if ($this->getSelectionHandlerSetting('auto_create')) {
      $target_bundles = $this->getSelectionHandlerSetting('target_bundles');
      // If there's no target bundle at all, use the target_type. It's the
      // default for bundleless entity types.
      if (empty($target_bundles)) {
        $bundle = $this->getFieldSetting('target_type');
      }
      // If there's only one target bundle, use it.
      elseif (count($target_bundles) == 1) {
        $bundle = reset($target_bundles);
      }
      // If there's more than one target bundle, use the autocreate bundle
      // stored in selection handler settings.
      elseif (!$bundle = $this->getSelectionHandlerSetting('auto_create_bundle')) {
        // If no bundle has been set as auto create target means that there is
        // an inconsistency in entity reference field settings.
        trigger_error(sprintf(
          "The 'Create referenced entities if they don't already exist' option is enabled but a specific destination bundle is not set. You should re-visit and fix the settings of the '%s' (%s) field.",
          $this->fieldDefinition->getLabel(),
          $this->fieldDefinition->getName()
        ), E_USER_WARNING);
      }
    }

    return $bundle;
  }

  /**
   * Returns the value of a setting for the entity reference selection handler.
   *
   * @param string $setting_name
   *   The setting name.
   *
   * @return mixed
   *   The setting value.
   */
  protected function getSelectionHandlerSetting($setting_name) {
    $settings = $this->getFieldSetting('handler_settings');
    return $settings[$setting_name] ?? NULL;
  }

}
