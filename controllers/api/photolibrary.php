<?php
class ApiPhotoLibrary_Controller extends ApiController {
  public static $user_auth = true;
  public static $user_access_level = 'admin';

  /**
   *  Access error
   */
  public function actionIndex() {
    return $this->error('access_denied');
  }

  /**
   *  Return tags by category
   *
   *  @param {int} collection Category id
   *  @return {object} Tags list
   */
  public function actionGetTags($collection = 0) {
    if (!$this->_checkCollectionId($collection)) {
      return $this->error('invalid_collection_id');
    }

    return $this->success(
      $this->_getCollectionTags($collection)
    );
  }

  /**
   *  Delete photo
   *
   *  @param {int} photo_id Photo id
   *  @return {object} Photos
   */
  public function actionDeletePhoto($photo_id = 0) {
    if (!is_string($photo_id) || !preg_match('#^\d{1,10}$#', $photo_id)) {
      return $this->error('invalid_photo_id');
    }

    if (!$photo = PhotoLibrary::find($photo_id)) {
      return $this->error('invalid_photo_id');
    }

    if ($photo->user_id != User::get_user_id()) {
      if (!User::hasAccess('admin')) {
        return $this->error('invalid_photo_id');
      }
    }

    if ($photo->photo_deleted) {
      return $this->success();
    }

    $photo->photo_deleted = 1;
    $photo->save();

    $this->_updatePhotoTags($photo);

    if ($photo->photo_collection_id) {
      if ($collection = $this->_updateCollection($photo->photo_collection_id)) {
        return $this->success(array(
          'collection' => $collection,
        ));
      }
    }

    return $this->success();
  }

  /**
   *  Restore photo
   *
   *  @param {int} photo_id Photo id
   *  @return {object} Photos
   */
  public function actionRestorePhoto($photo_id = 0) {
    if (!is_string($photo_id) || !preg_match('#^\d{1,10}$#', $photo_id)) {
      return $this->error('invalid_photo_id');
    }

    if (!$photo = PhotoLibrary::find($photo_id)) {
      return $this->error('invalid_photo_id');
    }

    if ($photo->user_id != User::get_user_id()) {
      if (!User::hasAccess('admin')) {
        return $this->error('invalid_photo_id');
      }
    }

    if (!$photo->photo_deleted) {
      return $this->success();
    }

    $photo->photo_deleted = 0;
    $photo->save();

    $this->_updatePhotoTags($photo);

    if ($photo->photo_collection_id) {
      if ($collection = $this->_updateCollection($photo->photo_collection_id)) {
        return $this->success(array(
          'collection' => $collection,
        ));
      }
    }

    return $this->success();
  }

