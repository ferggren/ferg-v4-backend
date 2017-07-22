<?php
class ApiPhotostream_Controller extends ApiController {
  public function actionIndex() {
    return $this->error('access_denied');
  }

  public function actionGetMarkers() {
    $ret = [];

    $photos = PhotoLibrary::whereAnd('photo_show_in_photostream', '=', '1');
    $photos->whereAnd('photo_deleted', '=', '0');
    $photos->orderBy('photo_orderby', 'desc');

    foreach($photos->get() as $photo) {
      $export = $photo->export();

      if (!$photo->photo_location || !$photo->photo_gps || !$export['photo_tiny']) {
        continue;
      }

      $url  = '/' . Lang::getLang() . '/photostream/' . $photo->photo_id;

      $ret[] = array(
        'pic'  => $export['photo_tiny'],
        'tags' => implode(',', array(
          $photo->photo_location,
          $photo->photo_category,
          $photo->photo_camera,
          $photo->photo_lens,
        )),
        'type' => 'photostream',
        'id'   => (int)$photo->photo_id,
        'url'  => $url,
        'loc'  => $photo->photo_location,
        'gps'  => $photo->photo_gps,
      );
    }

    return $this->success($ret);
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

    if (!$photo->photo_show_in_photostream) {
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

    $where[] = "photo_show_in_photostream = 1";
    $where[] = "photo_deleted = 0";

    $photos = PhotoLibrary::orderBy('photo_orderby', 'desc');
    $photos->whereRaw(implode(' AND ', $where));

    if (!($count = $photos->count())) {
      return $this->success($ret);
    }

    $rpp = 24;
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
   *  Return photos by tag
   *
   *  @param {string} tag Tag
   *  @return {object} Photo IDs
   */
  protected function _getTagPhotos($tag) {
    return Tags::getTagRelations("photostream", $tag);
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

    $where[] = 'photo_id != "'.Database::escape($photo->photo_id).'"';
    $where[] = "photo_orderby ".($type == 'next' ? '>' : '<').' "'.Database::escape($photo->photo_orderby).'"';
    $where[] = "photo_show_in_photostream = 1";
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