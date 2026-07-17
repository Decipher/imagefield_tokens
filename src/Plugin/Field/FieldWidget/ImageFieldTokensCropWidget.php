<?php

declare(strict_types=1);

namespace Drupal\imagefield_tokens\Plugin\Field\FieldWidget;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\image_widget_crop\ImageWidgetCropInterface;
use Drupal\image_widget_crop\Plugin\Field\FieldWidget\ImageCropWidget;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\media_library\Form\AddFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'image_widget_crop' widget.
 *
 * @FieldWidget(
 *   id = "imagefield_tokens_widget_crop",
 *   label = @Translation("ImageField Tokens Widget crop"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class ImageFieldTokensCropWidget extends ImageCropWidget {

  /**
   * Constructs a new ImageFieldTokensCropWidget object.
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
   * @param \Drupal\image_widget_crop\ImageWidgetCropInterface $iwc_manager
   *   The ImageWidgetCrop manager service.
   * @param \Drupal\Core\Entity\EntityStorageInterface $image_style_storage
   *   The image style entity storage.
   * @param \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $crop_type_storage
   *   The crop type storage.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current user service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ElementInfoManagerInterface $element_info, ImageWidgetCropInterface $iwc_manager, EntityStorageInterface $image_style_storage, ConfigEntityStorageInterface $crop_type_storage, ConfigFactoryInterface $config_factory, protected AccountInterface $currentUser, protected ModuleHandlerInterface $moduleHandler) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $element_info, $iwc_manager, $image_style_storage, $crop_type_storage, $config_factory);
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
      $container->get('image_widget_crop.manager'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('entity_type.manager')->getStorage('crop_type'),
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('module_handler')
    );

  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $entity_type_id = '';
    $object = $form_state->getFormObject();
    if ($object instanceof EntityFormInterface) {
      $entity_type_id = $object->getEntity()->getEntityTypeId();
    }
    // When not on an entity form. Try to detect entity type with another way.
    elseif (isset($element['#entity_type'])) {
      $entity_type_id = $element['#entity_type'];
    }

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
   * Form API callback: Processes a crop_image field element.
   *
   * Expands the image_image type to include the alt and title fields.
   *
   * This method is assigned as a #process callback in formElement() method.
   *
   * @phpstan-param mixed $element
   * @phpstan-param mixed $form
   *
   * @return array
   *   The elements with parents fields.
   */
  public static function process($element, FormStateInterface $form_state, $form): array {
    $element = parent::process($element, $form_state, $form);

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
      // Get entity data.
      $entity_type = $current_entity->getEntityTypeId();
      $entity_bundle = $current_entity->bundle();
      // Get field settings.
      $field_name = $element['#field_name'];
      $field_config = FieldConfig::loadByName($entity_type, $entity_bundle, $field_name);
      $field_settings = $field_config->getSettings();
    }

    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];
    $alt_token = '';
    $title_token = '';

    if (!empty($field_settings) && !empty($field_settings['default_image'])) {
      if (empty($item['alt'])) {
        $item['alt'] = $field_settings['default_image']['alt'];
      }
      if (empty($item['title'])) {
        $item['title'] = $field_settings['default_image']['title'];
      }
    }

    if (isset($item['alt'])) {
      $alt_token = \Drupal::token()->replace($item['alt'], [$entity_type => $current_entity]);
      if (empty($alt_token)) {
        $alt_token = $item['alt'];
      }
    }
    if (isset($item['title'])) {
      $title_token = \Drupal::token()->replace($item['title'], [$entity_type => $current_entity]);
      if (empty($title_token)) {
        $title_token = $item['title'];
      }
    }

    // Add the additional alt and title fields.
    $element['alt'] = [
      '#title' => new TranslatableMarkup('Alternative text'),
      '#type' => 'textfield',
      '#default_value' => $alt_token ?? '',
      '#description' => new TranslatableMarkup('This text will be used by screen readers, search engines, or when the image cannot be loaded.'),
      // @see https://www.drupal.org/node/465106#alt-text
      '#maxlength' => 512,
      '#weight' => -12,
      '#access' => (bool) $item['fids'] && $element['#alt_field'],
      '#required' => $element['#alt_field_required'],
      '#element_validate' => $element['#alt_field_required'] ? [[static::class, 'validateRequiredFields']] : [],
    ];

    $element['title'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Title'),
      '#default_value' => $title_token ?? '',
      '#description' => new TranslatableMarkup('The title is used as a tool tip when the user hovers the mouse over the image.'),
      '#maxlength' => 1024,
      '#weight' => -11,
      '#access' => (bool) $item['fids'] && $element['#title_field'],
      '#required' => $element['#title_field_required'],
      '#element_validate' => $element['#title_field_required'] ? [[static::class, 'validateRequiredFields']] : [],
    ];

    $element['#value']['alt'] = $alt_token;
    $element['#value']['title'] = $title_token;

    return $element;
  }

}
