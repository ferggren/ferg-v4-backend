<?php
class ApiFeed_Controller extends ApiController {
  public function actionIndex() {
    return $this->error('access_denied');
  }
  
  /**
   *  Return feed
   *  
   *  @param {int} page Page offset
   *  @param {string} tag Search by tag
   *  @return {object} Feed
   */
  public function actionGetFeed($page = 1, $tag = '') {
    $ret = array(
      'page'  => 1,
      'pages' => 1,
      'list'  => array()
    );

    if (!preg_match('#^[0-9a-zA-ZАаБбВвГгДдЕеЁёЖжЗзИиЙйКкЛлМмНнОоПпРрСсТтУуФфХхЦцЧчШшЩщЪъЫыЬьЭэЮюЯя?.,?!\s:/_-]{0,50}$#ui', $tag)) {
      return $this->error('invalid_tag');
    }

    $where = array();

    if ($tag) {
      if (!($feed = Tags::getTagRelations("feed", $tag))) {
        return $this->success($ret);
      }

      if (!count($feed)) {
        return $this->success($ret);
      }

      $where[] = 'feed_id IN (' . implode(',', $feed) . ')';
    }

    $res = Feed::whereRaw(implode(' AND ', $where));
    $res->orderBy('feed_order', 'DESC');

    if (!($count = $res->count())) {
      return $this->success($ret);
    }

    $rpp = 16;
    $ret['page'] = is_numeric($page) ? (int)$page : 1;
    $ret['pages'] = (int)($count / $rpp);
    if (($ret['pages'] * $rpp) < $count) ++$ret['pages'];
    if ($ret['page'] > $ret['pages']) $ret['page'] = $ret['pages'];

    $res->limit($rpp, ($ret['page'] - 1) * $rpp);

    foreach ($res->get() as $row) {
      $url  = '/' . Lang::getLang() . '/';
      $url .= $row->feed_type . '/';
      $url .= $row->feed_target_id;

      $ret['list'][] = array(
        'preview' => $row->feed_preview,
        'ratio'   => (double)$row->feed_ratio,
        'title'   => (Lang::getLang() == 'ru' ? $row->feed_title_ru : $row->feed_title_en),
        'desc'    => (Lang::getLang() == 'ru' ? $row->feed_desc_ru : $row->feed_desc_en),
        'type'    => $row->feed_type,
        'url'     => $url,
        'date'    => (int)$row->feed_timestamp,
      );
    }

    return $this->success($ret);
  }
}