<?php

namespace Drupal\localgov_publications_importer_ai\Plugin\LocalGovImporter\Transform;

use Drupal\ai\Plugin\ProviderProxy;
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
- Properly escape all quotes, double quotes and special characters in JSON strings
- Ensure any JSON you create is valid. This is really important.

Split strategy:
- Look for major headings, topic shifts, or natural content breaks
- Each page should feel complete but part of a larger whole
- Distribute content evenly across pages
- Don\'t create pages that are too short (under 100 words) unless necessary

Example format:
[
  {"title":"Introduction and Overview","content":"<h1>Main Title<\/h1><p>Intro content...<\/p>"},
  {"title":"Key Concepts","content":"<h2>Section Title<\/h2><p>More content...<\/p>"},
  {"title":"Advanced Topics","content":"<h2>Another Section<\/h2><p>Final content...<\/p>"}
]
';

  /**
   * AI Provider ID.
   *
   * If this is empty the default provider for chat will be used.
   */
  protected string $aiProviderId = '';

  /**
   * AI Model ID.
   *
   * If this is empty the default model for chat will be used.
   */
  protected string $aiModelId = '';

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
    protected AiProviderPluginManager $aiProviderPluginManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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

    $this->configureAi();

    $provider = $this->aiProvider();
    if ($provider === NULL) {
      return;
    }

    $messages = new ChatInput([
      new chatMessage('user', $allContent),
    ]);

    try {
      $chatOutput = $provider->chat($messages, $this->aiModelId);
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

    $aiResponse = $this->extractAndDecodeJson($aiResponseText);

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

   */
  protected function aiProvider(): ?ProviderProxy {

    // If there's no AI provider configured, don't try to use one.
    // @todo Consider better ways to handle this.
    // Log an error? Show a flash message?
    if ($this->aiProviderId === '' || $this->aiModelId === '') {
      return NULL;
    }

    $provider = $this->aiProviderPluginManager->createInstance($this->aiProviderId);
    // Provider is an instance of ProviderProxy, which uses __call() to call
    // methods on the inner plugin. Hence, it looks like ::setChatSystemRole()
    // doesn't exist.
    $provider->setChatSystemRole($this->prompt);

    return $provider;
  }

  /**

   */
  protected function configureAi(): void {

    if (isset($this->configuration['prompt'])) {
      $this->prompt = $this->configuration['prompt'];
    }

    if (isset($this->configuration['aiProviderId'])) {
      $this->aiProviderId = $this->configuration['aiProviderId'];
    }

    if (isset($this->configuration['aiModelId'])) {
      $this->aiModelId = $this->configuration['aiModelId'];
    }

    // Keys are provider_id, model_id if we get an array back.
    $defaults = $this->aiProviderPluginManager->getDefaultProviderForOperationType('chat');
    if (is_array($defaults)) {
      if ($this->aiProviderId === '') {
        $this->aiProviderId = $defaults['provider_id'];
      }
      if ($this->aiModelId === '') {
        $this->aiModelId = $defaults['model_id'];
      }
    }
  }

  /**
   * Find a JSON encoded array of objects in a longer string.
   *
   * LLMs will often prepend intro text to their response, despite being asked
   * not to. This method cuts down a response to just the JSON.
   *
   * @return string
   */
  protected function extractAndDecodeJson($aiResponseText) {

    // Here we need to trim off anything before or after the JSON, eg:
    // "I'll format the provided text into valid JSON with multiple pages:"
    // Or even: "[This is the JSON output that represents the formatted content from the document.]"

    // Remove any pairs of square brackets and their contents, if their contents
    // does not contain a curly brace. This is either the AI's intro message
    // contained in [] (why, Claude? Why??), or an empty result set, which we
    // can't do anything with anyway.
    $aiResponseText = preg_replace('/\[[^{]+\]/', '', $aiResponseText);

    // Look for the start and end of the JSON encoded array of object, and trim
    // off anything outside it.
    $json_start = strpos($aiResponseText, '[');
    $json_end = strrpos($aiResponseText, ']');
    $json_length = 1 + $json_end - $json_start;
    $aiResponseText = substr($aiResponseText, $json_start, $json_length);

    return json_decode($aiResponseText, TRUE);
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