  /**
   *  Update photo
   *
   *  @param {int} page Page id
   *  @param {string} tags List of comma-seperated tags
   *  @param {int} photo_collection Photo collection id
   *  @param {int} tags_collection Tags collection id
   *  @return {object} Photos
   */
  public function actionUpdatePhoto($id = 0, $photo_collection = 0, $tags_collection = 0) {
    if (!is_string($id) || !preg_match('#^\d{1,10}$#', $id)) {
      return $this->error('invalid_photo_id');
    }

    if (!$photo = PhotoLibrary::find($id)) {
      return $this->error('invalid_photo_id');
    }

    if ($photo->user_id != User::get_user_id()) {
      if (!User::hasAccess('admin')) {
        return $this->error('invalid_photo_id');
      }
    }

    if ($photo->photo_deleted) {
      return $this->error('invalid_photo_id');
    }

    if ($photo_collection && !$this->_checkCollectionId($photo_collection)) {
      return $this->error('invalid_collection_id');
    }

    if ($tags_collection && !$this->_checkCollectionId($tags_collection)) {
      return $this->error('invalid_collection_id');
    }

    $text_regexp = '[0-9a-zA-ZАаБбВвГгДдЕеЁёЖжЗзИиЙйКкЛлМмНнОоПпРрСсТтУуФфХхЦцЧчШшЩщЪъЫыЬьЭэЮюЯя?.,?!\s:/_-]';

    $fields = array(
      'title_ru'      => "#^{$text_regexp}{1,50}$#ui",
      'title_en'      => "#^{$text_regexp}{1,50}$#ui",
      'gps'           => '#^-?\d{1,3}\.\d{1,20}[\s,]++-?\d{1,3}\.\d{1,20}$#ui',
      'taken'         => '#^(\d{4})\.(\d{2})\.(\d{2})$#ui',
      'iso'           => '#^\d{2,7}$#ui',
      'aperture'      => '#^f/\d{1,2}(?:\.\d)?$#ui',
      'shutter_speed' => '#^(\d{1,4}|1/\d{1,5})$#ui',
      'camera'        => "#^{$text_regexp}{1,20}$#ui",
      'lens'          => "#^{$text_regexp}{1,50}$#ui",
      'category'      => "#^{$text_regexp}{1,150}$#ui",
      'fl'            => '#^\d{1,4}(?:\.\d{1,2})?$#ui',
      'efl'           => '#^\d{1,4}(?:\.\d{1,2})?$#ui',
    );

    $photo->photo_taken_timestamp = 0;
    $photo->photo_orderby = $photo->photo_id;

    foreach ($fields as $field => $regexp) {
      $key = 'photo_' . $field;

      $photo->$key = '';

      if (!isset($_POST[$field]) || !$_POST[$field]) {
        continue;
      }

      if (!is_string($_POST[$field])) {
        return $this->error('invalid_' . $field);
      }

      if (!preg_match($regexp, $_POST[$field], $data)) {
        return $this->error('invalid_' . $field);
      }

      if ($field == 'taken') {
        $time = mktime(0, 0, 0, $data[2], $data[3], $data[1]);
        $photo->photo_taken_timestamp = $time;
        $photo->photo_orderby = $time + (int)$photo->photo_id;
      }

      $photo->$key = trim($_POST[$field]);
    }

    if ($photo->photo_collection_id != $photo_collection) {
      $old = $photo->photo_collection_id;
      $new = $photo_collection;

      $this->_removePhotoTags($photo);

      if ($old != 0) {
        $photo->photo_collection_id = 0;
        $photo->save();
        $this->_updateCollection($old);
      }

      if ($new != 0) {
        $photo->photo_collection_id = $new;
        $photo->save();
        $this->_updateCollection($new);
      }

      $this->_updatePhotoTags($photo);
    }
    else {
      $photo->save();
      $this->_updatePhotoTags($photo);
    }

    return $this->success(array(
      'collection' => $tags_collection,
      'tags'       => $this->_getCollectionTags($tags_collection),
    ));
  }

  /**
   *  Return user photos
   *
   *  @param {int} page Page id
   *  @param {string} tags List of comma-seperated tags
   *  @param {int} collection Collection id
   *  @return {object} Photos
   */
  public function actionGetPhotos($page = 1, $tags = '', $collection = 0) {
    $ret = array(
      'page'   => 1,
      'pages'  => 1,
      'photos' => array(),
    );

    if (!$this->_checkCollectionId($collection)) {
      return $this->error('invalid_collection_id');
    }

    $where = array();

    if ($tags) {
      if (!($where_in = $this->_makeTagsWhere($collection))) {
        return $this->success($ret);
      }

      if (!count($where_in)) {
        return $this->success($ret);
      }

      $where[] = "photo_id IN (".implode(',', $where_in).")";
    }

    $where[] = "user_id = '".Database::escape(User::get_user_id())."'";
    $where[] = "photo_deleted = 0";

    if ($collection) {
      $where[] = "photo_collection_id = '".Database::escape($collection)."'";
    }

    $photos = PhotoLibrary::orderBy('photo_added', 'desc');
    $photos->whereRaw(implode(' AND ', $where));

    if (!($count = $photos->count())) {
      return $this->success($ret);
    }

    $rpp = 24;
    $ret['page'] = is_numeric($page) ? (int)$page : 1;
    $ret['pages'] = (int)($count / $rpp);
    if (($ret['pages'] * $rpp) < $count) ++$ret['pages'];
    if ($ret['page'] > $ret['pages']) $ret['page'] = $ret['pages'];

    $photos->limit(
      $rpp,
      (($ret['page'] - 1) * $rpp)
    );

    foreach ($photos->get() as $photo) {
      $ret['photos'][] = $photo->export(true);
    }

    return $this->success($ret);
  }

  /**
   *  Return user photo collections (with statistics)
   *
   *  @return {object} Collection
   */
  public function actionGetCollections() {
    $ret = array();

    $collections = PhotoLibraryCollections::whereAnd('user_id', '=', User::get_user_id());
    $collections->whereAnd('collection_deleted', '=', 0);

    foreach ($collections->get() as $collection) {
      $preview = false;

      if ($collection->collection_cover_photo_hash) {
        $preview = StoragePreview::makePreviewLink(
          $collection->collection_cover_photo_hash, array(
            'crop'   => true,
            'width'  => 400,
            'height' => 150,
            'align'  => 'center',
            'valign' => 'top',
        ));
      }

      $ret[] = array(
        'id'      => $collection->collection_id,
        'name'    => $collection->collection_name,
        'updated' => $collection->collection_updated,
        'cover'   => $preview,
        'photos'  => $collection->collection_photos,
      );
    }

    return $this->success($ret);
  }

