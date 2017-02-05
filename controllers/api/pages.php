<?php
class ApiPages_Controller extends ApiController {
  static $_types = array(
    "dev",
    "events",
    "blog",
  );
  /**
   *  Access error
   */
  public function actionIndex() {
    return $this->error('access_denied');
  }

  /**
   *  Create new page
   *
   *  @param {string} type Page type
   *  @return {object} Created page
   */
  public function actionCreatePage($type) {
    if (!User::isAuthenticated()) {
      return $this->error('access_denied');
    }

    if (!User::hasAccess('admin')) {
      return $this->error('access_denied');
    }

    if (!in_array($type, self::$_types)) {
      return $this->error('access_denied');
    }

    $page = new MediaPages;
    $page->page_type = $type;
    $page->save();

    return $this->success($page->export());
  }

  /**
   *  Get pages
   *
   *  @param {int} page Page number
   *  @param {string} type Page type
   *  @param {string} visible Visible flag
   *  @param {string} tag Search pages by tag
   *  @return {object} Found pages
   */
  public function actionGetPages($page, $type, $visible = 'visible', $tag) {
    $ret = array(
      'page'  => 1,
      'pages' => 1,
      'list'  => array(),
    );

    if (!in_array($type, self::$_types)) {
      return $this->success($ret);
    }

    $visible = $this->_checkVisibility($visible);

    $where = array();

    if ($tag) {
      if (!($pages = $this->_getTagPages($type, $visible, $tag))) {
        return $this->success($ret);
      }

      if (!count($pages)) {
        return $this->success($ret);
      }

      $where[] = 'page_id IN (' . implode(',', $pages) . ')';
    }

    $where[] = "page_type = '" . Database::escape($type) . "'";

    if ($visible != 'all') {
      $where[] = 'page_visible = ' . (($visible == 'visible') ? '1' : '0');
    }

    $where[] = "page_deleted = 0";

    $pages = MediaPages::whereRaw($where = implode(' AND ', $where));
    $pages->orderBy('page_date_timestamp', 'DESC');
    $pages->orderBy('page_id', 'DESC');

    if (!($count = $pages->count())) {
      return $this->success($ret);
    }

    $rpp = 10;
    $ret['page'] = is_numeric($page) ? (int)$page : 1;
    $ret['pages'] = (int)($count / $rpp);
    if (($ret['pages'] * $rpp) < $count) ++$ret['pages'];
    if ($ret['page'] > $ret['pages']) $ret['page'] = $ret['pages'];

    $pages->limit(
      $rpp,
      (($ret['page'] - 1) * $rpp)
    );

    foreach ($pages->get() as $page) {
      $ret['list'][] = $page->export();
    }

    return $this->success($ret);
  }

  /**
   *  Restore page
   *  @param {int} id Page id
   *  @return {boolean} Status
   */
  public function actionRestorePage($id) {
    return $this->_changePageFlag($id, "deleted", 0);
  }

  /**
   *  Restore page
   *  @param {int} id Page id
   *  @return {boolean} Status
   */
  public function actionDeletePage($id) {
    return $this->_changePageFlag($id, "deleted", 1);
  }

  /**
   *  Restore page
   *  @param {int} id Page id
   *  @return {boolean} Status
   */
  public function actionHidePage($id) {
    return $this->_changePageFlag($id, "visible", 0);
  }

  /**
   *  Restore page
   *  @param {int} id Page id
   *  @return {boolean} Status
   */
  public function actionShowPage($id) {
    return $this->_changePageFlag($id, "visible", 1);
  }

  /**
   *  Update photo preview
   *
   *  @param {int} id Page id
   *  @param {int} photo_id Photo id
   *  @return {object} Photo's updated preview
   */
  public function actionUpdatePhoto($id, $photo_id) {
    if (!($page = $this->_getPage($id, true))) {
      return $this->error('access_denied');
    }

    if ($page->page_deleted) {
      return $this->error('incorrect_page_id');
    }

    if ($page->page_photo_id == $photo_id) {
      return $this->success($page->export()['preview']);
    }

    $page->page_photo_id = 0;

    if ($photo_id && preg_match('#^\d++$#', $photo_id)) {
      if (!($photo = PhotoLibrary::find($photo_id))) {
        return $this->error('incorrect_photo_id');
      }

      if ($photo->photo_deleted) {
        return $this->error('incorrect_photo_id');
      }

      $page->page_photo_id = $photo_id;
    }

    $page->save();

    return $this->success($page->export()['preview']);
  }

