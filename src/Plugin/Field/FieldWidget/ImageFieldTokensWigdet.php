<?php

declare(strict_types=1);

namespace Drupal\imagefield_tokens\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\image\Plugin\Field\FieldWidget\ImageWidget;
use Drupal\media_library\Form\AddFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'image_image' widget.
 *
 * @FieldWidget(
 *   id = "imagefield_tokens",
 *   label = @Translation("ImageField Tokens"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class ImageFieldTokensWigdet extends ImageWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'default_alt' => '',
      'default_title' => '',
    ] + parent::defaultSettings();
  }

  /**
   * Constructs a new ImageFieldTokensWigdet object.
   *
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Field definition.
   * @param array $settings
   *   Field settings.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $element_info
   *   The element info manager.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current user service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ElementInfoManagerInterface $element_info, ImageFactory $image_factory, protected AccountInterface $currentUser, protected ModuleHandlerInterface $moduleHandler, protected EntityRepositoryInterface $entityRepository) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $element_info, $image_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('element_info'),
      $container->get('image.factory'),
      $container->get('current_user'),
      $container->get('module_handler'),
      $container->get('entity.repository')
    );

  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['default_alt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default alternative text'),
      '#default_value' => $this->getSetting('default_alt'),
      '#description' => $this->t('Token-based default value for the alt attribute. Used when the stored alt is empty.'),
      '#maxlength' => 512,
    ];

    $form['default_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default title'),
      '#default_value' => $this->getSetting('default_title'),
      '#description' => $this->t('Token-based default value for the title attribute. Used when the stored title is empty.'),
      '#maxlength' => 1024,
    ];

    if ($this->moduleHandler->moduleExists('token')) {
      $entity_type_id = $this->fieldDefinition->getTargetEntityTypeId();
      $form['token_tree'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => [$entity_type_id],
        '#show_restricted' => TRUE,
        '#weight' => 90,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $default_alt = $this->getSetting('default_alt');
    $default_title = $this->getSetting('default_title');

    if (!empty($default_alt)) {
      $summary[] = $this->t('Default alt: @value', ['@value' => $default_alt]);
    }
    if (!empty($default_title)) {
      $summary[] = $this->t('Default title: @value', ['@value' => $default_title]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $entity_type_id = '';
    // Get setting for token.
    $field_settings = $this->getFieldSettings();
    $object = $form_state->getFormObject();
    if ($object instanceof EntityFormInterface) {
      $entity_type_id = $object->getEntity()->getEntityTypeId();
    }
    // When not on an entity form. Try to detect entity type with another way.
    elseif (isset($element['#entity_type'])) {
      $entity_type_id = $element['#entity_type'];
    }

    // Add image validation.
    $element['#upload_validators']['FileIsImage'] = [];

    // Add upload dimensions validation.
    if ($field_settings['max_resolution'] || $field_settings['min_resolution']) {
      $element['#upload_validators']['FileImageDimensions'] = [
        'maxDimensions' => $field_settings['max_resolution'],
        'minDimensions' => $field_settings['min_resolution'],
      ];
    }

    $extensions = $field_settings['file_extensions'];
    $supported_extensions = $this->imageFactory->getSupportedExtensions();

    // If using custom extension validation, ensure that the extensions are
    // supported by the current image toolkit. Otherwise, validate against all
    // toolkit supported extensions.
    $extensions = empty($extensions) ? $supported_extensions : array_intersect(explode(' ', $extensions), $supported_extensions);
    $element['#upload_validators']['FileExtension']['extensions'] = implode(' ', $extensions);

    // Add mobile device image capture acceptance.
    $element['#accept'] = 'image/*';

    // Add properties needed by process() method.
    $element['#preview_image_style'] = $this->getSetting('preview_image_style');
    $element['#title_field'] = $field_settings['title_field'];
    $element['#title_field_required'] = $field_settings['title_field_required'];
    $element['#alt_field'] = $field_settings['alt_field'];
    $element['#alt_field_required'] = $field_settings['alt_field_required'];

    // Default image.
    $default_image = $field_settings['default_image'];
    if (empty($default_image['uuid'])) {
      $default_image = $this->fieldDefinition->getFieldStorageDefinition()->getSetting('default_image');
    }
    // Convert the stored UUID into a file ID.
    if (!empty($default_image['uuid']) && $entity = $this->entityRepository->loadEntityByUuid('file', $default_image['uuid'])) {
      $default_image['fid'] = $entity->id();
    }
    $element['#default_image'] = empty($default_image['fid']) ? [] : $default_image;

    // Pass widget settings for token-based defaults.
    $element['#default_alt'] = $this->getSetting('default_alt');
    $element['#default_title'] = $this->getSetting('default_title');

    if (!$this->currentUser->isAnonymous()) {
      // Add token link to the form.
      $form['#token'] = TRUE;
      if ($this->moduleHandler->moduleExists('token')) {
        $element['token_tree'] = [
          '#theme' => 'token_tree_link',
          '#token_types' => [$entity_type_id],
          '#show_restricted' => TRUE,
          '#weight' => 90,
          '#prefix' => '<div class="token-token">',
          '#suffix' => '</div>',
        ];
      }
    }

    return $element;
  }

  /**
   * Form API callback: Processes an image_image field element.
   *
   * Expands the image_image type to include the alt and title fields with
   * token replacement support.
   *
   * This method is assigned as a #process callback in formElement() method.
   *
   * @phpstan-param mixed $element
   * @phpstan-param mixed $form
   */
  public static function process($element, FormStateInterface $form_state, $form): array {
    $entity_type = '';
    $current_entity = NULL;
    // Get form object to retrieve parent entity.
    $form_object = $form_state->getFormObject();

    if ($form_object instanceof EntityFormInterface) {
      $current_entity = $form_object->getEntity();
    }
    // Support for media library.
    elseif ($form_object instanceof AddFormBase) {
      $form_storage = $form_state->getStorage();
      if (isset($form_storage['media'][0])) {
        $current_entity = $form_storage['media'][0];
      }
    }

    if (!empty($current_entity)) {
      $entity_type = $current_entity->getEntityTypeId();
    }

    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];

    // Fill alt & title fields from widget settings if they are empty.
    if (empty($item['alt']) && !empty($element['#default_alt'])) {
      $element['#value']['alt'] = $element['#default_alt'];
    }
    if (empty($item['title']) && !empty($element['#default_title'])) {
      $element['#value']['title'] = $element['#default_title'];
    }

    // Call parent process to set up AJAX handlers, buttons, preview,
    // alt/title text fields, and all other ManagedFile infrastructure.
    $element = parent::process($element, $form_state, $form);

    // Apply token replacement to alt/title default values.
    $alt_value = $element['alt']['#default_value'] ?? '';
    $title_value = $element['title']['#default_value'] ?? '';

    $alt_token = '';
    $title_token = '';

    if (!empty($alt_value)) {
      $alt_token = \Drupal::token()->replace($alt_value, [$entity_type => $current_entity]);
      if (empty($alt_token)) {
        $alt_token = $alt_value;
      }
    }
    if (!empty($title_value)) {
      $title_token = \Drupal::token()->replace($title_value, [$entity_type => $current_entity]);
      if (empty($title_token)) {
        $title_token = $title_value;
      }
    }

    $element['alt']['#default_value'] = $alt_token;
    $element['title']['#default_value'] = $title_token;
    $element['#value']['alt'] = $alt_token;
    $element['#value']['title'] = $title_token;

    return $element;
  }

}
