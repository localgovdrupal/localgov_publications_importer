<?php

namespace Drupal\localgov_publications_importer_ai\Plugin\LocalGovImporter\Transform;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Exception\AiRequestErrorException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_provider_aws_bedrock\Decorator\BedrockJsonSerializeDecorator;
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
  protected string $prompt = '
You are a website content editor. Format the provided text into valid JSON only.

Requirements:
- Return ONLY a JSON array of page objects, no other text
- Each page object has: "title" (string), "content" (string)
- Split the content into MULTIPLE pages
- Each page should contain 200-500 words of content when possible
- Break pages at natural stopping points: section boundaries, topic changes, or major headings
- Content value contains HTML using only: h1, h2, h3, h4, h5, h6, p, ul, ol, li
- Use the first line as h1 if it\'s a complete sentence
- Preserve original text exactly, only add HTML tags
- Generate descriptive titles that reflect each page\'s main topic
- Properly escape all quotes and special characters in JSON strings

Split strategy:
- Look for major headings, topic shifts, or natural content breaks
- Each page should feel complete but part of a larger whole
- Distribute content evenly across pages
- Don\'t create pages that are too short (under 100 words) unless necessary

Example format:
[
  {"title":"Introduction and Overview","content":"<h1>Main Title</h1><p>Intro content...</p>"},
{"title":"Key Concepts","content":"<h2>Section Title</h2><p>More content...</p>"},
{"title":"Advanced Topics","content":"<h2>Another Section</h2><p>Final content...</p>"}
]
  ';

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

    $allContent = implode(" ", $content);

    //@todo: Rename this var.
    // Keys are provider_id, model_id.
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
      new chatMessage('user', $allContent),
    ]);

    try {
      $chatOutput = $provider->chat($messages, $sets['model_id']);
      $message = $chatOutput->getNormalized();
      $rawOutput = $chatOutput->getRawOutput();

      // We only know how to handle this for bedrock at the moment.
      if ($rawOutput instanceof BedrockJsonSerializeDecorator) {
        $rawJson = $rawOutput->jsonSerialize();
        if ($rawJson['stopReason'] === 'max_tokens') {
          \Drupal::logger('localgov_publications_importer')->error("Hit maximum output token limit when generating content.");
        }
      }
    }
    catch (AiRequestErrorException $e) {
      // AiRequestErrorException is thrown for timeouts.
      // We could retry this request.
      throw new RetryableTransformFailure("Request to AI failed.", 0, $e);
    }

    $aiResponseText = $message->getText();

    // Here we need to trim off anything before or after the JSON, eg:
    // "I'll format the provided text into valid JSON with multiple pages:"
    // @todo Move this to a function.
    $json_start = strpos($aiResponseText, '[');
    $json_end = strrpos($aiResponseText, ']');
    $json_length = 1 + $json_end - $json_start;
    $aiResponseText = substr($aiResponseText, $json_start, $json_length);

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
