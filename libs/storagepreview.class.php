<?php
class StoragePreview {
  /**
   *  Checks if preview is available for the file
   *
   *  @param {string} file_name File name
   *  @param {string} file_path File path
   *  @return {boolean} Preview availability
   */
  public static function checkPreviewFeature($file_name, $file_path) {
    if (StorageFiles::getFileMedia($file_name) != 'image') {
      return false;
    }

    $ext = StorageFiles::getFileExt($file_name);

    if (!in_array($ext, array('jpg', 'jpeg', 'png', 'gif'))) {
      return false;
    }

    if (!($img = self::_createImage($file_path))) {
      return false;
    }

    imageDestroy($img);

    return true;
  }

  /**
   *  Makes signed preview link
   *
   *  @param {object} file File db entry
   *  @param {array} preview_options List of preview options
   *  @return {string} Signed preview link
   */
  public static function makePreviewLink($file_hash, $preview_options = array()) {
    $options = self::_validatePreviewOptions($preview_options);

    $link = array(
      'img',
      $file_hash
    );

    foreach ($options as $option => $value) {
      if ($option == 'crop') {
        $link[] = 'crop';
        continue;
      }

      if ($option == 'width') {
        $link[] = 'w' . $value;
        continue;
      }

      if ($option == 'height') {
        $link[] = 'h' . $value;
        continue;
      }

      if ($option == 'copyright') {
        $link[] = 'copyright-' . rawurlencode($value);
        continue;
      }

      if ($option == 'align' || $option == 'valign') {
        $link[] = $value;
        continue;
      }
    }

    $link[] = self::_makeSign($file_hash, $options);

    $link = '/' . implode('/', $link) . '/';

    return $link;
  }

  /**
   *  Extract options from link
   *
   *  @param {array} options Options list
   */
  public static function parsePreviewOptions($options) {
    $extracted = array();

    if (is_string($options)) {
      $options = explode('/', $options);
    }

    foreach ($options as $option) {
      if (!is_string($option)) {
        continue;
      }

      if ($option == 'crop') {
        $extracted['crop'] = true;
        continue;
      }

      if (in_array($option, array('left', 'center', 'right'))) {
        $extracted['align'] = $option;
        continue;
      }

      if (in_array($option, array('top', 'middle', 'bottom'))) {
        $extracted['valign'] = $option;
        continue;
      }

      if (preg_match('#^w(\d{2,5})$#', $option, $data)) {
        $extracted['width'] = $data[1];
        continue;
      }

      if (preg_match('#^h(\d{2,5})$#', $option, $data)) {
        $extracted['height'] = $data[1];
        continue;
      }

      if (preg_match('#^copyright-(.{1,50})$#', $option, $data)) {
        $extracted['copyright'] = $data[1];
        continue;
      }
    }

    return self::_validatePreviewOptions($extracted);
  }

  /**
   *  Check if preview sign is correct
   *
   *  @param {object} file File db entry
   *  @param {array} preview_options List of preview options
   *  @param {string} preview_sign Preview sign
   *  @return {boolean} Preview check status
   */
  public static function checkPreviewSign($file, $preview_options, $preview_sign) {
    return self::_makeSign($file->file_hash, $preview_options) == $preview_sign;
  }

  /**
   *  Makes preview file with specified options
   *
   *  @param {object} file File db entry
   *  @param {array} options List of preview options
   *  @return {string} Preview path
   */
  public static function makePreview($file, $options) {
    $options = self::_validatePreviewOptions($options);

    if ($file->file_media != 'image') {
      return false;
    }

    if (!$file->file_preview) {
      return false;
    }

    if (!isset($options['align'])) {
      $options['align'] = 'center';
    }

    if (!isset($options['valign'])) {
      $options['valign'] = 'top';
    }

    $hash = self::_makePreviewHash($file, $options);
    if ($preview = self::_getPreviewFromCache($hash)) {
      return $preview;
    }

    if (!($preview = self::_makePreview($file, $options))) {
      return false;
    }

    if (!($preview = self::_savePreview($file, $hash, $preview))) {
      return false;
    }

    return $preview;
  }

