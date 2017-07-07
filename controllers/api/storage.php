<?php
class ApiStorage_Controller extends ApiController {
  /**
   *  Return access error
   */
  public function actionIndex() {
    return $this->error('access_denied');
  }

  public function actionDeleteFile($file_id = false) {
    if (!User::isAuthenticated()) {
      return $this->error('access_denied');
    }

    if (!is_string($file_id) || !preg_match('#^\d{1,10}$#', $file_id)) {
      return $this->error('ivalid_file_id');
    }

    if (!$file = StorageFiles::find($file_id)) {
      return $this->error('ivalid_file_id');
    }

    if ($file->file_deleted) {
      return $this->error('ivalid_file_id');
    }

    if (!User::hasAccess('admin')) {
      return $this->error('ivalid_file_id');
    }

    $file->file_deleted = 1;
    $file->save();

    return $this->success();
  }

  public function actionRestoreFile($file_id = false) {
    if (!User::isAuthenticated()) {
      return $this->error('access_denied');
    }

    if (!is_string($file_id) || !preg_match('#^\d{1,10}$#', $file_id)) {
      return $this->error('ivalid_file_id');
    }

    if (!$file = StorageFiles::find($file_id)) {
      return $this->error('ivalid_file_id');
    }

    if (!$file->file_deleted) {
      return $this->error('ivalid_file_id');
    }

    if (!User::hasAccess('admin')) {
      return $this->error('ivalid_file_id');
    }

    $file->file_deleted = 0;
    $file->save();
    
    return $this->success();
  }

  /**
   *  Return statistic
   *  Media - number of user files of each media type
   *
   *  If admin_mode is enabled (and user is admin),
   *  Function returns statistics about all files
   *
   *  @param {string} media List of comma-separated media types
   *  @return {object} Media and group statistic
   */
  public function actionGetMediaStats($media = '', $group = '') {
    if (!User::isAuthenticated()) {
      return $this->success(array());
    }

    if (!User::hasAccess('admin')) {
      return $this->success(array());
    }

    $media = self::_validateMedia($media);
    $media = self::_loadMediaStats($media, $group);

    return $this->success($media);
  }

  /**
   *  Get files list
   */
  public function actionGetFiles() {
    $ret = array(
      'files' => array(),
      'page' => 1,
      'pages' => 1,
      'total' => 0,
      'rpp' => Config::get('storage.results_per_page'),
    );

    $search = self::_validateSearch();

    if (!User::isAuthenticated()) {
      return $this->success($ret);
    }

    // Nothing to return
    if (!count($search['media'])) {
      return $this->success($ret);
    }

    // where query
    $where = array();

    // User id & user group
    if ($search['group']) {
      $where[] = 'file_group = "' . Database::escape($search['group']) . '"';
    }

    // media
    $where_media = array();
    foreach ($search['media'] as $media) {
      $where_media[] = '"' . Database::escape($media) . '"';
    }

    $where[] = 'file_media IN (' . implode(',', $where_media) . ')';
    $where[] = 'file_deleted = "0"';
    $where = implode(' AND ', $where);

    $count = StorageFiles::whereRaw($where);

    if (!($count = $count->count())) {
      return $this->success($ret);
    }

    $rpp = $ret['rpp'];
    $pages = (int)($count / $rpp);
    if (($pages * $rpp) < $count) ++$pages;

    if ($search['page'] > $pages) {
      $search['page'] = $pages;
    }

    $files = StorageFiles::whereRaw($where);

    if ($search['orderby'] == 'popular') {
      $files->orderBy('file_downloads', 'desc');
    }
    else if ($search['orderby'] == 'biggest') {
      $files->orderBy('file_size', 'desc');
    }
    else if ($search['orderby'] == 'smallest') {
      $files->orderBy('file_size', 'asc');
    }
    else {
      $files->orderBy('file_id', 'desc');
    }

    $files->limit(
      $rpp,
      (($search['page'] - 1) * $rpp)
    );

    $files = $files->get();

    if (!count($files)) {
      return $this->success($ret);
    }

    $ret['page'] = $search['page'];
    $ret['pages'] = $pages;
    $ret['total'] = $count;

    foreach ($files as $file) {
      if ($file->file_deleted) {
        continue;
      }

      $ret['files'][] = $file->exportInfo();
    }

    return $this->success($ret);
  }

