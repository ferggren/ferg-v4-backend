<?php
/**
 *  Tags 
 */
class Tags {
  /**
   *  Return all tags in the group(s)
   *
   *  @param {object} groups List of groups
   *  @return {object} Tags for each group
   */
  public static function getTags($groups) {
    if (!is_array($groups)) {
      $groups = array($groups);
    }

    if (!count($groups)) {
      return array();
    }

    $ret = array();

    foreach ($groups as $group) {
      $ret[$group] = array();
    }

    $res = Database::from(array(
      'tags_groups tg',
      'tags t',
    ));

    $res->whereAnd('tg.group_name', 'IN', array_keys($ret));
    $res->whereAnd('t.group_id', '=', 'tg.group_id', false);
    $res->whereAnd('t.tag_weight', '>', 0);
    $res->orderBy('t.tag_name', 'asc');

    foreach ($res->get() as $tag) {
      if (!isset($ret[$tag->group_name])) {
        continue;
      }

      $ret[$tag->group_name][$tag->tag_name] = (int)$tag->tag_weight;
    }

    return count($ret) == 1 ? $ret[$groups[0]] : $ret;
  }

  /**
   *  Return all targets attached to the tag
   *
   *  @param {string} tag_group Tags group
   *  @param {string} tag_name Tag value
   *  @return {object} List of targets
   */
  public static function getTagRelations($tag_group, $tag_name) {
    if (!($group_id = self::_getGroupId($tag_group, false))) {
      return array();
    }

    if (!($tag = self::_getTagObject($group_id, $tag_name, false))) {
      return array();
    }

    $res = Database::from('tags_relations');
    $res->whereAnd('tag_id', '=', $tag->tag_id);

    $ret = array();

    foreach ($res->get() as $rel) {
      $ret[] = $rel->relation_id;
    }

    return $ret;
  }

  /**
   *  Attach tags to a target
   *
   *  @param {string} group_name Tags group
   *  @param {int} relation_id Relation id
   *  @param {string} target_tags Relation tags
   *  @param {int} relation_weight Relation weight
   */
  public static function attachTags($group_name, $relation_id, $relation_tags, $relation_weight = 1) {
    if (!($group_id = self::_getGroupId($group_name))) {
      return false;
    }

    if (!preg_match('#^\d++$#', $relation_id)) {
      return false;
    }

    // decrease weight
    $res = Database::from('tags_relations');
    $res->whereAnd('group_id', '=', $group_id);
    $res->whereAnd('relation_id', '=', $relation_id);

    foreach ($res->get() as $rel) {
      $tag = Database::from('tags');
      $tag->whereAnd('tag_id', '=', $rel->tag_id);

      if (count($tag = $tag->get()) != 1) {
        return false;
      }

      $tag = $tag[0];

      $tag->tag_weight = (int)$tag->tag_weight - (int)$rel->relation_weight;
      $tag->save();
    }

    // remove attached tags
    $delete = Database::from('tags_relations');
    $delete->whereAnd('group_id', '=', $group_id);
    $delete->whereAnd('relation_id', '=', $relation_id);
    $delete->delete();

    // attach tags
    foreach ($relation_tags as $tag) {
      if (!($tag = self::_getTagObject($group_id, $tag))) {
        continue;
      }

      $rel = new Database('tags_relations');
      $rel->group_id = $group_id;
      $rel->tag_id = $tag->tag_id;
      $rel->relation_id = $relation_id;
      $rel->relation_weight = (int)$relation_weight;
      $rel->save();

      $tag->tag_weight = (int)$tag->tag_weight + (int)$relation_weight;
      $tag->save();
    }

    return true;
  }

  /**
   *  Return group id
   *
   *  @param {string} group_name Group name
   *  @param {boolean} insert Create group if not exists, 
   *  @return {int} Group id
   */
  protected static function _getGroupId($group_name, $insert = true) {
    if (!($group_name = trim($group_name))) {
      return false;
    }

    static $cache = false;

    if ($cache === false) {
      $cache = array();

      foreach(Database::from('tags_groups')->get() as $row) {
        $cache[$row->group_name] = $row->group_id;
      }
    }

    if (isset($cache[$group_name])) {
      return $cache[$group_name];
    }

    if (!$insert) {
      return false;
    }

    $res = new Database('tags_groups');
    $res->group_name = $group_name;
    $res->save();

    return $cache[$group_name] = $res->group_id;
  }


  /**
   *  Return tag object
   *
   *  @param {int} group_id Tag group id
   *  @param {string} tag_name Tag 
   *  @param {boolean} insert Create new tag if not exists
   *  @return {object} Tag object
   */
  protected static function _getTagObject($group_id, $tag_name, $insert = true) {
    if (!($tag_name = trim($tag_name))) {
      return false;
    }

    $res = Database::from('tags');
    $res->whereAnd('group_id', '=', $group_id);
    $res->whereAnd('tag_name', 'LIKE', $tag_name);
    $res = $res->get();

    if (count($res) > 0) {
      return count($res) == 1 ? $res[0] : false;
    }

    if (!$insert) {
      return false;
    }

    $res = new Database('tags');
    $res->group_id = $group_id;
    $res->tag_name = $tag_name;
    $res->tag_weight = 0;
    $res->save();

    return $res;
  }
}
?>