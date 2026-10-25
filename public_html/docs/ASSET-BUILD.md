# Asset build and dependency boundary

Use Node 22 (see `.nvmrc`) and the committed lockfile:

```sh
npm ci
npm run production
npm run test:assets
npm run test:deployment
npm audit
```

`production` builds minified assets; `development` builds readable assets. `watch`,
`watch-poll`, and `hot` are compatibility aliases for a filesystem watcher. They do
not run a public development server, enable HMR, or implement polling.

The pipeline uses esbuild and Dart Sass directly. Laravel Mix, webpack, their
loaders, and `--openssl-legacy-provider` are no longer required. Existing
`public/js/app.js`, `public/css/app.css`, and `public/css/toastr.css` URLs are
unchanged. A content-hashed `mix-manifest.json` supports older `mix()` templates.
Font Awesome files are copied to `public/webfonts`; legal notices remain in the
built bundles. Production source maps are deliberately not published.

The DOM regression tests execute the actual compiled bundle with JSDOM. They
cover jQuery, Bootstrap modals, AdminLTE PushMenu registration, Select2,
axios/lodash initialization, compiled CSS, font files, and manifest hashes.
They supplement rather than replace testing layouts in real browsers.

## Explicit legacy boundary

The legacy administration views still require Bootstrap 4.6.2, Popper 1.16.1,
jQuery 3.7.1, Font Awesome 5.15.4 and AdminLTE 3.2.0. Replacing these with
Bootstrap 5/AdminLTE 4 would require a coordinated template and interaction
migration. Bootstrap 4 is end-of-life; a zero npm audit is not a claim that this
UI stack is supported or that every vendored/CDN/static asset is vulnerability-free.

Only AdminLTE's distribution JavaScript and CSS are kept under
`resources/vendor/admin-lte`, together with its MIT license. This avoids
installing its large collection of unrelated example plugins (editors, charts,
PDF generators, date pickers, and others) in the build dependency graph. These
vendored files are not covered by npm audit and must be reviewed/upgraded
explicitly. The JS requires only jQuery; the application's actual runtime
packages are separately declared in package.json.

The files are unmodified from the official npm `admin-lte@3.2.0` tarball:
https://registry.npmjs.org/admin-lte/-/admin-lte-3.2.0.tgz

SHA-256:
- Tarball: `92650fb9e582b9e01f691693be79b1213608a6282a7f062c139bfeafc8593df5`
- adminlte.js: `c872388958d4ae5ca26435e85741d7901dd2e6cb125b91b9cea857a2429559b4`
- adminlte.css: `f99d5cef704447ff68fa6b47e64eef3c6e91ad51ce1f0e3d006d561e81b8b34a`
- LICENSE: `6811af56d87e64675ad70c66e9c7d28524e18e8d34e9420e60c00b5657d1604c`

Bootstrap's legacy Sass deprecations are suppressed narrowly during compilation;
this does not hide compiler errors. See:
https://getbootstrap.com/docs/4.6/end-of-life/
https://esbuild.github.io/api/

## Security maintenance

Axios and lodash are updated to current patched releases in the lockfile.
Wrangler's transitive Undici is overridden within major 7 to `^7.30.1` because
its then-pinned version was affected by GHSA-4qv8-2p9j-2685,
GHSA-f72v-6w45-q472 and GHSA-xjj6-f33q-929c. Revisit this override when Wrangler
ships a patched dependency itself. Do not use audit's suggested downgrade to an
older Wrangler as a substitute for patching the affected HTTP client.

Run audits again when installing or releasing. See STATIC-ASSET-SECURITY.md for the subsequent review of loaded static assets,
local locked replacements for script CDNs, and remaining legacy boundaries.
