<?php
class ApiUser_Controller extends ApiController {
  public function actionIndex() {
    return $this->error('access_denied');
  }

  public function actionGetInfo() {
    if (!User::isAuthenticated()) {
      return $this->success(array());
    }

    return $this->success(User::getUser()->export(true));
  }

  public function actionLogout() {
    if (!User::isAuthenticated()) {
      return $this->success();
    }

    session::logout();
    return $this->success();
  }
}
?>