<?php
class ApiTags_Controller extends ApiController {
  public function actionIndex() {
    return $this->error('access_denied');
  }

  /**
   *  Return feed tags
   *  
   *  @param {string} groups Comma-separated groups
   *  @return {object} Tags list
   */
  public function actionGetTags($group) {
    if (!preg_match('#^[0-9a-zA-Z_,.-]++$#uis', $group)) {
      return $this->error('incorrect_tag_group');
    }

    if (!is_array($tags = $this->_getTags($group))) {
      return $this->error('incorrect_tag_group');
    };

    return $this->success($tags);
  }


  /**
   *  Return tags in group
   *
   *  @param {string} group Tags group 
   *  @return {string} Tags
   */
  protected function _getTags($group) {
    if ($group == 'feed') {
      return Tags::getTags('feed');
    }

    if ($group == 'gallery') {
      if (!($gallery_id = $this->_getGalleryCollectionId())) {
        return false;
      }

      $groups = Tags::getTags(array(
        "photos_{$gallery_id}_category",
        "photos_{$gallery_id}_lens",
        "photos_{$gallery_id}_camera",
      ));

      if (!$groups) return false;

      $ret = array();

      foreach ($groups as $group => $tags) {
        foreach ($tags as $tag => $count) {
          $ret[$tag] = $count;
        }
      }

      return $ret;
    }

    if (in_array($group, array('dev', 'events', 'blog'))) {
      return Tags::getTags("pages_{$group}_visible");
    }

    return false;
  }

  /**
   *  Get gallery ID
   */
  protected function _getGalleryCollectionId() {
    static $id = false;

    if ($id !== false) {
      return $id;
    }

    $res = Database::from('photolibrary_collections');
    $res->whereAnd('collection_name', 'LIKE', 'gallery');
    $res->whereAnd('user_id', '=', 1);

    if (!count($res = $res->get())) {
      return $id = 0;
    }

    return $id = $res[0]->collection_id;
  }
}