  /**
   *  Uplaod file statistic
   *  All information is taken from $_FILES AND $_POST
   *
   *  @return {object} Uploaded file info
   */
  public function actionUpload() {
    if (!User::isAuthenticated()) {
      return $this->error('access_denied');
    }

    if (!User::hasAccess('admin')) {
      return $this->error('access_denied');
    }

    // If file even uploaded?
    if (!isset($_FILES) || !is_array($_FILES)) {
      return $this->error('error_file_not_uploaded');
    }

    if (!isset($_FILES['upload']) || !is_array($_FILES['upload'])) {
      return $this->error('error_file_not_uploaded');
    }

    $upload = $_FILES['upload'];

    // Any errors here?
    if (isset($upload['error']) && $upload['error']) {
      // No arrays are allowed here
      if (is_array($upload['error'])) {
        return $this->error('error_file_upload_error');
      }

      $error = $upload['error'];

      if ($error == UPLOAD_ERR_NO_FILE) {
        return $this->error('error_file_not_uploaded');
      }

      if ($error == UPLOAD_ERR_INI_SIZE || $error == UPLOAD_ERR_FORM_SIZE) {
        return $this->error('error_file_is_too_big');
      }

      return $this->error('error_file_not_uploaded');
    }

    // check fields
    foreach (array('name', 'tmp_name') as $field) {
      if (isset($upload[$field]) && !is_array($upload[$field])) {
        continue;
      }

      return $this->error('error_file_upload_error');
    }

    // get file info
    if (!is_array($file_info = self::_processUploadedFile($upload))) {
      if ($file_info) {
        return $this->error($file_info);
      }

      return $this->error('error_file_upload_error');
    }
    // get file hash
    if (!($file_info['hash'] = self::_makeFileHash($file_info))) {
      return $this->error('error_file_upload_error');
    }

    // move file
    if (!($file_info['path'] = self::_moveUploadedFile($file_info))) {
      return $this->error('storage.error_file_upload_error');
    }

    // create entry
    $file = new StorageFiles;

    $file->file_group = $file_info['group'];
    $file->file_hash = $file_info['hash'];
    $file->file_name = $file_info['name'];
    $file->file_media = $file_info['media'];
    $file->file_path = $file_info['path'];
    $file->file_deleted = 0;
    $file->file_size = $file_info['size'];
    $file->file_uploaded = time();
    $file->file_downloads = 0;
    $file->file_last_download_time = 0;
    $file->file_last_download_ip = 0;
    $file->file_preview = $file_info['preview'] ? '1' : '0';
    $file->file_access = $file_info['access'];

    $file->save();

    return $this->success($file->exportInfo());
  }

  /**
   * Validate user media
   *
   *  @param {string} user_media List of comma-separated media types
   *  @return {array} List of validated media types
   */
  protected static function _validateMedia($user_media) {
    $valid_media = array(
      'image',
      'video',
      'audio',
      'document',
      'source',
      'archive',
      'other'
    );

    if (!is_string($user_media)) {
      $user_media = '';
    }

    if (!preg_match('#^[0-9a-z,]{1,100}$#', $user_media)) {
      $user_media = '';
    }

    $_user_media_valid = array();
    foreach (explode(',', $user_media) as $media) {
      if (!in_array($media, $valid_media)) {
        continue;
      }

      $_user_media_valid[] = $media;
    }

    $user_media = $_user_media_valid;

    if (!count($user_media)) {
      $user_media = $valid_media;
    }

    return $user_media;
  }

  /**
   *  Return user/global media types statistics
   *
   *  @param {array} user_media List of media types
   *  @return {array} List of media types with statistics
   */
  protected static function _loadMediaStats($user_media, $group = false) {
    $stats = array();

    if (!count($user_media) || !User::isAuthenticated()) {
      return $stats;
    }

    $where = array();

    if ($group) {
      $where[] = 'file_group = "'.Database::escape($group).'"';      
    }

    $where_media = array();
    foreach ($user_media as $media) {
      $stats[$media] = 0;
      $where_media[] = '"' . Database::escape($media) . '"';
    }

    $where[] = 'file_media IN (' . implode(',', $where_media) . ')';
    $where[] = 'file_deleted = "0"';
    $where = implode(' AND ', $where);

    $res = Database::query(
      'SELECT
        COUNT(*) as count,
        file_media
      FROM
        storage_files
      WHERE
        ' . $where . '
      GROUP BY
        file_media'
    );

    if (!$res || !is_array($res)) {
      return array();
    }

    foreach ($res as $stat) {
      $stats[$stat['file_media']] = (int)$stat['count'];
    }

    return $stats;
  }