  /**
   *  Validates preview options
   *
   *  @param {array} preview_options List of preview options
   *  @return {array} Validated preview options
   */
  protected static function _validatePreviewOptions($options) {
    $config = Config::get('storage.image_preview');
    $validated = array();

    if (isset($options['crop']) && $options['crop']) {
      $validated['crop'] = true;
    }

    if (isset($options['width']) && is_numeric($options['width'])) {
      if ($options['width'] >= $config['min_filewidth'] && $options['width'] <= $config['max_width']) {
        $validated['width'] = (int)$options['width'];
      }
    }

    if (isset($options['height']) && is_numeric($options['height'])) {
      if ($options['height'] >= $config['min_fileheight'] && $options['width'] <= $config['max_height']) {
        $validated['height'] = (int)$options['height'];
      }
    }

    if (isset($options['align']) && in_array($options['align'], array('left', 'center', 'right'))) {
      $validated['align'] = $options['align'];
    }

    if (isset($options['valign']) && in_array($options['valign'], array('top', 'middle', 'bottom'))) {
      $validated['valign'] = $options['valign'];
    }

    if (isset($options['copyright']) && preg_match('#^.{1,50}$#', $options['copyright'])) {
      $validated['copyright'] = $options['copyright'];
    }

    return $validated;
  }

  /**
   *  Creates image
   *
   *  @param {string} img_path File path
   *  @param {boolean} convert2true_color convert image to true color
   *  @return {object} Image resource (or false)
   */
  protected static function _createImage($img_path, $convert2true_color = false) {
    $config = Config::get('storage.image_preview');

    if (!file_exists($img_path)) {
      return false;
    }

    if (filesize($img_path) >= $config['max_filesize']) {
      return false;
    }

    if (!($info = getimagesize($img_path))) {
      return false;
    }

    $w = $info[0];
    $h = $info[1];
    $type = $info[2];

    if ($w >= $config['max_filewidth']) return false;
    if ($w <= $config['min_filewidth']) return false;
    if ($h >= $config['max_fileheight']) return false;
    if ($h <= $config['min_fileheight']) return false;

    $img = false;
    switch($type) {
      case 1: {
        $img = imagecreatefromgif($img_path);
        break;
      }
      
      case 2: {
        $img = imagecreatefromjpeg($img_path);
        break;
      }
      
      case 3: {
        $img = imagecreatefrompng($img_path);

        imageAlphaBlending($img, true);
        imageSaveAlpha($img, true);

        break;
      }
    }

    if(!$img) return false;

    $w = imagesx($img);
    $y = imagesy($img);

    if ($w >= $config['max_filewidth']) return false;
    if ($w <= $config['min_filewidth']) return false;
    if ($h >= $config['max_fileheight']) return false;
    if ($h <= $config['min_fileheight']) return false;

    if ($convert2true_color && !imageIsTrueColor($img)) {
      $tmp = $img;
      
      $img = imagecreatetruecolor($w, $h);
      imagesavealpha($img, true);
      imageAlphaBlending($img, true);
      imageCopyResampled($img, $tmp, 0, 0, 0, 0, $w, $h, $w, $h);
      imageDestroy($tmp);
    }

    return $img;
  }

  /**
   *  Creates sign for preview link
   *
   *  @param {object} file StorageFiles object
   *  @param {array} options Preview options
   *  @return {string} Preview sign
   */
  protected static function _makeSign($file_hash, $options) {
    $sign = array(Config::get('storage.salt'));
    $sign[] = $file_hash;

    ksort($options);

    foreach ($options as $key => $value) {
      $sign[] = $key . ':' . $value;
    }

    $sign[] = $file_hash;
    $sign[] = Config::get('storage.salt');

    $sign = substr(md5(implode(';', $sign)), 0, 6);

    return $sign;
  }

