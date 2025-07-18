<?php

namespace Drupal\user_wall;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Datetime\DateFormatterInterface; // Hinzufügen

class LazyBuilder implements TrustedCallbackInterface
{

  protected $entityTypeManager;
  protected $formBuilder;
  protected $currentUser;
  protected $postRenderer;
  protected $dateFormatter; // Hinzufügen

  // Konstruktor anpassen
  public function __construct(EntityTypeManagerInterface $entityTypeManager, FormBuilderInterface $formBuilder, AccountInterface $currentUser, PostRenderer $postRenderer, DateFormatterInterface $dateFormatter)
  {
    $this->entityTypeManager = $entityTypeManager;
    $this->formBuilder = $formBuilder;
    $this->currentUser = $currentUser;
    $this->postRenderer = $postRenderer;
    $this->dateFormatter = $dateFormatter; // Hinzufügen
  }

  public static function trustedCallbacks()
  {
    return ['buildWall'];
  }

  public function buildWall($user_id)
  {
    $post_form = [];
    if ($this->currentUser->id() == $user_id) {
      $post_form = $this->formBuilder->getForm('Drupal\user_wall\Form\UserWallPostForm', $user_id);
    }

    $post_ids = $this->entityTypeManager->getStorage('user_wall_post')->getQuery()
      ->condition('uid', $user_id)
      ->sort('created', 'DESC')
      ->accessCheck(TRUE)
      ->execute();

    // NEU: Beiträge nach Datum gruppieren
    $posts_by_date = [];
    $posts = $this->entityTypeManager->getStorage('user_wall_post')->loadMultiple($post_ids);

    foreach ($posts as $post) {
      $date_key = $this->dateFormatter->format($post->get('created')->value, 'custom', 'Y-m-d');
      $posts_by_date[$date_key][] = $this->postRenderer->buildPost($post->id());
    }

    $user = User::load($user_id);
    $username = $user->getDisplayName();

    return [
      '#theme' => 'user_wall',
      '#posts_by_date' => $posts_by_date, // Übergabe des gruppierten Arrays
      '#post_form' => $post_form,
      '#user_id' => $user_id,
      '#username' => $username,
      '#prefix' => '<div id="user-wall-wrapper">',
      '#suffix' => '</div>',
      '#attached' => ['library' => ['core/drupal.ajax', 'user_wall/user_wall']],
    ];
  }
}
