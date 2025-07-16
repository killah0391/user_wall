<?php

namespace Drupal\user_wall\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\user_wall\PostRenderer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for User Wall AJAX actions.
 */
class UserWallController extends ControllerBase {

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
   * Constructs a new UserWallController object.
   */
  public function __construct(Connection $database, AccountInterface $currentUser, PostRenderer $postRenderer) {
    $this->database = $database;
    $this->currentUser = $currentUser;
    $this->postRenderer = $postRenderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('current_user'),
      $container->get('user_wall.post_renderer')
    );
  }

  /**
   * Toggles a like on a post via AJAX.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * The current request.
   * @param int $post_id
   * The ID of the post to like/unlike.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   * The AJAX response.
   */
  public function likePost(Request $request, $post_id) {
    $uid = $this->currentUser->id();

    $query = $this->database->select('user_wall_likes', 'l')
      ->fields('l', ['lid'])
      ->condition('pid', $post_id)
      ->condition('uid', $uid);
    $result = $query->execute()->fetchField();

    if ($result) {
      // User has already liked, so unlike it.
      $this->database->delete('user_wall_likes')
        ->condition('lid', $result)
        ->execute();
    } else {
      // User has not liked it yet.
      $this->database->insert('user_wall_likes')
        ->fields(['pid' => $post_id, 'uid' => $uid])
        ->execute();
    }

    $post_render_array = $this->postRenderer->buildPost($post_id);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#wall-post-' . $post_id, $post_render_array));
    return $response;
  }

  /**
   * Adds a comment to a post via AJAX.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * The current request.
   * @param int $post_id
   * The ID of the post to comment on.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   * The AJAX response.
   */
  public function addComment(Request $request, $post_id) {
    $comment_text = $request->request->get('comment');
    if (!empty($comment_text)) {
      $this->database->insert('user_wall_comments')
        ->fields([
          'pid' => $post_id,
          'uid' => $this->currentUser->id(),
          'comment' => $comment_text,
          'created' => \Drupal::time()->getRequestTime(),
        ])
        ->execute();
    }

    $post_render_array = $this->postRenderer->buildPost($post_id);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#wall-post-' . $post_id, $post_render_array));
    return $response;
  }
}
