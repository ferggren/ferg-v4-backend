<?php
class MediaPages extends Database {
  protected static $table = 'media_pages';
  protected static $primary_key = 'page_id';
  protected static $timestamps = true;

  public function export($export_html = false) {
    $ret = array(
      'id'        => (int)$this->page_id,
      'type'      => $this->page_type,
      'visible'   => !!$this->page_visible,
      'versions'  => array(),
      'date'      => $this->page_date,
      'timestamp' => (int)$this->page_date_timestamp,
      'tags'      => $this->page_tags,
      'title'     => '',
      'desc'      => '',
      'html'      => '',
      'preview'   => array(
        'big'   => '',
        'small' => '',
      ),
    );

    $entry_lang = false;

    if ($this->page_versions) {
      $ret['versions'] = explode(',', $this->page_versions);

      if (in_array(Lang::getLang(), $ret['versions'])) {
        $entry_lang = Lang::getLang();
      }
      else {
        $entry_lang = $ret['versions'][0];
      }
    }

    if ($this->page_photo_id) {
      $ret['preview'] = $this->_makePreview($this->page_photo_id);
    }

    if ($entry_lang) {
      $entry = Database::from(array(
        'media_entries_content ec',
        'media_entries e',
      ));

      $entry->whereAnd('e.entry_key', '=', 'page_' . $this->page_id);
      $entry->whereAnd('ec.entry_id', '=', 'e.entry_id', false);
      $entry->whereAnd('ec.entry_lang', '=', $entry_lang);
      $entry->whereAnd('ec.entry_visible', '=', '1');
      $entry->limit(1);

      if (count($entry = $entry->get())) {
        $ret['title'] = $entry[0]->entry_title;
        $ret['desc']  = $entry[0]->entry_desc;

        if ($export_html) {
          $ret['html'] = $entry[0]->entry_text_html;
        }
      }
    }

    return $ret;
  }

  protected function _makePreview($photo_id) {
    $ret = array(
      'big'   => '',
      'small' => '',
    );

    if (!$photo_id) {
      return $ret;
    }

    if (!($photo = PhotoLibrary::find($photo_id))) {
      return $ret;
    }

    if ($photo->photo_deleted) {
      $this->page_photo_id = 0;
      $this->save();

      return $ret;
    }

    return array(
      'big' => StoragePreview::makePreviewLink(
        $photo->file_hash,array(
          'crop'      => true,
          'width'     => 1680,
          'height'    => 500,
          'align'     => 'center',
          'valign'    => 'middle',
          'copyright' => 'ferg.in',
        )
      ),

      'photo' => StoragePreview::makePreviewLink(
        $photo->file_hash,array(
          'width'     => 1680,
          'copyright' => 'ferg.in',
        )
      ),

      'small' => StoragePreview::makePreviewLink(
        $photo->file_hash,array(
          'crop'   => true,
          'width'  => 700,
          'height' => 200,
          'align'  => 'center',
          'valign' => 'middle',
        )
      ),
    );
  }
}