<?php
class ApiTags_Controller extends ApiController {
  public function actionIndex() {
    return $this->error('access_denied');
  }

  /**
   *  Return feed tags
   *  
   *  @param {string} groups Comma-separated groups
   *  @return {object} Tags list
   */
  public function actionGetTags($group) {
    if (!preg_match('#^[0-9a-zA-Z_,.-]++$#uis', $group)) {
      return $this->error('incorrect_tag_group');
    }

    if (!is_array($tags = $this->_getTags($group))) {
      return $this->error('incorrect_tag_group');
    };

    return $this->success($tags);
  }


  /**
   *  Return tags in group
   *
   *  @param {string} group Tags group 
   *  @return {string} Tags
   */
  protected function _getTags($group) {
    if ($group == 'feed') {
      return Tags::getTags('feed');
    }

    if ($group == 'photostream') {
      return Tags::getTags("photostream");
    }

    if (in_array($group, array('dev', 'travel', 'blog'))) {
      return Tags::getTags("pages_{$group}_visible");
    }

    return false;
  }
}