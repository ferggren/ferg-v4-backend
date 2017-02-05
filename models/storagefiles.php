<?php
class StorageFiles extends Database {
  protected static $table = 'storage_files';
  protected static $primary_key = 'file_id';
  protected static $timestamps = false;

  /**
   *  Exports file common info
   *
   *  @return {array} File info
   */
  public function exportInfo() {
    $info = array(
      'id' => (int)$this->file_id,
      'name' => $this->file_name,
      'media' => $this->file_media,
      'uploaded' => (int)$this->file_uploaded,
      'downloads' => (int)$this->file_downloads,
      'size' => (int)$this->file_size,
      'preview' => !!$this->file_preview,
      'hash' => $this->file_hash,
      'group' => false,
      'link_download' => $this->getDownloadLink(),
    );

    if ($this->group_id) {
      $info['group'] = self::__getGroupName(
        $this->group_id
      );
    }

    if ($this->file_preview) {
      $info['link_preview'] = $this->getPreviewLink(
        array(
          'crop' => true,
          'width' => 100,
          'height' => 100,
          'align' => 'center',
          'valign' => 'middle',
        )
      );

      if (!$info['link_preview']) {
        $info['link_preview'] = false;
        $info['file_preview'] = false;
      }
    }

    return $info;
  }

  /**
   *  Return download link
   *
   *  @return {string} Download link
   */
  public function getDownloadLink() {
    if ($this->file_deleted) {
      return false;
    }

    $link = self::__makeSiteAddress();
    $link .= '/dl/';
    $link .= $this->file_hash;

    return $link;
  }

  /**
   *  Return preview link
   *
   *  @return {string} Preview link
   */
  public function getPreviewLink($options = array()) {
    if ($this->file_deleted) {
      return false;
    }

    if (!($link = StoragePreview::makePreviewLink($this->file_hash, $options))) {
      return false;
    }

    $link = self::__makeSiteAddress() . $link;

    return $link;
  }

  /**
   *  Check if user has access
   *
   *  @param {string} user_id User id (default = current user)
   *  @return {boolean} Access status
   */
  public function userHasAccess($user_id = false) {
    if ($this->file_deleted) {
      return false;
    }

    if (!is_numeric($user_id)) {
      if (User::isAuthenticated()) {
        $user_id = User::get_user_id();
      }
    }

    if ($this->file_access == 'public') {
      return true;
    }

    return $user_id == $this->user_id;
  }

  /**
   *  Update downloads counter
   */
  public function updateDownloads() {
    if ($this->file_deleted) {
      return false;
    }

    if (User::isAuthenticated() && User::hasAccess('admin')) {
      return false;
    }

    if (!($ip = Session::getSessionIp())) {
      return false;
    }

    $ip = ip2decimal($ip);

    if ($this->file_last_download_ip == $ip) {
      return false;
    }

    $this->file_last_download_ip = $ip;
    $this->file_last_download_time = time();
    $this->file_downloads = ++$this->file_downloads;
    $this->save();

    $res = new Database('storage_history');
    $res->file_id = $this->file_id;
    $res->user_ip = $ip;
    $res->access_time = time();
    $res->save();

    return true;
  }

  /**
   *  Return file extension
   *
   *  @param {string} file_name File name
   *  @return {string} File extension
   */
  public static function getFileExt($file_name) {
    $file_name = strtolower($file_name);

    if (!preg_match('#\.([0-9a-z_-]{1,6})$#', $file_name, $data)) {
      return '';
    }

    return strtolower($data[1]);
  }

  /**
   *  Return file media type (by file extension)
   *
   *  @param {string} file_name File name
   *  @return {string} File media
   */
  public static function getFileMedia($file_name) {
    if (!($ext = self::getFileExt($file_name))) {
      return 'other';
    }

    $media_groups = array(
      'image' => array(
        'gif', 'png', 'jpg', 'jpeg', 'psd', 'bmp', 'tiff',
        'dng', 'raw',
      ),

      'video' => array(
        'flv', 'mkv', 'webm', 'vob', 'ogv', 'mov', 'qt', 'avi',
        'mp4', 'm4p', 'm4v', 'mpg', 'mp2', 'mpeg', 'mpe', 'mpv',
        'mpeg', 'm2v', '3gp',
      ),

      'audio' => array(
        'aac', 'aiff', 'amr', 'aa', 'aax', 'act', 'ape',
        'au', 'dvf', 'flac', 'm4a', 'mmf', 'mp3', 'mpc',
        'msf', 'ogg', 'oga', 'ra', 'rm', 'wav', 'wma',
        'aif', 'mid',
      ),

      'document' => array(
        'doc', 'docx', 'msg', 'odt', 'rtf', 'tex', 'txt',
        'wpd', 'wps', 'pps', 'ppt', 'pptx', 'ai', 'sdg',
        'pdf', 'indd', 'xlr', 'xls', 'xlsx', 'txt', 'dwg',
        'dxf',
      ),

      'source' => array(
        'htm', 'html', 'xml', 'sql', 'js', 'jsp', 'c',
        'class', 'cpp', 'cs', 'h', 'java', 'lua', 'm',
        'pl', 'py', 'sh', 'sln', 'swift', 'vb', 'src',
        'cfg', 'perl', 'r', 'rb', 's', 'asm', 'asp',
        'css', 'scss', 'sass', 'inc', 'ini', 'json',
        'c++', 'cmake', 'rake', 'pyt', 'rbw', 'scala',
        'php',
      ),

      'archive' => array(
        'iso', 'tar', 'bz2', 'gz', '7z', 's7z', 'apk', 'arc',
        'cab', 'dmg', 'rar', 'sfx', 'zip', 'zipx', 'rpm',
        'pkg', 'deb', 
      ),
    );

    foreach ($media_groups as $group => $extensions) {
      if (in_array($ext, $extensions)) {
        return $group;
      }
    }

    return 'other';
  }

  /**
   *  Return group name
   *
   *  @param {number} group_id Group id
   *  @return {string} Group name
   */
  protected static function __getGroupName($group_id) {
    static $groups = array();

    if (!$group_id) {
      return false;
    }

    if (isset($groups[$group_id])) {
      return $groups[$group_id];
    }

    $group = Database::from('storage_groups');
    $group->whereAnd('group_id', '=', $group_id);

    $group = $group->get();

    if (!is_array($group) || count($group) != 1) {
      return $groups[$group_id] = false;
    }

    return $groups[$group_id] = $group[0]->group_name;
  }

  /**
   *  Return full site address (with proto & port)
   *
   *  @return {string} Site address
   */
  protected static function __makeSiteAddress() {
    static $link = false;

    if ($link !== false) {
      return $link;
    }

    $link  = isSecureConnection() ? 'https' : 'http';
    $link .= '://' . Config::get('storage.domain');

    $port = 80;

    if (isset($_SERVER['SERVER_PORT']) && preg_match('#^\d{1,5}$#', $_SERVER['SERVER_PORT'])) {
      $port = (int)$_SERVER['SERVER_PORT'];
    }

    if (isset($_SERVER['HTTP_HOST']) && preg_match('#:(\d{1,5})$#', $_SERVER['HTTP_HOST'], $data)) {
      $port = (int)$data[1];
    }

    if (!in_array($port, array(80, 443))) {
      $link .= ':' . $port;
    }

    return $link;
  }
}