  /**
   *  Create hash for set [file, options]
   *
   *  @param {object} file StorageFiles object
   *  @param {array} options Preview options
   *  @return {string} Preview hash
   */
  protected static function _makePreviewHash($file, $options) {
    $list = array(
      $file->file_hash,
      $file->file_id,
    );

    ksort($options);

    foreach ($options as $key => $value) {
      $list[] = $key . ':' . $value;
    }

    return substr(md5(implode(';', $list)), 0, 14);
  }

  /**
   *  Check if preview is in cache and return path to it
   *
   *  @param {string} hash Preview hash
   *  @return {string} Preview path
   */
  protected static function _getPreviewFromCache($hash) {
    $res = Database::from('storage_previews');
    $res->where('preview_hash', '=', $hash);
    $res = $res->get();

    if (count($res) != 1) {
      return false;
    }

    $res = $res[0];

    if (!file_exists(ROOT_PATH . $res->preview_path)) {
      $res->delete();
      return false;
    }

    if ($res->preview_last_access > (time() - 60)) {
      $res->preview_last_access = time();
      $res->preview_downloads++;
      $res->save();
    }

    return $res->preview_path;
  }

  /**
   *  Creates preview from file with specified options
   *
   *  @param {object} file StorageFiles object
   *  @param {array} options Preview options
   *  @return {resource} Preview image
   */
  protected static function _makePreview($file, $options) {
    $config = Config::get('storage.image_preview');

    $image_source = self::_createImage(
      ROOT_PATH . $file->file_path,
      true
    );

    if (!$image_source) {
      return false;
    }

    $source_w = imagesx($image_source);
    $source_h = imagesy($image_source);
    $source_r = $source_w / $source_h;

    if (!isset($options['width']) && !isset($options['height'])) {
      $options['width'] = $source_w;
      $options['height'] = $source_h;
    }

    if (!isset($options['width'])) {
      $options['width'] = round($options['height'] * $source_r);
    }

    if (!isset($options['height'])) {
      $options['height'] = round($options['width'] / $source_r);
    }

    if ($options['width'] > $source_w) {
      $options['width'] = $source_w;
      $options['height'] = round($source_w / $source_r);
    }

    if ($options['height'] > $source_h) {
      $options['height'] = $source_h;
      $options['width'] = round($source_h * $source_r);
    }

    if ($options['width'] > $config['max_width']) {
      $options['width'] = $config['max_width'];
      $options['height'] = round($options['width'] / $source_r);
    }

    if ($options['height'] > $config['max_height']) {
      $options['height'] = $config['max_height'];
      $options['width'] = round($options['height'] * $source_r);
    }

    if (isset($options['crop']) && $options['crop']) {
      $image_new = self::_cropImage($image_source, $options);
    }
    else {
      $image_new = self::_resizeImage($image_source, $options);
    }

    if (isset($options['copyright']) && $options['copyright']) {
      self::_addTextToImage(
        $image_new,
        array(
          'valign'  => 'bottom',
          'align'   => 'right',
          'text'    => $options['copyright'],
          'padding' => array(4, 8),
          'margin'  => array(5, 5),
          'size'    => 12,
        )
      );
    }

    if (!$image_new) {
      return false;
    }

    imageDestroy($image_source);

    return $image_new;
  }

