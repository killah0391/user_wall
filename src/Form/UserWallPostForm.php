<?php

namespace Drupal\user_wall\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\user_wall\PostRenderer;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UserWallPostForm extends FormBase
{

  protected $currentUser;
  protected $postRenderer;
  protected $entityTypeManager;

  public function __construct(AccountInterface $currentUser, PostRenderer $postRenderer, EntityTypeManagerInterface $entityTypeManager)
  {
    $this->currentUser = $currentUser;
    $this->postRenderer = $postRenderer;
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('current_user'),
      $container->get('user_wall.post_renderer'),
      $container->get('entity_type.manager')
    );
  }

  public function getFormId()
  {
    return 'user_wall_post_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $user_id = NULL)
  {
    $form['#attributes']['enctype'] = 'multipart/form-data';

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Title'),
      '#attributes' => ['class' => ['form-control', 'mb-2']],
      '#maxlength' => 50,
    ];

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('What are you thinking?'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('What are you thinking?'),
      '#attributes' => ['class' => ['form-control']],
      '#rows' => 3,
      '#maxlength' => 456,
    ];

    $form['image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload an image'),
      '#description' => Markup::create('<small>'.$this->t('Only 5 images per post are allowed.'). '</small>'),
      '#upload_location' => 'public://user_wall_images/',
      '#upload_validators' => [
        'file_validate_extensions' => ['gif png jpg jpeg'],
      ],
      '#multiple' => TRUE,
      '#attributes' => ['class' => ['form-control']],
      '#theme' => 'image_widget',
      '#preview_image_style' => 'medium',
    ];

    $form['input_container'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['user-wall-input-container']],
    ];

    $form['input_container']['title'] = $form['title'];
    unset($form['title']);

    $form['input_container']['message'] = $form['message'];
    unset($form['message']);

    $form['input_container']['image'] = $form['image'];
    unset($form['image']);

    $form['#attached']['library'][] = 'user_wall/user_wall';

    $form['input_container']['actions']['#type'] = 'actions';
    $form['input_container']['actions']['submit'] = [
      '#type' => 'submit',
      '#title' => $this->t('Post'),
      '#attributes' => ['class' => ['send-button']],
      '#ajax' => [
        'callback' => '::ajaxSubmitCallback',
        'wrapper' => 'user-wall-wrapper',
      ],
    ];

    $form['user_id'] = ['#type' => 'hidden', '#value' => $user_id];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);
    if (empty($form_state->getValue('message')) && empty($form_state->getValue('image'))) {
      $form_state->setErrorByName('message', $this->t('You must enter a title, a message or upload an image.'));
    }

    $images = $form_state->getValue('image');
    if (is_array($images) && count($images) > 5) {
      $discarded_fids = array_slice($images, 5);
      if (!empty($discarded_fids)) {
        $files_to_delete = $this->entityTypeManager->getStorage('file')->loadMultiple($discarded_fids);
        foreach ($files_to_delete as $file) {
          $file->delete();
        }
      }
      $allowed_images = array_slice($images, 0, 5);
      $form_state->setValue('image', $allowed_images);
      $this->messenger()->addWarning($this->t('You can upload a maximum of 5 images. The extra images have been deleted.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $user_id = $form_state->getValue('user_id');
    $message = $form_state->getValue('message');
    $images = $form_state->getValue('image');
    $title = $form_state->getValue('title');

    $image_targets = [];
    if (!empty($images)) {
      foreach ($images as $fid) {
        $image_targets[] = ['target_id' => $fid];
      }
    }

    $this->entityTypeManager->getStorage('user_wall_post')->create([
      'uid' => $user_id,
      'title' => $title,
      'message' => $message,
      'image' => $image_targets, // Hier wird das Array der Bilder übergeben
      'created' => \Drupal::time()->getRequestTime(),
    ])->save();

    $user_input = $form_state->getUserInput();
    unset($user_input['title']);
    unset($user_input['message']);
    unset($user_input['image']);
    $form_state->setUserInput($user_input);
    $form_state->setRebuild(TRUE);
  }

  public function ajaxSubmitCallback(array &$form, FormStateInterface $form_state)
  {
    $user_id = $form_state->getValue('user_id');
    $response = new AjaxResponse();

    $post_ids = $this->entityTypeManager->getStorage('user_wall_post')->getQuery()
      ->condition('uid', $user_id)
      ->sort('created', 'DESC')
      ->accessCheck(TRUE)
      ->execute();

    $wall_posts_render = [];
    foreach ($post_ids as $post_id) {
      $wall_posts_render[] = $this->postRenderer->buildPost($post_id);
    }

    $rebuilt_form = $form;

    $wall_build = [
      '#theme' => 'user_wall',
      '#wall_posts' => $wall_posts_render,
      '#post_form' => $rebuilt_form,
      '#user_id' => $user_id,
      '#cache' => ['max-age' => 0],
    ];

    $response->addCommand(new ReplaceCommand('#user-wall-wrapper', $wall_build));

    if (empty($form_state->getValue('message')) && empty($form_state->getValue('image'))) {
      $this->messenger()->addError($this->t('You must enter a message or upload an image.'));
    }

    $images = $form_state->getValue('image');
    if (is_array($images) && count($images) > 5) {
      // Behalte nur die ersten 5 Bilder
      $allowed_images = array_slice($images, 0, 5);

      // Aktualisiere den Formularstatus mit der korrigierten Liste
      $form_state->setValue('image', $allowed_images);

      // Informiere den Benutzer mit einer Warnung, anstatt einen blockierenden Fehler auszulösen
      $this->messenger()->addWarning($this->t('Only first 5 images are uploaded. Other images have been deleted.'));
    }

    $ajax_messages = \Drupal::messenger()->deleteAll();
    if (!empty($ajax_messages)) {
      $first_message_in_batch = TRUE;
      foreach ($ajax_messages as $type => $messages_of_type) {
        foreach ($messages_of_type as $individual_message_text) {
          $response->addCommand(new MessageCommand(
            $individual_message_text,
            NULL,
            ['type' => $type], // Options for Drupal.message().add()
            $first_message_in_batch // clearPrevious flag for the command
          ));
          $first_message_in_batch = FALSE;
        }
      }
    }
    return $response;
  }
}
