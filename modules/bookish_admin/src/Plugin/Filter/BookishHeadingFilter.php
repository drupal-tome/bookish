<?php

namespace Drupal\bookish_admin\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * A filter that adds IDs and anchor links to all headings.
 *
 * @Filter(
 *   id = "bookish_heading_filter",
 *   title = @Translation("Add anchor links to headings"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE
 * )
 */
class BookishHeadingFilter extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    if (stristr($text, '<h') !== FALSE) {
      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);
      foreach ($xpath->query("//*[contains('h1 h2 h3 h4 h5 h6', name())]") as $node) {
        $id = $node->getAttribute('id');
        if (!$id) {
          $id = Html::getId($node->textContent);
          $node->setAttribute('id', $id);
        }
        $wrapper = $dom->createElement('span', '');
        $wrapper->setAttribute('class', 'bookish-heading-link-wrapper');
        $anchor = $dom->createElement('a', '');
        $span = $dom->createElement('span', 'Link to ' . $id . ' heading');
        $span->setAttribute('class', 'visually-hidden');
        $anchor->appendChild($span);
        $anchor->setAttribute('class', 'bookish-heading-link');
        $anchor->setAttribute('href', '#' . $id);
        $wrapper->appendChild($anchor);
        $node->parentNode->replaceChild($wrapper, $node);
        $wrapper->appendChild($node);
      }
      $result->setProcessedText(Html::serialize($dom));
    }

    return $result;
  }

}
