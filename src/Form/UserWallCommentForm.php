<?php

namespace Drupal\user_wall\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form for adding a comment to a wall post.
 */
class UserWallCommentForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'user_wall_comment_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $post_id = NULL)
  {
    // Each comment form on the page needs a unique ID.
    $form['#form_id'] = $this->getFormId() . '_' . $post_id;

    $form['comment'] = [
      '#type' => 'textarea',
      '#rows' => 2,
      '#title' => $this->t('Write a comment...'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Write a comment...'),
      '#maxlength' => 1000,
      '#attributes' => ['class' => ['form-control']],
      '#resizable' => false,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Comment'),
      '#ajax' => [
        'url' => Url::fromRoute('user_wall.comment.add', ['post_id' => $post_id]),
        'wrapper' => 'wall-post-' . $post_id,
        'effect' => 'fade',
      ],
      '#attributes' => ['class' => ['btn btn-secondary']],
    ];

    // The controller needs the comment text. We pass it via the form.
    $form['#action'] = Url::fromRoute('user_wall.comment.add', ['post_id' => $post_id])->toString();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    // Submission is handled by the controller to provide an AJAX response.
  }
}
