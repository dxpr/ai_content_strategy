<?php

namespace Drupal\ai_content_strategy\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/**
 * Service for managing recommendation storage operations.
 *
 * Handles all CRUD operations for the key-value store that holds
 * AI-generated content strategy recommendations.
 */
class RecommendationStorageService {

  /**
   * Key-Value collection name.
   */
  const KV_COLLECTION = 'ai_content_strategy.recommendations';

  /**
   * Key for storing recommendations.
   */
  const KV_KEY = 'recommendations';

  /**
   * The key-value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $keyValue;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a RecommendationStorageService.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key-value factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    KeyValueFactoryInterface $key_value_factory,
    TimeInterface $time,
  ) {
    $this->keyValue = $key_value_factory->get(self::KV_COLLECTION);
    $this->time = $time;
  }

  /**
   * Gets all stored recommendation data.
   *
   * @return array|null
   *   The stored data with 'data', 'timestamp', and optional 'pages_analyzed',
   *   or NULL if no data exists.
   */
  public function getStoredData(): ?array {
    $stored_data = $this->keyValue->get(self::KV_KEY);

    if (!$stored_data) {
      return NULL;
    }

    // Handle legacy format (direct array without metadata).
    if (is_array($stored_data) && !isset($stored_data['data'])) {
      return [
        'data' => $stored_data,
        'timestamp' => NULL,
        'pages_analyzed' => NULL,
      ];
    }

    return $stored_data;
  }

  /**
   * Gets recommendations data only.
   *
   * @return array
   *   The recommendations array, or empty array if none exist.
   */
  public function getRecommendations(): array {
    $stored = $this->getStoredData();
    return $stored['data'] ?? [];
  }

  /**
   * Gets the last generation timestamp.
   *
   * @return int|null
   *   The timestamp, or NULL if never generated.
   */
  public function getLastRunTimestamp(): ?int {
    $stored = $this->getStoredData();
    return $stored['timestamp'] ?? NULL;
  }

  /**
   * Gets the pages analyzed count.
   *
   * @return int|null
   *   The count, or NULL if not tracked.
   */
  public function getPagesAnalyzed(): ?int {
    $stored = $this->getStoredData();
    return $stored['pages_analyzed'] ?? NULL;
  }

  /**
   * Gets recommendations for a specific section.
   *
   * @param string $section
   *   The section identifier.
   *
   * @return array
   *   The section's recommendations, or empty array if none.
   */
  public function getSection(string $section): array {
    $recommendations = $this->getRecommendations();
    return $recommendations[$section] ?? [];
  }

  /**
   * Saves recommendations with metadata.
   *
   * @param array $recommendations
   *   The recommendations data.
   * @param int|null $pages_analyzed
   *   Optional pages analyzed count.
   *
   * @return int
   *   The timestamp of the save operation.
   */
  public function saveRecommendations(array $recommendations, ?int $pages_analyzed = NULL): int {
    $timestamp = (int) $this->time->getCurrentTime();

    $data = [
      'data' => $recommendations,
      'timestamp' => $timestamp,
    ];

    if ($pages_analyzed !== NULL) {
      $data['pages_analyzed'] = $pages_analyzed;
    }

    $this->keyValue->set(self::KV_KEY, $data);

    return $timestamp;
  }

  /**
   * Finds a card by title within a section.
   *
   * @param string $section
   *   The section identifier.
   * @param string $title
   *   The card title to find.
   *
   * @return int|null
   *   The card index, or NULL if not found.
   */
  public function findCardIndex(string $section, string $title): ?int {
    $recommendations = $this->getRecommendations();

    if (!isset($recommendations[$section])) {
      return NULL;
    }

    foreach ($recommendations[$section] as $index => $card) {
      $card_title = $card['title'] ?? $card['topic'] ?? $card['content_type'] ?? $card['signal'] ?? '';
      if ($card_title === $title) {
        return $index;
      }
    }

    return NULL;
  }

  /**
   * Gets a card by section and title.
   *
   * @param string $section
   *   The section identifier.
   * @param string $title
   *   The card title.
   *
   * @return array|null
   *   The card data, or NULL if not found.
   */
  public function getCard(string $section, string $title): ?array {
    $index = $this->findCardIndex($section, $title);

    if ($index === NULL) {
      return NULL;
    }

    $recommendations = $this->getRecommendations();
    return $recommendations[$section][$index] ?? NULL;
  }

  /**
   * Appends ideas to a card.
   *
   * @param string $section
   *   The section identifier.
   * @param string $title
   *   The card title.
   * @param array $ideas
   *   The ideas to append (strings or idea objects).
   *
   * @return int
   *   The starting index of the new ideas.
   *
   * @throws \RuntimeException
   *   If the card is not found.
   */
  public function appendIdeas(string $section, string $title, array $ideas): int {
    $stored = $this->getStoredData();
    $recommendations = $stored['data'] ?? [];

    $card_index = $this->findCardIndex($section, $title);
    if ($card_index === NULL) {
      throw new \RuntimeException('Card not found');
    }

    $existing_ideas = $recommendations[$section][$card_index]['content_ideas'] ?? [];
    $starting_index = count($existing_ideas);

    $recommendations[$section][$card_index]['content_ideas'] = array_merge(
      $existing_ideas,
      $ideas
    );

    $this->saveRecommendationsPreservingMetadata($recommendations, $stored);

    return $starting_index;
  }

