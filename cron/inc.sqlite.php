<?php
// .make.(acis|spec).php 共通設定ファイル

require_once 'inc.setup.php';

if ($dbpath && !file_exists($dbpath)) mkdir($dbpath, 0705, true);

$maindb = 'acis.db';
$subdb  = 'spec.db';
$udflag = 'update';

function _regexp($pattern, $target, $opt = '') {
  if ($opt) {
    if (strpos($opt, 'k') !== false) {
      $pattern = str_replace(array('（','）','［','］','ー'), array('\(','\)','\[','\]','\-'), $pattern);
    }
    $pattern = _strconv($pattern, $opt);
    return preg_match("'$pattern'", _strconv($target));
  } else {
    return preg_match("'$pattern'", $target);
  }
}

function _re_replace($pattern, $target, $replacement) {
  if (is_null($target)) $target = '';
  return preg_replace("'$pattern'", $replacement, $target);
}

function _replace($target, $search, $replacement) {
  if (is_null($target)) $target = '';
  return str_replace($search, $replacement, $target);
}

function _explode($delimiter, $target, $col) {
  $cols = explode($delimiter, $target);
  return $cols[$col - 1] ? $cols[$col - 1] : NULL;
}

function _fuzzy($str) {
  $from = array(
    'ふぁ','ふぃ','ふゅ','ふぇ','ふぉ',
    'ぷぁ','ぷぃ','ぷゅ','ぷぇ','ぷぉ',
    'ヴぁ','ヴぃ','ヴゅ','ヴぇ','ヴぉ',
    'ゔぁ','ゔぃ','ゔゅ','ゔぇ','ゔぉ',
    'ぶぁ','ぶぃ','ぶゅ','ぶぇ','ぶぉ',
    'ひゃ','ひぃ','ひゅ','ひぇ','ひょ',
    'ぴゃ','ぴぃ','ぴゅ','ぴぇ','ぴょ',
    'びゃ','びぃ','びゅ','びぇ','びょ',
    'でぃ'
  );
  $to = array(
    'は','ひ','ふ','へ','ほ',
    'ぱ','ぴ','ぷ','ぺ','ぽ',
    'ば','び','ぶ','べ','ぼ',
    'ば','び','ぶ','べ','ぼ',
    'ば','び','ぶ','べ','ぼ',
    'は','ひ','ふ','へ','ほ',
    'ぱ','ぴ','ぷ','ぺ','ぽ',
    'ば','び','ぶ','べ','ぼ',
    'じ'
  );
  return str_replace($from, $to, mb_convert_kana($str, 'HVc'));
}

function _strconv($str, $opt = 'ckwv') {
  $from = array(
    ' ',',','-','ー','ぁ','ぃ','ぅ','ぇ','ぉ','っ','ゃ','ゅ','ょ','ゎ',
    'が','ぎ','ぐ','げ','ご','ざ','じ','ず','ぜ','ぞ','だ','ぢ','づ','で','ど',
    'ば','び','ぶ','べ','ぼ','ぱ','ぴ','ぷ','ぺ','ぽ'
  );
  $to = array(
    '','','','','あ','い','う','え','お','つ','や','ゆ','よ','わ',
    'か','き','く','け','こ','さ','し','す','せ','そ','た','し','つ','て','と',
    'は','ひ','ふ','へ','ほ','は','ひ','ふ','へ','ほ'
  );
//  if (func_num_args() == 1) $opt = 'cwkv';
  $opt = strtolower($opt);
  if (strpos($opt, 'w') !== false) $mod = 'asKV';
  if (strpos($opt, 'k') !== false) $mod = 'ascHV';
  if ($mod) $str = mb_convert_kana($str, $mod);
  if (strpos($opt, 'c') !== false) $str = mb_strtoupper($str);
  if (strpos($opt, 'v') !== false) $str = str_replace($from, $to, $str);
  return $str;
}

