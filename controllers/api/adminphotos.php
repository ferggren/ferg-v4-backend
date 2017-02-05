<?php
class ApiAdminPhotos_Controller extends ApiController {
  public static $user_auth = true;
  public static $user_access_level = 'admin';

  /**
   *  Access error
   */
  public function actionIndex() {
    return $this->error('access_denied');
  }

  /**
   *  Return photo info
   *
   *  @param {int} photo_id Photo id
   *  @return {object} Photo info
   */
  public function actionGetPhotoUrl($photo_id) {
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

    if (!($file = StorageFiles::find($photo->file_id))) {
      return $this->error('invalid_photo_id');
    }

    if ($file->file_deleted) {
      return $this->error('invalid_photo_id');
    }

    return $this->success($file->exportInfo());
  }
}