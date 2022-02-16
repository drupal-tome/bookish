<?php

namespace Drupal\bookish_speed\EventSubscriber;

use Drupal\tome_static\Event\ModifyHtmlEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds all possible CSS/JS paths to the Tome build to support routing.
 */
class ModifyHtmlSubscriber implements EventSubscriberInterface {

  /**
   * The name of the state .
   *
   * @var string
   */
  const STATIC_KEY = 'bookish_speed.local_paths';

  /**
   * Reacts to a modify HTML event.
   *
   * @param \Drupal\tome_static\Event\ModifyHtmlEvent $event
   *   The event.
   */
  public function modifyHtml(ModifyHtmlEvent $event) {
    $local_paths = &drupal_static(ModifyHtmlSubscriber::STATIC_KEY, []);
    foreach ($local_paths as $local_path) {
      $event->addInvokePath($local_path);
    }
    $local_paths = [];
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TomeStaticEvents::MODIFY_HTML][] = ['modifyHtml'];
    return $events;
  }

}
