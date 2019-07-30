<?php

namespace Drupal\imagefield_tokens\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\image_widget_crop\Plugin\Field\FieldWidget\ImageCropWidget;

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
   * {@inheritdoc}
   *
   * @return array[]
   *   The form elements for a single widget for this field.
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $entity_type_id = $form_state->getFormObject()->getEntity()->getEntityTypeId();
    if (!\Drupal::currentUser()->isAnonymous()) {
      // Add token link to the form.
      $form['#token'] = TRUE;
      $moduleHandler = \Drupal::service('module_handler');
      if ($moduleHandler->moduleExists('token')) {
        $form['token_tree'] = [
          '#theme' => 'token_tree_link',
          '#token_types' => [$entity_type_id],
          '#show_restricted' => TRUE,
          '#weight' => 90,
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
   * @return array
   *   The elements with parents fields.
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $element = parent::process($element, $form_state, $form);

    $entity_type = '';
    $current_entity = NULL;
    // Get form object to retrieve parent entity.
    $form_object = $form_state->getFormObject();
    $get_entity = method_exists($form_object, 'getEntity');

    if ($get_entity) {
      $current_entity = $form_object->getEntity();
      if (!empty($current_entity)) {
        $entity_type = $current_entity->getEntityTypeId();
        $entity_bundle = $current_entity->bundle();
        $field_name = $element['#field_name'];
        $field_config = FieldConfig::loadByName($entity_type, $entity_bundle, $field_name);
        $field_settings = $field_config->getSettings();
      }
    }

    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];
    $alt_token = '';
    $title_token = '';

    if (!empty($field_settings) && !empty($field_settings['default_image']) && (empty($item['alt']) || empty($item['title']))) {
      $item['alt'] = $field_settings['default_image']['alt'];
      $item['title'] = $field_settings['default_image']['title'];
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
      '#title' => t('Alternative text'),
      '#type' => 'textfield',
      '#default_value' => $alt_token ?? '',
      '#description' => t('This text will be used by screen readers, search engines, or when the image cannot be loaded.'),
      // @see https://www.drupal.org/node/465106#alt-text
      '#maxlength' => 512,
      '#weight' => -12,
      '#access' => (bool) $item['fids'] && $element['#alt_field'],
      '#required' => $element['#alt_field_required'],
      '#element_validate' => $element['#alt_field_required'] === 1 ? [[get_called_class(), 'validateRequiredFields']] : [],
    ];

    $element['title'] = [
      '#type' => 'textfield',
      '#title' => t('Title'),
      '#default_value' => $title_token ?? '',
      '#description' => t('The title is used as a tool tip when the user hovers the mouse over the image.'),
      '#maxlength' => 1024,
      '#weight' => -11,
      '#access' => (bool) $item['fids'] && $element['#title_field'],
      '#required' => $element['#title_field_required'],
      '#element_validate' => $element['#title_field_required'] === 1 ? [[get_called_class(), 'validateRequiredFields']] : [],
    ];

    $element['#value']['alt'] = $alt_token;
    $element['#value']['title'] = $title_token;

    return $element;
  }

}
