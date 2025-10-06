<?php

namespace Drupal\ai_content_strategy\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service for building prompts from category templates with token replacement.
 */
class CategoryPromptBuilder {
  use StringTranslationTrait;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new CategoryPromptBuilder.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Builds a prompt from a category template with token replacement.
   *
   * @param string $template
   *   The prompt template.
   * @param array $tokens
   *   Array of token replacements.
   *
   * @return string
   *   The processed prompt.
   */
  public function buildPrompt(string $template, array $tokens): string {
    return strtr($template, $tokens);
  }

  /**
   * Builds the main strategy generation prompt with category instructions.
   *
   * @param array $categories
   *   Array of enabled RecommendationCategory entities.
   * @param array $site_structure
   *   Site structure data.
   * @param array $sitemap_urls
   *   Sitemap URLs data.
   *
   * @return string
   *   The complete prompt.
   */
  public function buildStrategyPrompt(array $categories, array $site_structure, array $sitemap_urls): string {
    $category_sections = [];
    $required_keys = [];
    $schema_examples = [];

    foreach ($categories as $category) {
      $category_id = $category->id();
      $required_keys[] = $category_id;

      // Build category-specific instructions.
      $instructions = $category->getInstructions();
      if (empty($instructions)) {
        $this->loggerFactory->get('ai_content_strategy')->warning(
          'Category @label has no instructions configured.',
          ['@label' => $category->label()]
        );
        continue;
      }

      $category_sections[] = "**{$category->label()}**:\n{$instructions}";

      // Build universal schema example for this category.
      $schema_examples[$category_id] = [
        [
          'title' => 'Example ' . $category->label(),
          'description' => 'Example description based on site context',
          'priority' => 'high',
          'content_ideas' => [
            'Example content idea 1 based on site context',
            'Example content idea 2 based on site context',
            'Example content idea 3 based on site context',
            'Example content idea 4 based on site context',
            'Example content idea 5 based on site context',
          ],
        ],
      ];
    }

    // Build the complete prompt.
    $prompt = "<prompt>\n";
    $prompt .= "  <instructions>\n";
    $prompt .= "Analysis Instructions:\n";
    $prompt .= "1. First analyze the site structure, URLs, and navigation to understand:\n";
    $prompt .= "   - The site's primary purpose and domain\n";
    $prompt .= "   - Existing content types and formats\n";
    $prompt .= "   - Current content organization\n";
    $prompt .= "   - Target audience indicators\n";
    $prompt .= "   - Industry/sector context\n\n";

    $prompt .= "2. Then provide recommendations for the following categories:\n\n";

    // Add category-specific instructions.
    if (!empty($category_sections)) {
      $prompt .= implode("\n\n", $category_sections) . "\n\n";
    }

    $prompt .= "Rules for Recommendations:\n";
    $prompt .= "1. ALL recommendations must be directly inferred from the site's actual content and structure\n";
    $prompt .= "2. NO generic suggestions - each recommendation should clearly relate to the site's specific domain and purpose\n";
    $prompt .= "3. Content ideas must be specific and actionable\n";
    $prompt .= "4. Generate exactly 5 highly specific content ideas for each recommendation\n";
    $prompt .= "5. For each category, provide EXACTLY 2 distinct recommendations\n";
    $prompt .= "6. Each recommendation MUST include a priority level (high/medium/low)\n\n";

    $prompt .= "The response must be a valid JSON object with these exact keys: " . implode(', ', $required_keys) . "\n";
    $prompt .= "  </instructions>\n\n";

    // Add schema example.
    $prompt .= "  <schema_example>\n";
    $prompt .= Json::encode($schema_examples);
    $prompt .= "\n  </schema_example>\n\n";

    // Add website data.
    $prompt .= "  <website_data>\n";
    $prompt .= "Homepage:\n";
    $prompt .= "Title: {homepage_title}\n";
    $prompt .= "Content: {homepage_content}\n\n";
    $prompt .= "Primary Navigation:\n";
    $prompt .= "{primary_menu}\n\n";
    $prompt .= "Existing Content URLs:\n";
    $prompt .= "{urls}\n";
    $prompt .= "  </website_data>\n\n";

    $prompt .= "  <response_requirements>\n";
    $prompt .= "Return ONLY the JSON object, no other text. The response must be parseable by PHP's json_decode().\n";
    $prompt .= "Each category MUST contain EXACTLY 2 distinct recommendations - no more, no less.\n";
    $prompt .= "Each recommendation MUST have EXACTLY 5 content ideas.\n";
    $prompt .= "All recommendations MUST use the structure: {title, description, priority, content_ideas}\n";
    $prompt .= "  </response_requirements>\n";
    $prompt .= "</prompt>";

    // Replace tokens.
    $tokens = [
      '{homepage_title}' => $site_structure['homepage']['title'] ?? '',
      '{homepage_content}' => $site_structure['homepage']['content'] ?? '',
      '{primary_menu}' => $this->formatMenuItems($site_structure['primary_menu'] ?? []),
      '{urls}' => $this->formatUrls($sitemap_urls['urls'] ?? []),
    ];

    return $this->buildPrompt($prompt, $tokens);
  }

