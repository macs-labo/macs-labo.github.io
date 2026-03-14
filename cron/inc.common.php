<?php
// .make.*.php 共通設定ファイル

require_once 'inc.setup.php';

$dopage = "https://pesticide.maff.go.jp/agricultural-chemicals";
$dlpage = "https://www.acis.famic.go.jp/ddata/index2.htm";

mb_internal_encoding('utf8');
mb_detect_order('UTF-8,sjis-win,eucjp-win');
$debug = __DIR__ !== $crondir;
$chkbase = $chkbase ?? $datdir;

// debug モードの場合、エラー表示設定をして text/plain でヘッダ出力
if ($debug) {
  error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
  ini_set('display_errors', 1);
  header('Content-type: text/plain');
  echo "$chkbase\n";
}

// 強制更新フラグ取得
function getForceUpdate() {
  global $argv;

  // 1. Web経由を先にチェック
  $fupdate = $_REQUEST['update'] ?? false;

  // 2. CLI経由なら上書き（または追加チェック）
  if (PHP_SAPI === 'cli' && isset($argv)) {
    $fupdate = in_array('update=1', $argv); // 文字列として "update=1" が含まれているか
    if (isset($argv[1])) echo "$argv[1]\n";
  }

  // 3. 最終判定： "1", 1, "true", true などをすべて正しく true とみなす
  // filter_var を使うと、文字列の "false" や "0" も正しく偽にできます
  return filter_var($fupdate, FILTER_VALIDATE_BOOLEAN);
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
  clearstatcache();
  $res = get_headers($url, true, $context);
  $res['ResponseCode'] = intval(substr($res[0], 9, 3));
  return $res;
}

/* ファイルの Last-Modified 取得 */
function getLastModified($url) {
  if (!$url) return false;

  // GitHub の Raw URL かどうかを判定
  if (preg_match('|^https://raw\.githubusercontent\.com/([^/]+)/([^/]+)/([^/]+)/(.+)$|', $url, $matches)) {
    $owner  = $matches[1];
    $repo   = $matches[2];
    $branch = $matches[3]; // 今回のAPIでは使いませんが抽出は可能
    $path   = $matches[4];

    $api_url = "https://api.github.com/repos/$owner/$repo/commits?path=$path&page=1&per_page=1";

    $token = getenv('GH_TOKEN'); // yamlで設定した環境変数
    $opt = array(
      'http' => array(
        'method' => 'GET',
        'header' => array(
          'User-Agent: PHP-Script',
          'Authorization: token ' . $token
        )
      )
    );
    $context = stream_context_create($opt);
    $res_json = @file_get_contents($api_url, false, $context);
    
    if ($res_json) {
      $data = json_decode($res_json, true);
      if (isset($data[0]['commit']['committer']['date'])) {
        return strtotime($data[0]['commit']['committer']['date']);
      }
    }
    return false; // API取得失敗時
  }

  // 通常のHTTP URLの場合
  if (strpos($url, 'http') === 0) {
    $res = http_headers($url);
    return $res['ResponseCode'] == 200 ? strtotime($res['Last-Modified'] ?? 0) : false;
  }

  // ローカルファイルの場合
  return filemtime($url);
}

/* 更新されていれば mtime いなければ false を返す */
function is_modified($url, $date, $forceupdate = false) {
  $mtime = getLastModified($url);
  if (!$mtime) return false;
  if (is_string($date)) $date = strtotime($date);
  return $forceupdate || $mtime > $date ? $mtime : false;
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
