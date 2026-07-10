<?php

declare(strict_types=1);

namespace Drupal\Tests\imagefield_tokens\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\crop\Entity\CropType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\imagefield_tokens\Plugin\Field\FieldWidget\ImageFieldTokensCropWidget;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ImageFieldTokensCropWidget.
 *
 * Covers the instanceof EntityFormInterface guard and the independent
 * alt/title default image condition introduced in #3341021.
 *
 * @group imagefield_tokens
 */
#[Group('imagefield_tokens')]
#[RunTestsInSeparateProcesses]
class ImageFieldTokensCropWidgetTest extends KernelTestBase {

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
    'crop',
    'image_widget_crop',
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
    $this->installEntitySchema('crop');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'file', 'image', 'crop']);

    CropType::create([
      'id' => 'free',
      'label' => 'Free',
      'aspect_ratio' => '',
    ])->save();

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_crop_image',
      'entity_type' => 'node',
      'type' => 'image',
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_crop_image',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Crop Image',
      'settings' => [
        'alt_field' => 1,
        'title_field' => 1,
        'default_image' => [
          'uuid' => '',
          'alt' => 'Default alt',
          'title' => 'Default title',
          'width' => 0,
          'height' => 0,
        ],
      ],
    ])->save();

    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', 'article', 'default')
      ->setComponent('field_crop_image', [
        'type' => 'imagefield_tokens_widget_crop',
      ])
      ->save();

    $this->widgetManager = \Drupal::service('plugin.manager.field.widget');
  }

  /**
   * Tests formElement() with a real EntityFormInterface.
   *
   * Ensures the instanceof guard in formElement() works with entity forms
   * and does not fatal.
   */
  public function testFormElementWithEntityForm(): void {
    $node = Node::create([
      'title' => 'Test article',
      'type' => 'article',
    ]);
    $node->save();

    $items = $node->get('field_crop_image');
    $items->setValue([]);

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity($node);

    $form_state = new FormState();
    $form_state->setFormObject($form_object);

    $widget = $this->widgetManager->getInstance([
      'field_definition' => FieldConfig::load('node.article.field_crop_image'),
      'configuration' => [
        'type' => 'imagefield_tokens_widget_crop',
        'settings' => [],
        'third_party_settings' => [],
      ],
    ]);

    $form = [
      '#parents' => [],
      '#field_parents' => [],
    ];
    $element = $widget->form($items, $form, $form_state);

    self::assertNotEmpty($element, 'Crop widget rendered with EntityFormInterface without fatal.');
  }

  /**
   * Tests process() fills alt and title independently from default image.
   *
   * Ensures the independent alt/title condition correctly fills each field
   * separately when only one is empty, and fills both when both are empty.
   */
  public function testProcessWithDefaultImage(): void {
    $node = Node::create([
      'title' => 'Test article',
      'type' => 'article',
    ]);
    $node->save();

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity($node);

    $form_state = new FormState();
    $form_state->setFormObject($form_object);

    $base_element = [
      'fids' => ['#value' => []],
      '#files' => [],
      '#default_image' => [],
      '#default_alt' => 'Default alt',
      '#default_title' => 'Default title',
      '#preview_image_style' => '',
      '#alt_field' => TRUE,
      '#title_field' => TRUE,
      '#alt_field_required' => FALSE,
      '#title_field_required' => FALSE,
      '#display_field' => FALSE,
      '#description_field' => FALSE,
      '#cardinality' => 1,
      '#id' => 'edit-field-crop-image',
      '#field_name' => 'field_crop_image',
      '#parents' => [],
      '#array_parents' => [],
      '#crop_list' => [],
      '#crop_preview_image_style' => 'crop_thumbnail',
      '#show_default_crop' => TRUE,
      '#show_crop_area' => FALSE,
      '#warn_multiple_usages' => TRUE,
      '#crop_types_required' => [],
    ];

    // Both empty: both filled from defaults.
    $element = $base_element + ['#value' => ['alt' => '', 'title' => '']];
    $result = ImageFieldTokensCropWidget::process($element, $form_state, []);
    self::assertEquals('Default alt', $result['alt']['#default_value']);
    self::assertEquals('Default title', $result['title']['#default_value']);

    // Only alt empty: alt from default, title keeps custom.
    $element = $base_element + ['#value' => ['alt' => '', 'title' => 'Custom title']];
    $result = ImageFieldTokensCropWidget::process($element, $form_state, []);
    self::assertEquals('Default alt', $result['alt']['#default_value']);
    self::assertEquals('Custom title', $result['title']['#default_value']);

    // Only title empty: alt keeps custom, title from default.
    $element = $base_element + ['#value' => ['alt' => 'Custom alt', 'title' => '']];
    $result = ImageFieldTokensCropWidget::process($element, $form_state, []);
    self::assertEquals('Custom alt', $result['alt']['#default_value']);
    self::assertEquals('Default title', $result['title']['#default_value']);
  }

  /**
   * Tests defaultSettings() includes the widget-specific settings.
   */
  public function testDefaultSettings(): void {
    $defaults = ImageFieldTokensCropWidget::defaultSettings();
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
      'field_definition' => FieldConfig::load('node.article.field_crop_image'),
      'configuration' => [
        'type' => 'imagefield_tokens_widget_crop',
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
      'field_definition' => FieldConfig::load('node.article.field_crop_image'),
      'configuration' => [
        'type' => 'imagefield_tokens_widget_crop',
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