  /**
   * Formats menu items for the prompt.
   *
   * @param array $menu_items
   *   The menu items array.
   *
   * @return string
   *   Formatted menu items.
   */
  protected function formatMenuItems(array $menu_items): string {
    if (empty($menu_items)) {
      return 'No menu items found';
    }

    $formatted = [];
    foreach ($menu_items as $item) {
      $formatted[] = '- ' . $item['title'] . ' (' . $item['url'] . ')';
    }
    return implode("\n", $formatted);
  }

  /**
   * Formats URLs for the prompt.
   *
   * @param array $urls
   *   The URLs array.
   *
   * @return string
   *   Formatted URLs.
   */
  protected function formatUrls(array $urls): string {
    if (empty($urls)) {
      return 'No URLs found';
    }

    return '- ' . implode("\n- ", array_slice($urls, 0, 50));
  }

  /**
   * Builds add-more prompts for a specific category.
   *
   * @param \Drupal\ai_content_strategy\Entity\RecommendationCategory $category
   *   The category entity.
   * @param string $existing_recommendations
   *   Formatted string of existing recommendations.
   *
   * @return array
   *   Array with 'system' and 'user' prompts.
   */
  public function buildAddMorePrompts($category, string $existing_recommendations = ''): array {
    $category_id = $category->id();
    $category_label = $category->label();
    $instructions = $category->getInstructions();

    // Fallback if no instructions.
    if (empty($instructions)) {
      $instructions = "Identify opportunities related to {$category_label}.";
    }

    $system_prompt = <<<PROMPT
You are an AI content strategist analyzing a website's content structure.
Based on the provided site information, generate 2 new recommendations for the "{$category_label}" category.

Category Instructions:
{$instructions}

Rules for Recommendations:
1. ALL recommendations must be directly inferred from the site's actual content and structure
2. NO generic suggestions - each recommendation should clearly relate to the site's specific domain and purpose
3. Content ideas must be specific and actionable
4. Generate exactly 5 highly specific content ideas for each recommendation
5. Each recommendation MUST include a priority level (high/medium/low)
6. Ensure recommendations are unique and complementary to existing ones
PROMPT;

    $user_prompt = <<<PROMPT
<context>
<site_info>
Homepage:
Title: {homepage_title}
Content: {homepage_content}

Primary Navigation:
{primary_menu}

Existing Content URLs:
{urls}
</site_info>

<existing_recommendations>
{existing_recommendations}
</existing_recommendations>
</context>

Generate 2 new distinct recommendations. Return ONLY a JSON object with this exact structure:
{
  "{$category_id}": [
    {
      "title": "Recommendation Title",
      "description": "Detailed description based on site context",
      "priority": "high",
      "content_ideas": [
        "Specific content idea 1",
        "Specific content idea 2",
        "Specific content idea 3",
        "Specific content idea 4",
        "Specific content idea 5"
      ]
    },
    {
      "title": "Second Recommendation Title",
      "description": "Detailed description based on site context",
      "priority": "medium",
      "content_ideas": [
        "Specific content idea 1",
        "Specific content idea 2",
        "Specific content idea 3",
        "Specific content idea 4",
        "Specific content idea 5"
      ]
    }
  ]
}
PROMPT;

    return [
      'system' => $system_prompt,
      'user' => $user_prompt,
    ];
  }

}
