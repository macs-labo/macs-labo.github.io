<?php
//カレントディレクトリをスクリプトディレクトリに変更
chdir(__DIR__);

$fupdate = getForceUpdate();
require_once 'inc.acis.csv.php';
?>
