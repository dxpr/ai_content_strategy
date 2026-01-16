<?php

namespace Drupal\ai_content_strategy\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Utility\Error;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for analyzing existing site content.
 */
class ContentAnalyzer {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The menu active trail service.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $menuActiveTrail;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a ContentAnalyzer object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menu_active_trail
   *   The menu active trail service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MenuActiveTrailInterface $menu_active_trail,
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory,
    RendererInterface $renderer,
    ModuleHandlerInterface $module_handler,
    LoggerInterface $logger,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->menuActiveTrail = $menu_active_trail;
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->renderer = $renderer;
    $this->moduleHandler = $module_handler;
    $this->logger = $logger;
  }

  /**
   * Gets the front page content as plain text.
   *
   * @return string
   *   The front page content as plain text.
   */
  protected function getFrontPageContent(): string {
    // Get the front page path from configuration.
    $front_uri = $this->configFactory->get('system.site')->get('page.front');

    if (empty($front_uri)) {
      return '';
    }

    // Extract node ID if front page is a node.
    if (preg_match('/node\/(\d+)/', $front_uri, $matches)) {
      $nid = $matches[1];
      try {
        $node = $this->entityTypeManager->getStorage('node')->load($nid);
        if ($node) {
          // Build the node view.
          $view_builder = $this->entityTypeManager->getViewBuilder('node');
          $build = $view_builder->view($node);

          // Render the node.
          $html = $this->renderer->renderInIsolation($build);

          // Convert HTML to plain text.
          return strip_tags($html);
        }
      }
      catch (\Exception $e) {
        // Log error but continue with empty content.
        Error::logException($this->logger, $e);
      }
    }

    return '';
  }

  /**
   * Gets the site structure including homepage, navigation, and URLs.
   *
   * @return array
   *   Site structure array.
   */
  public function getSiteStructure(): array {
    try {
      // Get front page content.
      $front_content = $this->getFrontPageContent();

      // Get primary menu if Menu UI module is available.
      $menu_items = [];
      if ($this->menuActiveTrail && $this->moduleHandler->moduleExists('menu_ui')) {
        try {
          $menu_tree = $this->menuActiveTrail->getActiveTrailIds('main');

          foreach ($menu_tree as $id => $active) {
            if ($id === 'main:') {
              continue;
            }

            $parts = explode(':', $id);
            $menu_items[] = [
              'title' => end($parts),
              'url' => '/' . implode('/', array_slice($parts, 1)),
            ];
          }
        }
        catch (\Exception $e) {
          Error::logException($this->logger, $e);
        }
      }

      return [
        'homepage' => [
          'title' => $this->configFactory->get('system.site')->get('name'),
          'content' => $front_content,
        ],
        'primary_menu' => $menu_items,
      ];
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e);
      return [
        'homepage' => ['title' => '', 'content' => ''],
        'primary_menu' => [],
      ];
    }
  }

  /**
   * Gets the sitemap URLs.
   *
   * This method processes sitemaps iteratively to avoid recursion depth issues.
   * It handles two types of XML structures:
   *   1. <urlset>: Contains direct page URLs.
   *   2. <sitemapindex>: Contains links to other sitemaps.
   *
   * @return array
   *   An array containing:
   *   - 'urls': (array) A flat list of all discovered URLs.
   *   - 'error': (string|null) Error message if fetching or parsing failed.
   */
  public function getSitemapUrls(): array {
    // Generate absolute URL for sitemap.xml.
    $sitemap_url = Url::fromUserInput('/sitemap.xml')
      ->setAbsolute()
      ->toString();

    $urls = [];
    // Queue of sitemap URLs to be processed.
    $sitemaps_to_process = [$sitemap_url];
    // Track processed sitemaps to prevent infinite loops
    // from circular references.
    $processed_sitemaps = [];

    while (!empty($sitemaps_to_process)) {
      $current_url = array_shift($sitemaps_to_process);

      // Avoid infinite loops if sitemaps reference each other.
      if (isset($processed_sitemaps[$current_url])) {
        continue;
      }
      $processed_sitemaps[$current_url] = TRUE;

      $result = $this->fetchSitemapXml($current_url);

      if ($result['error']) {
        return [
          'urls' => [],
          'error' => $result['error'],
        ];
      }

      $xml = $result['xml'];

      // Case 1: The sitemap contains direct URLs (<urlset> structure).
      if (isset($xml->url)) {
        foreach ($xml->url as $url_entry) {
          $urls[] = (string) $url_entry->loc;
        }
      }

      // Case 2: The sitemap is an index pointing to other sitemaps
      // (<sitemapindex> structure).
      if (isset($xml->sitemap)) {
        foreach ($xml->sitemap as $sub_sitemap) {
          $sitemaps_to_process[] = (string) $sub_sitemap->loc;
        }
      }
    }

    return [
      'urls' => $urls,
      'error' => NULL,
    ];
  }

  /**
   * Fetches and parses a sitemap XML.
   *
   * @param string $url
   *   The URL of the sitemap.
   *
   * @return array
   *   An array with:
   *   - 'xml': (SimpleXMLElement|null) The parsed XML object on success.
   *   - 'error': (string|null) Error message on failure.
   */
  protected function fetchSitemapXml(string $url): array {
    try {
      // Fetch the XML content via HTTP.
      $response = $this->httpClient->request('GET', $url);
      $xml_content = $response->getBody()->getContents();

      // Attempt to parse the XML string.
      if ($xml = simplexml_load_string($xml_content)) {
        return [
          'xml' => $xml,
          'error' => NULL,
        ];
      }

      return [
        'xml' => NULL,
        'error' => $this->t('The sitemap.xml file could not be parsed. Please ensure it contains valid XML.'),
      ];
    }
    catch (GuzzleException $e) {
      // Handle HTTP-level errors (e.g., 404, connection issues).
      return [
        'xml' => NULL,
        'error' => $this->t('Could not fetch sitemap.xml. Error: @error', ['@error' => $e->getMessage()]),
      ];
    }
    catch (\Exception $e) {
      // Handle any other unexpected exceptions.
      return [
        'xml' => NULL,
        'error' => $this->t('An unexpected error occurred while processing sitemap.xml. Error: @error', ['@error' => $e->getMessage()]),
      ];
    }
  }

}