  /**
   *  Create new photo collection
   *
   *  @param {string} collection Collection name
   *  @return {object} Collection object
   */
  public function actionCreateCollection($name = '') {
    if (!is_string($name)) {
      return $this->error('invalid_collection_name');
    }

    if (iconv_strlen($name) < 1 || iconv_strlen($name) > 20) {
      return $this->error('invalid_collection_name');
    }

    $exists = Database::from('photolibrary_collections');
    $exists->where('user_id', '=', User::get_user_id());
    $exists->whereAnd('collection_deleted', '=', 0);
    $exists->whereAnd('collection_name', 'LIKE', $name);

    if ($exists->count()) {
      return $this->error('collection_name_exists');
    }

    $collection = new PhotoLibraryCollections;
    $collection->user_id = User::get_user_id();
    $collection->collection_name = $name;
    $collection->collection_updated = time();
    $collection->collection_created = time();
    $collection->collection_cover_photo_id = 0;
    $collection->save();

    return $this->success(array(
      'id'      => $collection->collection_id,
      'name'    => $collection->collection_name,
      'updated' => $collection->collection_updated,
      'cover'   => '',
      'photos'  => 0,
    ));
  }

  /**
   *  Update collection title
   *
   *  @param {string} collection Collection name
   *  @param {int} id Collection id
   *  @return {object} Collection object
   */
  public function actionUpdateCollection($id = 0, $name = '') {
    if (!is_string($name)) {
      return $this->error('invalid_collection_name');
    }

    if (iconv_strlen($name) < 1 || iconv_strlen($name) > 20) {
      return $this->error('invalid_collection_name');
    }

    if (!is_string($id) || !preg_match('#^\d{1,10}$#', $id)) {
      return $this->error('invalid_collection_id');
    }

    if (!$collection = PhotoLibraryCollections::find($id)) {
      return $this->error('invalid_collection_id');
    }

    if ($collection->user_id != User::get_user_id()) {
      if (!User::hasAccess('admin')) {
        return $this->error('invalid_collection_id');
      }
    }

    if ($collection->collection_deleted) {
      return $this->error();
    }

    if ($collection->collection_name == $name) {
      return $this->success();
    }

    $exists = Database::from('photolibrary_collections');
    $exists->where('user_id', '=', User::get_user_id());
    $exists->whereAnd('collection_deleted', '=', 0);
    $exists->whereAnd('collection_name', 'LIKE', $name);

    if ($exists->count()) {
      return $this->error('collection_name_exists');
    }

    $collection->collection_name = $name;
    $collection->save();

    return $this->success();
  }

  /**
   *  Delete collection
   *
   *  @param {int} id Collection id
   *  @return {boolean} Result
   */
  public function actionDeleteCollection($id = 0) {
    if (!is_string($id) || !preg_match('#^\d{1,10}$#', $id)) {
      return $this->error('invalid_collection_id');
    }

    if (!$collection = PhotoLibraryCollections::find($id)) {
      return $this->error('invalid_collection_id');
    }

    if ($collection->user_id != User::get_user_id()) {
      if (!User::hasAccess('admin')) {
        return $this->error('invalid_collection_id');
      }
    }

    if ($collection->collection_deleted) {
      return $this->success();
    }

    $collection->collection_deleted = 1;
    $collection->save();

    return $this->success();
  }

  /**
   *  Restore collection
   *
   *  @param {int} id Collection id
   *  @return {boolean} Result
   */
  public function actionRestoreCollection($id = 0) {
    if (!is_string($id) || !preg_match('#^\d{1,10}$#', $id)) {
      return $this->error('invalid_collection_id');
    }

    if (!$collection = PhotoLibraryCollections::find($id)) {
      return $this->error('invalid_collection_id');
    }

    if ($collection->user_id != User::get_user_id()) {
      if (!User::hasAccess('admin')) {
        return $this->error('invalid_collection_id');
      }
    }

    if (!$collection->collection_deleted) {
      return $this->success();
    }

    $collection->collection_deleted = 0;
    $collection->save();

    return $this->success();
  }

