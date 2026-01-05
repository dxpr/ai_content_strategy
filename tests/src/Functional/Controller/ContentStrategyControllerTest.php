<?php

namespace Drupal\Tests\ai_content_strategy\Functional\Controller;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Functional tests for ContentStrategyController.
 *
 * @coversDefaultClass \Drupal\ai_content_strategy\Controller\ContentStrategyController
 * @group ai_content_strategy
 */
class ContentStrategyControllerTest extends BrowserTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai_content_strategy',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permissions to access content strategy.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a user with admin permissions.
    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'view ai content strategy recommendations',
    ]);
  }

  /**
   * Tests access to the content strategy page.
   */
  public function testContentStrategyPageAccess() {
    // Test anonymous access - should be denied.
    $this->drupalGet('/admin/reports/ai/content-strategy');
    $this->assertSession()->statusCodeEquals(403);

    // Test authenticated user access.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/reports/ai/content-strategy');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('AI Content Strategy Recommendations');
  }

  /**
   * Tests the recommendations page displays properly.
   */
  public function testRecommendationsPageStructure() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/reports/ai/content-strategy');

    // Check for key page elements.
    $this->assertSession()->pageTextContains('Generate Recommendations');
    $this->assertSession()->elementExists('css', '.generate-recommendations');
    $this->assertSession()->elementExists('css', '.content-strategy-recommendations');
  }

  /**
   * Tests user permissions for content strategy access.
   */
  public function testUserPermissions() {
    // Create a user without permissions.
    $user = $this->drupalCreateUser([]);
    $this->drupalLogin($user);
    $this->drupalGet('/admin/reports/ai/content-strategy');
    $this->assertSession()->statusCodeEquals(403);

    // Create a user with specific permissions.
    $privileged_user = $this->drupalCreateUser([
      'view ai content strategy recommendations',
    ]);
    $this->drupalLogin($privileged_user);
    $this->drupalGet('/admin/reports/ai/content-strategy');
    $this->assertSession()->statusCodeEquals(200);
  }

}
