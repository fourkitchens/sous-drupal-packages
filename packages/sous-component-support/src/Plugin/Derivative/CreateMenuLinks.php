<?php

namespace Drupal\sous_component_support\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds creation links for the Editor dashboard.
 */
final class CreateMenuLinks extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Constructs the menu link deriver.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];
    $weight = 0;
    foreach (['node_type' => 'node', 'media_type' => 'media'] as $bundle_type => $entity_type) {
      $types = $this->entityTypeManager->getStorage($bundle_type)->loadMultiple();
      $class = $this->entityTypeManager->getDefinition($bundle_type)->getClass();
      uasort($types, [$class, 'sort']);
      foreach ($types as $type) {
        $this->derivatives[$bundle_type . '.' . $type->id()] = [
          'title' => $type->label(),
          'route_name' => "entity.$entity_type.add_form",
          'route_parameters' => [$bundle_type => $type->id()],
          'weight' => $weight++,
        ] + $base_plugin_definition;
      }
    }
    return $this->derivatives;
  }

}