  /**
   *  Update photo date
   *
   *  @param {int} id Page id
   *  @param {string} date Date
   *  @return {object} Photo's updated date
   */
  public function actionUpdateDate($id, $date) {
    if (!($page = $this->_getPage($id, true))) {
      return $this->error('access_denied');
    }

    if ($page->page_deleted) {
      return $this->error('incorrect_page_id');
    }

    if ($page->page_date == $date) {
      return $this->success(array(
        'date'      => $page->page_date,
        'timestamp' => $page->page_date_timestamp,
      ));
    }

    $page->page_date = '';
    $page->page_date_timestamp = 0;

    if ($date) {
      if (!preg_match('#^(\d{4})\.(\d{1,2})\.(\d{1,2})$#u', $date, $data)) {
        return $this->error('incorrect_date');
      }

      $page->page_date = $date;
      $page->page_date_timestamp = mktime(
        0, 0, 0,
        $data[2], $data[3], $data[1]
      );
    }

    $page->save();

    return $this->success(array(
      'date'      => $page->page_date,
      'timestamp' => $page->page_date_timestamp,
    ));
  }

  /**
   *  Update photo tags
   *
   *  @param {int} id Page id
   *  @param {string} tags Tags
   *  @return {object} Photo's updated date
   */
  public function actionUpdateTags($id, $tags) {
    if (!($page = $this->_getPage($id, true))) {
      return $this->error('access_denied');
    }

    if ($page->page_deleted) {
      return $this->error('incorrect_page_id');
    }

    if (!preg_match('#^[0-9a-zA-ZАаБбВвГгДдЕеЁёЖжЗзИиЙйКкЛлМмНнОоПпРрСсТтУуФфХхЦцЧчШшЩщЪъЫыЬьЭэЮюЯя?.,?!\s:/_-]{0,200}$#iu', $tags)) {
      return $this->error('incorrect_tags');
    }

    $page->page_tags = $tags;
    $page->save();

    $this->_updatePageTags($page);

    return $this->success(array(
      'tags' => Tags::getTags("pages_{$page->page_type}_all"),
      'page_tags' => $page->page_tags,
    ));
  }

  /**
   *  Update page versions
   *
   *  @param {int} id Page id
   *  @return {boolean} Status
   */
  public function actionUpdateVersions($id) {
    if (!($page = $this->_getPage($id, true))) {
      return $this->error('incorrect_page_id');
    }

    if ($page->page_deleted) {
      return $this->error('incorrect_page_id');
    }

    $versions = array();

    $res = Database::from(array(
      'media_entries_content ec',
      'media_entries e',
    ));

    $res->whereAnd('e.entry_key', '=', 'page_' . $page->page_id);
    $res->whereAnd('ec.entry_id', '=', 'e.entry_id', false);
    $res->whereAnd('ec.entry_lang', 'IN', array('ru', 'en'));
    $res->whereAnd('ec.entry_visible', '=', '1');
    $res = $res->get();

    foreach ($res as $entry) {
      $versions[] = $entry->entry_lang;
    }

    $page->page_versions = implode(',', $versions);
    $page->save();

    return $this->success();
  }

  /**
   *  Return page info
   *
   *  @param {int} id Page id
   *  @return {object} Page info
   */
  public function actionGetPage($id) {
    if (!($page = $this->_getPage($id, false))) {
      return $this->error('incorrect_page_id');
    }

    if ($page->page_deleted) {
      return $this->error('incorrect_page_id');
    }

    if (!$page->page_visible) {
      if (!User::isAuthenticated()) {
        return $this->error('incorrect_page_id');
      }

      if (!User::hasAccess('admin')) {
        return $this->error('incorrect_page_id');
      }
    }

    if (!User::isAuthenticated() || !User::hasAccess('admin')) {
      $user_ip = ip2decimal(Session::getSessionIp());

      if ($page->page_last_view_ip != $user_ip) {
        $page->page_last_view_ip = $user_ip;
        $page->page_views++;
        $page->save();
      }
    }

    return $this->success($page->export(true));
  }

