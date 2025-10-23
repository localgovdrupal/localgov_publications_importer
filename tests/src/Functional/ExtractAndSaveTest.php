<?php

namespace Drupal\Tests\localgov_publications_importer\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\NodeInterface;

/**
 * Tests our default extract and save plugins.
 *
 * @group localgov_publications_importer
 */
class ExtractAndSaveTest extends BrowserTestBase {

  use FileProviderTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'localgov_publications_importer',
  ];

  /**
   * The extract plugin manager.
   *
   * @var \Drupal\localgov_publications_importer\ExtractOperationManager
   */
  protected $extractManager;

  /**
   * The save plugin manager.
   *
   * @var \Drupal\localgov_publications_importer\SaveOperationManager
   */
  protected $saveManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->extractManager = $this->container->get('plugin.manager.localgov_importer.extract');
    $this->saveManager = $this->container->get('plugin.manager.localgov_importer.save');
  }

  /**
   * Test the smalot_pdfparser extract and save_publication save plugins.
   *
   * @dataProvider fileProvider
   */
  public function testExtractAndSaveAsPublications($fileName, $title, $pageCount): void {

    $extractPlugin = $this->extractManager->createInstance('smalot_pdfparser');
    $savePlugin = $this->saveManager->createInstance('save_publication');

    $import = $this->createImport($fileName);

    $extractPlugin->extract($import);

    $this->assertEquals($title, $import->getTitle(), "Check title has been populated.");
    $this->assertEquals($pageCount, count($import->getPages()), "Check pages have been populated.");

    $node = $savePlugin->import($import);
    $this->assertInstanceOf(NodeInterface::class, $node);
  }

}
