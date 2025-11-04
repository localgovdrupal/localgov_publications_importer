<?php

namespace Drupal\Tests\localgov_publications_importer\Functional;

use Drupal\Core\File\FileExists;
use Drupal\file\Entity\File;
use Drupal\localgov_publications_importer\Entity\Import;
use Drupal\localgov_publications_importer\ImportInterface;

/**
 * Common methods to read test metadata and set up imports.
 */
trait FileProviderTrait {

  /**
   * Data provider for PDF file test data.
   */
  public static function fileProvider(): array {
    // We keep test data in a separate module, installed as a dev dependency.
    // This is because it's quite big, and we don't want to install it in
    // everyone's sites.
    $dataDir = dirname(__FILE__) . "/../../../../localgov_publications_importer_test_data/data";

    $files = json_decode(file_get_contents($dataDir . '/manifest.json'), TRUE);

    self::assertIsArray($files, "Couldn't read test data manifest.");

    $rtn = [];
    foreach ($files as $file) {
      $rtn[] = [
        $dataDir . '/' . $file['filename'],
        $file['title'],
        $file['page_count'],
        $file['links'] ?? NULL,
      ];
    }
    return $rtn;
  }

  /**
   * Data provider for PDF file test data with links.
   */
  public static function fileProviderWithLinks(): array {
    return array_filter(self::fileProvider(), function ($file) {
      return isset($file[3]);
    });
  }

  /**
   * Build an import from a filename.
   */
  public function createImport($fileName): ImportInterface {
    $directory = 'public://';
    $targetLocation = $directory . '/' . basename($fileName);

    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $file_system->copy($fileName, $targetLocation, FileExists::Replace);

    $user = $this->drupalCreateUser();

    $file = File::create([
      'filename' => basename($fileName),
      'uri' => $targetLocation,
      'status' => 1,
      'uid' => $user->id(),
    ]);
    $file->save();

    $import = Import::create([
      'file' => $file,
      'title' => $file->getFilename(),
      'creator' => $user,
      'pipeline' => 'standard',
    ]);

    $import->save();

    return $import;
  }

}