  /**
   *  Validate and prepare uploaded file
   *
   *  @param {array} upload File info from $_FILES
   *  @return {array} File processed info
   */
  protected static function _processUploadedFile($upload) {
    $info = array();

    // file name
    $upload['name'] = str_replace(
      array("\r", "\n", "\t", "\\", ':'),
      array(' ', ' ', ' ', '', ''),
      $upload['name']
    );

    if (strlen($upload['name']) > 70) {
      return 'error_filename_too_big';
    }

    $info['name'] = $upload['name'];

    // check tmp path
    if (!file_exists($upload['tmp_name'])) {
      return 'error_file_upload_error';
    }

    $info['tmp_path'] = $upload['tmp_name'];

    // check file size
    $info['size'] = filesize($upload['tmp_name']);

    if ($info['size'] <= 0) {
      return 'error_file_is_empty';
    }

    if ($info['size'] > Config::get('storage.max_filesize')) {
      return 'error_file_is_too_big';
    }
    // file media
    $info['media'] = StorageFiles::getFileMedia($info['name']);

    // access level
    $info['access'] = 'public';

    if (isset($_POST['file_access']) && $_POST['file_access'] == 'private') {
      $info['access'] = 'private';
    }

    // group
    $info['group'] = '';
    if (isset($_POST['file_group']) && $_POST['file_group'] && preg_match('#^[0-9a-zA-Z_,-]++$#', $_POST['file_group'])) {
      $info['group'] = $_POST['file_group'];
    }

    if (!in_array($info['group'], array('storage', 'photolibrary'))) {
      return 'error_incorrect_group';
    }

    // check if file meets required media type
    // media type is specified in storage widget
    if (!isset($_POST['file_media'])) {
      return 'error_incorrect_media_type';
    }

    if (!($media = self::_validateMedia($_POST['file_media']))) {
      return 'error_incorrect_media_type';
    }

    if (!in_array($info['media'], $media)) {
      return 'error_incorrect_media_type';
    }

    // check if preview available
    $info['preview'] = StoragePreview::checkPreviewFeature(
      $info['name'],
      $info['tmp_path']
    );

    return $info;
  }

  /**
   *  Generates uniq hash for file
   *
   *  @param {array} file_info uploaded file info
   *  @return {strgin} File hash
   */
  protected static function _makeFileHash($file_info) {
    while (true) {
      if (!($hash = makeRandomString(8))) {
        return false;
      }

      $res = Database::from('storage_files');
      $res->whereAnd('file_hash', '=', $hash);
      $count = $res->count();

      if (!$count) {
        return $hash;
      }
    }
  }

  /**
   *  Generates path for uploaded file & moves it
   *
   *  @param {array} file_info uploaded file info
   *  @return {strgin} Generated file path
   */
  protected static function _moveUploadedFile($file_info) {
    $path  = '/uploads/';

    for ($i = 0; $i <= 1; ++$i) {
      $path .=  substr($file_info['hash'], $i, 1) . '/';

      if (is_dir(ROOT_PATH . $path)) {
        continue;
      }

      $oldumask = umask(0);
      mkdir(
        ROOT_PATH . $path,
        octdec(str_pad('755', 4, '0', STR_PAD_LEFT)),
        true
      );
      umask($oldumask);
    }

    $path .= $file_info['hash'];

    if (file_exists(ROOT_PATH . $path)) {
      return false;
    }

    if (!(copy($file_info['tmp_path'], ROOT_PATH . $path))) {
      return false;
    }

    return $path;
  }
  /**
   *  Validate search data
   *
   *  @return {array} Search params
   */
  protected static function _validateSearch() {
    // validate data
    $fields = array(
      'media',
      'group',
      'orderby',
      'page',
    );

    $info = array();
    foreach ($fields as $field) {
      $info[$field] = false;

      if (!isset($_POST[$field])) {
        continue;
      }

      if (!is_numeric($_POST[$field]) && !is_string($_POST[$field])) {
        continue;
      }

      $value = $_POST[$field];

      if (!preg_match('#^[0-9a-zA-Z_,-]++$#', $value)) {
        continue;
      }

      $info[$field] = $value;
    }

    // admin mode
    $info['admin_mode'] = false;
    if (isset($_POST['admin_mode']) &&
      $_POST['admin_mode'] &&
      $_POST['admin_mode'] == 'enabled' &&
      User::hasAccess('admin')
      ) {
      $info['admin_mode'] = true;
    }

    // orderby
    $orderby = array(
      'latest',
      'popular',
      'biggest',
      'smallest',
    );

    if (!in_array($info['orderby'], $orderby)) {
      $info['orderby'] = 'latest';
    }

    // media
    $info['media'] = self::_validateMedia($info['media']);

    // page
    if (preg_match('#^[0-9]{1,5}$#', $info['page'])) {
      $info['page'] = (int)$info['page'];
    }
    else {
      $info['page'] = 1;
    }

    if ($info['page'] <= 0 || $info['page'] >= 200) {
      $info['page'] = 1;
    }

    return $info;
  }
}
?>