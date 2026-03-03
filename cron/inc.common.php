<?php
// .make.*.php 共通設定ファイル

require_once 'inc.setup.php';

$dopage = "https://pesticide.maff.go.jp/agricultural-chemicals";
$dlpage = "http://www.acis.famic.go.jp/ddata/index2.htm";

mb_internal_encoding('utf8');
mb_detect_order('UTF-8,sjis-win,eucjp-win');
//$debug = strpos(__FILE__, $crondir) === false;
$debug = 1;
// debug モードの場合、エラー表示設定をして text/plain でヘッダ出力
if ($debug) {
  error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
  ini_set('display_errors', 1);
  header('Content-type: text/plain');
}

// 強制更新フラグ取得
function getForceUpdate() {
  global $argv; // CLI引数にアクセス
  echo PHP_SAPI . "\n";
  print_r($argv);
  $fupdate = $_REQUEST['update'] ?? false;
  if (PHP_SAPI === 'cli') $fupdate = isset($argv) && in_array('update=1', $argv);
  return $fupdate;
}

function getResponseCode() {
  return intval(substr($http_response_header[0], 9, 3));
}

function http_headers($url, $headers = null) {
  $opt['method'] = 'HEAD';
  if (isset($headers)) {
    if (is_array($headers)) $headers = implode("\r\n", $headers);
    if ($headers != '') $opt['header'] = $headers;
  }
  $context = stream_context_create(array('http' => $opt));
  $res = get_headers($url, true, $context);
  $res['ResponseCode'] = intval(substr($res[0], 9, 3));
  return $res;
}

/* 更新されていれば mtime いなければ false を返す */
function is_modified($url, $date, $forceupdate = false) {
  if ($date === false || $forceupdate) {
     $header = '';
  } else {
    if (!is_string($date)) $date = gmdate('D, d M Y H:i:s T', $date);
    $header = "If-Modified-Since: $date";
  }
  $res = http_headers($url, $header);
  if ($res['ResponseCode'] == 200) {
    return strtotime($res['Last-Modified']);
  } else {
    return false;
  }
}

/* is_modified で mtime を返すようにしたので、基本こっちは使わない */
function http_filemtime($url) {
  $res = http_headers($url);
  if ($res['ResponseCode'] == 200) {
    return strtotime($res['Last-Modified']);
  } else {
    return false;
  }
}

function logputs($script, $str, $subject = '') {
  global $mailto;
  $date = date('Y.m.d H:i:s');
  $fh = fopen(dirname(__FILE__).'/log.txt', 'a');
  $body = "$date $script $str\n";
  fwrite($fh, $body);
  fclose($fh);
  if ($subject && $mailto) {
    mb_send_mail($mailto, $subject, $body); 
  }
}

/*
function updatetopic($title) {
  global $dbname, $dbuser, $dbpass;
  if (!$dbname) return;
  $datetime = date("Y-m-d H:i:s", filemtime("$datdir/$csvzip"));
  $sql = "INSERT INTO macs_topic (display, ctime, subject, content, href) "
       . "VALUES (10, '{$datetime}' , '農薬登録情報更新', 'データページの農薬登録情報を「{$title}」に更新しました。', 'data.php');";
  $db = new PDO("mysql:host=localhost;dbname=$dbname", $dbuser, $dbpass, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
  $db->exec($sql);
  unset($db);
}
*/
?>
