<?php

namespace Drupal\localgov_publications_importer\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\localgov_publications_importer\Batch;
use Drupal\localgov_publications_importer\Service\Importer as PublicationImporter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Publication import form.
 */
class PublicationImportForm extends FormBase {

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PublicationImporter $publicationImporter,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('localgov_publications_importer.importer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'publication_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#attributes'] = ['enctype' => 'multipart/form-data'];

    $form['my_file'] = [
      '#type' => 'managed_file',
      '#name' => 'my_file',
      '#title' => $this->t('File *'),
      '#size' => 20,
      '#description' => $this->t('PDF format only'),
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf'],
      ],
      // @todo Upload to private.
      '#upload_location' => 'public://my_files/',
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    [$fid] = $form_state->getValue('my_file');
    /** @var \Drupal\file\FileInterface $file */
    $file = $this->entityTypeManager->getStorage('file')->load($fid);

    $batch = new BatchBuilder();
    $batch->setTitle('Importing ' . $file->getFilename())
      ->setFinishCallback([Batch::class, 'finished'])
      ->setInitMessage('Commencing')
      ->setProgressMessage('Importing. Elapsed time: @elapsed.')
      ->setErrorMessage('An error occurred during import.');

    $batch->addOperation([Batch::class, 'extract'], [$file->getFileUri()]);
    $batch->addOperation([Batch::class, 'transform']);
    $batch->addOperation([Batch::class, 'save']);

    batch_set($batch->toArray());
  }

}
