<?php

namespace Drupal\Tests\localgov_publications_importer\Functional;

use Drupal\Tests\BrowserTestBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests our access control.
 *
 * @group localgov_publications_importer
 */
class AccessControlTest extends BrowserTestBase {

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
   * Test access for anon users.
   */
  public function testAnonAccess(): void {

    $this->drupalGet('admin/content/imports');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    $this->drupalGet('admin/config/system/import-pipeline');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    $this->drupalGet('admin/config/system/import-pipeline/standard');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);
  }

}
