<?php

namespace Drupal\ai_content_strategy\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service for building prompts from category templates with token replacement.
 */
class CategoryPromptBuilder {
  use StringTranslationTrait;

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
    $category_instructions = [];
    $schema_examples = [];
    $required_keys = [];

    foreach ($categories as $category) {
      $category_id = $category->id();
      $required_keys[] = $category_id;

      // Build category-specific instructions.
      $prompt_template = $category->getPromptTemplate();
      if (!empty($prompt_template)) {
        $category_instructions[] = $prompt_template;
      }

      // Build schema example for this category.
      $field_mapping = $category->getFieldMapping();
      if (!empty($field_mapping)) {
        $schema_examples[$category_id] = $this->buildCategorySchemaExample($category);
      }
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

    $prompt .= "2. Then identify:\n";
    $prompt .= "   - Missing content types compared to similar sites in the domain\n";
    $prompt .= "   - Underrepresented topics within the site's focus area\n";
    $prompt .= "   - Opportunities to demonstrate expertise in the site's domain\n";
    $prompt .= "   - Trust-building elements appropriate for the site type\n\n";

    $prompt .= "Rules for Recommendations:\n";
    $prompt .= "1. ALL recommendations must be directly inferred from the site's actual content and structure\n";
    $prompt .= "2. NO generic suggestions - each recommendation should clearly relate to the site's specific domain and purpose\n";
    $prompt .= "3. Content ideas must be specific and actionable\n";
    $prompt .= "4. Generate exactly 5 highly specific content ideas for each recommendation\n";
    $prompt .= "5. For each section, provide EXACTLY 2 distinct recommendations\n";
    $prompt .= "6. Each recommendation MUST include a priority level (high/medium/low)\n\n";

    // Add category-specific instructions.
    if (!empty($category_instructions)) {
      $prompt .= implode("\n\n", $category_instructions) . "\n\n";
    }

    $prompt .= "The response must be a valid JSON object with these exact keys: " . implode(', ', $required_keys) . "\n";
    $prompt .= "  </instructions>\n\n";

    // Add schema example.
    $prompt .= "  <schema_example>\n";
    $prompt .= json_encode($schema_examples, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
    $prompt .= "Each section MUST contain EXACTLY 2 distinct recommendations - no more, no less.\n";
    $prompt .= "Each recommendation MUST have EXACTLY 5 content ideas.\n";
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
   * Builds a schema example for a category.
   *
   * @param \Drupal\ai_content_strategy\Entity\RecommendationCategory $category
   *   The category entity.
   *
   * @return array
   *   Schema example array.
   */
  protected function buildCategorySchemaExample($category): array {
    $field_mapping = $category->getFieldMapping();
    $primary_field = $field_mapping['primary_field'] ?? 'title';
    $secondary_field = $field_mapping['secondary_field'] ?? 'description';
    $priority_field = $field_mapping['priority_field'] ?? 'priority';

    return [
      [
        $primary_field => 'Example ' . $category->label(),
        $secondary_field => 'Example description based on site context',
        $priority_field => 'high',
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

}