  /**
   * Updates a field on a card.
   *
   * @param string $section
   *   The section identifier.
   * @param string $title
   *   The card title.
   * @param string $field
   *   The field name.
   * @param mixed $value
   *   The new value.
   *
   * @return string|null
   *   The new title if title was changed, NULL otherwise.
   *
   * @throws \RuntimeException
   *   If the card is not found.
   */
  public function updateCardField(string $section, string $title, string $field, $value): ?string {
    $stored = $this->getStoredData();
    $recommendations = $stored['data'] ?? [];

    $card_index = $this->findCardIndex($section, $title);
    if ($card_index === NULL) {
      throw new \RuntimeException('Card not found');
    }

    $recommendations[$section][$card_index][$field] = $value;

    $this->saveRecommendationsPreservingMetadata($recommendations, $stored);

    // Return new title if title field was updated.
    return $field === 'title' ? $value : NULL;
  }

  /**
   * Updates a field on an idea within a card.
   *
   * @param string $section
   *   The section identifier.
   * @param string $title
   *   The card title.
   * @param int $idea_index
   *   The idea index.
   * @param string $field
   *   The field name ('text', 'implemented', 'link').
   * @param mixed $value
   *   The new value.
   *
   * @throws \RuntimeException
   *   If the card or idea is not found.
   */
  public function updateIdeaField(string $section, string $title, int $idea_index, string $field, $value): void {
    $stored = $this->getStoredData();
    $recommendations = $stored['data'] ?? [];

    $card_index = $this->findCardIndex($section, $title);
    if ($card_index === NULL) {
      throw new \RuntimeException('Card not found');
    }

    if (!isset($recommendations[$section][$card_index]['content_ideas'][$idea_index])) {
      throw new \RuntimeException('Idea not found');
    }

    $idea = &$recommendations[$section][$card_index]['content_ideas'][$idea_index];

    // Convert string idea to object format if needed.
    if (is_string($idea)) {
      $idea = [
        'text' => $idea,
        'implemented' => FALSE,
        'link' => '',
      ];
    }

    $idea[$field] = $value;

    $this->saveRecommendationsPreservingMetadata($recommendations, $stored);
  }

  /**
   * Deletes a card.
   *
   * @param string $section
   *   The section identifier.
   * @param string $title
   *   The card title.
   *
   * @throws \RuntimeException
   *   If the card is not found.
   */
  public function deleteCard(string $section, string $title): void {
    $stored = $this->getStoredData();
    $recommendations = $stored['data'] ?? [];

    $card_index = $this->findCardIndex($section, $title);
    if ($card_index === NULL) {
      throw new \RuntimeException('Card not found');
    }

    unset($recommendations[$section][$card_index]);
    $recommendations[$section] = array_values($recommendations[$section]);

    $this->saveRecommendationsPreservingMetadata($recommendations, $stored);
  }

  /**
   * Deletes an idea from a card.
   *
   * @param string $section
   *   The section identifier.
   * @param string $title
   *   The card title.
   * @param int $idea_index
   *   The idea index.
   *
   * @throws \RuntimeException
   *   If the card or idea is not found.
   */
  public function deleteIdea(string $section, string $title, int $idea_index): void {
    $stored = $this->getStoredData();
    $recommendations = $stored['data'] ?? [];

    $card_index = $this->findCardIndex($section, $title);
    if ($card_index === NULL) {
      throw new \RuntimeException('Card not found');
    }

    if (!isset($recommendations[$section][$card_index]['content_ideas'][$idea_index])) {
      throw new \RuntimeException('Idea not found');
    }

    unset($recommendations[$section][$card_index]['content_ideas'][$idea_index]);
    $recommendations[$section][$card_index]['content_ideas'] = array_values(
      $recommendations[$section][$card_index]['content_ideas']
    );

    $this->saveRecommendationsPreservingMetadata($recommendations, $stored);
  }

  /**
   * Adds recommendations to a section.
   *
   * @param string $section
   *   The section identifier.
   * @param array $new_recommendations
   *   The recommendations to add.
   */
  public function addToSection(string $section, array $new_recommendations): void {
    $stored = $this->getStoredData();
    $recommendations = $stored['data'] ?? [];

    $existing = $recommendations[$section] ?? [];
    $recommendations[$section] = array_merge($existing, $new_recommendations);

    $this->saveRecommendationsPreservingMetadata($recommendations, $stored);
  }

  /**
   * Saves recommendations while preserving existing metadata.
   *
   * @param array $recommendations
   *   The recommendations data.
   * @param array|null $stored
   *   The existing stored data with metadata.
   */
  protected function saveRecommendationsPreservingMetadata(array $recommendations, ?array $stored): void {
    $timestamp = (int) $this->time->getCurrentTime();

    $data = [
      'data' => $recommendations,
      'timestamp' => $timestamp,
    ];

    if (isset($stored['pages_analyzed'])) {
      $data['pages_analyzed'] = $stored['pages_analyzed'];
    }

    $this->keyValue->set(self::KV_KEY, $data);
  }

}
