// サイト名と分室名
// FAMIC との使用許諾の関係があるので、○○分室の部分だけ適当な名称に変更
const sitename = "携帯農薬検索実験室";
let branch = "○○分室"; //本室、秋田分室、大阪分室は自動設定するのでこのまま

const branches = [
  { host: 'macs.xii.jp', branch: '本室' },
  { host: 'macs-labo.github.io', branch: '本室別館' },
  { host: 'macs.kabe.info', branch: '秋田分室' },
  { host: 'noyaku.ebb.jp', branch: '大阪分室' }
];
branches.forEach(b => {
  if (window.location.host === b.host) {
    branch = b.branch;
  }
});

const acfbpath = '/acfinder';
const macspath = branch === '本室別館' ? 'https://emacs.vercel.com' : '/macs';
const datapath = '/data';
const rootsite = 'http://macs.xii.jp/';