  /**
   *  Crops preview image
   *
   *  @param {resource} image_source Image resource
   *  @param {array} options Resize options
   *  @return {resource} Cropped image
   */
  protected static function _cropImage($image_source, $options) {
    $image_new = imagecreatetruecolor($options['width'], $options['height']);
    imageAlphaBlending($image_new, true);
    imageSaveAlpha($image_new, true);

    $color = imageColorAllocateAlpha($image_new, 255, 255, 255, 127);
    imagefill($image_new, 0, 0, $color);

    $source_w = imagesx($image_source);
    $source_h = imagesy($image_source);

    $scale = min(
      $source_w / $options['width'],
      $source_h / $options['height']
    );

    $scaled_w = min($source_w, round($options['width'] * $scale));
    $scaled_h = min($source_h, round($options['height'] * $scale));

    $image_x = 0;
    $image_y = 0;

    if ($source_w > $scaled_w) {
      if ($options['align'] == 'center') {
        $image_x = (int)(($source_w - $scaled_w) / 2);
      }

      if ($options['align'] == 'right') {
        $image_x = $source_w - $scaled_w;
      }

      if ($options['align'] == 'left') {
        $image_x = 0;
      }
    }

    if ($source_h > $scaled_h) {
      if ($options['valign'] == 'middle') {
        $image_y = (int)(($source_h - $scaled_h) / 2);
      }

      if ($options['valign'] == 'bottom') {
        $image_y = $source_h - $scaled_h;
      }

      if ($options['valign'] == 'top') {
        $image_y = 0;
      }
    }

    imagecopyresampled(
      $image_new, $image_source,
      0, 0,
      $image_x, $image_y,
      $options['width'], $options['height'],
      $scaled_w, $scaled_h
    );

    return $image_new;
  }

  /**
   *  Resize preview image
   */
  protected static function _resizeImage($image_source, $options) {
    $image_new = imagecreatetruecolor($options['width'], $options['height']);
    imageAlphaBlending($image_new, true);
    imageSaveAlpha($image_new, true);

    $color = imageColorAllocateAlpha($image_new, 255, 255, 255, 127);
    imagefill($image_new, 0, 0, $color);

    $source_w = imagesx($image_source);
    $source_h = imagesy($image_source);

    $scale = max(
      $source_w / $options['width'],
      $source_h / $options['height']
    );

    $scaled_w = round($source_w / $scale);
    $scaled_h = round($source_h / $scale);

    $offset_x = 0;
    $offset_y = 0;

    if ($options['width'] > $scaled_w) {
      if ($options['align'] == 'center') {
        $offset_x = (int)(($options['width'] - $scaled_w) / 2);
      }

      if ($options['align'] == 'right') {
        $offset_x = $options['width'] - $scaled_w;
      }

      if ($options['align'] == 'left') {
        $offset_x = 0;
      }
    }

    if ($options['height'] > $scaled_h) {
      if ($options['valign'] == 'middle') {
        $offset_y = (int)(($options['height'] - $scaled_h) / 2);
      }

      if ($options['valign'] == 'bottom') {
        $offset_y = $options['height'] - $scaled_h;
      }

      if ($options['valign'] == 'top') {
        $offset_y = 0;
      }
    }

    imagecopyresampled(
      $image_new, $image_source,
      $offset_x, $offset_y,
      0, 0,
      $scaled_w, $scaled_h,
      $source_w, $source_h
    );

    return $image_new;
  }