  /**
   *  Create new photo
   *
   *  @param {int} file_id Storage file id
   *  @param {int} collection Collection id
   *  @return {object} Photo
   */
  public function actionAddPhoto($file_id = 0, $collection = 0) {
    if (!is_string($file_id) || !preg_match('#^\d{1,10}$#', $file_id)) {
      return $this->error('invalid_file_id');
    }

    if (!$file = StorageFiles::find($file_id)) {
      return $this->error('invalid_file_id');
    }

    if ($file->file_deleted) {
      return $this->error('invalid_file_id');
    }

    if ($file->user_id != User::get_user_id()) {
      return $this->error('invalid_file_id');
    }

    if (PhotoLibrary::find($file->file_id)) {
      return $this->error('invalid_file_id');
    }

    if ($file->file_media != 'image') {
      $this->_actionDeleteFile($file);
      return $this->error('file_is_not_image');
    }

    if (!$file->file_preview) {
      $this->_actionDeleteFile($file);
      return $this->error('file_is_not_image');
    }

    if (!file_exists(ROOT_PATH . $file->file_path)) {
      $this->_actionDeleteFile($file);
      return $this->error('invalid_file');
    }

    if (!$this->_checkCollectionId($collection)) {
      $this->_actionDeleteFile($file);
      return $this->error('invalid_collection_id');
    }

    $info = array();

    if (!($size = getimagesize(ROOT_PATH . $file->file_path, $info))) {
      $this->_actionDeleteFile($file);
      return $this->error('file_is_not_image');
    }

    $photo = new PhotoLibrary;
    $photo->file_id = $file_id;
    $photo->file_hash = $file->file_hash;
    $photo->user_id = User::get_user_id();
    $photo->photo_collection_id = $collection;
    $photo->photo_size = "{$size[0]}x{$size[1]}";
    $photo->photo_added = time();
    $photo->save();

    $photo->photo_orderby = $photo->photo_id;
    $photo->save();

    $ret = array(
      'photo'      => $photo->export(true),
      'collection' => false,
    );

    if ($collection) {
      if ($collection = $this->_updateCollection($collection)) {
        $ret['collection'] = $collection;
      }
    }

    return $this->success($ret);
  }

  protected function _actionDeleteFile($file) {
    $file->file_deleted = 1;
    $file->save();
  }

  /**
   *  Check if collection id is valid
   *
   *  @param {int} collection_id Collection id
   *  @return {boolean} Is collection valid
   */
  protected function _checkCollectionId($collection_id) {
    $collection_id = $collection_id ? $collection_id : 0;

    if (!preg_match('#^\d++$#', $collection_id)) {
      return false;
    }

    if (!$collection_id) {
      return true;
    }

    if (!($collection = PhotoLibraryCollections::find($collection_id))) {
      return false;
    }

    return !$collection->collection->collection_deleted;
  }

  /**
   *  Update collections stats
   *
   *  @param {int} collection_id Collection id
   *  @return {boolean} Is collection valid
   */
  protected function _updateCollection($collection_id) {
    if (!($collection = PhotoLibraryCollections::find($collection_id))) {
      return false;
    }

    $collection->collection_updated = $collection->collection_created;
    $collection->collection_cover_photo_id = 0;
    $collection->collection_cover_photo_hash = '';
    $collection->collection_photos = 0;

    $photo = PhotoLibrary::where('photo_collection_id', '=', $collection_id);
    $photo->whereAnd('photo_deleted', '=', 0);

    $collection->collection_photos = $photo->count();

    $photo->limit(1);
    $photo->orderBy('photo_added', 'desc');
    $photo = $photo->get();

    $preview = '';

    if (count($photo)) {
      $collection->collection_cover_photo_id = $photo[0]->photo_id;
      $collection->collection_cover_photo_hash = $photo[0]->file_hash;
      $collection->collection_updated = $photo[0]->photo_added;

      $preview = StoragePreview::makePreviewLink(
        $photo[0]->file_hash, array(
          'crop'   => true,
          'width'  => 400,
          'height' => 150,
          'align'  => 'center',
          'valign' => 'middle',
      ));
    }

    $collection->save();

    return array(
      'id'      => $collection->collection_id,
      'photos'  => $collection->collection_photos,
      'cover'   => $preview,
      'updated' => $collection->collection_updated,
    );
  }

