<?php

namespace Drupal\bookish_tags\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The key value factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValueFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The selection plugin manager.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface
   */
  protected $selectionManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a BookishTagsWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key value factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selection_manager
   *   The selection plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, KeyValueFactoryInterface $key_value_factory, AccountInterface $current_user, SelectionPluginManagerInterface $selection_manager, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->keyValueFactory = $key_value_factory;
    $this->currentUser = $current_user;
    $this->selectionManager = $selection_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('keyvalue'),
      $container->get('current_user'),
      $container->get('plugin.manager.entity_reference_selection'),
      $container->get('entity_type.manager'),
    );
  }

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

    $key_value_storage = $this->keyValueFactory->get('entity_autocomplete');
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
    $uid = $this->currentUser->id();
    $handler = $this->selectionManager->getInstance($selection_settings);
    $data = json_decode($values, TRUE);
    if (!is_array($data)) {
      return [];
    }
    $items = [];
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
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
