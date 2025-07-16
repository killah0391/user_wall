<?php

namespace Drupal\user_wall;

use Drupal\user\Entity\User;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Defines a lazy builder for the user wall.
 * Implements TrustedCallbackInterface for security.
 */
class LazyBuilder implements TrustedCallbackInterface
{

  protected $database;
  protected $entityTypeManager;
  protected $formBuilder;
  protected $currentUser;
  protected $postRenderer;

  public function __construct(Connection $database, EntityTypeManagerInterface $entityTypeManager, FormBuilderInterface $formBuilder, AccountInterface $currentUser, PostRenderer $postRenderer)
  {
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
    $this->formBuilder = $formBuilder;
    $this->currentUser = $currentUser;
    $this->postRenderer = $postRenderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks()
  {
    return ['buildWall'];
  }

  /**
   * Builds the user wall.
   *
   * This method is a #lazy_builder callback.
   *
   * @param int $user_id
   * The user ID for whom to build the wall.
   *
   * @return array
   * A renderable array containing the user wall.
   */
  public function buildWall($user_id)
  {
    $wall_posts_render = [];
    $post_form = [];

    if ($this->currentUser->id() == $user_id) {
      $post_form = $this->formBuilder->getForm('Drupal\user_wall\Form\UserWallPostForm', $user_id);
    }

    $post_ids = $this->database->select('user_wall_posts', 'p')
      ->fields('p', ['pid'])
      ->condition('p.uid', $user_id)
      ->orderBy('p.created', 'DESC')
      ->execute()->fetchCol();

    foreach ($post_ids as $post_id) {
      $wall_posts_render[] = $this->postRenderer->buildPost($post_id);
    }

    $user = User::load($user_id);
    $username = $user->getDisplayName();

    return [
      '#theme' => 'user_wall',
      '#wall_posts' => $wall_posts_render,
      '#post_form' => $post_form,
      '#user_id' => $user_id,
      '#username' => $username,
      '#prefix' => '<div id="user-wall-wrapper">',
      '#suffix' => '</div>',
      '#attached' => ['library' => ['core/drupal.ajax']],
    ];
  }
}
