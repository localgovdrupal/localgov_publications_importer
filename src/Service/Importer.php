<?php

namespace Drupal\localgov_publications_importer\Service;

use Drupal\localgov_publications_importer\ExtractOperationManager;
use Drupal\localgov_publications_importer\Plugin\ExtractInterface;
use Drupal\localgov_publications_importer\Plugin\SaveInterface;
use Drupal\localgov_publications_importer\SaveOperationManager;
use Drupal\node\NodeInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\AiProviderPluginManager;

/**
 * Imports content from uploaded files.
 */
class Importer {

  /**
   * Constructor.
   */
  public function __construct(
    protected AiProviderPluginManager $aiProvider,
    protected ExtractOperationManager $extractOperationManager,
    protected SaveOperationManager $saveOperationManager,
  ) {
  }

  /**
   * Imports the given file as a new Localgov Publication page.
   */
  public function importPdf($pathToFile): ?NodeInterface {

    $import = $this->extractOperation()
      ->setSource($pathToFile)
      ->getImport();

    // @todo Everything left in this loop should be a transform plugin.
    foreach ($import->getPages() as $page) {

      // One of the example PDFs I tried came out wth \t\n after every single
      // word, which rendered as line breaks and made the output a single column
      // of words. Swop these for spaces.
      $content = str_replace("\t\n", ' ', $page->getText());

      // Find the default selected LLM:
      $sets = $this->aiProvider->getDefaultProviderForOperationType('chat');

      $provider = $this->aiProvider->createInstance($sets['provider_id']);
      $messages = new ChatInput([
        new chatMessage('system', 'This plain text document has been stripped of its formatting. Please add the formatting back in, and give me the whole document back as valid HTML.'),
        new chatMessage('user', $content),
      ]);
      $message = $provider->chat($messages, $sets['model_id'])->getNormalized();
      $content = $message->getText();
    }

    return $this->saveOperation()->import($import);
  }

  /**
   * Gets the extract operation to use.
   */
  protected function extractOperation(): ExtractInterface {
    $extractOperationDefinitions = $this->extractOperationManager->getDefinitions();

    // @todo There should only be one extract operation in this pipeline.
    // Provide a way to choose it, and the other operations!
    $extractOperationDefinition = reset($extractOperationDefinitions);

    /** @var \Drupal\localgov_publications_importer\Plugin\ExtractInterface $extractOperation */
    $extractOperation = $this->extractOperationManager->createInstance($extractOperationDefinition['id']);

    return $extractOperation;
  }

  /**
   * Gets the save operation to use.
   */
  protected function saveOperation(): SaveInterface {
    $saveOperationDefinitions = $this->saveOperationManager->getDefinitions();

    // @todo There should only be one save operation in this pipeline.
    // Provide a way to choose it, and the other operations!
    $saveOperationDefinition = reset($saveOperationDefinitions);

    /** @var \Drupal\localgov_publications_importer\Plugin\SaveInterface $saveOperation */
    $saveOperation = $this->extractOperationManager->createInstance($saveOperationDefinition['id']);

    return $saveOperation;
  }

}
