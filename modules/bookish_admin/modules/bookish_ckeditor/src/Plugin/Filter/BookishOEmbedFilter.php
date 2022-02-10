<?php

namespace Drupal\bookish_ckeditor\Plugin\Filter;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders non-previewable OEmbed content.
 *
 * @Filter(
 *   id = "bookish_oembed_filter",
 *   title = @Translation("Display embedded OEmbed URLs if possible."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE
 * )
 */
class BookishOEmbedFilter extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * An array of providers, copied from the CKEditor 5 codebase.
   *
   * @var string[]
   */
  protected $providers;

  /**
   * Constructs a BookishOEmbedFitler object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $http_client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    // This list is taken from CKEditor 5.
    // @see https://github.com/ckeditor/ckeditor5/blob/30286f77b39526fce2856b03b9be0ba4cc91d1c7/packages/ckeditor5-media-embed/src/mediaembedediting.js
    $this->providers = [
      [
        'name' => 'dailymotion',
        'url' => '/^dailymotion\.com\/video\/(\w+)/',
      ],
      [
        'name' => 'spotify',
        'url' => [
          '/^open\.spotify\.com\/(artist\/\w+)/',
          '/^open\.spotify\.com\/(album\/\w+)/',
          '/^open\.spotify\.com\/(track\/\w+)/',
        ],
      ],
      [
        'name' => 'youtube',
        'url' => [
          '/^(?:m\.)?youtube\.com\/watch\?v=([\w-]+)/',
          '/^(?:m\.)?youtube\.com\/v\/([\w-]+)/',
          '/^youtube\.com\/embed\/([\w-]+)/',
          '/^youtu\.be\/([\w-]+)/',
        ],
      ],
      [
        'name' => 'vimeo',
        'url' => [
          '/^vimeo\.com\/(\d+)/',
          '/^vimeo\.com\/[^\/]+\/[^\/]+\/video\/(\d+)/',
          '/^vimeo\.com\/album\/[^\/]+\/video\/(\d+)/',
          '/^vimeo\.com\/channels\/[^\/]+\/(\d+)/',
          '/^vimeo\.com\/groups\/[^\/]+\/videos\/(\d+)/',
          '/^vimeo\.com\/ondemand\/[^\/]+\/(\d+)/',
          '/^player\.vimeo\.com\/video\/(\d+)/',
        ],
      ],
      [
        'name' => 'instagram',
        'url' => '/^instagram\.com\/p\/(\w+)/',
      ],
      [
        'name' => 'twitter',
      // \/ added for security reasons.
        'url' => '/^twitter\.com\//',
      ],
      [
        'name' => 'googleMaps',
        'url' => [
          '/^google\.com\/maps/',
          '/^goo\.gl\/maps/',
      // \/ added for security reasons.
          '/^maps\.google\.com\//',
      // \/ added for security reasons.
          '/^maps\.app\.goo\.gl\//',
        ],
      ],
      [
        'name' => 'flickr',
      // \/ added for security reasons.
        'url' => '/^flickr\.com\//',
      ],
      [
        'name' => 'facebook',
      // \/ added for security reasons.
        'url' => '/^facebook\.com\//',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    if (stristr($text, '<oembed') !== FALSE) {
      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);
      foreach ($xpath->query('//oembed') as $node) {
        $url = $node->getAttribute('url');
        [$url, $provider] = $this->getUrlAndProvider($url);
        if (!$url || !$provider) {
          $data = ['html' => 'Cannot find provider for URL.'];
        }
        else {
          $data = $this->getOembedData($url, $provider);
        }
        $wrapper = $dom->createElement('div', '');
        $wrapper->setAttribute('class', 'bookish-oembed-wrapper bookish-oembed-wrapper-' . ($provider ? $provider['name'] : 'unknown'));
        $iframe = $dom->createElement('iframe', '');
        $iframe->setAttribute('class', 'bookish-oembed bookish-oembed-' . ($provider ? $provider['name'] : 'unknown'));
        $iframe->setAttribute('srcdoc', '<style>iframe { max-width: 100% !important; }</style>' . $data['html']);
        if (!empty($provider) && $provider['name'] === 'flickr') {
          $iframe->setAttribute('scrolling', 'no');
        }
        if (isset($data['width'])) {
          $iframe->setAttribute('width', $data['width']);
        }
        if (isset($data['height'])) {
          $iframe->setAttribute('height', $data['height']);
        }
        $iframe->setAttribute('sandbox', 'allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox');
        $wrapper->appendChild($iframe);
        $node->parentNode->replaceChild($wrapper, $node);
      }
      if (!empty($provider) && $provider['name'] === 'twitter') {
        $result->setAttachments([
          'library' => [
            'bookish_ckeditor/iframeSize',
          ],
        ]);
      }
      $result->setProcessedText(Html::serialize($dom));
    }

    return $result;
  }

  /**
   * Fetches the data for a given oEmbed URL.
   *
   * @param string $url
   *   A user-provided URL.
   * @param array $provider
   *   The provider information.
   *
   * @return string
   *   The fetched data.
   */
  protected function getOembedData($url, array $provider) {
    $url = 'https://' . $url;
    $query = [
      'url' => $url,
    ];
    $request_url = '';
    switch ($provider['name']) {
      case 'twitter':
        $request_url = 'https://publish.twitter.com/oembed';
        break;

      case 'flickr':
        $request_url = 'https://www.flickr.com/services/oembed/';
        $query['format'] = 'json';
        break;
    }
    if (!$request_url) {
      return ['html' => 'This provider is not supported.'];
    }

    try {
      $response = $this->httpClient->request('GET', $request_url, [
        RequestOptions::TIMEOUT => 5,
        'query' => $query,
      ]);
    }
    catch (\Exception $e) {
      return ['html' => 'Could not retrieve the oEmbed HTML.'];
    }

    $content = (string) $response->getBody();
    $data = Json::decode($content);

    if (json_last_error() !== JSON_ERROR_NONE) {
      return ['html' => 'Error decoding oEmbed data.'];
    }
    if (empty($data) || !is_array($data) || !isset($data['html']) || !is_string($data['html'])) {
      return ['html' => 'The oEmbed data does not have valid HTML.'];
    }

    return $data;
  }

  /**
   * Looks up a provider by URL.
   *
   * @param string $url
   *   A user-provided URL.
   *
   * @return array|bool
   *   The provider, or FALSE if one cannot be found.
   */
  protected function getProvider($url) {
    foreach ($this->providers as $provider) {
      $provider['url'] = (array) $provider['url'];
      foreach ($provider['url'] as $pattern) {
        if (preg_match($pattern, $url) === 1) {
          return $provider;
        }
      }
    }
    return FALSE;
  }

  /**
   * Gets the lookup URL and provider for a given URL.
   *
   * @param string $url
   *   A user-provided URL.
   *
   * @return array
   *   A tuple in the format [url,provider], [FALSE, FALSE] in case of error.
   *
   * @see https://github.com/ckeditor/ckeditor5/blob/30286f77b39526fce2856b03b9be0ba4cc91d1c7/packages/ckeditor5-media-embed/src/mediaregistry.js#L138
   */
  protected function getUrlAndProvider($url) {
    $url = preg_replace('|^https?://|', '', $url);
    if ($provider = $this->getProvider($url)) {
      return [$url, $provider];
    }
    $url = preg_replace('|^www\.|', '', $url);
    if ($provider = $this->getProvider($url)) {
      return [$url, $provider];
    }
    return [FALSE, FALSE];
  }

}
