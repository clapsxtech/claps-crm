import { build } from 'esbuild';
import { compile } from 'sass';
import { cp, mkdir, readFile, rm, writeFile, watch } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = fileURLToPath(new URL('../', import.meta.url));
process.chdir(root);
const production = process.argv.includes('--production');
const options = { minify: production, legalComments: 'eof', target: ['es2020'], logLevel: 'warning' };
async function buildAssets() {
    await mkdir('public/js', { recursive: true });
    await mkdir('public/css', { recursive: true });
    await build({ ...options, entryPoints: ['resources/js/app.js'], bundle: true, platform: 'browser', outfile: 'public/js/app.js' });
    for (const name of ['app', 'toastr']) {
        const compiled = compile(`resources/sass/${name}.scss`, {
            loadPaths: ['node_modules'], quietDeps: true,
            // Legacy Bootstrap 4 styles use Sass imports; tracked in docs/ASSET-BUILD.md.
            silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'],
        });
        await build({ ...options, stdin: { contents: compiled.css, loader: 'css', resolveDir: path.resolve('resources/sass') },
            bundle: true, outfile: `public/css/${name}.css`, external: ['../webfonts/*'] });
    }
    await cp('node_modules/@fortawesome/fontawesome-free/webfonts', 'public/webfonts', { recursive: true });
    // Browser vendor files are copied from audited, locked npm packages, never a floating CDN.
    const vendorAssets = {
        'jquery/dist/jquery.min.js': 'browser-vendor/jquery.min.js',
        'bootstrap/dist/js/bootstrap.bundle.min.js': 'browser-vendor/bootstrap.bundle.min.js',
        'bootstrap/dist/css/bootstrap.min.css': 'frontent_form/bootstrap.min.css',
        'select2/dist/js/select2.min.js': 'browser-vendor/select2.min.js',
        'moment/min/moment.min.js': 'browser-vendor/moment.min.js',
        'daterangepicker/daterangepicker.js': 'browser-vendor/daterangepicker.js',
        'jszip/dist/jszip.min.js': 'adminlte/jszip.min.js',
        'datatables.net/js/jquery.dataTables.min.js': 'adminlte/jquery.dataTables.min.js',
        'datatables.net-bs4/js/dataTables.bootstrap4.min.js': 'adminlte/dataTables.bootstrap4.min.js',
        'datatables.net-bs4/css/dataTables.bootstrap4.min.css': 'adminlte/dataTables.bootstrap4.min.css',
        'pdfmake/build/pdfmake.min.js': 'adminlte/pdfmake.min.js',
        'pdfmake/build/vfs_fonts.js': 'adminlte/vfs_fonts.js',
        'gsap/dist/gsap.min.js': 'error/gsap.min.js',
        'datatables.net-buttons/js/dataTables.buttons.min.js': 'adminlte/dataTables.buttons.min.js',
        'datatables.net-buttons/js/buttons.html5.min.js': 'adminlte/buttons.html5.min.js',
        'datatables.net-buttons/js/buttons.print.min.js': 'adminlte/buttons.print.min.js',
        'datatables.net-buttons/js/buttons.colVis.min.js': 'adminlte/buttons.colVis.min.js',
        'datatables.net-buttons-bs4/js/buttons.bootstrap4.min.js': 'adminlte/buttons.bootstrap4.min.js',
        'datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css': 'adminlte/buttons.bootstrap4.min.css',
        'datatables.net-responsive/js/dataTables.responsive.min.js': 'adminlte/dataTables.responsive.min.js',
        'datatables.net-responsive-bs4/js/responsive.bootstrap4.min.js': 'adminlte/responsive.bootstrap4.min.js',
        'datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css': 'adminlte/responsive.bootstrap4.min.css',
        'ion-rangeslider/js/ion.rangeSlider.min.js': 'browser-vendor/ion.rangeSlider.min.js',
    };
    for (const [source, destination] of Object.entries(vendorAssets)) {
        await mkdir(path.dirname(`public/${destination}`), { recursive: true });
        await cp(`node_modules/${source}`, `public/${destination}`);
    }
    await cp('node_modules/datatables.net/js/jquery.dataTables.min.js', 'public/js/jquery.dataTables.min.js');
    // No application caller remains for these obsolete duplicate / demo plugins.
    for (const asset of [
        'adminlte/jquery-ui.min.js', 'adminlte/jquery.validate.min.js',
        'adminlte/bootstrap-colorpicker.min.js', 'adminlte/adminlte.min.js', 'adminlte/demo.js',
        'adminlte/toastr.min.js', 'adminlte/select2.min.js',
        'js/tinymce.min.js', 'js/popper.js',
        'core/js/jquery.magnific-popup.min.js', 'core/js/slick.min.js', 'core/js/custom.jquery.js',
    ]) await rm(`public/${asset}`, { force: true });
    // Keep the Laravel mix() contract for older templates without depending on Mix.
    const manifest = {};
    for (const asset of ['js/app.js', 'css/app.css', 'css/toastr.css']) {
        const content = await readFile(`public/${asset}`);
        manifest[`/${asset}`] = `/${asset}?id=${createHash('sha256').update(content).digest('hex').slice(0, 20)}`;
    }
    await writeFile('public/mix-manifest.json', JSON.stringify(manifest, null, 2) + '\n');
    for (const artifact of ['public/js/app.js.map', 'public/js/app.js.LICENSE.txt', 'public/css/app.css.map', 'public/css/toastr.css.map']) {
        await rm(artifact, { force: true });
    }
    console.log(`Assets built (${production ? 'production' : 'development'}).`);
}
await buildAssets();
if (process.argv.includes('--watch')) {
    // Async iteration serializes builds; no stale build can finish over a newer one.
    for await (const event of watch('resources', { recursive: true })) {
        if (!event.filename || !/\.(js|css|scss)$/.test(event.filename)) continue;
        try { await buildAssets(); } catch (error) { console.error(error); }
    }
}
