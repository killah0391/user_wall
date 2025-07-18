<?php

namespace Drupal\user_wall\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\Core\Entity\EntityChangedTrait;

/**
 * @ContentEntityType(
 * id = "user_wall_comment",
 * label = @Translation("User Wall Comment"),
 * base_table = "user_wall_comment",
 * entity_keys = {
 * "id" = "id",
 * "uuid" = "uuid",
 * "uid" = "uid",
 * "owner" = "uid",
 * "created" = "created",
 * "changed" = "changed",
 * },
 * )
 */
class UserWallComment extends ContentEntityBase implements ContentEntityInterface, EntityOwnerInterface
{
  use EntityOwnerTrait;
  use EntityChangedTrait;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type)
  {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['post_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Post'))
      ->setDescription(t('The post this comment belongs to.'))
      ->setSetting('target_type', 'user_wall_post');

    $fields['comment'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Comment'))
      ->setDescription(t('The comment text.'))
      ->setTranslatable(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }
}