  protected function _removePhotoTags($photo) {
    $user_id = User::get_user_id();

    $tags = array(
      'iso',
      'shutter_speed',
      'aperture',
      'camera',
      'lens',
      'category',
      'fl',
      'efl',
    );

    foreach ($tags as $tag) {
      $key    = 'photo_' . $tag;

      Tags::attachTags(
        "photos_{$user_id}_0_{$tag}",
        $photo->photo_id,
        array()
      );

      if ($photo->photo_collection_id) {
        Tags::attachTags(
          "photos_{$photo->photo_collection_id}_{$tag}",
          $photo->photo_id,
          array()
        );
      }
    }
  }

  protected function _updatePhotoTags($photo) {
    $user_id = User::get_user_id();

    $tags = array(
      'iso',
      'shutter_speed',
      'aperture',
      'camera',
      'lens',
      'category',
      'fl',
      'efl',
    );

    foreach ($tags as $tag) {
      $key    = 'photo_' . $tag;
      $value  = $photo->$key;
      $values = array();

      if ($value && !$photo->photo_deleted) {
        if ($tag != 'category') {
          $values = array($photo->$key);
        }
        else {
          foreach (explode(',', $value) as $_value) {
            if (!($_value = trim($_value))) {
              continue;
            }

            $values[] = $_value;
          }
        }
      }

      Tags::attachTags(
        "photos_{$user_id}_0_{$tag}",
        $photo->photo_id,
        $values
      );

      if ($photo->photo_collection_id) {
        Tags::attachTags(
          "photos_{$photo->photo_collection_id}_{$tag}",
          $photo->photo_id,
          $values
        );
      }
    }
  }

  /**
   *  Return collections tags
   *
   *  @param {int} collection_id Collection id
   *  @return {boolean} Is collection valid
   */
  protected function _getCollectionTags($collection) {
    if (!$collection) {
      $key = "photos_".User::get_user_id()."_0_";
    }
    else {
      $key = "photos_{$collection}_";
    }

    $tags = Tags::getTags(array(
      "{$key}iso",
      "{$key}shutter_speed",
      "{$key}aperture",
      "{$key}camera",
      "{$key}lens",
      "{$key}category",
      "{$key}fl",
      "{$key}efl",
    ));

    return array(
      "iso"           => $tags["{$key}iso"],
      "shutter_speed" => $tags["{$key}shutter_speed"],
      "aperture"      => $tags["{$key}aperture"],
      "camera"        => $tags["{$key}camera"],
      "lens"          => $tags["{$key}lens"],
      "category"      => $tags["{$key}category"],
      "fl"            => $tags["{$key}fl"],
      "efl"           => $tags["{$key}efl"],
    );
  }

  /**
   *  Return photo id for tags in $_POST
   */
  protected function _makeTagsWhere($collection) {
    if (!$collection) {
      $key = "photos_".User::get_user_id()."_0_";
    }
    else {
      $key = "photos_{$collection}_";
    }

    $tags = array(
      "iso"           => "",
      "shutter_speed" => "",
      "aperture"      => "",
      "camera"        => "",
      "lens"          => "",
      "category"      => "",
      "fl"            => "",
      "efl"           => "",
    );

    foreach ($tags as $tag => $value) {
      if (!isset($_POST['tag_' . $tag]) || !$_POST['tag_' . $tag]) {
        continue;
      }

      $value = $_POST['tag_' . $tag];

      if (!is_string($value)) {
        return false;
      }

      if (!preg_match('#^[0-9a-zA-ZАаБбВвГгДдЕеЁёЖжЗзИиЙйКкЛлМмНнОоПпРрСсТтУуФфХхЦцЧчШшЩщЪъЫыЬьЭэЮюЯя?.,?!\s:/_-]{1,50}$#ui', $value)) {
        return false;
      }

      $tags[$tag] = $value;
    }

    $insert_all    = true;
    $needed_amount = 0;
    $photos_ids    = array();

    foreach ($tags as $tag => $value) {
      if (!$value) {
        continue;
      }

      if (!count($photos = Tags::getTagRelations($key.$tag, $value))) {
        return array();
      }

      foreach ($photos as $photo) {
        if (!$insert_all && !isset($photos_ids[$photo])) {
          continue;
        }

        if (!isset($photos_ids[$photo])) {
          $photos_ids[$photo] = 0;
        }

        ++$photos_ids[$photo];
      }

      ++$needed_amount;
      $insert_all = false;
    }

    $ret = array();

    foreach ($photos_ids as $photo_id => $amount) {
      if ($amount < $needed_amount) {
        continue;
      }

      $ret[] = $photo_id;
    }

    return $ret;
  }
}
?>