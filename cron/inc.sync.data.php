<?php
// .round.data.php 設定ファイル

require_once 'inc.csv.php';
require_once 'inc.acis.php';
require_once 'inc.spec.php';
require_once 'inc.dokusei.php';

$fupdate = getForceUpdate();

$git = 'https://macs-labo.github.io/data';

$sites[] = $git;
if (!$fupdate) {
  $sites[] = 'https://macs.xii.jp/data';
  $sites[] = 'https://macs.kabe.info/data';
  //$sites[] = 'https://noyaku.ebb.jp/data'; // 大阪分室の CRON 同期が終わるまで巡回から外す
}

$files1[] = $dokusei;
$files1[] = $suisan;
$files1[] = $seizai;
$files1[] = $rubyfile;
$files1[] = $kwfile;
$files1[] = $byochu;
$files1[] = $sakumotsu;
$files1[] = $csvzip;
$files1[] = $update;
$files1[] = $subzip;
$files1[] = $mainzip;

foreach($specfiles as $item => $file) {
  $src[$item] = "$git/$file";
}
?>
