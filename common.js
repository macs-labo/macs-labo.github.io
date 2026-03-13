function renderedHtml() {
  const year = new Date().getFullYear();
  return `
<!DOCTYPE html>
<html>
<head>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<meta http-equiv="Content-Style-Type" content="text/css" />
<meta name="viewport" content="width=device-width, user-scalable=yes, initial-scale=1.0, minimum-scale=0.5, maximum-scale=1.5" />
<!--<link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">-->
<link rel="apple-touch-icon" href="/apple-touch-icon-180x180.png" sizes="180x180">
<link rel="icon" type="image/png" href="/android-chrome-192x192.png" sizes="192x192">
<link rel="stylesheet" type="text/css" href="/macs/style.macs.css" />
<style type="text/css">
a {
  color: black;
  font-weight: 500;
  text-decoration: underline;
  text-decoration-thickness: 0.4em;
  text-decoration-color: #9eceff;
  text-underline-offset: -0.25em;
  text-decoration-skip-ink: none;
}
.note {
  font-style: oblique;
}
${style}
</style>
<title>${sitename}${branch}</title>
</head>
<body>
<div id="header">
  ${head}
</div>
<div class="underhead">
  ${head}
</div>
<div id="main">
  ${main}
</div>
<div id="navi">
  ${navi}
</div>
<div id="footer">
  <ul>
    <li>当サイト(携帯農薬検索実験室)が本サービスで提供する情報は、FAMIC(独立行政法人農林水産消費安全技術センター)ホームページから取得した農薬登録情報を当サイトで加工し、検索できるようにしたものです。</li>
    <li>情報取得先であるFAMICは、FAMICホームページの情報を用いて当サイトが行う本サービスの提供等の一切の行為により、直接または間接的に生じた利用者またはそれ以外の第三者の損害については、その内容、方法の如何にかかわらず、一切責任を負いません。</li>
    <li>当サイト運営者は、FAMICホームページから取得した農薬登録情報の加工及び検索に万全を期していますが、本サービスを利用した結果いかなる損害が発生したとしても、一切責任を負いません。</li>
    <li>本サービスが使用するデータベースおよびプログラムのソースコードは MIT ライセンスで公開していますが、サービスの持続性確保のため、必ず「<a href="https://github.com/macs-labo/macs-labo.github.io#readme" target="_blank">利用規約</a>」をご確認の上、不具合報告等のメインテナンスへの協力をお願いします。</li>
  </ul>
  <p class="note">PC や大画面タブレットでは「ACFinder Browser Edition」、スマホや小画面タブレットでは「携帯農薬検索システム」をお使いください。</p>
</div>
<footer>
  <p>MACS Lab: Mobile Agricultural Chemicals Search Laboratory</p>
  <p>&copy; 2004-${year} MACS Lab.</p>
</footer>
</body>
</html>
`;
}

document.addEventListener('DOMContentLoaded', async () => {
  if (typeof initVars === 'function') await initVars();
  document.open();
  document.write(renderedHtml());
  document.close();
});
