<?php

declare(strict_types=1);

namespace Drupal\Tests\imagefield_tokens\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\imagefield_tokens\Plugin\Field\FieldWidget\ImageFieldTokensWigdet;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\node\Entity\Node;

/**
 * Tests that widgets handle non-entity form objects without fatal errors.
 *
 * Regression test for #3341021: VBO passes a ConfigureAction form object
 * that is not an EntityFormInterface. The old method_exists() check let it
 * through, then getEntity() returned NULL and getEntityTypeId() causes a fatal.
 *
 * @group imagefield_tokens
 */
#[Group('imagefield_tokens')]
#[RunTestsInSeparateProcesses]
class ImageFieldTokensVboCompatibilityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore DrupalPractice.Objects.StrictSchemaDisabled.StrictConfigSchema
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'file',
    'image',
    'node',
    'user',
    'token',
    'imagefield_tokens',
  ];

  /**
   * The widget plugin manager.
   *
   * @var \Drupal\Core\Field\WidgetPluginManager
   */
  protected $widgetManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'file', 'image']);

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_image',
      'entity_type' => 'node',
      'type' => 'image',
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_image',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Image',
      'settings' => [
        'alt_field' => 1,
        'title_field' => 1,
      ],
    ])->save();

    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', 'article', 'default')
      ->setComponent('field_image', [
        'type' => 'imagefield_tokens',
      ])
      ->save();

    $this->widgetManager = \Drupal::service('plugin.manager.field.widget');
  }

  /**
   * Tests that the widget does not fatal with a non-entity form object.
   *
   * Simulates the VBO scenario where ConfigureAction (a FormBase, not
   * EntityFormInterface) is the active form object.
   */
  public function testFormElementWithoutEntityForm(): void {
    $node = Node::create([
      'title' => 'Test article',
      'type' => 'article',
    ]);
    $node->save();

    $items = $node->get('field_image');
    $items->setValue([]);

    $form_state = new FormState();
    $form_state->setFormObject(new StubNonEntityForm());

    $widget = $this->widgetManager->getInstance([
      'field_definition' => FieldConfig::load('node.article.field_image'),
      'configuration' => [
        'type' => 'imagefield_tokens',
        'settings' => [],
        'third_party_settings' => [],
      ],
    ]);

    // With the old method_exists() code this would fatal because
    // getEntity() returns NULL and getEntityTypeId() is called on it.
    // With the instanceof fix, it falls through to #entity_type.
    $form = [
      '#entity_type' => 'node',
      '#bundle' => 'article',
      '#parents' => [],
      '#field_parents' => [],
    ];
    $element = $widget->form($items, $form, $form_state);

    self::assertNotEmpty($element, 'Widget rendered form elements without fatal.');
  }

  /**
   * Tests formElement() with a real EntityFormInterface (TRUE path).
   *
   * Ensures the instanceof guard correctly detects entity forms and calls
   * getEntity()->getEntityTypeId() without the old NULL-check.
   */
  public function testFormElementWithEntityForm(): void {
    $node = Node::create([
      'title' => 'Test article',
      'type' => 'article',
    ]);
    $node->save();

    $items = $node->get('field_image');
    $items->setValue([]);

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity($node);

    $form_state = new FormState();
    $form_state->setFormObject($form_object);

    $widget = $this->widgetManager->getInstance([
      'field_definition' => FieldConfig::load('node.article.field_image'),
      'configuration' => [
        'type' => 'imagefield_tokens',
        'settings' => [],
        'third_party_settings' => [],
      ],
    ]);

    $form = [
      '#parents' => [],
      '#field_parents' => [],
    ];
    $element = $widget->form($items, $form, $form_state);

    self::assertNotEmpty($element, 'Widget rendered with EntityFormInterface without fatal.');
  }

  /**
   * Tests process() with a non-entity form object.
   *
   * Ensures the static process() method handles non-EntityFormInterface form
   * objects without fatal, exercising the instanceof guard in the process
   * callback.
   */
  public function testProcessWithoutEntityForm(): void {
    $form_state = new FormState();
    $form_state->setFormObject(new StubNonEntityForm());

    $element = [
      '#value' => [],
      'fids' => ['#value' => []],
      '#files' => [],
      '#default_image' => [],
      '#default_alt' => 'Test alt',
      '#default_title' => 'Test title',
      '#preview_image_style' => '',
      '#alt_field' => FALSE,
      '#title_field' => FALSE,
      '#alt_field_required' => FALSE,
      '#title_field_required' => FALSE,
      '#field_name' => 'field_image',
      '#parents' => [],
      '#array_parents' => [],
    ];

    $result = ImageFieldTokensWigdet::process($element, $form_state, []);

    self::assertArrayHasKey('alt', $result, 'Process added alt field.');
    self::assertArrayHasKey('title', $result, 'Process added title field.');
    self::assertEquals('Test alt', $result['alt']['#default_value']);
    self::assertEquals('Test title', $result['title']['#default_value']);
  }

  /**
   * Tests defaultSettings() includes the widget-specific settings.
   */
  public function testDefaultSettings(): void {
    $defaults = ImageFieldTokensWigdet::defaultSettings();
    self::assertArrayHasKey('default_alt', $defaults);
    self::assertArrayHasKey('default_title', $defaults);
    self::assertEquals('', $defaults['default_alt']);
    self::assertEquals('', $defaults['default_title']);
  }

  /**
   * Tests settingsForm() renders default_alt and default_title fields.
   */
  public function testSettingsForm(): void {
    $widget = $this->widgetManager->getInstance([
      'field_definition' => FieldConfig::load('node.article.field_image'),
      'configuration' => [
        'type' => 'imagefield_tokens',
        'settings' => ['default_alt' => 'Alt [node:title]', 'default_title' => 'Title [node:title]'],
        'third_party_settings' => [],
      ],
    ]);

    $form = [];
    $form_state = new FormState();
    $settings_form = $widget->settingsForm($form, $form_state);

    self::assertArrayHasKey('default_alt', $settings_form);
    self::assertEquals('Alt [node:title]', $settings_form['default_alt']['#default_value']);
    self::assertArrayHasKey('default_title', $settings_form);
    self::assertEquals('Title [node:title]', $settings_form['default_title']['#default_value']);
    self::assertArrayHasKey('token_tree', $settings_form);
  }

  /**
   * Tests settingsSummary() includes configured default values.
   */
  public function testSettingsSummary(): void {
    $widget = $this->widgetManager->getInstance([
      'field_definition' => FieldConfig::load('node.article.field_image'),
      'configuration' => [
        'type' => 'imagefield_tokens',
        'settings' => ['default_alt' => 'Test alt', 'default_title' => 'Test title'],
        'third_party_settings' => [],
      ],
    ]);

    $summary = $widget->settingsSummary();
    $summary_text = implode(' ', $summary);

    self::assertStringContainsString('Test alt', $summary_text);
    self::assertStringContainsString('Test title', $summary_text);
  }

}

/**
 * Stub form that is not an EntityFormInterface but has getEntity().
 *
 * This mirrors VBO's ConfigureAction form, which has a getEntity() method
 * but does not implement EntityFormInterface.
 */
class StubNonEntityForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'stub_non_entity_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Returns NULL, mimicking VBO's ConfigureAction.
   *
   * The old method_exists() check would return TRUE for this method,
   * then call getEntityTypeId() on the NULL return value.
   */
  public function getEntity(): ?object {
    return NULL;
  }

}
