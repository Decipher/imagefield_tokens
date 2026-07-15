<?php

declare(strict_types=1);

namespace Drupal\imagefield_tokens\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Drupal\image\ImageDerivativeUtilities;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\token\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'image' formatter.
 *
 * @FieldFormatter(
 *   id = "imagefield_tokens",
 *   label = @Translation("ImageField Tokens"),
 *   field_types = {
 *     "image"
 *   },
 *   quickedit = {
 *     "editor" = "image"
 *   }
 * )
 */
class ImageFieldTokensFormatter extends ImageFormatter {

  /**
   * Constructs an ImageFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityStorageInterface $image_style_storage
   *   The image style storage.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $routeMatch
   *   RouteMatch service.
   * @param \Drupal\token\Token $tokenService
   *   Token service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $current_user, EntityStorageInterface $image_style_storage, FileUrlGeneratorInterface $file_url_generator, protected CurrentRouteMatch $routeMatch, protected Token $tokenService) {
    $parent_args = [
      $plugin_id, $plugin_definition, $field_definition, $settings,
      $label, $view_mode, $third_party_settings, $current_user,
      $image_style_storage, $file_url_generator,
    ];
    // Drupal 11.4 added ImageDerivativeUtilities as a required parameter.
    if (class_exists(ImageDerivativeUtilities::class)) {
      // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
      $parent_args[] = \Drupal::service(ImageDerivativeUtilities::class);
    }
    // @phpstan-ignore arguments.count
    parent::__construct(...$parent_args);
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
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('file_url_generator'),
      $container->get('current_route_match'),
      $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return mixed[]
   *   A render array for the field.
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $entity = NULL;
    $files = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($files)) {
      return $elements;
    }

    $url = NULL;
    $image_link_setting = $this->getSetting('image_link');
    // Check if the formatter involves a link.
    if ($image_link_setting === 'content') {
      $entity = $items->getEntity();
      if (!$entity->isNew()) {
        $url = $entity->toUrl();
      }
    }
    elseif ($image_link_setting === 'file') {
      $link_file = TRUE;
    }

    $image_style_setting = $this->getSetting('image_style');

    // Collect cache tags to be added for each item in the field.
    $base_cache_tags = [];
    if (!empty($image_style_setting)) {
      $image_style = $this->imageStyleStorage->load($image_style_setting);
      $base_cache_tags = $image_style->getCacheTags();
    }

    foreach ($files as $delta => $file) {
      \assert($file instanceof FileInterface);
      $cache_contexts = [];
      if (isset($link_file)) {
        $image_uri = $file->getFileUri();
        $url = $this->fileUrlGenerator->generate($image_uri);
      }
      $cache_tags = Cache::mergeTags($base_cache_tags, $file->getCacheTags());

      // Extract field item attributes for the theme function, and unset them
      // from the $item so that the field template does not re-render them.
      // @phpstan-ignore property.notFound
      $item = $file->_referringItem;
      $item_attributes = $item->_attributes;
      unset($item->_attributes);
      // Get item values.
      $item_values = $item->getValue();
      // Get entity from request.
      $request_params = $this->routeMatch->getParameters()->all();
      if (count($request_params) > 0) {
        foreach ($request_params as $param) {
          if (is_object($param)) {
            $entity = $param;
          }
        }
      }
      $data = [];
      if ($entity) {
        try {
          // @phpstan-ignore method.notFound
          if (method_exists($entity, 'getEntityTypeId')) {
            $data[$entity->getEntityTypeId()] = $entity;
          }
          elseif (method_exists($entity, 'getContext')) {
            $entity_type = $entity->getContext('entity')->getContextData()->getValue('entity')->getEntityTypeId();
            $data[$entity_type] = $entity;
          }
        }
        catch (ContextException) {
          // No entity context. Not necessarily an error. Just keep going.
        }
      }
      // Replace entity tokens.
      $alt_bubbles = new BubbleableMetadata();
      $alt_token = $this->tokenService->replace($item_values['alt'], $data, [], $alt_bubbles);
      $title_bubbles = new BubbleableMetadata();
      $title_token = $this->tokenService->replace($item_values['title'], $data, [], $title_bubbles);
      // Set converted values to the item.
      $item_values['alt'] = $alt_token;
      $item_values['title'] = $title_token;
      $item->setValue($item_values);

      $elements[$delta] = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.4.0', fn(): array => [
        '#theme' => 'image_formatter',
        '#item' => $item,
        '#attributes' => $item_attributes,
        '#image_style' => $image_style_setting,
        '#url' => $url,
        '#cache' => [
          'tags' => $cache_tags,
          'contexts' => $cache_contexts,
        ],
      ], fn(): array => [
        '#theme' => 'image_formatter',
        '#item' => $item,
        '#item_attributes' => $item_attributes,
        '#image_style' => $image_style_setting,
        '#url' => $url,
        '#cache' => [
          'tags' => $cache_tags,
          'contexts' => $cache_contexts,
        ],
      ]);

      // Add cache info related to tokens.
      $existing_elements_cache = CacheableMetadata::createFromRenderArray($elements[$delta]);
      $token_bubbleable_metadata = $alt_bubbles->merge($title_bubbles);
      $updated_elements_cache = $existing_elements_cache->merge($token_bubbleable_metadata);
      $updated_elements_cache->applyTo($elements[$delta]);
    }

    return $elements;
  }

}
