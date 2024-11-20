<?php

namespace Drupal\localgov_publications_importer\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
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
  public static function create(ContainerInterface $container) {
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
  public function submitForm(array &$form, FormStateInterface $form_state) {

    [$fid] = $form_state->getValue('my_file');
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    $node = $this->publicationImporter->importPdf($file->uri->value);

    if ($node) {
      // Redirect to the node we created.
      $form_state->setRedirect('entity.node.canonical', ['node' => $node->id()]);
    }
  }

}
