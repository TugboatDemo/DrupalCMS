<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\EventSubscriber;

use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 *
 * @todo Remove this either when https://www.drupal.org/i/3508338 is released,
 *   or when Gin has been moved into core and https://www.drupal.org/i/3554693
 *   has been released.
 */
final class AccessDeniedSubscriber extends HttpExceptionSubscriberBase {

  public function __construct(
    private readonly RedirectDestinationInterface $redirect,
    private readonly RouteMatchInterface $routeMatch,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats(): array {
    return ['html'];
  }

  /**
   * {@inheritdoc}
   */
  protected static function getPriority(): int {
    // Run before the User module's exception subscriber.
    return 100;
  }

  protected function on403(ExceptionEvent $event): void {
    // If this is a node route, the request is handled by ECA, which returns
    // a 404 for unpublished nodes. If you're already logged in, redirecting to
    // the login form makes no sense in any case.
    if ($this->routeMatch->getRouteName() === 'entity.node.canonical' || $this->currentUser->isAuthenticated()) {
      return;
    }

    // All other 403s should redirect to the login page, so we work around
    // https://www.drupal.org/i/3508338 by doing a full redirect.
    $destination = $this->redirect->get();
    $url = Url::fromRoute('user.login')
      // Only include the redirect destination if it's not the user page.
      ->setOption('query', $destination === Url::fromRoute('user.page')->toString() ? [] : $this->redirect->getAsArray())
      ->toString();
    $event->setResponse(new RedirectResponse($url));
  }

}
