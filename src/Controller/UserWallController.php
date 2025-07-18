<?php

namespace Drupal\user_wall\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Session\AccountInterface;
use Drupal\user_wall\PostRenderer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\user_wall\Entity\UserWallPost;

class UserWallController extends ControllerBase
{

  protected $currentUser;
  protected $postRenderer;

  public function __construct(AccountInterface $currentUser, PostRenderer $postRenderer)
  {
    $this->currentUser = $currentUser;
    $this->postRenderer = $postRenderer;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('current_user'),
      $container->get('user_wall.post_renderer')
    );
  }

  public function likePost(Request $request, UserWallPost $user_wall_post)
  {
    $uid = $this->currentUser->id();
    $post_id = $user_wall_post->id();

    $query = $this->entityTypeManager()->getStorage('user_wall_like')->getQuery()
      ->condition('post_id', $post_id)
      ->condition('uid', $uid)
      ->accessCheck(TRUE);
    $results = $query->execute();

    if ($results) {
      $this->entityTypeManager()->getStorage('user_wall_like')->delete(
        $this->entityTypeManager()->getStorage('user_wall_like')->loadMultiple($results)
      );
    } else {
      $this->entityTypeManager()->getStorage('user_wall_like')->create([
        'uid' => $uid,
        'post_id' => $post_id,
      ])->save();
    }

    $post_render_array = $this->postRenderer->buildPost($post_id);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#wall-post-' . $post_id, $post_render_array));
    return $response;
  }

  public function addComment(Request $request, UserWallPost $user_wall_post)
  {
    $comment_text = $request->request->get('comment');
    $post_id = $user_wall_post->id();

    if (!empty($comment_text)) {
      $this->entityTypeManager()->getStorage('user_wall_comment')->create([
        'uid' => $this->currentUser->id(),
        'post_id' => $post_id,
        'comment' => $comment_text,
        'created' => \Drupal::time()->getRequestTime(),
      ])->save();
    }

    $post_render_array = $this->postRenderer->buildPost($post_id);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#wall-post-' . $post_id, $post_render_array));
    return $response;
  }
}
