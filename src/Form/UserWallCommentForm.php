<?php

namespace Drupal\user_wall\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class UserWallCommentForm extends FormBase
{

  public function getFormId()
  {
    return 'user_wall_comment_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $post_id = NULL)
  {
    $form['#form_id'] = $this->getFormId() . '_' . $post_id;

    $form['comment'] = [
      '#type' => 'textarea',
      '#rows' => 2,
      '#title' => $this->t('Write a comment...'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Write a comment...'),
      '#maxlength' => 500,
      '#attributes' => ['class' => ['form-control']],
      '#resizable' => false,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Comment'),
      '#ajax' => [
        'url' => Url::fromRoute('user_wall.comment.add', ['user_wall_post' => $post_id]),
        'wrapper' => 'wall-post-' . $post_id,
        'effect' => 'fade',
      ],
    ];

    $form['#action'] = Url::fromRoute('user_wall.comment.add', ['user_wall_post' => $post_id])->toString();

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    // Submission is handled by the controller.
  }
}
