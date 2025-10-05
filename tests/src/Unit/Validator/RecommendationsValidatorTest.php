<?php

namespace Drupal\Tests\ai_content_strategy\Unit\Validator;

use Drupal\ai_content_strategy\Validator\RecommendationsValidator;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for RecommendationsValidator.
 *
 * @coversDefaultClass \Drupal\ai_content_strategy\Validator\RecommendationsValidator
 * @group ai_content_strategy
 */
class RecommendationsValidatorTest extends UnitTestCase {

  /**
   * The validator instance.
   *
   * @var \Drupal\ai_content_strategy\Validator\RecommendationsValidator
   */
  protected $validator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->validator = new RecommendationsValidator();
  }

  /**
   * Tests validateRecommendations with valid data.
   *
   * @covers ::validateRecommendations
   */
  public function testValidateRecommendationsWithValidData() {
    $recommendations = [
      'content_gaps' => [
        ['title' => 'Test Gap', 'priority' => 'high', 'description' => 'Test description'],
      ],
      'authority_topics' => [
        ['title' => 'Test Topic', 'priority' => 'medium', 'description' => 'Test description'],
      ],
      'expertise_demonstrations' => [
        ['title' => 'Test Demo', 'priority' => 'low', 'description' => 'Test description'],
      ],
      'trust_signals' => [
        ['title' => 'Test Signal', 'priority' => 'high', 'description' => 'Test description'],
      ],
    ];

    $result = $this->validator->validateRecommendations($recommendations);
    $this->assertTrue($result);
  }

  /**
   * Tests validateRecommendations with empty data.
   *
   * @covers ::validateRecommendations
   */
  public function testValidateRecommendationsWithEmptyData() {
    $recommendations = [];
    $result = $this->validator->validateRecommendations($recommendations);
    $this->assertFalse($result);
  }

  /**
   * Tests validateRecommendations with invalid structure.
   *
   * @covers ::validateRecommendations
   */
  public function testValidateRecommendationsWithInvalidStructure() {
    $recommendations = [
      'invalid_section' => ['some data'],
    ];

    $result = $this->validator->validateRecommendations($recommendations);
    $this->assertFalse($result);
  }

  /**
   * Tests validateRecommendations with missing required fields.
   *
   * @covers ::validateRecommendations
   */
  public function testValidateRecommendationsWithMissingFields() {
    $recommendations = [
      'content_gaps' => [
        // Missing priority and description.
        ['title' => 'Test Gap'],
      ],
    ];

    $result = $this->validator->validateRecommendations($recommendations);
    $this->assertFalse($result);
  }

}
