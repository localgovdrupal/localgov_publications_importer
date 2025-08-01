<?php

namespace Drupal\Tests\localgov_publications_importer\Functional;

use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\localgov_publications_importer\ImportInterface;

/**
 * Tests our default extract and save plugins.
 *
 * @group localgov_publications_importer
 */
class ExtractAndSaveTest extends BrowserTestBase {

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
   * Get the directory we're reading PDF files from.
   */
  protected function dataDir(): string {
    // We keep test data in a separate module, installed as a dev dependency.
    // This is because it's quite big, and we don't want to install it in
    // everyone's sites.
    return dirname(__FILE__) . "/../../../../localgov_publications_importer_test_data/data/";
  }

  /**
   * Data provider for PDF file test data.
   */
  public static function fileProvider(): array {
    $rtn = [];
    foreach (scandir($this->dataDir()) as $dirname) {
      if (str_starts_with($dirname, '.')) {
        continue;
      }
      // Look one level down for files.
      if (is_dir($this->dataDir() . '/' . $dirname)) {
        foreach (scandir($this->dataDir() . '/' . $dirname) as $name) {
          if (str_starts_with($name, '.')) {
            continue;
          }
          $rtn[] = [$dirname . '/' . $name];
        }
      }
    }
    return $rtn;
  }

  /**
   * Test the smalot_pdfparser extract and save_publication save plugins.
   *
   * @dataProvider fileProvider
   */
  public function testGetImportReturnsImportInterface($fileName): void {

    $extractPlugin = $this->extractManager->createInstance('smalot_pdfparser');
    $savePlugin = $this->saveManager->createInstance('save_publication');

    $import = $extractPlugin->setSource($this->dataDir() . $fileName)->getImport();
    $this->assertInstanceOf(ImportInterface::class, $import, "SmalotPdfParserExtract::getImport() failed on file: {$fileName}");

    $node = $savePlugin->import($import);
    $this->assertInstanceOf(NodeInterface::class, $node);
  }

}
