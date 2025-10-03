<?php

namespace Drupal\localgov_publications_importer\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\localgov_publications_importer\Batch;
use Drupal\localgov_publications_importer\Entity\Import;
use Drupal\localgov_publications_importer\Service\Importer as PublicationImporter;
use Drupal\user\Entity\User;
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
      '#upload_location' => 'private://localgov_publications_importer/',
    ];

    $importPipelines = $this->entityTypeManager
      ->getStorage('import_pipeline')
      ->loadMultiple();

    if (count($importPipelines) === 0) {
      $link = Link::fromTextAndUrl($this->t('Import Pipeline admin page'), Url::fromRoute('entity.import_pipeline.collection'));
      $this->messenger()->addError($this->t('There are no configured import pipelines. Please add one from the @link.', ['@link' => $link->toString()]));
    }

    $importPipelineOptions = [];
    foreach ($importPipelines as $importPipeline) {
      $importPipelineOptions[$importPipeline->id()] = $importPipeline->label();
    }

    $form['import_pipeline'] = [
      '#type' => 'radios',
      '#title' => $this->t('Import Pipeline'),
      '#options' => $importPipelineOptions,
      '#default_value' => array_key_first($importPipelineOptions),
      '#required' => TRUE,
      '#description' => $this->t('Select the import pipeline to use for processing the uploaded file.'),
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

    $importPipelineId = $form_state->getValue('import_pipeline');

    $user = User::load(\Drupal::currentUser()->id());

    $import = Import::create([
      'file' => $file,
      'title' => $file->getFilename(),
      'creator' => $user,
      'pipeline' => $importPipelineId,
    ]);

    $import->save();

    // Mark the file as permanant so it doesn't get cleaned up prematurely.
    $file->setPermanent();
    $file->save();

    $form_state->setRedirect('view.imports.page_1');
  }

}
