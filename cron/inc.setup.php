<?php
// データ更新エラー発生時にスクリプトが送信する通知メールのアドレス
$mailto = ''; // 空白でも CRON ジョブのエラーメールの設定をしておけば CRON ジョブがエラーメールを送信します

// MACS ホストディレクトリ xrea/coreserver v1 なら /public_html/DOMAIN または /public_html
$macsdir = ''; // GitHub 運用の場合は設定不要

// サーバ上のユーザルートディレクトリ(空文字列なら自動取得)
$user_root = ''; // 自動取得できない場合は xrea/coreserver v1 なら /virtual/USERID を手動設定

// GitHub Actions 環境（DB更新スクリプト実行時）の自動設定
if (getenv('GITHUB_ACTIONS') === 'true') {
  $datdir  = '../data';
  $crondir = '.';
  $libdir  = '.';
  $dbpath  = false;
  // 判定基準をリポジトリの Raw URL に統一
  $chkbase = 'https://raw.githubusercontent.com/macs-labo/macs-labo.github.io/main/data';
  return 0;
}

// サーバ上のユーザルートディレクトリの絶対パス自動取得
if (!$user_root) { // 1. 環境変数をチェック（CLI実行時などのため）
  $user_root = $_SERVER['HOME'] ?? getenv('HOME');
}
if (!$user_root && function_exists('posix_getpwuid')) { // 2. 取れなかったらシステム情報から取得を試みる
  $userInfo = posix_getpwuid(posix_geteuid());
  $user_root = $userInfo['dir'] ?? false;
}
if (!$user_root) { // 3. それでもダメならスクリプトパスから逆算する（最終手段）
  $parts = explode(DIRECTORY_SEPARATOR, __DIR__); 
  $user_root = count($parts) > 3 ? implode(DIRECTORY_SEPARATOR, array_slice($parts, 0, 3)) : __DIR__;
}
//$user_root = rtrim($user_root, DIRECTORY_SEPARATOR);

$no = preg_match('/(\d+)$/', __DIR__, $matches) ? $matches[1] : '';;
$datdir  = "{$user_root}$macsdir/data$no"; // データ公開ディレクトリ
$crondir = "$user_root/cron$no"; // cron ディレクトリ
$libdir  = "$user_root/lib/php"; // php ライブラリディレクトリ
$dbpath  = "$user_root/sqlitedb/famic$no"; // データベースディレクトリ
?>
