<?php

namespace Drupal\ai_content_strategy\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;
use Drupal\Core\Render\RendererInterface;

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
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MenuActiveTrailInterface $menu_active_trail,
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory,
    RendererInterface $renderer
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->menuActiveTrail = $menu_active_trail;
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->renderer = $renderer;
  }

  /**
   * Gets the front page content as plain text.
   *
   * @return string
   *   The front page content as plain text.
   */
  protected function getFrontPageContent(): string {
    \Drupal::messenger()->addStatus('Fetching front page content...');
    
    // Get the front page path from configuration
    $front_uri = $this->configFactory->get('system.site')->get('page.front');
    \Drupal::messenger()->addStatus('Front page URI: ' . $front_uri);
    
    if (empty($front_uri)) {
      \Drupal::messenger()->addWarning('No front page URI found in configuration.');
      return '';
    }

    // Extract node ID if front page is a node
    if (preg_match('/node\/(\d+)/', $front_uri, $matches)) {
      $nid = $matches[1];
      try {
        $node = $this->entityTypeManager->getStorage('node')->load($nid);
        if ($node) {
          \Drupal::messenger()->addStatus('Front page node found.');
          
          // Build the node view
          $view_builder = $this->entityTypeManager->getViewBuilder('node');
          $build = $view_builder->view($node);
          
          // Render the node
          $html = $this->renderer->renderPlain($build);
          
          // Convert HTML to plain text
          $text = strip_tags($html);
          \Drupal::messenger()->addStatus('Retrieved front page content: ' . strlen($text) . ' characters');
          
          return $text;
        }
      }
      catch (\Exception $e) {
        \Drupal::messenger()->addError('Error loading front page node: ' . $e->getMessage());
      }
    }
    
    \Drupal::messenger()->addWarning('Could not retrieve front page content.');
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
      \Drupal::messenger()->addMessage('Getting site structure...');
      
      // Get front page content
      $front_content = $this->getFrontPageContent();
      
      // Get primary menu
      $menu_tree = $this->menuActiveTrail->getActiveTrailIds('main');
      $menu_items = [];
      
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
      
      \Drupal::messenger()->addMessage('Found ' . count($menu_items) . ' menu items');

      return [
        'homepage' => [
          'title' => $this->configFactory->get('system.site')->get('name'),
          'content' => $front_content,
        ],
        'primary_menu' => $menu_items,
      ];
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError('Error getting site structure: ' . $e->getMessage());
      return [
        'homepage' => ['title' => '', 'content' => ''],
        'primary_menu' => [],
      ];
    }
  }

  /**
   * Gets the sitemap URLs.
   *
   * @return array
   *   An array containing URLs from the sitemap and any error messages.
   */
  public function getSitemapUrls(): array {
    try {
      // Generate absolute URL for sitemap.xml
      $sitemap_url = Url::fromUserInput('/sitemap.xml')
        ->setAbsolute()
        ->toString();

      $response = $this->httpClient->request('GET', $sitemap_url);
      $xml_content = $response->getBody()->getContents();
      
      if ($xml = simplexml_load_string($xml_content)) {
        $urls = [];
        foreach ($xml->url as $url) {
          $urls[] = (string) $url->loc;
        }
        return [
          'urls' => $urls,
          'error' => NULL,
        ];
      }
      
      return [
        'urls' => [],
        'error' => $this->t('The sitemap.xml file could not be parsed. Please ensure it contains valid XML.'),
      ];
    }
    catch (GuzzleException $e) {
      return [
        'urls' => [],
        'error' => $this->t('Could not fetch sitemap.xml. Error: @error', ['@error' => $e->getMessage()]),
      ];
    }
    catch (\Exception $e) {
      return [
        'urls' => [],
        'error' => $this->t('An unexpected error occurred while processing sitemap.xml. Error: @error', ['@error' => $e->getMessage()]),
      ];
    }
  }
} 