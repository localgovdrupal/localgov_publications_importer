<?php

namespace Drupal\localgov_publications_importer_ai\Plugin\LocalGovImporter\Transform;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\localgov_publications_importer\Attribute\Transform;
use Drupal\localgov_publications_importer\PageInterface;
use Drupal\localgov_publications_importer\Plugin\LocalGovImporter\Transform\TransformPluginBase;
use Masterminds\HTML5;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Transform operation that uses AI to clean up content.
 */
#[Transform(
  id: 'transform_ai',
  label: new TranslatableMarkup('AI'),
  description: new TranslatableMarkup('Uses AI to reintroduce missing document structure.')
)]
class Ai extends TransformPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.provider'),
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
  public function order(): int {
    return 40;
  }

  /**
   * {@inheritDoc}
   */
  public function transformPage(PageInterface $page): void {

    $sets = $this->aiProvider->getDefaultProviderForOperationType('chat');

    // If there's no AI provider returned, don't try to use one.
    // @todo Consider better ways to handle this.
    // Log an error? Show a flash message?
    if (is_null($sets)) {
      return;
    }

    $provider = $this->aiProvider->createInstance($sets['provider_id']);
    $messages = new ChatInput([
      new chatMessage('system', 'This plain text document has been stripped of its formatting. Please add the formatting back in, and give me the whole document back as valid HTML.'),
      new chatMessage('user', $page->getContent()),
    ]);
    $message = $provider->chat($messages, $sets['model_id'])->getNormalized();

    // This is a fallback. It'll be overwritten below if we find a title element
    // in the returned message.
    $page->setContent($message->getText());

    $html5 = new HTML5(['disable_html_ns' => TRUE, 'encoding' => 'UTF-8']);
    $dom = $html5->loadHTML($message->getText());

    // Use the contents of <title> for the page title.
    $title = $dom->getElementsByTagName('title')->item(0);
    if ($title instanceof \DOMNode) {
      $page->setTitle($title->nodeValue);
    }

    // Remove <footer>.
    $footer = $dom->getElementsByTagName('footer')->item(0);
    if ($footer instanceof \DOMNode) {
      $footer->parentNode->removeChild($footer);
    }

    // Use the contents of the <body> for the content.
    $body = $dom->getElementsByTagName('body')->item(0);
    if ($body instanceof \DOMNode) {
      $content = $dom->saveHTML($body);
      $page->setContent($content);
    }
  }

}
