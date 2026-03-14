<?php
//カレントディレクトリをスクリプトディレクトリに変更
chdir(__DIR__);

require_once 'inc.common.php';
require_once 'inc.sqlite.php';
require_once 'inc.spec.php';

$updated = false;
$fupdate = getForceUpdate();

$total = -microtime(true);

$db = new PDO("sqlite:$subdb");
//$db->sqliteCreateFunction('regexp', '_regexp', 2);
$db->sqliteCreateFunction('re_replace', '_re_replace', 3);
//$db->sqliteCreateFunction('replace', '_replace', 3);
$db->sqliteCreateFunction('ifnullstr', '_ifnullstr', 2);
$db->sqliteCreateFunction('concat', '_concat');
$db->sqliteCreateAggregate('concat', '_concatStep', '_concatFinal', 2);
$db->sqliteCreateFunction('concat2', '_concat2');
$db->sqliteCreateAggregate('concat2', '_concat2Step', '_concatFinal', 2);

// spec.zip と各ソースのタイムスタンプを比較して、ソースが新しければデータベース更新
$mtime = getLastModified("$chkbase/$subzip");
foreach($src as $item => $file) {
  if (is_modified("$chkbase/$specfiles[$item]", $mtime, $fupdate)) {
    $sql = str_replace('spec.', '', file_get_contents($file));
    if (strtolower(mb_detect_encoding($sql, 'sjis-win, utf-8')) == 'sjis-win') $sql = mb_convert_encoding($sql, 'utf-8', 'sjis-win');
    $res = $db->exec($sql);
    if ($res === false) {
      $err = $db->errorInfo();
      logputs("spec $item", $err[2], 'Cron DB Error');
      if ($debug) echo "spec: $item: $err[2]\n";
      return 2;
    }
    $updated = true;
    if ($debug) echo "spec: $item: Updated\n";
  } else {
    if ($debug) echo "spec: $item: Not Modified\n";
  }
}

unset($db);
copy($subdb, "$datdir/$subdb"); // $datdir にコピー

$total += microtime(true);

if (!$updated) return 1;
// 公開 zip ファイル更新
exec("zip -Dq $subzip $subdb");
rename("./$subzip", "$datdir/$subzip");
// 検索システム用データベース更新
  if ($dbpath) {
  mkdir("$dbpath/$udflag");
  copy("./$subdb", "$dbpath/$subdb");
  //touch("$dbpath/$subdb", getLastModified($subdb));
  rmdir("$dbpath/$udflag");
}
logputs('spec', "Created $total");
if ($debug) echo "spec: Created $total\n";
return 0;
?>
