<?php

namespace Drupal\Tests\localgov_publications_importer\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests links in our default extract plugin.
 *
 * @group localgov_publications_importer
 */
class LinkTest extends BrowserTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->extractManager = $this->container->get('plugin.manager.localgov_importer.extract');
  }

  /**
   * Tests that the smalot_pdfparser plugin extracts links correctly.
   *
   * @dataProvider fileProviderWithLinks
   */
  public function testLinks($fileName, $title, $pageCount, $links = NULL): void {

    $extractPlugin = $this->extractManager->createInstance('smalot_pdfparser');
    $import = $this->createImport($fileName);
    $extractPlugin->extract($import);

    foreach ($import->getPages() as $i => $page) {
      $pageLinks = $links[$i] ?? [];
      // Get the links from the content, and compare against the expected list.
      preg_match_all("#<a[^<]+</a>#", $page->getContent(), $matches);
      $this->assertEquals($pageLinks, $matches[0]);
    }
  }

}
