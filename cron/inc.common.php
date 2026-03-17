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
}

// 強制更新フラグ取得
function getForceUpdate() {
  global $argv;

  // 1. Web経由を先にチェック
  $fupdate = $_REQUEST['update'] ?? false;

  // 2. CLI経由なら上書き（または追加チェック）
  if (PHP_SAPI === 'cli' && isset($argv)) {
    $fupdate = in_array('update=1', $argv); // 文字列として "update=1" が含まれているか
  }

  // 3. 最終判定： "1", 1, "true", true などをすべて正しく true とみなす
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

  // 1. ローカルの zip 内ファイル判定 (path/to/archive.zip/innerfile.csv)
  if (preg_match('/\.zip\//i', $url)) {
    $parts = explode('.zip/', $url, 2);
    $zipPath = $parts[0] . '.zip';
    $innerFile = $parts[1];

    if (file_exists($zipPath)) {
      $zip = new ZipArchive();
      if ($zip->open($zipPath) === TRUE) {
        $stat = $zip->statName($innerFile);
        $zip->close();
        // チェックアウト直後の zip 自体の mtime はあてにならないため、zip 内のファイルの mtime を優先
        return isset($stat['mtime']) ? $stat['mtime'] : false;
      }
    }
  }

  // 2. GitHub の Raw URL かどうかを判定 (GitHub API 経由でコミット日時を取得)
  if (preg_match('|^https://raw\.githubusercontent\.com/([^/]+)/([^/]+)/([^/]+)/(.+)$|', $url, $matches)) {
    $owner  = $matches[1];
    $repo   = $matches[2];
    $branch = $matches[3];
    $path   = $matches[4];

    $api_url = "https://api.github.com/repos/$owner/$repo/commits?path=$path&sha=$branch&page=1&per_page=1";

    // トークン取得ロジック
    $token = '';
    if (PHP_SAPI === 'cli') { 
      // CLI実行 (GitHub Actions 上の PHP) の場合は環境変数を参照
      $token = getenv('GITHUB_TOKEN') ?: getenv('GH_TOKEN') ?: '';
    } else {
      // Web経由 (curl経由) の場合はカスタムヘッダー X-GitHub-Token を参照
      // Apache経由の場合、PHPでは $_SERVER['HTTP_X_GITHUB_TOKEN'] に格納される
      $token = $_SERVER['HTTP_X_GITHUB_TOKEN'] ?? '';
    }

    $http_headers = array('User-Agent: PHP-Script');
    if ($token) {
      $http_headers[] = 'Authorization: token ' . $token;
    }
    
    $opt = array(
      'http' => array(
        'method' => 'GET',
        'header' => implode("\r\n", $http_headers),
        'ignore_errors' => true
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
    return false;
  }

  // 3. 通常の HTTP URL の場合
  if (strpos($url, 'http') === 0) {
    $res = http_headers($url);
    return ($res['ResponseCode'] == 200) ? strtotime($res['Last-Modified'] ?? 0) : false;
  }

  // 4. 通常のローカルファイルの場合
  if (file_exists($url)) {
    clearstatcache();
    return filemtime($url);
  }

  return false;
}

/* 更新されていれば mtime いなければ false を返す */
function is_modified($url, $date, $forceupdate = false) {
  $mtime = getLastModified($url);
  if (!$mtime) return false;
  if (is_string($date)) $date = strtotime($date);
  return ($mtime > $date) || $forceupdate ? $mtime : false;
}

/**
 * ファイルのハッシュ値を取得する（zip内のファイルにも対応）
 */
function get_path_hash($path, $algo = 'sha256') {
  if (preg_match('/\.zip\//i', $path)) {
    $parts = explode('.zip/', $path, 2);
    $zipPath = $parts[0] . '.zip';
    $innerFile = $parts[1];

    $zip = new ZipArchive();
    if ($zip->open($zipPath) === TRUE) {
      $fp = $zip->getStream($innerFile);
      if (!$fp) {
        $zip->close();
        return false;
      }
      $ctx = hash_init($algo);
      while (!feof($fp)) {
        hash_update($ctx, fread($fp, 8192));
      }
      $hash = hash_final($ctx);
      fclose($fp);
      $zip->close();
      return $hash;
    }
    return false;
  }

  if (!file_exists($path)) return false;
  return hash_file($algo, $path);
}

// ハッシュ比較によるファイル更新チェック
function is_changed($path1, $path2) {
  $h1 = get_path_hash($path1);
  $h2 = get_path_hash($path2);
  return $h1 !== $h2;
}

// GitHub Actions 上では $path を URL 変換
function convUrl($path) {
  global $chkbase, $dbpath; 
  // $dbpath が false (GitHub Actions 環境) の場合、
  // $chkbase (/data) を自分自身のディレクトリ (/cron) に変換してURLを生成
  return $dbpath ? $path : dirname($chkbase) . '/cron/' . basename($path); 
}

function logputs($script, $str, $subject = '') {
  global $mailto;
  $date = date('Y.m.d H:i:s');
  $fh = fopen(__DIR__.'/log.txt', 'a');
  $body = "$date $script $str\n";
  fwrite($fh, $body);
  fclose($fh);
  if ($subject && $mailto) {
    mb_send_mail($mailto, $subject, $body); 
  }
}
?>