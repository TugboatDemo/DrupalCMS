<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_cms_helper\Kernel;

use Drupal\Core\DefaultContent\Exporter;
use Drupal\Core\DefaultContent\ExportMetadata;
use Drupal\Core\DefaultContent\ExportResult;
use Drupal\Core\DefaultContent\PreExportEvent;
use Drupal\drupal_cms_helper\ProcessRunner;
use Drupal\drupal_cms_helper\SiteExporter;
use Drupal\KernelTests\KernelTestBase;
use PhpTuf\ComposerStager\API\Process\Service\ComposerProcessRunnerInterface;
use PhpTuf\ComposerStager\API\Process\Service\RsyncProcessRunnerInterface;
use PHPUnit\Framework\Attributes\Group;

#[Group('drupal_cms_helper')]
final class ServiceOverridesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'drupal_cms_helper',
    'package_manager',
    'path_alias',
    'update',
  ];

  /**
   * Tests that container services are altered correctly.
   */
  public function testServiceAlterations(): void {
    $this->assertInstanceOf(
      ProcessRunner::class,
      $this->container->get(ComposerProcessRunnerInterface::class),
    );
    $this->assertInstanceOf(
      ProcessRunner::class,
      $this->container->get(RsyncProcessRunnerInterface::class),
    );

    // The default content exporter's classes should exist (they are polyfilled
    // in 11.2.x).
    $this->assertTrue(class_exists(Exporter::class));
    $this->assertTrue(class_exists(ExportMetadata::class));
    $this->assertTrue(class_exists(ExportResult::class));
    $this->assertTrue(class_exists(PreExportEvent::class));
    // We should be able to instantiate both the default content exporter by
    // itself, and the site exporter which depends on it.
    $this->assertInstanceOf(
      Exporter::class,
      $this->container->get(Exporter::class),
    );
    $this->assertInstanceOf(
      SiteExporter::class,
      $this->container->get(SiteExporter::class),
    );
  }

}
