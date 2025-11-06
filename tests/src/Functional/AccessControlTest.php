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
   * Data provider for paths and permissions.
   *
   * @todo The 'administer' permissions should map to more than one path.
   *
   * NB that 'administer imports' doesn't allow access to admin/content/imports
   * at the moment, as it's a view that only lets you choose one permission to
   * check.
   */
  public function permissionPathDataProvider(): array  {
    return [
      ['create imports', 'admin/content/imports/create'],
      ['view imports', 'admin/content/imports'],
      ['administer imports', 'admin/content/imports/create'],
      ['delete own imports', 'admin/content/imports/1/delete'],
      ['administer import pipelines', 'admin/config/system/import-pipeline'],
      ['create import pipelines', 'admin/config/system/import-pipeline/add'],
      ['view import pipelines', 'admin/config/system/import-pipeline'],
      ['update import pipelines', 'admin/config/system/import-pipeline/test_pipeline'],
      ['delete import pipelines', 'admin/config/system/import-pipeline/test_pipeline/delete'],
    ];
  }

  /**
   * Test access is allowed when appropriate.
   * @dataProvider permissionPathDataProvider
   */
  public function testAllowAccess(string $permission, string $path): void {

    // Create a user with a permission.
    $user = $this->drupalCreateUser([$permission]);

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
    $entityTypeManager = $this->container->get('entity_type.manager');

    // Create an import pipeline owned by this user in case we need it.
    $importPipelineStorage = $entityTypeManager->getStorage('import_pipeline');
    $importPipelineStorage->create([
      'id' => 'test_pipeline',
      'label' => 'Test pipeline',
    ])->save();

    // Create an import owned by this user in case we need it.
    $importStorage = $entityTypeManager->getStorage('import');
    $importStorage->create([
      'pipeline' => 'test_pipeline',
      'creator' => $user,
    ])->save();

    // First check that we deny access before we log in.
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    // Log the user in.
    $this->drupalLogin($user);

    // Check the user can access the path that was previously denied.
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
  }

}
