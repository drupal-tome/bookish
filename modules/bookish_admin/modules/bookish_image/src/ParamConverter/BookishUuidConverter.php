<?php

namespace Drupal\bookish_image\ParamConverter;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\ParamConverter\EntityConverter;
use Symfony\Component\Routing\Route;

/**
 * Converts file UUIDs for the Bookish image form route.
 */
class BookishUuidConverter extends EntityConverter {

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (!Uuid::isValid($value)) {
      return parent::convert($value, $definition, $name, $defaults);
    }
    $entity_type_id = $this->getEntityTypeFromDefaults($definition, $name, $defaults);
    $uuid_key = $this->entityTypeManager->getDefinition($entity_type_id)
      ->getKey('uuid');
    if ($storage = $this->entityTypeManager->getStorage($entity_type_id)) {
      if (!$entities = $storage->loadByProperties([$uuid_key => $value])) {
        return NULL;
      }
      $entity = reset($entities);
      return $entity;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return $route->getRequirement('_convert_uuid') === 'TRUE' && isset($definition['type']) && $definition['type'] === 'entity:file';
  }

}
