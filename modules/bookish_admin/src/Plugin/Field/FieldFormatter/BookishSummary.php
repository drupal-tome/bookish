<?php

namespace Drupal\bookish_admin\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\text\Plugin\Field\FieldFormatter\TextTrimmedFormatter;

/**
 * Plugin implementation of the 'bookish_summary' formatter.
 *
 * @FieldFormatter(
 *   id = "bookish_summary",
 *   label = @Translation("Summary or trimmed, with limited tags"),
 *   field_types = {
 *     "text_with_summary"
 *   },
 *   quickedit = {
 *     "editor" = "form"
 *   }
 * )
 */
class BookishSummary extends TextTrimmedFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    foreach ($elements as &$element) {
      $text = $element['#text'];
      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);
      foreach ($xpath->query("//*[not(contains('pre br strong em u s div p html body', name()))]") as $node) {
        $node->parentNode->removeChild($node);
      }
      $element['#text'] = Html::serialize($dom);
    }
    return $elements;
  }

}
