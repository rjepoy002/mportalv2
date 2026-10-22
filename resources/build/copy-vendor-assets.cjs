const fs = require('node:fs'), path = require('node:path');
const root = path.resolve(__dirname, '../..');
const copy = (from, to) => {
  const target = path.join(root, to);
  fs.mkdirSync(path.dirname(target), {recursive:true});
  fs.copyFileSync(path.join(root, 'node_modules', from), target);
};
for (const file of ['bootstrap-icons.min.css', 'fonts/bootstrap-icons.woff', 'fonts/bootstrap-icons.woff2']) {
  copy('bootstrap-icons/font/' + file, 'assets/vendor/bootstrap-icons/' + file);
}
copy('bootstrap-icons/LICENSE', 'assets/vendor/bootstrap-icons/LICENSE');
copy('chart.js/dist/chart.umd.js', 'assets/vendor/chart.js/chart.umd.js');
copy('chart.js/dist/chart.umd.js.map', 'assets/vendor/chart.js/chart.umd.js.map');
copy('chart.js/LICENSE.md', 'assets/vendor/chart.js/LICENSE.md');
copy('tailwindcss/LICENSE', 'assets/vendor/tailwindcss/LICENSE');