  /**
   *  Return tags assigned to page type
   *
   *  @param {string} type Pages type
   *  @param {string} visible Visibility type
   *  @return {object} Tags list
   */
  public function actionGetTags($type, $visible = 'visible') {
    if (!in_array($type, self::$_types)) {
      return $this->error('incorrect_type');
    }

    $visible = $this->_checkVisibility($visible);

    return $this->success(Tags::getTags(
      "pages_{$type}_{$visible}"
    ));
  }

  /**
   *  Check if user has access to $visible
   *
   *  @param {string} visible Visibility type
   *  @return {string} Visibility type allowed for user
   */
  protected function _checkVisibility($visible) {
    if (!User::isAuthenticated()) {
      return 'visible';
    }

    if (!User::hasAccess('admin')) {
      return 'visible';
    }

    if (!in_array($visible, array('all', 'visible', 'hidden'))) {
      return 'visible';
    }

    return $visible;
  }

  /**
   *  Return pages by tag
   *
   *  @param {string} type Pages type
   *  @param {string} visible Visibility type
   *  @param {string} tag Tag
   *  @return {object} Pages id's
   */
  protected function _getTagPages($type, $visible, $tag) {
    $key = "pages_{$type}_{$visible}";

    if (!preg_match('#^[0-9a-zA-ZАаБбВвГгДдЕеЁёЖжЗзИиЙйКкЛлМмНнОоПпРрСсТтУуФфХхЦцЧчШшЩщЪъЫыЬьЭэЮюЯя?.,?!\s:/_-]{1,50}$#ui', $tag)) {
      return false;
    }

    return Tags::getTagRelations($key, $tag);
  }

  /**
   *  Change page field
   *
   *  @param {int} id Page id
   *  @param {string} field Page field
   *  @param {int} value Page field value
   *  @return {boolean} Status
   */
  protected function _changePageFlag($id, $field, $value) {
    if (!($page = $this->_getPage($id, true))) {
      return $this->error('access_denied');
    }

    if (!in_array($field, array('deleted', 'visible'))) {
      return $this->error('incorrect_field');
    }

    $field = 'page_' . $field;
    $value = $value ? '1' : '0';

    if ($page->$field == $value) {
      return $this->success();
    }

    $page->$field = $value;
    $page->save();

    $this->_updatePageTags($page);

    return $this->success();
  }

  /**
   *  Get page object by page id
   *
   *  @param {int} id Page id
   *  @param {boolean} check_access Check user access
   *  @return {object} Pag object
   */
  protected function _getPage($id, $check_access = true) {
    if ($check_access) {
      if (!User::isAuthenticated()) {
        return false;
      }

      if (!User::hasAccess('admin')) {
        return false;
      }
    }

    if (!preg_match('#^\d++$#', $id)) {
      return false;
    }

    if (!($page = MediaPages::find($id))) {
      return false;
    }

    return $page;
  }

  /**
   *  Update page tags
   *
   *  @param {object} page Page object
   */
  protected function _updatePageTags($page) {
    $values         = array();
    $values_visible = array();
    $values_all     = array();

    foreach (explode(',', $page->page_tags) as $tag) {
      if (!($tag = trim($tag))) {
        continue;
      }

      $values[] = $tag;
    }

    if (!$page->page_deleted) {
      $values_all = $values;

      if ($page->page_visible) {
        $values_visible = $values;
      }
    }

    Tags::attachTags(
      "pages_{$page->page_type}_all",
      $page->page_id,
      $values_all
    );

    Tags::attachTags(
      "pages_{$page->page_type}_visible",
      $page->page_id,
      $values_visible
    );
  }
}
?>