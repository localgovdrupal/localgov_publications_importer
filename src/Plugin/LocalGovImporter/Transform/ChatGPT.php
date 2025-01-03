<?php

namespace Drupal\localgov_publications_importer\Plugin\LocalGovImporter\Transform;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\localgov_publications_importer\Attribute\Transform;
use Drupal\localgov_publications_importer\Page;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Transform operation to Transform content as an HTML publication.
 */
#[Transform(
  id: 'transform_chatgpt',
  label: new TranslatableMarkup('ChatGPT'),
  description: new TranslatableMarkup('Uses ChatGPT to reintroduce missing document structure.')
)]
class ChatGPT extends TransformPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.provider')
    );
  }

  /**
   * Constructor.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AiProviderPluginManager $aiProvider,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritDoc}
   */
  public function transformPage(Page $page): void {

    $sets = $this->aiProvider->getDefaultProviderForOperationType('chat');

    $provider = $this->aiProvider->createInstance($sets['provider_id']);
    $messages = new ChatInput([
      new chatMessage('system', 'This plain text document has been stripped of its formatting. Please add the formatting back in, and give me the whole document back as valid HTML.'),
      new chatMessage('user', $page->getContent()),
    ]);
    $message = $provider->chat($messages, $sets['model_id'])->getNormalized();
    $page->setContent($message->getText());
  }

}
