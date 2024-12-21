<?php

namespace Drupal\localgov_publications_importer\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\localgov_publications_importer\ExtractOperationManager;
use Drupal\localgov_publications_importer\Plugin\ExtractInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Smalot\PdfParser\Config as PdfParserConfig;
use Smalot\PdfParser\Parser as PdfParser;
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
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AiProviderPluginManager $aiProvider,
    protected ExtractOperationManager $extractOperationManager,
  ) {
  }

  /**
   * Imports the given file as a new Localgov Publication page.
   */
  public function importPdf($pathToFile): ?NodeInterface {

    $import = $this->extractOperation()
      ->setSource($pathToFile)
      ->getImport();

    $rootPage = NULL;
    $weight = 0;

    $nodeStorage = $this->entityTypeManager->getStorage('node');

    foreach ($import->getPages() as $page) {

      if ($rootPage === NULL) {
        $book = [
          'bid' => 'new',
        ];
        $title = $import->getTitle();
      }
      else {
        $book = [
          'bid' => $rootPage->id(),
          'pid' => $rootPage->id(),
          'weight' => $weight++,
        ];
        $title = 'Page ' . $page->getPageNumber();
      }

      $publicationPage = $nodeStorage->create([
        'type' => 'localgov_publication_page',
        'title' => $title,
        'book' => $book,
      ]);

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

      $this->addBodyAsParagraph($publicationPage, $content);

      $publicationPage->save();

      if ($rootPage === NULL) {
        $rootPage = $publicationPage;
      }
    }

    return $rootPage;
  }

  /**
   * Add content to the given node as a paragraph.
   */
  public function addBodyAsParagraph(NodeInterface $node, string $text): void {

    // Create the paragraph that holds the text. NB that both the paragraph
    // and the field on it are called 'localgov_text'.
    $paragraph = Paragraph::create([
      'type' => 'localgov_text',
      'localgov_text' => [
        'value' => $text,
        'format' => 'wysiwyg',
      ],
    ]);
    $paragraph->save();

    $paragraphList[] = [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];

    $node->get('localgov_publication_content')->setValue($paragraphList);
  }

  protected function extractOperation(): ExtractInterface {
    $extractOperationDefinitions = $this->extractOperationManager->getDefinitions();

    // @todo: There should only be one extract operation in this pipeline.
    // Provide a way to choose it, and the other operations!
    $extractOperationDefinition = reset($extractOperationDefinitions);

    /** @var \Drupal\localgov_publications_importer\Plugin\ExtractInterface $extractOperation */
    $extractOperation = $this->extractOperationManager->createInstance($extractOperationDefinition['id']);

    return $extractOperation;
  }

}
