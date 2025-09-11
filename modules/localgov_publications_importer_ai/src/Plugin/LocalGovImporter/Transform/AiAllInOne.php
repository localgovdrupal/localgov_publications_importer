<?php

namespace Drupal\localgov_publications_importer_ai\Plugin\LocalGovImporter\Transform;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Exception\AiRequestErrorException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\localgov_publications_importer\Attribute\Transform;
use Drupal\localgov_publications_importer\Exception\RetryableTransformFailure;
use Drupal\localgov_publications_importer\ImportInterface;
use Drupal\localgov_publications_importer\Page;
use Drupal\localgov_publications_importer\Plugin\LocalGovImporter\Transform\TransformPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Transform operation that uses AI to clean up content.
 */
#[Transform(
  id: 'transform_ai_aio',
  label: new TranslatableMarkup('AI all in one.'),
  description: new TranslatableMarkup('Uses AI to reintroduce missing document structure.')
)]
class AiAllInOne extends TransformPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The default AI prompt to use for transforming content.
   *
   * This can be overridden by the plugin's configuration.
   */
  protected string $prompt = 'You are a website content editor. Your task is to format
    the plain text the user will provide for you with appropriate HTML markup.
    Do not rewrite or edit the text content of the document, the text content
    must be returned exactly as is. Only return HTML markup that would be valid
    for pasting inside a website CMS text editor, do not include markdown style
    backticks. Use the first line as a <h1> if it makes sense as a complete sentence, mark up the remainder of the text
    using only the html tags <h2>, <h3>, <h4>, <h5>, <h6>, <p>, <ul>, <ol>,
    <li>. Keep headings in sequence and be consistent. You will be sent the text
    for an entire document. Split the given text into pages where page breaks are
    appropriate. Return a single message for all the pages, comprised of
    valid JSON. The returned message should be an array of objects, where each
    object has a title key with a suggested title for the page, and a content
    key, with the HTML content for that page. Only return JSON. Do not wrap the JSON message in markdown.';

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

    if (isset($configuration['prompt'])) {
      $this->prompt = $configuration['prompt'];
    }
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
  public function transform(ImportInterface $import, ?int $page = NULL): void {

    // We only want to do this once.
    // @todo A Proper way of doing this. Like a property of the plugin?
    if ($page > 0) {
      return;
    }

    $content = [];

    // Get all the content.
    foreach ($import->getPages() as $pageObj) {
      $content[] = $pageObj->getContent();
    }

    $sets = $this->aiProvider->getDefaultProviderForOperationType('chat');

    // If there's no AI provider returned, don't try to use one.
    // @todo Consider better ways to handle this.
    // Log an error? Show a flash message?
    if (is_null($sets)) {
      return;
    }

    /** @var \Drupal\ai\OperationType\Chat\ChatInterface $provider */
    $provider = $this->aiProvider->createInstance($sets['provider_id']);
    $provider->setChatSystemRole($this->prompt);

    $messages = new ChatInput([
      new chatMessage('user', implode(" ", $content)),
    ]);

    try {
      $message = $provider->chat($messages, $sets['model_id'])->getNormalized();
    }
    catch (AiRequestErrorException $e) {
      // AiRequestErrorException is thrown for timeouts.
      // We could retry this request.
      throw new RetryableTransformFailure("Request to AI failed.", 0, $e);
    }

    $aiResponseText = $message->getText();
    $aiResponse = json_decode($aiResponseText, TRUE);

    if ($aiResponse === NULL) {
      // Decoding the response failed.
      \Drupal::logger('localgov_publications_importer')->error("Couldn't decode JSON response.");
      \Drupal::logger('localgov_publications_importer')->info($aiResponseText);
      return;
    }

    $pages = [];

    foreach ($aiResponse as $aiSuggestedPage) {
      $page = new Page();
      $page->setTitle($aiSuggestedPage['title']);
      $page->setContent($aiSuggestedPage['content']);
      $pages[] = $page;
    }

    $import->setPages($pages);
  }

  /**
   * {@inheritDoc}
   */
  public function isConfigurable(): bool {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfigurationForm(): array {
    return [
      'prompt' => [
        '#type' => 'textarea',
        '#description' => new TranslatableMarkup("The prompt that will be sent to the AI to describe what you'd like to do with the extracted content"),
        '#default_value' => $this->prompt,
      ],
    ];
  }

}
