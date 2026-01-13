<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageCopyTrait;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\DefaultContent\Exporter;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\RoleInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Exports the current site as a recipe.
 *
 * This is part of Drupal CMS's developer-facing API and may be relied upon. You
 * may also take advantage of the public helper methods `loadAllContent()` and
 * `getExtensionRequirements()`.
 */
final class SiteExporter implements LoggerAwareInterface {

  use LoggerAwareTrait;
  use StorageCopyTrait;
  use StringTranslationTrait;

  public function __construct(
    private readonly ModuleExtensionList $moduleList,
    private readonly ThemeExtensionList $themeList,
    private readonly FileSystemInterface $fileSystem,
    private readonly StorageInterface $storage,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ConfigManagerInterface $configManager,
    private readonly Exporter $contentExporter,
  ) {}

  /**
   * Exports the current site's configuration and content into a recipe.
   *
   * @param string $destination
   *   The path where the recipe should be created.
   */
  public function export(string $destination): void {
    $this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    $extensions = $this->getInstalledExtensions();
    $this->updateComposerJson($destination, $extensions);

    // All simple config from the System and User modules needs to be updated
    // with config actions, because it's guaranteed to exist before the recipe
    // is applied.
    $names = [
      ...$this->storage->listAll('system.'),
      ...$this->storage->listAll('user.'),
      'core.menu.static_menu_link_overrides',
    ];
    $names = array_filter(
      $names,
      fn (string $name): bool => $this->configManager->getEntityTypeIdByName($name) === NULL,
    );
    foreach ($this->storage->readMultiple($names) as $name => $data) {
      unset($data['_core'], $data['uuid']);
      $actions[$name]['simpleConfigUpdate'] = $data;
    }
    // The anonymous and authenticated roles are guaranteed to exist by the time
    // the recipe is applied, so they too must be changed with config actions.
    $locked_roles = $this->entityTypeManager->getStorage('user_role')
      ->loadMultiple([
        RoleInterface::ANONYMOUS_ID,
        RoleInterface::AUTHENTICATED_ID,
      ]);
    foreach ($locked_roles as $role) {
      assert($role instanceof RoleInterface);
      $name = $names[] = $role->getConfigDependencyName();
      $actions[$name]['grantPermissions'] = $role->getPermissions();
    }
    $this->updateRecipe($destination, array_keys($extensions), $actions ?? []);

    $storage = new SiteExportFileStorage(
      $this->configManager,
      $this->entityTypeManager,
      $destination . '/config',
    );
    self::replaceStorageContents($this->storage, $storage);
    // The core.extension config should never be included in a recipe.
    $names[] = 'core.extension';
    // Exclude the default collection's System and User configuration from the
    // exported config. We DO want to include it in the non-default collections
    // -- i.e., translations.
    array_walk($names, $storage->createCollection(StorageInterface::DEFAULT_COLLECTION)->delete(...));

    // Export all content, with its dependencies, as files.
    foreach ($this->loadAllContent() as $entity) {
      $this->contentExporter->exportWithDependencies($entity, $destination . '/content');
    }
  }

  /**
   * Loads all exportable content entities.
   *
   * @return iterable<\Drupal\Core\Entity\ContentEntityInterface>
   *   An iterable that yields content entities.
   */
  public function loadAllContent(): iterable {
    foreach ($this->entityTypeManager->getDefinitions() as $id => $entity_type) {
      // Path aliases are created when the content is, and therefore should not
      // be exported. Internal entities are more of a grey area, but we can
      // safely assume they shouldn't be exported (content moderation states are
      // the main example in core).
      if ($entity_type->isInternal() || $id === 'path_alias') {
        continue;
      }
      if ($entity_type->entityClassImplements(ContentEntityInterface::class)) {
        $storage = $this->entityTypeManager->getStorage($id);
        $query = $storage->getQuery()->accessCheck(FALSE);
        // Ignore users 0 or 1, since they always exist with those IDs.
        if ($id === 'user') {
          $query->condition('uid', 1, '>');
        }
        foreach ($query->execute() as $entity_id) {
          yield $storage->load($entity_id);
        }
      }
    }
  }