  /**
   *  Add text to image
   *
   *  @param {resource} Image resource
   *  @param {object} config Render params
   */
  protected static function _addTextToImage($image, $config = array()) {
    if (!is_array($config)) return false;

    $config = array(
      'valign'  => isset($config['valign']) ? $config['valign'] : 'middle',
      'align'   => isset($config['align']) ? $config['align'] : 'center',
      'text'    => isset($config['text']) ? $config['text'] : '',
      'margin'  => isset($config['margin']) ? $config['margin'] : array(0, 0),
      'padding' => isset($config['padding']) ? $config['padding'] : array(0, 0),
      'size'    => isset($config['size']) ? $config['size'] : 20,
      'file'    => isset($config['file']) ? $config['file'] : 'opensans-bold.ttf',
    );

    if (!in_array($config['valign'], array('top', 'middle', 'bottom'))) {
      return false;
    }

    if (!in_array($config['align'], array('right', 'center', 'left'))) {
      return false;
    }

    if (!file_exists($font_path = ROOT_PATH . '/system/fonts/' . $config['file'])) {
      return false;
    }

    if (!($bbox = imagettfbbox($config['size'], 0, $font_path, $config['text']))) {
      return false;
    }

    $text_width    = abs($bbox[2] - $bbox[0]);
    $text_height   = abs($bbox[1] - $bbox[7]);
    $text_baseline = abs($bbox[5] < 0 ? $bbox[5] : 0);

    if ($text_width <= 0 || $text_height <= 0) {
      return false;
    }

    $image_width  = imagesx($image);
    $image_height = imagesy($image);

    if ($text_width + $config['margin'][0] + $config['padding'][0] > $image_width) return false;
    if ($text_height + $config['margin'][1] + $config['padding'][1] > $image_height) return false;

    $text_color = imageColorAllocateAlpha($image, 255, 255, 255, 70);
    $back_color = imageColorAllocateAlpha($image, 0, 0, 0, 100);

    $x = $config['margin'][0] + $config['padding'][0];
    $y = $config['margin'][1] + $config['padding'][1];

    if ($config['valign'] == "middle") $y = ($image_height / 2) - ($text_height / 2);
    if ($config['valign'] == "bottom") $y = $image_height - $text_height - $config['margin'][0] - ($config['padding'][0]);
    if ($config['align'] == "center") $x = ($image_width / 2) - ($text_width / 2);
    if ($config['align'] == "right") $x = $image_width - $text_width - $config['margin'][1] - ($config['padding'][1]);

    $x = (int)$x;
    $y = (int)$y;
    
    imageFilledRectangle(
      $image,
      $x - $config['padding'][1], $y - $config['padding'][0],
      $x + $text_width + $config['padding'][1], $y + $text_height + $config['padding'][0],
      $back_color
    );

    imagettftext(
      $image,
      $config['size'], 0,
      $x, $y + $text_baseline,
      $text_color,
      $font_path,
      $config['text']
    );
  }

  /**
   *  Save preview into file & cache it into DB
   *
   *  @param {string} hash Preview hash
   *  @param {resource} image Image resource
   *  @return {string} Preview path
   */
  protected static function _savePreview($file, $hash, $image) {
    $path = '/tmp/previews/';

    for ($i = 0; $i < 2; ++$i) {
      $path .= substr($hash, $i *2, 2);
      $path .= '/';

      if (is_dir(ROOT_PATH . $path)) {
        continue;
      }

      $oldumask = umask(0);
      mkdir(
        ROOT_PATH . $path,
        octdec(str_pad('777', 4, '0', STR_PAD_LEFT)),
        true
      );
      umask($oldumask);
    }

    $path .= $hash;

    if (preg_match('#\.png$#', $file->file_name)) {
      imagepng($image, ROOT_PATH . $path, 8);
    }
    else {
      imagejpeg($image, ROOT_PATH . $path, 85);
    }

    if (!file_exists(ROOT_PATH . $path)) {
      return false;
    }

    self::_runGC();

    $res = new Database('storage_previews');
    $res->preview_hash = $hash;
    $res->preview_path = $path;
    $res->preview_last_access = time();
    $res->preview_downloads = 0;
    $res->save();

    return $path;
  }

  /**
   *  Remove outdated previews
   */
  protected static function _runGC() {
    $res = Database::from('storage_previews');

    $res->orderBy('preview_downloads', 'DESC');
    $res->orderBy('preview_last_access', 'DESC');
    $res->limit(100, 900);

    $res = $res->get();

    foreach ($res as $preview) {
      if (file_exists(ROOT_PATH . $preview->preview_path)) {
        unlink(ROOT_PATH . $preview->preview_path);
      }

      $preview->delete();
    }
  }
}   
?>