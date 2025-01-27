<?php

namespace Drupal\ai_content_strategy\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;

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
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MenuActiveTrailInterface $menu_active_trail,
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->menuActiveTrail = $menu_active_trail;
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
  }

  /**
   * Gets the homepage and primary menu structure.
   *
   * @return array
   *   An array containing homepage and menu information.
   */
  public function getSiteStructure(): array {
    $homepage = $this->getHomepageContent();
    $menu_items = $this->getPrimaryMenuItems();

    return [
      'homepage' => $homepage,
      'primary_menu' => $menu_items,
    ];
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

  /**
   * Gets the homepage content.
   *
   * @return array
   *   Homepage content information.
   */
  protected function getHomepageContent(): array {
    $config = $this->configFactory->get('system.site');
    $front_uri = $config->get('page.front') ?: '/';
    
    // Get the node if the homepage is a node.
    if (preg_match('/node\/(\d+)/', $front_uri, $matches)) {
      $node = $this->entityTypeManager->getStorage('node')->load($matches[1]);
      if ($node) {
        return [
          'title' => $node->getTitle(),
          'type' => $node->bundle(),
          'uri' => $front_uri,
        ];
      }
    }

    return [
      'uri' => $front_uri,
    ];
  }

  /**
   * Gets the primary menu items.
   *
   * @return array
   *   Array of primary menu items.
   */
  protected function getPrimaryMenuItems(): array {
    $menu_tree = $this->entityTypeManager->getStorage('menu_link_content')
      ->loadByProperties(['menu_name' => 'main']);
    
    $items = [];
    foreach ($menu_tree as $menu_link) {
      $items[] = [
        'title' => $menu_link->getTitle(),
        'url' => $menu_link->getUrlObject()->toString(),
        'weight' => $menu_link->getWeight(),
      ];
    }

    return $items;
  }
} 