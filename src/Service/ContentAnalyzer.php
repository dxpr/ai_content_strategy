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
   * Gets the front page content as structured plain text.
   *
   * Parses the HTML to extract headings, paragraphs, and list items
   * rather than losing all structure via strip_tags().
   *
   * @return string
   *   The front page content as structured plain text.
   */
  protected function getFrontPageContent(): string {
    $front_uri = $this->configFactory->get('system.site')->get('page.front');

    if (empty($front_uri)) {
      return '';
    }

    if (!preg_match('/node\/(\d+)/', $front_uri, $matches)) {
      return '';
    }

    $nid = $matches[1];
    try {
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      if (!$node) {
        return '';
      }

      $view_builder = $this->entityTypeManager->getViewBuilder('node');
      $build = $view_builder->view($node);
      $html = (string) $this->renderer->renderInIsolation($build);

      return $this->extractStructuredText($html);
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e);
      return '';
    }
  }

  /**
   * Extracts structured plain text from HTML, preserving headings and paragraphs.
   *
   * @param string $html
   *   The rendered HTML.
   *
   * @return string
   *   Structured plain text with headings, paragraphs, and list items.
   */
  protected function extractStructuredText(string $html): string {
    $doc = new \DOMDocument();
    @$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);

    $xpath = new \DOMXPath($doc);
    $nodes = $xpath->query('//h1|//h2|//h3|//p[not(ancestor::li)]|//li');

    $parts = [];
    $list_count = 0;

    foreach ($nodes as $node) {
      $text = trim($node->textContent);
      if (empty($text)) {
        continue;
      }

      $tag = $node->nodeName;

      if (in_array($tag, ['h1', 'h2', 'h3'], TRUE)) {
        $level = (int) $tag[1];
        $parts[] = str_repeat('#', $level) . ' ' . $text;
      }
      elseif ($tag === 'p' && mb_strlen($text) > 20) {
        $parts[] = $text;
      }
      elseif ($tag === 'li' && mb_strlen($text) > 5 && $list_count < 15) {
        $parts[] = '- ' . mb_substr($text, 0, 120);
        $list_count++;
      }
    }

    $result = implode("\n\n", $parts);

    $result = preg_replace('/[ \t]+/', ' ', $result);
    $result = preg_replace('/\n{3,}/', "\n\n", $result);
    return mb_substr(trim($result), 0, 8000);
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
        'ai_visibility' => $this->getAiVisibilityData(),
      ];
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e);
      return [
        'homepage' => ['title' => '', 'content' => ''],
        'primary_menu' => [],
        'ai_visibility' => [],
      ];
    }
  }

  /**
   * Gets AI visibility data: robots.txt crawler rules, llms.txt, sitemap quality.
   *
   * @return array
   *   AI visibility data with keys: robots_txt, llms_txt, sitemap_quality.
   */
  public function getAiVisibilityData(): array {
    return [
      'robots_txt' => $this->checkRobotsTxt(),
      'llms_txt' => $this->checkLlmsTxt(),
      'sitemap_quality' => $this->checkSitemapQuality(),
    ];
  }

  /**
   * Checks robots.txt for AI crawler directives.
   *
   * Parses User-agent groups (including consecutive User-agent lines that
   * share a single set of directives) and checks for full-site Disallow.
   *
   * @return array
   *   Array with 'exists', 'ai_crawlers_blocked', 'ai_crawlers_allowed',
   *   'summary'.
   */
  protected function checkRobotsTxt(): array {
    $result = [
      'exists' => FALSE,
      'ai_crawlers_blocked' => [],
      'ai_crawlers_allowed' => [],
      'summary' => '',
    ];

    $ai_bots = [
      'GPTBot',
      'ChatGPT-User',
      'ClaudeBot',
      'Claude-Web',
      'PerplexityBot',
      'Google-Extended',
      'Amazonbot',
      'Bytespider',
      'CCBot',
      'Cohere-ai',
      'FacebookBot',
      'anthropic-ai',
    ];

    try {
      $robots_url = Url::fromUserInput('/robots.txt')
        ->setAbsolute()
        ->toString();

      $response = $this->httpClient->request('GET', $robots_url, [
        'timeout' => 5,
        'http_errors' => FALSE,
      ]);

      if ($response->getStatusCode() !== 200) {
        $result['summary'] = 'No robots.txt found.';
        return $result;
      }

      $content = $response->getBody()->getContents();
      $result['exists'] = TRUE;

      // Parse into groups: consecutive User-agent lines share one directive
      // block, per the robots.txt specification.
      $lines = explode("\n", $content);
      $current_agents = [];
      $blocked_set = [];

      foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, '#')) {
          continue;
        }

        // Blank lines end a group per the robots.txt specification.
        if (empty($line)) {
          $current_agents = [];
          continue;
        }

        if (preg_match('/^User-agent:\s*(.+)/i', $line, $matches)) {
          $current_agents[] = trim($matches[1]);
        }
        else {
          if (preg_match('/^Disallow:\s*\/\s*$/i', $line)) {
            foreach ($current_agents as $agent) {
              if ($agent === '*') {
                foreach ($ai_bots as $bot) {
                  $blocked_set[$bot] ??= 'wildcard';
                }
              }
              else {
                foreach ($ai_bots as $bot) {
                  if (strcasecmp($agent, $bot) === 0) {
                    $blocked_set[$bot] = 'explicit';
                  }
                }
              }
            }
          }
          // Any non-User-agent directive ends the agent accumulation.
          $current_agents = [];
        }
      }

      foreach ($blocked_set as $bot => $source) {
        $label = $source === 'wildcard' ? $bot . ' (via wildcard)' : $bot;
        $result['ai_crawlers_blocked'][] = $label;
      }

      foreach ($ai_bots as $bot) {
        if (!isset($blocked_set[$bot])) {
          $result['ai_crawlers_allowed'][] = $bot;
        }
      }

      if (!empty($result['ai_crawlers_blocked'])) {
        $result['summary'] = 'Blocked: ' . implode(', ', $result['ai_crawlers_blocked']);
      }
      else {
        $result['summary'] = 'No AI crawlers are explicitly blocked.';
      }

    }
    catch (\Exception $e) {
      $result['summary'] = 'Could not check robots.txt: ' . $e->getMessage();
    }

    return $result;
  }

  /**
   * Checks for llms.txt presence and content.
   *
   * @return array
   *   Array with 'exists', 'content_preview', 'summary'.
   */
  protected function checkLlmsTxt(): array {
    $result = [
      'exists' => FALSE,
      'content_preview' => '',
      'summary' => '',
    ];

    try {
      $llms_url = Url::fromUserInput('/llms.txt')
        ->setAbsolute()
        ->toString();

      $response = $this->httpClient->request('GET', $llms_url, [
        'timeout' => 5,
        'http_errors' => FALSE,
      ]);

      if ($response->getStatusCode() === 200) {
        $content_type = $response->getHeaderLine('Content-Type');
        // Guard against HTML error pages returned with a 200 status.
        if ($content_type && !str_contains($content_type, 'text/plain') && str_contains($content_type, 'text/html')) {
          $result['summary'] = 'No llms.txt file found (HTML response at that path).';
          return $result;
        }
        $content = $response->getBody()->getContents();
        $result['exists'] = TRUE;
        $result['content_preview'] = mb_substr(trim($content), 0, 500);
        $result['summary'] = 'llms.txt found (' . strlen($content) . ' bytes).';
      }
      else {
        $result['summary'] = 'No llms.txt file found at site root.';
      }
    }
    catch (\Exception $e) {
      $result['summary'] = 'Could not check llms.txt: ' . $e->getMessage();
    }

    return $result;
  }

  /**
   * Checks sitemap.xml quality for AI discoverability.
   *
   * @return array
   *   Array with 'has_lastmod', 'has_priority', 'has_changefreq',
   *   'lastmod_count', 'total_urls', 'summary'.
   */
  protected function checkSitemapQuality(): array {
    $result = [
      'has_lastmod' => FALSE,
      'has_priority' => FALSE,
      'has_changefreq' => FALSE,
      'lastmod_count' => 0,
      'total_urls' => 0,
      'stale_urls' => 0,
      'summary' => '',
    ];

    try {
      $sitemap_url = Url::fromUserInput('/sitemap.xml')
        ->setAbsolute()
        ->toString();

      $fetch = $this->fetchSitemapXml($sitemap_url);
      if ($fetch['error'] || $fetch['xml'] === NULL) {
        $result['summary'] = 'Could not parse sitemap.xml.';
        return $result;
      }

      $xml = $fetch['xml'];

      if (!isset($xml->url)) {
        $result['summary'] = 'Sitemap contains no URL entries (may be an index).';
        return $result;
      }

      $total = 0;
      $lastmod_count = 0;
      $priority_count = 0;
      $changefreq_count = 0;
      $stale_count = 0;
      $one_year_ago = time() - (365 * 24 * 60 * 60);

      foreach ($xml->url as $url_entry) {
        $total++;

        if (isset($url_entry->lastmod) && !empty((string) $url_entry->lastmod)) {
          $lastmod_count++;
          $lastmod_time = strtotime((string) $url_entry->lastmod);
          if ($lastmod_time !== FALSE && $lastmod_time < $one_year_ago) {
            $stale_count++;
          }
        }

        if (isset($url_entry->priority)) {
          $priority_count++;
        }

        if (isset($url_entry->changefreq)) {
          $changefreq_count++;
        }
      }

      $result['total_urls'] = $total;
      $result['lastmod_count'] = $lastmod_count;
      $result['has_lastmod'] = $lastmod_count > 0;
      $result['has_priority'] = $priority_count > 0;
      $result['has_changefreq'] = $changefreq_count > 0;
      $result['stale_urls'] = $stale_count;

      $parts = [];
      $parts[] = $total . ' URLs';
      $parts[] = $lastmod_count . '/' . $total . ' have lastmod';
      if ($stale_count > 0) {
        $parts[] = $stale_count . ' have not been updated in over a year';
      }
      $result['summary'] = implode('; ', $parts);

    }
    catch (\Exception $e) {
      $result['summary'] = 'Sitemap quality check failed: ' . $e->getMessage();
    }

    return $result;
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
