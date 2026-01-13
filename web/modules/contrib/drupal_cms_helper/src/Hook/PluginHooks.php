<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class PluginHooks {

  use StringTranslationTrait;

  #[Hook('project_browser_source_info_alter')]
  public function projectBrowserSourceInfoAlter(array &$definitions): void {
    $definition = &$definitions['drupalorg_jsonapi'];
    if (strval($definition['label']) === strval($definition['local_task']['title'])) {
      $definition['local_task']['title'] = $this->t('Browse modules');
    }
  }

  #[Hook(
    'menu_links_discovered_alter',
    order: new OrderAfter(['navigation']),
  )]
  public function alterDiscoveredMenuLinks(array &$definitions): void {
    // @see \Drupal\navigation\NavigationContentLinks::addMenuLinks()
    foreach ($definitions as $id => $definition) {
      if (isset($definition['parent']) && $definition['parent'] === 'navigation.create') {
        unset($definitions[$id]);
      }
    }
    unset($definitions['navigation.create']);
  }

  #[Hook('menu_local_actions_alter')]
  public function alterLocalActions(array &$definitions): void {
    // Make bulk upload the default administrative experience for adding media.
    if (isset($definitions['media_library_bulk_upload.list'], $definitions['media.add'])) {
      $definitions['media_library_bulk_upload.list']['title'] = $definitions['media.add']['title'];
      // Make the original action appear nowhere, but don't unset it entirely
      // in case other code needs to alter it.
      $definitions['media.add']['appears_on'] = [];
    }
  }

}
