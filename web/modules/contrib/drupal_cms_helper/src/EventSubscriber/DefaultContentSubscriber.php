<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\EventSubscriber;

use Drupal\Core\DefaultContent\PreExportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adjusts exported default content for Drupal CMS.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class DefaultContentSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreExportEvent::class => 'preExport',
    ];
  }

  /**
   * Prepares to export a content entity.
   */
  public function preExport(PreExportEvent $event): void {
    // Canvas adds a `target_uuid` property to entity reference fields, which is
    // useless in exported content.
    $callbacks = $event->getCallbacks();
    $decorated = $callbacks['field_item:entity_reference'] ?? NULL;
    if ($decorated) {
      $event->setCallback('field_item:entity_reference', function () use ($decorated): ?array {
        $values = $decorated(...func_get_args());
        if (is_array($values)) {
          unset($values['target_uuid']);
        }
        return $values;
      });
    }
  }

}
