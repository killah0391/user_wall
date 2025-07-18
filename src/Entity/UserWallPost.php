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
 * id = "user_wall_post",
 * label = @Translation("User Wall Post"),
 * base_table = "user_wall_post",
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
class UserWallPost extends ContentEntityBase implements ContentEntityInterface, EntityOwnerInterface
{
  use EntityOwnerTrait;
  use EntityChangedTrait;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type)
  {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The title of the post.'))
      ->setTranslatable(TRUE);

    $fields['message'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Message'))
      ->setDescription(t('The message of the post.'))
      ->setTranslatable(TRUE);

    $fields['image'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Image'))
      ->setSettings([
        'file_directory' => 'user_wall_images',
        'alt_field_required' => FALSE,
      ])
      ->setCardinality(5);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }
}
