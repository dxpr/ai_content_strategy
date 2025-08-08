<?php

namespace Drupal\ai_content_strategy\Validator;

use Drupal\ai\JsonSchema\JsonSchemaValidatorInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Validates content strategy recommendations against JSON schema.
 */
class RecommendationsValidator {

  /**
   * The JSON schema validator.
   *
   * @var \Drupal\ai\JsonSchema\JsonSchemaValidatorInterface
   */
  protected $validator;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a RecommendationsValidator object.
   *
   * @param \Drupal\ai\JsonSchema\JsonSchemaValidatorInterface $validator
   *   The JSON schema validator.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    JsonSchemaValidatorInterface $validator,
    ModuleHandlerInterface $module_handler,
  ) {
    $this->validator = $validator;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Validates recommendations data against schema.
   *
   * @param array $data
   *   The recommendations data to validate.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  public function validate(array $data): bool {
    $module_path = $this->moduleHandler->getModule('ai_content_strategy')->getPath();
    $schema_path = $module_path . '/config/schema/ai_content_strategy.schema.json';
    $schema = json_decode(file_get_contents($schema_path), TRUE);

    return $this->validator->validate($data, $schema);
  }

  /**
   * Gets validation errors if any.
   *
   * @return array
   *   Array of validation errors.
   */
  public function getErrors(): array {
    return $this->validator->getErrors();
  }

}
