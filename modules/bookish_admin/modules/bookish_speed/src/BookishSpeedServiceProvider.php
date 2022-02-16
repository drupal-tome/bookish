<?php

namespace Drupal\bookish_speed;

use Drupal\bookish_speed\EventSubscriber\ModifyHtmlSubscriber;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

/**
 * Registers services in the container.
 */
class BookishSpeedServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');
    if (isset($modules['tome_static'])) {
      $container->register('bookish_speed.modify_html_subscriber', ModifyHtmlSubscriber::class)
        ->addTag('event_subscriber');
    }
  }

}
