<?php
class PhotoLibrary extends Database {
  protected static $table = 'photolibrary';
  protected static $primary_key = 'photo_id';
  protected static $timestamps = false;

  public function export($full_info = false) {
    $export = array(
      'id' => (int)$this->photo_id,
      'gps' => $this->photo_gps ? $this->photo_gps : "",
      'taken' => $this->photo_taken ? $this->photo_taken : "",
      'timestamp' => (int)$this->photo_taken_timestamp,
      'width' => 0,
      'height' => 0,
      
      'tags' => array(
        'iso'           => $this->photo_iso ? $this->photo_iso : "",
        'shutter_speed' => $this->photo_shutter_speed ? $this->photo_shutter_speed : "",
        'aperture'      => $this->photo_aperture ? $this->photo_aperture : "",
        'lens'          => $this->photo_lens ? $this->photo_lens : "",
        'camera'        => $this->photo_camera ? $this->photo_camera : "",
        'category'      => $this->photo_category ? $this->photo_category : "",
        'fl'            => $this->photo_fl ? $this->photo_fl : "",
        'efl'           => $this->photo_efl ? $this->photo_efl : "",
        'location'      => $this->photo_location ? $this->photo_location : "",
      ),
    );

    if ($full_info) {
      $export['added']         = (int)$this->photo_added;
      $export['title_ru']      = $this->photo_title_ru ? $this->photo_title_ru : "";
      $export['title_en']      = $this->photo_title_en ? $this->photo_title_en : "";
      $export['collection_id'] = (int)$this->photo_collection_id;
      $export['photostream']   = !!$this->photo_show_in_photostream;
    }

    if (Lang::getLang() == 'ru') {
      $export['title'] = $this->photo_title_ru ? $this->photo_title_ru : $this->photo_title_en;
    }
    else {
      $export['title'] = $this->photo_title_en ? $this->photo_title_en : $this->photo_title_ru;
    }

    $export['ratio'] = 1;

    if (preg_match('#^(\d{1,5})x(\d{1,5})$#', $this->photo_size, $data)) {
      $export['width'] = (int)$data[1];
      $export['height'] = (int)$data[2];
      $export['ratio'] = round($export['width'] / $export['height'], 2);
    }

    $w = 500; $h = 250;

    if ($export['ratio'] <= 1.0) $w = 300; $h = 300;
    if ($export['ratio'] > 1.5) $w = 700; $h = 250;
    if ($export['ratio'] > 2.0) $w = 900; $h = 250;

    $export['preview'] = StoragePreview::makePreviewLink(
      $this->file_hash, array(
        'crop'   => true,
        'width'  => $w,
        'height' => $h,
        'align'  => 'center',
        'valign' => 'middle',
      )
    );

    $export['photo_big'] = StoragePreview::makePreviewLink(
      $this->file_hash, array(
        'crop'      => false,
        'width'     => 1680,
        'copyright' => 'ferg.in',
      )
    );

    $export['photo_small'] = StoragePreview::makePreviewLink(
      $this->file_hash, array(
        'crop'  => false,
        'height' => 200,
      )
    );

    $export['photo_tiny'] = StoragePreview::makePreviewLink(
      $this->file_hash, array(
        'crop'   => true,
        'height' => 40,
        'width'  => 40,
        'align'  => 'center',
        'valign' => 'middle',
      )
    );

    return $export;
  }
}
?>