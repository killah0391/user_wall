<?php

namespace Drupal\user_wall;

use Drupal\Core\Url;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class PostRenderer
{
  use StringTranslationTrait;

  protected $entityTypeManager;
  protected $formBuilder;
  protected $currentUser;
  protected $renderer;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, FormBuilderInterface $formBuilder, AccountInterface $currentUser, RendererInterface $renderer)
  {
    $this->entityTypeManager = $entityTypeManager;
    $this->formBuilder = $formBuilder;
    $this->currentUser = $currentUser;
    $this->renderer = $renderer;
  }

  public function buildPost($post_id)
  {
    $post = $this->entityTypeManager->getStorage('user_wall_post')->load($post_id);

    if (!$post) {
      return [];
    }

    $post_author = $post->getOwner();

    // Iteriert durch alle Bilder und erstellt ein Render-Array für jedes
    $images_render = [];
    if (!$post->get('image')->isEmpty()) {
      foreach ($post->get('image') as $image_item) {
        $thumbnail_url = $image_item->entity->createFileUrl(); // Thumbnail-URL
        $original_url = $image_item->entity->createFileUrl(FALSE); // Original-URL

        // Erstelle das Bild-Element, das angezeigt wird
        $image = [
          '#theme' => 'image',
          '#uri' => $thumbnail_url,
          '#attributes' => [
            // NEU: Diese Attribute sind für dein JS-Skript
            'class' => ['js-zoomable-image'],
            'data-zoom-src' => $original_url,
          ],
        ];
        $images_render[] = $image;
      }
    }

    $like_count = $this->entityTypeManager->getStorage('user_wall_like')->getQuery()
      ->condition('post_id', $post_id)
      ->count()
      ->accessCheck(TRUE)
      ->execute();

    $like_text = $this->formatPlural($like_count, '@count', '@count');

    $comment_ids = $this->entityTypeManager->getStorage('user_wall_comment')->getQuery()
      ->condition('post_id', $post_id)
      ->sort('created', 'ASC')
      ->accessCheck(TRUE)
      ->execute();

    $comments = [];
    if (!empty($comment_ids)) {
      $commentsData = $this->entityTypeManager->getStorage('user_wall_comment')->loadMultiple($comment_ids);

      foreach ($commentsData as $comment) {
        $comment_author = $comment->getOwner();
        $comments[] = [
          'author' => $comment_author ? $comment_author->getDisplayName() : 'Anonymous',
          'comment' => $comment->get('comment')->value,
          'created' => $comment->get('created')->value,
        ];
      }
    }

    $comment_form = $this->formBuilder->getForm('Drupal\user_wall\Form\UserWallCommentForm', $post_id);

    $like_link = [
      '#type' => 'link',
      '#title' => $this->isLiked($post_id) ? Markup::create('<i class="bi bi-heart-fill"></i>') : Markup::create('<i class="bi bi-heart"></i>'),
      '#url' => Url::fromRoute('user_wall.post.like', ['user_wall_post' => $post_id]),
      '#attributes' => [
        'class' => ['use-ajax', 'btn', 'btn-sm', $this->isLiked($post_id) ? 'text-danger' : 'text-muted'],
        'data-ajax-wrapper' => 'wall-post-' . $post_id,
      ],
    ];

    return [
      '#theme' => 'user_wall_post',
      '#post' => [
        'pid' => $post_id,
        'author' => $post_author ? $post_author->getDisplayName() : 'Anonymous',
        'title' => $post->get('title')->value,
        'message' => $post->get('message')->value,
        'images' => $images_render, // Geändert von 'image' zu 'images'
        'created' => $post->get('created')->value,
      ],
      '#like_link' => $like_link,
      '#like_text' => $like_text,
      '#comments' => $comments,
      '#comment_form' => $comment_form,
      '#attached' => ['library' => ['core/drupal.ajax', 'match_chat/match_chat_image_zoom']],
    ];
  }

  protected function isLiked($post_id)
  {
    $count = $this->entityTypeManager->getStorage('user_wall_like')->getQuery()
      ->condition('post_id', $post_id)
      ->condition('uid', $this->currentUser->id())
      ->count()
      ->accessCheck(TRUE)
      ->execute();
    return $count > 0;
  }
}
