<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Plugin\Block;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 * This is an internal part of Drupal CMS and may be changed or removed at any
 * time without warning. External code should not interact with this class.
 *
 * @todo Remove this when https://www.drupal.org/i/3557882 is released.
 */
#[Block(
  id: 'dashboard_optional',
  admin_label: new TranslatableMarkup('Optional dashboard block'),
  category: new TranslatableMarkup('Dashboard'),
)]
final class OptionalBlock implements BlockPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    private readonly BlockPluginInterface $decorated,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $decorated_id = $configuration['decorates'] ?? 'broken';
    unset($configuration['decorates']);

    $block_manager = $container->get(BlockManagerInterface::class);
    if ($block_manager->hasDefinition($decorated_id)) {
      $decorated = $block_manager->createInstance($decorated_id, $configuration);
    }
    else {
      $decorated =  $block_manager->createInstance('broken');
    }
    return new self($decorated);
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string|\Stringable {
    return $this->decorated->label();
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account, $return_as_object = FALSE): AccessResultInterface|bool {
    return $this->decorated->access($account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return $this->decorated->getPluginId() === 'broken' ? [] : $this->decorated->build();
  }

  /**
   * {@inheritdoc}
   */
  public function createPlaceholder(): bool {
    return $this->decorated->createPlaceholder();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigurationValue($key, $value): void {
    $this->decorated->setConfigurationValue($key, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    return $this->decorated->blockForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state): void {
    $this->decorated->blockValidate($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->decorated->blockSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getMachineNameSuggestion(): string {
    return $this->decorated->getMachineNameSuggestion();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return $this->decorated->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return $this->decorated->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return $this->decorated->getCacheMaxAge();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return [
      'decorates' => $this->getPluginId(),
    ] + $this->decorated->getConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->decorated->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return $this->decorated->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    return $this->decorated->calculateDependencies();
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseId(): string {
    return $this->decorated->getBaseId();
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeId(): ?string {
    return $this->decorated->getDerivativeId();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $this->decorated->buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->decorated->validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->decorated->submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId(): string {
    return $this->decorated->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition(): array {
    return $this->decorated->getPluginDefinition();
  }

  public function __call(string $name, array $arguments) {
    return $this->decorated->$name(...$arguments);
  }

}
