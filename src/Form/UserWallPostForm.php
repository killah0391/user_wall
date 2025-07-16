<?php

namespace Drupal\user_wall\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\user_wall\PostRenderer;

/**
 * Provides a form for creating a new wall post.
 */
class UserWallPostForm extends FormBase
{

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The post renderer service.
   *
   * @var \Drupal\user_wall\PostRenderer
   */
  protected $postRenderer;

  /**
   * Constructs a new UserWallPostForm.
   */
  public function __construct(Connection $database, AccountInterface $currentUser, PostRenderer $postRenderer)
  {
    $this->database = $database;
    $this->currentUser = $currentUser;
    $this->postRenderer = $postRenderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('database'),
      $container->get('current_user'),
      $container->get('user_wall.post_renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'user_wall_post_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $user_id = NULL)
  {
    // Ensure the form can handle file uploads.
    $form['#attributes']['enctype'] = 'multipart/form-data';

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('What are you thinking?'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Share a thought...'),
      '#attributes' => ['class' => ['form-control']],
      '#rows' => 3,
    ];

    $form['image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload an image'),
      '#upload_location' => 'public://user_wall_images/',
      '#upload_validators' => [
        'file_validate_extensions' => ['gif png jpg jpeg'],
      ],
      '#multiple' => TRUE,
      '#attributes' => ['class' => ['form-control']],
      '#theme' => 'image_widget',
      '#preview_image_style' => 'medium',
    ];

    // Wrap the textarea, file input, and submit button in a container for positioning
    $form['input_container'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['user-wall-input-container']],
    ];

    $form['input_container']['message'] = $form['message'];
    unset($form['message']);

    $form['input_container']['image'] = $form['image'];
    unset($form['image']);

    // Attach js library
    $form['#attached']['library'][] = 'user_wall/user_wall';

    // Move the actions (submit) inside the input container
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

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);
    // A post must contain either a message or an image.
    if (empty($form_state->getValue('message')) && empty($form_state->getValue('image')[0])) {
      $form_state->setErrorByName('message', $this->t('You must enter a message or upload an image.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    // Ihre Logik zum Speichern der Daten. Dies ist bereits korrekt.
    $user_id = $form_state->getValue('user_id');
    $message = $form_state->getValue('message');
    $images = $form_state->getValue('image');

    $fids = [];
    if (!empty($images)) {
      foreach ($images as $image_fid) {
        $file = File::load($image_fid);
        if ($file) {
          $file->setPermanent();
          $file->save();
          $fids[] = $file->id();
        }
      }
    }

    $this->database->insert('user_wall_posts')
      ->fields([
        'uid' => $user_id,
        'message' => $message,
        'fid' => !empty($fids) ? reset($fids) : NULL,
        'created' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();

    // --- KORREKTUR: Felder gezielt zurücksetzen ---

    // 1. Die Benutzereingaben abrufen.
    $user_input = $form_state->getUserInput();

    // 2. Nur die Eingaben für 'message' und 'image' entfernen.
    // Die 'user_id' und andere Formular-Metadaten bleiben erhalten.
    unset($user_input['message']);
    unset($user_input['image']);

    // 3. Den bereinigten Input zurück in den Form-Status schreiben.
    $form_state->setUserInput($user_input);

    // 4. Die Form zum Neuaufbau anweisen.
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxSubmitCallback(array &$form, FormStateInterface $form_state)
  {
    $user_id = $form_state->getValue('user_id');
    $response = new AjaxResponse();

    // Fetch all posts for the user to rebuild the wall content.
    $post_ids = $this->database->select('user_wall_posts', 'p')
      ->fields('p', ['pid'])
      ->condition('p.uid', $user_id)
      ->orderBy('p.created', 'DESC')
      ->execute()->fetchCol();

    $wall_posts_render = [];
    foreach ($post_ids as $post_id) {
      $wall_posts_render[] = $this->postRenderer->buildPost($post_id);
    }

    // The $form variable is the rebuilt form because submitForm() called setRebuild()
    // and cleared the values.
    $rebuilt_form = $form;

    // Assemble the final render array for the whole wall.
    $wall_build = [
      '#theme' => 'user_wall',
      '#wall_posts' => $wall_posts_render,
      '#post_form' => $rebuilt_form,
      '#user_id' => $user_id,
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    // Replace the entire wall wrapper with the newly constructed content.
    // This now includes the fresh, empty form.
    $response->addCommand(new ReplaceCommand('#user-wall-wrapper', $wall_build));

    // --- REMOVE THE OLD REPLACE COMMAND ---
    // $response->addCommand(new ReplaceCommand('#image', '<div class="image" id="image" style="display: none;"></div>'));

    return $response;
  }
}
