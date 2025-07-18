<?php

namespace Drupal\user_wall\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * @ContentEntityType(
 * id = "user_wall_like",
 * label = @Translation("User Wall Like"),
 * base_table = "user_wall_like",
 * entity_keys = {
 * "id" = "id",
 * "uuid" = "uuid",
 * "uid" = "uid",
 * "owner" = "uid",
 * },
 * )
 */
class UserWallLike extends ContentEntityBase implements ContentEntityInterface, EntityOwnerInterface
{
  use EntityOwnerTrait;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type)
  {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user who liked the post.'))
      ->setSetting('target_type', 'user');

    $fields['post_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Post'))
      ->setDescription(t('The post that was liked.'))
      ->setSetting('target_type', 'user_wall_post');

    return $fields;
  }
}
