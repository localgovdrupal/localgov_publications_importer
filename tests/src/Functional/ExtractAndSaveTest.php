<?php

namespace Drupal\Tests\localgov_publications_importer\Functional;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\file\Entity\File;
use Drupal\localgov_publications_importer\Entity\Import;
use Drupal\localgov_publications_importer\ImportInterface;
use Drupal\node\NodeInterface;

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
   * Data provider for PDF file test data.
   */
  public static function fileProvider(): array {
    // We keep test data in a separate module, installed as a dev dependency.
    // This is because it's quite big, and we don't want to install it in
    // everyone's sites.
    $dataDir = dirname(__FILE__) . "/../../../../localgov_publications_importer_test_data/data";
    $rtn = [];
    foreach (scandir($dataDir) as $dirname) {
      if (str_starts_with($dirname, '.')) {
        continue;
      }
      // Look one level down for files.
      if (is_dir($dataDir . '/' . $dirname)) {
        foreach (scandir($dataDir . '/' . $dirname) as $name) {
          if (str_starts_with($name, '.')) {
            continue;
          }
          $rtn[] = [$dataDir . '/' . $dirname . '/' . $name];
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

    $directory = 'public://';
    $targetLocation = $directory . '/' . basename($fileName);

    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $file_system->prepareDirectory($directory, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $file_system->copy($fileName, $targetLocation, FileExists::Replace);

    $file = File::create([
      'filename' => basename($fileName),
      'uri' => $targetLocation,
      'status' => 1,
      'uid' => 1,
    ]);
    $file->save();

    $import = Import::create([
      'file' => $file,
      'title' => $file->getFilename(),
      // We may not need these.
      'creator' => NULL,
      'pipeline' => '',
    ]);

    $import->save();

    $import = $extractPlugin->extract($import);
    $this->assertInstanceOf(ImportInterface::class, $import, "SmalotPdfParserExtract::getImport() failed on file: {$fileName}");

    $node = $savePlugin->import($import);
    $this->assertInstanceOf(NodeInterface::class, $node);
  }

}