function _chemruby($str) {
$from = array(
  '農薬','肥料','塗布','成型','小型','有機','黄斑','亜鉛','金属','丹礬','撒粉',
  '生薬','生石灰','石灰','硫黄','安息香','抽出','多角','酒石','雑草','除草',
  '還元','澱粉','不可欠','検疫','花木','野菜','種子','家庭','園芸','小球',
  '側条','注入','高度','機械','防散','東日本大震災','津波','被害','東北',
  '元気','太郎','退治','黒帯','一発','一本締','闘力','半蔵','大豆','強力',
  '快速','用心棒','美人','色彩','当番','若丸','将軍','月光','登熟','上手',
  '大臣','名人','大将','先陣','流星','維新','防人','醸造','青枯','革命','置型',
  '稲','滅','剤','粒','水','和','溶','液','乳','油','粉','末','煙','蒸','他',
  '顆','非','化','物','第','一','二','五','体','性','基','素','系','株','核',
  '病','原','酸','酢','硫','酪','青','塩','銅','土','菌','糸','死','生','弱',
  '毒','炭','樹','脂','肪','無','燐','鉄','複','混','調','合','窒','臭','過',
  '糖','銀','粘','展','着','貯','穀','途','専','用','固','形','型','小','特',
  '製','状','号','加','安','全','卵','箱','細','微','星','袋','錠','豆','苗',
  '緑','衣','芝','花','華','楽','草','刈','笛','農','消','類','王','国','風',
  '神','嵐','兆','忍','除','殺','虫','菊','天','敵','燻','量','君','娘','北',
  '番','葉','河','入','枯','取','受','地','中','打','込','茶','子','撃','空',
  '斬','快','Ⅰ','Ⅱ','Ⅲ','Ⅳ','Ⅴ','α'
);
$to = array(
  'のうやく','ひりょう','とふ','せいけい','こがた','ゆうき','おうはん','あえん','きんぞく','たんばん','さんぷん',
  'しょうやく','せいせっかい','せっかい','いおう','あんそくこう','ちゅうしゅつ','たかく','しゅせき','ざっそう','じょそう',
  'かんげん','でんぷん','ふかけつ','けんえき','かぼく','やさい','しゅし','かてい','えんげい','しょうきゅう',
  'そくじょう','ちゅうにゅう','こうど','きかい','ぼうさん','ひがしにほんだいしんさい','つなみ','ひがい','とうほく',
  'げんき','たろう','たいじ','くろおび','いっぱつ','いっぽんじめ','とうりき','はんぞう','だいず','きょうりょく',
  'かいそく','ようじんぼう','びじん','しきさい','とうばん','わかまる','しょうぐん','げっこう','とうじゅく','じょうず',
  'だいじん','めいじん','たいしょう','せんじん','りゅうせい','いしん','さきもり','じょうぞう','あおがれ','かくめい','おきがた',
  'いね','めつ','ざい','りゅう','すい','わ','よう','えき','にゅう','ゆ','ふん','まつ','えん','じょう','た',
  'か','ひ','か','ぶつ','だい','いち','に','ご','たい','せい','き','そ','けい','かぶ','かく',
  'びょう','げん','さん','さく','りゅう','らく','せい','えん','どう','ど','きん','し','し','せい','じゃく',
  'どく','たん','じゅ','し','ぼう','む','りん','てつ','ふく','こん','ちょう','ごう','ちっ','しゅう','か',
  'とう','ぎん','ねん','てん','ちゃく','ちょ','こく','と','せん','よう','こ','けい','けい','こ','とく',
  'せい','じょう','ごう','か','あん','ぜん','らん','はこ','さい','び','ぼし','ふくろ','じょう','まめ','なえ',
  'りょく','い','しば','はな','はな','らく','くさ','かり','ぶえ','のう','しょう','るい','おう','こく','ふう',
  'じん','あらし','きざし','しのび','じょ','さっ','ちゅう','ぎく','てん','てき','くん','りょう','くん','むすめ','きた',
  'ばん','は','が','い','か','と','う','ち','ちゅう','う','こ','ちゃ','じ','げき','くう',
  'ぎり','かい','1','2','3','4','5','あるふぁ'
);
return mb_convert_kana(str_replace($from, $to, $str), 'c');
}

function _ifnullstr($expr, $replacement) {
  if (is_null($expr)) $expr = '';
  return $expr ? $expr : $replacement;
}

function _if($expr, $true, $false = NULL) {
  return $expr ? $true : $false;
}

function _concat() {
  $argc = func_num_args();
  if ($argc < 2) return '';
  $args = func_get_args();
  $connector = array_shift($args);
  $con = preg_quote($connector);
  $argc--;
  $res = null;
  while ($argc > 0) {
    $arg = array_shift($args);
    $argc--;
    if (is_null($arg) || !$arg) continue;
    if (is_null($res)) $res = '';
    $pats = explode($con, $arg);
    foreach($pats as $arg) {
      $pat = preg_quote($arg);
      if (!preg_match("'(^|$con)$pat($con|$)'", $res)) $res .= $res ? $connector.$arg : $arg;
    }
  }
  return $res;
}

function _concatStep(&$context, $row, $connector, $data) {
  $con = preg_quote($connector);
  if (is_null($data)) $data = '';
  $pats = explode($con, $data);
  foreach($pats as $arg) {
    $pat = preg_quote($arg);
    if (is_null($arg) || !$arg) continue;
    if (!isset($context)) $context = '';
    if (!preg_match("'(^|$con)$pat($con|$)'", $context)) $context .= $context ? $connector.$arg : $arg;
  }
  return $context;
}

function _concatFinal(&$context, $rows) {
  return $context;
}

function _concat2() {
  $argc = func_num_args();
  if ($argc < 2) return '';
  $args = func_get_args();
  $connector = array_shift($args);
  $argc--;
  $res = null;
  while ($argc > 0) {
    $arg = array_shift($args);
    $argc--;
    if (is_null($arg) || !$arg) continue;
    if (is_null($res)) $res = '';
    $res .= $res ? $connector.$arg : $arg;
  }
  return $res;
}

function _concat2Step(&$context, $row, $connector, $data) {
  if (!is_null($data) && $data != '') {
    if ($context) {
      $context .= $connector.$data;
    } else {
      $context = $data;
    }
  }
  return $context;
}

function dbCloseStatement(&$st) {
  if ($st) {
    $st->closeCursor();
    unset($st);
  }
}

function dbClose(&$db) {
  if (isset($db)) unset($db);
}
?>
