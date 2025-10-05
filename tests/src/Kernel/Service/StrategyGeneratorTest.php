<?php

namespace Drupal\Tests\ai_content_strategy\Kernel\Service;

use Drupal\ai_content_strategy\Service\StrategyGenerator;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Kernel tests for StrategyGenerator service.
 *
 * @coversDefaultClass \Drupal\ai_content_strategy\Service\StrategyGenerator
 * @group ai_content_strategy
 */
class StrategyGeneratorTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ai_content_strategy',
  ];

  /**
   * The strategy generator service.
   *
   * @var \Drupal\ai_content_strategy\Service\StrategyGenerator
   */
  protected $strategyGenerator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user']);

    // Mock the required services for StrategyGenerator.
    $ai_provider = $this->createMock('\Drupal\ai\AiProviderPluginManager');
    $content_analyzer = $this->createMock('\Drupal\ai_content_strategy\Service\ContentAnalyzer');
    $prompt_decoder = $this->createMock('\Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface');
    $messenger = $this->createMock('\Drupal\Core\Messenger\MessengerInterface');
    $config_factory = $this->container->get('config.factory');

    // Mock content analyzer to return some basic data.
    $content_analyzer->method('getSiteStructure')
      ->willReturn(['pages' => 5, 'types' => ['article', 'page']]);
    $content_analyzer->method('getSitemapUrls')
      ->willReturn(['urls' => ['https://example.com', 'https://example.com/about']]);

    $this->strategyGenerator = new StrategyGenerator(
      $ai_provider,
      $content_analyzer,
      $prompt_decoder,
      $messenger,
      $config_factory
    );
  }

  /**
   * Tests that the service can be instantiated.
   *
   * @covers ::__construct
   */
  public function testServiceInstantiation() {
    $this->assertInstanceOf(StrategyGenerator::class, $this->strategyGenerator);
  }

  /**
   * Tests getSectionItemKey method.
   *
   * @covers ::getSectionItemKey
   */
  public function testGetSectionItemKey() {
    // Use reflection to test private method.
    $reflection = new \ReflectionClass($this->strategyGenerator);
    $method = $reflection->getMethod('getSectionItemKey');
    $method->setAccessible(TRUE);

    $this->assertEquals('title', $method->invoke($this->strategyGenerator, 'content_gaps'));
    $this->assertEquals('topic', $method->invoke($this->strategyGenerator, 'authority_topics'));
    $this->assertEquals('format', $method->invoke($this->strategyGenerator, 'expertise_demonstrations'));
    $this->assertEquals('signal', $method->invoke($this->strategyGenerator, 'trust_signals'));
  }

  /**
   * Tests getSectionDescriptionKey method.
   *
   * @covers ::getSectionDescriptionKey
   */
  public function testGetSectionDescriptionKey() {
    // Use reflection to test private method.
    $reflection = new \ReflectionClass($this->strategyGenerator);
    $method = $reflection->getMethod('getSectionDescriptionKey');
    $method->setAccessible(TRUE);

    $this->assertEquals('description', $method->invoke($this->strategyGenerator, 'content_gaps'));
    $this->assertEquals('rationale', $method->invoke($this->strategyGenerator, 'authority_topics'));
    $this->assertEquals('implementation', $method->invoke($this->strategyGenerator, 'expertise_demonstrations'));
    $this->assertEquals('implementation', $method->invoke($this->strategyGenerator, 'trust_signals'));
  }

}