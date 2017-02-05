<?php
class ApiGallery_Controller extends ApiController {
  public function actionIndex() {
    return $this->error('access_denied');
  }


  /**
   *  Return photo info
   *  
   *  @param {int} id Photo id
   *  @param {string} groups Comma-separated groups
   *  @return {object} Tags list
   */
  public function actionGetPhoto($id, $tag) {
    if (!preg_match('#^[0-9a-zA-ZАаБбВвГгДдЕеЁёЖжЗзИиЙйКкЛлМмНнОоПпРрСсТтУуФфХхЦцЧчШшЩщЪъЫыЬьЭэЮюЯя?.,?!\s:/_-]{0,50}$#uis', $tag)) {
      return $this->error('incorrect_tag');
    }

    if (!($collection_id = $this->_getGalleryCollectionId())) {
      return $this->error('not_found');
    }

    if ($tag) {
      if (!($photos = $this->_getTagPhotos($tag))) {
        return $this->error('not_found');
      }

      if (!in_array($id, $photos)) {
        return $this->error('not_found');
      }

      $photos = null;
    }

    if (!($photo = PhotoLibrary::find($id))) {
      return $this->error('not_found');
    }

    if ($photo->photo_deleted) {
      return $this->error('not_found');
    }

    if ($photo->photo_collection_id != $collection_id) {
      return $this->error('not_found');
    }

    if (!User::isAuthenticated() || !User::hasAccess('admin')) {
      $user_ip = ip2decimal(Session::getSessionIp());

      if ($photo->photo_last_view_ip != $user_ip) {
        $photo->photo_last_view_ip = $user_ip;
        $photo->photo_views++;
        $photo->save();
      }
    }

    return $this->success(array(
      'next' => $this->_getPhotoNeighbors($photo, $tag, 'next'),
      'info' => $photo->export(),
      'prev' => $this->_getPhotoNeighbors($photo, $tag, 'prev'),
    ));
  }

  /**
   *  Return feed tags
   *  
   *  @param {int} page Page id
   *  @param {string} groups Comma-separated groups
   *  @return {object} Tags list
   */
  public function actionGetPhotos($page, $tag) {
    $ret = array(
      'page'   => 1,
      'pages'  => 1,
      'photos' => array(),
    );

    if (!preg_match('#^[0-9a-zA-ZАаБбВвГгДдЕеЁёЖжЗзИиЙйКкЛлМмНнОоПпРрСсТтУуФфХхЦцЧчШшЩщЪъЫыЬьЭэЮюЯя?.,?!\s:/_-]{0,50}$#uis', $tag)) {
      return $this->error('incorrect_tag');
    }

    if (!($collection_id = $this->_getGalleryCollectionId())) {
      return $this->error('photos_not_found');
    }

    $where = array();

    if ($tag) {
      if (!($where_in = $this->_getTagPhotos($tag))) {
        return $this->success($ret);
      }

      if (!count($where_in)) {
        return $this->success($ret);
      }

      $where[] = "photo_id IN (".implode(',', $where_in).")";
    }

    $where[] = "photo_collection_id = '".Database::escape($collection_id)."'";
    $where[] = "photo_deleted = 0";

    $photos = PhotoLibrary::orderBy('photo_orderby', 'desc');
    $photos->whereRaw(implode(' AND ', $where));

    if (!($count = $photos->count())) {
      return $this->success($ret);
    }

    $rpp = 14;
    $ret['page'] = is_numeric($page) ? (int)$page : 1;
    $ret['pages'] = (int)($count / $rpp);
    if (($ret['pages'] * $rpp) < $count) ++$ret['pages'];
    if ($ret['page'] > $ret['pages']) $ret['page'] = $ret['pages'];

    $photos->limit($rpp, (($ret['page'] - 1) * $rpp));

    foreach ($photos->get() as $photo) {
      $ret['photos'][] = $photo->export();
    }

    return $this->success($ret);
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

  /**
   *  Return photos by tag
   *
   *  @param {string} tag Tag
   *  @return {object} Photo IDs
   */
  protected function _getTagPhotos($tag) {
    static $ret = array();

    if (!$tag) {
      return array();
    }

    if (!($collection_id = $this->_getGalleryCollectionId())) {
      return array();
    }

    if (isset($ret[$tag])) {
      return $ret[$tag];
    }

    $key = "photos_{$collection_id}_";

    $groups = array(
      "camera",
      "lens",
      "category",
    );

    $uniq = array();

    foreach ($groups as $group) {
      if (!count($photos = Tags::getTagRelations($key.$group, $tag))) {
        continue;
      }

      foreach ($photos as $photo) {
        $uniq[$photo] = true;
      }
    }

    return $ret[$tag] = array_keys($uniq);
  }

  /**
   *  Return photo neighbors
   *
   *  @param {object} photo Photo object
   *  @param {string} tag Photos tag
   *  @param {string} type Neighbors type
   *  @return {object} Photos list
   */
  protected function _getPhotoNeighbors($photo, $tag, $type) {
    $where = array();

    if ($tag) {
      if (!($where_in = $this->_getTagPhotos($tag))) {
        return array();
      }

      if (!count($where_in)) {
        return array();
      }

      $where[] = "photo_id IN (".implode(',', $where_in).")";
    }

    if (!($collection_id = $this->_getGalleryCollectionId())) {
      return array();
    }

    $where[] = 'photo_id != "'.Database::escape($photo->photo_id).'"';
    $where[] = "photo_orderby ".($type == 'next' ? '>' : '<').' "'.Database::escape($photo->photo_orderby).'"';
    $where[] = "photo_collection_id = '".Database::escape($collection_id)."'";
    $where[] = "photo_deleted = 0";

    $photos = PhotoLibrary::orderBy('photo_orderby', $type == 'next' ? 'asc' : 'desc');
    $photos->whereRaw(implode(' AND ', $where));
    $photos->limit(3);

    $ret = array();

    foreach ($photos->get() as $photo) {
      $ret[] = $photo->export();
    }

    return $ret;
  }
}