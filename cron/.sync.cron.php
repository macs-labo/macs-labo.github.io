<?php
$file = 'cron.zip';
$src = "https://macs.xii.jp/cron/$file";
$reset = $_REQUEST['reset'] ?? 0;

$debug = 1;
if ($debug) {
  error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
  ini_set('display_errors', 1);
  header('Content-type: text/plain');
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
function is_modified($url, $date) {
  if ($date === false) {
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

//カレントディレクトリをスクリプトディレクトリに変更
chdir(__DIR__);

$total = -microtime(true);

// ローカルと携帯農薬実験室の cron.zip のタイムスタンプを比較して、実験室が新しければファイル同期
$mtime = is_modified($src, filemtime($file));
if (!$reset && $mtime === false) {
  if ($debug) echo "sync: $file: Not Modified\n";
  exit(1);
}

$body = file_get_contents($src);
if ($body === false) {
  if ($debug) echo "sync: Cannot get $src\n";
  exit(1);
}
file_put_contents($file, $body, LOCK_EX);
touch($file, $mtime);
if ($debug) echo "sync: $file: Updated\n";

if ($debug) {
  `unzip -o ./$file`;
} else {
  exec("unzip -o ./$file");
}
?>
