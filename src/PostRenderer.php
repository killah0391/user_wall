<?php

namespace Drupal\user_wall;

use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use Drupal\Core\Render\Markup;
use Drupal\Core\Database\Connection;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service for rendering a single wall post.
 */
class PostRenderer
{
  use StringTranslationTrait;

  protected $database;
  protected $entityTypeManager;
  protected $formBuilder;
  protected $currentUser;
  protected $renderer;

  public function __construct(Connection $database, EntityTypeManagerInterface $entityTypeManager, FormBuilderInterface $formBuilder, AccountInterface $currentUser, RendererInterface $renderer)
  {
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
    $this->formBuilder = $formBuilder;
    $this->currentUser = $currentUser;
    $this->renderer = $renderer;
  }

  /**
   * Builds the render array for a single post.
   */
  public function buildPost($post_id)
  {
    $post_data = $this->database->select('user_wall_posts', 'p')
      ->fields('p')
      ->condition('p.pid', $post_id)
      ->execute()->fetch();

    if (!$post_data) {
      return [];
    }

    $post_author = User::load($post_data->uid);
    $image_render = [];
    if ($post_data->fid) {
      $file = File::load($post_data->fid);
      if ($file) {
        $image_render = [
          '#theme' => 'image_style',
          '#style_name' => 'large', // Using a larger style for better display
          '#uri' => $file->getFileUri(),
          '#attributes' => ['class' => ['img-fluid', 'rounded']],
        ];
      }
    }

    $like_count = $this->database->select('user_wall_likes', 'l')
      ->condition('l.pid', $post_data->pid)
      ->countQuery()->execute()->fetchField();

    // Prepare the translated, pluralized string for the like count.
    $like_text = $this->formatPlural($like_count, '@count', '@count');

    $comments_results = $this->database->select('user_wall_comments', 'c')
      ->fields('c')
      ->condition('c.pid', $post_data->pid)
      ->orderBy('c.created', 'ASC')->execute()->fetchAll();

    $comments = [];
    foreach ($comments_results as $comment_data) {
      $comment_author = User::load($comment_data->uid);
      $comments[] = [
        'author' => $comment_author ? $comment_author->getDisplayName() : 'Anonymous',
        'comment' => $comment_data->comment,
      ];
    }

    $comment_form = $this->formBuilder->getForm('Drupal\user_wall\Form\UserWallCommentForm', $post_data->pid);

    $like_link = [
      '#type' => 'link',
      '#title' => $this->isLiked($post_data->pid) ? Markup::create('<i class="bi bi-heart-fill"></i>') : Markup::create('<i class="bi bi-heart"></i>'),
      '#url' => Url::fromRoute('user_wall.post.like', ['post_id' => $post_data->pid]),
      '#attributes' => [
        'class' => ['use-ajax', 'btn', 'btn-sm', $this->isLiked($post_data->pid) ? 'text-danger' : 'text-muted'],
        'data-ajax-wrapper' => 'wall-post-' . $post_data->pid,
      ],
    ];



    return [
      '#theme' => 'user_wall_post',
      '#post' => [
        'pid' => $post_data->pid,
        'author' => $post_author ? $post_author->getDisplayName() : 'Anonymous',
        'message' => $post_data->message,
        'image' => $image_render,
        'created' => $post_data->created, // Pass the timestamp to the template
      ],
      '#like_link' => $like_link,
      '#like_text' => $like_text, // Pass the prepared string to the template.
      '#comments' => $comments,
      '#comment_form' => $comment_form,
      '#attached' => ['library' => ['core/drupal.ajax']],
    ];
  }

  /**
   * Checks if the current user has liked a post.
   */
  protected function isLiked($post_id)
  {
    $count = $this->database->select('user_wall_likes', 'l')
      ->condition('pid', $post_id)
      ->condition('uid', $this->currentUser->id())
      ->countQuery()->execute()->fetchField();
    return $count > 0;
  }
}
