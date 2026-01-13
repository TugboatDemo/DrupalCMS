<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_cms_helper\Functional;

use Composer\InstalledVersions;
use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\drupal_cms_helper\EventSubscriber\AccessDeniedSubscriber;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversClass(AccessDeniedSubscriber::class)]
#[Group('drupal_cms_helper')]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
final class AccessDeniedTest extends BrowserTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['drupal_cms_helper', 'gin_login'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  public function testContentAccessDenied(): void {
    // Set up a content type, with Drupal CMS's content authoring infrastructure
    // that will produce a 404 for unpublished content.
    $dir = InstalledVersions::getInstallPath('drupal/drupal_cms_page');
    $this->applyRecipe($dir);

    // Create a page we don't have access to, and visiting it should be a 404
    // that doesn't redirect to the login page.
    $node = $this->drupalCreateNode(['type' => 'page']);
    $node->setUnpublished()->save();
    $this->drupalGet($node->toUrl());
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(404);
    // No login form should be here.
    $assert_session->elementNotExists('css', 'form');

    // Trying to access any other forbidden page, on the other hand, SHOULD send
    // us to the login form.
    $this->drupalGet('/admin');
    $assert_session->elementExists('css', '.user-login-form');
  }

  public function testLoginPageThemeOn403(): void {
    $this->container->get(ThemeInstallerInterface::class)->install(['gin']);
    $this->config('system.theme')->set('admin', 'gin')->save();

    $this->drupalGet('/admin');
    $assert_session = $this->assertSession();
    // We were fully redirected, so it's a 200 instead of a 403.
    $assert_session->statusCodeEquals(200);
    $this->assertStringContainsString('/user/login?destination=', $this->getUrl());
    // Gin should be the active theme; we can detect that since it will have
    // injected JS settings.
    $this->assertArrayHasKey('gin', $this->getDrupalSettings());

    // Trying to visit /user or /user/login while logged out should redirect to
    // the login form, but *without* the destination, since we never want to go
    // to the core user profile page.
    foreach (['/user', '/user/', '/user/login', '/user/login/'] as $url) {
      $this->drupalGet($url);
      $assert_session->statusCodeEquals(200);
      $this->assertStringNotContainsString('?destination=', $this->getUrl());
    }
  }

}
