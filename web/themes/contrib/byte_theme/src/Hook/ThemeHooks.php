<?php

declare(strict_types=1);

namespace Drupal\byte_theme\Hook;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\byte_theme\RenderCallbacks;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Contains hook implementations for Byte Theme.
 */
final class ThemeHooks {

  /**
   * The Drupal root.
   */
  private static ?string $appRoot = NULL;

  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly ThemeExtensionList $themeList,
    #[Autowire(param: 'app.root')] string $appRoot,
  ) {
    self::$appRoot ??= $appRoot;
  }

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public function alterElementInfo(array &$info): void {
    $info['component']['#pre_render'][] = [RenderCallbacks::class, 'preRenderComponent'];
  }

  /**
   * Implements hook_library_info_alter().
   */
  #[Hook('library_info_alter')]
  public function alterLibraryInfo(array &$libraries, string $extension): void {
    $override = static function (string $name, string $replacement) use (&$libraries): void {
      $old_parents = ['global', 'css', 'theme', $name];
      $new_parents = [...array_slice($old_parents, 0, -1), $replacement];
      $css_settings = NestedArray::getValue($libraries, $old_parents);
      NestedArray::setValue($libraries, $new_parents, $css_settings);
      NestedArray::unsetValue($libraries, $old_parents);
    };
    if ($extension === 'byte_theme') {
      if (file_exists(self::$appRoot . '/theme.css')) {
        $override('src/theme.css', '/theme.css');
      }
      if (file_exists(self::$appRoot . '/fonts.css')) {
        $override('src/fonts.css', '/fonts.css');
      }
    }
  }

  /**
   * Implements template_preprocess_image_widget().
   */
  #[Hook('preprocess_image_widget')]
  public function preprocessImageWidget(array &$variables): void {
    $data = &$variables['data'];

    // This prevents image widget templates from rendering preview container
    // HTML to users that do not have permission to access these previews.
    // @todo revisit in https://drupal.org/node/953034
    // @todo revisit in https://drupal.org/node/3114318
    if (isset($data['preview']['#access']) && $data['preview']['#access'] === FALSE) {
      unset($data['preview']);
    }
  }

  /**
   * Implements template_preprocess_views_view().
   */
  #[Hook('preprocess_views_view')]
  #[Hook('preprocess_views_view_unformatted')]
  public function preprocessView(array &$variables): void {
    $view = $variables['view'];
    assert($view instanceof ViewExecutable);
    $view_tags = preg_split('/\s+/', $view->storage->get('tag'));
    $variables['snap'] = in_array('snap', $view_tags, TRUE);
  }

  /**
   * Implements template_preprocess_html().
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    // Get the theme base path for font preloading.
    $variables['byte_theme_path'] = $this->requestStack->getCurrentRequest()->getBasePath() . '/' . $this->themeList->getPath('byte_theme');
  }

}
