<?php
class Storage_Controller extends BaseController {
  public function actionIndex() {
    header('Location: /');
    exit;
  }

  /**
   *  Disable default headers
   */
  protected static function sendDefaultHeaders() {
    return;
  }

  /**
   *  Download file
   *
   *  @param {string} file_hash File hash
   */
  public function actionDownload($file_hash) {
    if (self::_bruteforceIsFound()) {
      return self::_errorRedirect();
    }

    if (!($file = self::_getFile($file_hash))) {
      self::_bruteforceIncrease();
      return self::_errorRedirect();
    }

    if (!User::hasAccess('admin') && !$file->userHasAccess()) {
      self::_bruteforceIncrease();
      return self::_errorRedirect();
    }

    if (!file_exists(ROOT_PATH . $file->file_path)) {
      return self::_errorRedirect();
    }

    // if modified - ok
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE']) {
      self::_enableHTTPCaching();
      header("HTTP/1.0 304 Not Modified");
      exit;
    }

    // downloads
    $file->updateDownloads();

    // file info
    $filename = htmlspecialchars_decode(str_replace(
      array("\n", "\t", "\r", "'", '"'),
      array('', '', '', '', ''),
      $file->file_name
    ));

    $ext = StorageFiles::getFileExt($filename);
    $content_type = 'application/' . ($ext ? $ext : 'plain');

    if ($file->file_media == 'image' && in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'bmp'))) {
      $content_type = 'image/jpeg';
    }

    if ($file->file_media == 'source') {
      $content_type = 'text/' . ($ext ? $ext : 'plain');
    }

    self::_enableHTTPCaching();

    header('Content-Length: ' . filesize(ROOT_PATH . $file->file_path));
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');

    header('X-Accel-Redirect: ' . $file->file_path);

    exit;
  }

  /**
   *  Show file preview
   */
  public function actionPreview() {
    $options = func_get_args();

    if (!is_array($options) || count($options) < 2) {
      return self::_errorRedirect();
    }

    $file_hash = array_shift($options);
    $file_sign = array_pop($options);

    if (!preg_match('#^[0-9a-zA-Z_-]{8,10}$#', $file_hash)) {
      return self::_errorRedirect();
    }

    if (!preg_match('#^[0-9a-zA-Z_-]{6,10}$#', $file_sign)) {
      return self::_errorRedirect();
    }

    if (self::_bruteforceIsFound()) {
      return self::_errorRedirect();
    }

    if (!($file = self::_getFile($file_hash))) {
      self::_bruteforceIncrease();
      return self::_errorRedirect();
    }

    $options = StoragePreview::parsePreviewOptions($options);

    if (!StoragePreview::checkPreviewSign($file, $options, $file_sign)) {
      return self::_errorRedirect();
    }

    // if modified - ok
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE']) {
      self::_enableHTTPCaching();
      header('HTTP/1.0 304 Not Modified');
      exit;
    }

    $preview = StoragePreview::makePreview($file, $options);
    $file->updateDownloads();

    if (!$preview) {
      return self::_errorRedirect();
    }

    self::_enableHTTPCaching();

    $type = preg_match('#\.png$#', $file->file_name) ? 'png' : 'jpg';

    // file redirect
    header('Content-type: image/' . ($type == 'png' ? 'png' : 'jpeg'));
    header('Content-Length: ' . filesize(ROOT_PATH . $preview));
    header('Content-Disposition: filename="preview.'.$type.'"');
    header('X-Accel-Redirect: ' . $preview);

    exit;
  }

  /**
   *  Redirect when error happens
   */
  protected static function _errorRedirect() {
    self::_disableHTTPCaching();
    header('Location: /');
    exit;
  }

  /**
   *  Return file object
   *
   *  @param {string} file_hash File hash
   *  @return {object} File object
   */
  protected static function _getFile($file_hash) {
    if (!is_string($file_hash) || !preg_match('#^[0-9a-zA-Z_-]{8,10}$#', $file_hash)) {
      return false;
    }

    $file = StorageFiles::where('file_hash', '=', $file_hash);
    $file = $file->get();

    if (count($file) != 1) {
      return false;
    }

    $file = $file[0];

    if ($file->file_deleted) {
      return false;
    }

    return $file;
  }

  /**
   *  Disable browser caching
   */
  protected static function _disableHTTPCaching() {
    disableBrowserCaching();
  }

  /**
   *  Enable browser caching
   */
  protected static function _enableHTTPCaching() {
    header('ETag: ""');
    header('Last-Modified: '.gmdate('D, d M Y H:i:s', time()) . ' GMT');
    header('Expires: '.gmdate('D, d M Y H:i:s', time() + (60 * 60 * 24 * 7)) . ' GMT');
    header('Cache-Control: max-age=259200, public');
  }

  /**
   *  Check if user tries to brute force files id's
   *
   *  @return {boolean} Bruteforce check status
   */
  protected static function _bruteforceIsFound() {
    if (!($ip = Session::getSessionIp())) {
      return false;
    }

    $ip = ip2decimal($ip);

    Database::query(
      'DELETE FROM
        storage_bruteforce_protection
      WHERE
        attempt_time < ' . (time() - 14600)
    );

    $count = Database::from('storage_bruteforce_protection');
    $count->where('attempt_ip', '=', $ip);

    return $count->count() > 10;
  }

  /**
   *  Increase brute force attempts counter
   */
  protected static function _bruteforceIncrease() {
    if (!($ip = Session::getSessionIp())) {
      return false;
    }

    $ip = ip2decimal($ip);

    $res = new Database('storage_bruteforce_protection');
    $res->attempt_ip = $ip;
    $res->attempt_time = time();
    $res->save();
  }
}