  /**
   * Finds all installed modules and themes.
   *
   * @return \Drupal\Core\Extension\Extension[]
   *   All installed extensions, keyed by machine name.
   */
  private function getInstalledExtensions(): array {
    $modules = array_intersect_key(
      $this->moduleList->getList(),
      $this->moduleList->getAllInstalledInfo(),
    );
    $themes = array_intersect_key(
      $this->themeList->getList(),
      $this->themeList->getAllInstalledInfo(),
    );
    return array_filter(
      [...$modules, ...$themes],
      // Install profiles should always be excluded from recipes.
      fn (Extension $e): bool => $e->getType() !== 'profile',
    );
  }

  /**
   * Generates Composer version constraints for a set of extensions.
   *
   * @param \Drupal\Core\Extension\Extension[] $extensions
   *   A set of extensions.
   *
   * @return array<string, string>
   *   An array of Composer version constraints, keyed by package name.
   */
  public function getExtensionRequirements(array $extensions): array {
    $requirements = [];

    foreach ($extensions as $name => $extension) {
      $package_name = str_starts_with($extension->getPath(), 'core/')
        ? 'drupal/core'
        : 'drupal/' . ($extension->info['project'] ?? $name);

      try {
        $version = InstalledVersions::getPrettyVersion($package_name);
      }
      catch (\OutOfBoundsException) {
        $message = $this->t('Cannot determine a version constraint for @type @names because the package @package does not appear to be installed.', [
          '@type' => $extension->getType(),
          '@name' => $name,
          '@package' => $package_name,
        ]);
        $this->logger?->warning((string) $message);
        continue;
      }

      $stability = VersionParser::parseStability($version);
      $stability = VersionParser::normalizeStability($stability);
      $requirements[$package_name] = $stability === 'dev' ? $version : "^$version";

      if ($stability !== 'stable') {
        $message = $this->t('Package @package has a @stability version constraint, which may prevent the recipe from being installed into projects that require stable dependencies.', [
          '@stability' => $stability,
          '@package' => $package_name,
        ]);
        $this->logger?->warning((string) $message);
      }
    }
    return $requirements;
  }

  /**
   * Alters `composer.json` to match the site being exported.
   *
   * - The `type` key is always set to `drupal-recipe`.
   * - Version constraints are generated for all installed extensions and added
   *   to the `require` section; existing constraints are preserved.
   *
   * @param string $destination
   *   The directory where the site is being exported.
   * @param \Drupal\Core\Extension\Extension[] $extensions
   *   All installed extensions.
   */
  private function updateComposerJson(string $destination, array $extensions): void {
    $data = [];

    $destination .= '/composer.json';
    if (file_exists($destination)) {
      $data = file_get_contents($destination);
      $data = Json::decode($data);
    }
    $data['require'] = array_merge(
      $this->getExtensionRequirements($extensions),
      $data['require'] ?? [],
    );
    $data['type'] = Recipe::COMPOSER_PROJECT_TYPE;

    $data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($destination, $data);
  }

  /**
   * Alters `recipe.yml` to match the site being exported.
   *
   * - The `name` key, if defined in recipe.yml, is preserved. Otherwise, it
   *   defaults to the site name.
   * - The `type` key is always set to `Site`.
   * - Any extensions which are not already in the `install` list will be
   *   appended to it.
   * - The given config actions will be deep-merged into the config actions
   *   already in `recipe.yml`, with the given config actions "winning" any
   *   conflicts.
   *
   * @param string $destination
   *   The directory where the site is being exported.
   * @param string[] $extensions
   *   Machine names names of the installed extensions.
   * @param array $actions
   *   Config actions to be merged into the recipe.
   */
  private function updateRecipe(string $destination, array $extensions, array $actions): void {
    $recipe = [];

    $destination .= '/recipe.yml';
    if (file_exists($destination)) {
      $recipe = file_get_contents($destination);
      $recipe = Yaml::decode($recipe);
    }
    $recipe['name'] ??= $this->configFactory->get('system.site')->get('name');
    $recipe['type'] = 'Site';

    // Add any new extensions to the recipe's install list, preserving the order
    // of the extant list (if there is one).
    $recipe['install'] ??= [];
    array_push(
      $recipe['install'],
      ...array_diff($extensions, $recipe['install']),
    );

    // The passed-in config actions overwrite the ones in the recipe.
    $recipe['config']['actions'] = NestedArray::mergeDeep(
      $recipe['config']['actions'] ?? [],
      $actions,
    );

    file_put_contents($destination, Yaml::encode($recipe));
  }

}
