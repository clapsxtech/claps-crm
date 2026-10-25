// Publish only reviewed public asset trees. Never publish PHP, uploads, source
// maps, dotfiles, symlinks or application/credential files from the checkout.
import { readdir, mkdir, copyFile, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';
const destination = '.cloudflare-assets';
const extensions = new Set(['.css', '.js', '.woff', '.woff2', '.ttf', '.eot', '.otf', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico']);
await rm(destination, { recursive: true, force: true });
await mkdir(destination, { recursive: true });
async function stage(relative) {
    for (const file of await readdir(path.join('public', relative), { withFileTypes: true })) {
        if (file.name.startsWith('.') || file.isSymbolicLink()) continue;
        const item = path.join(relative, file.name);
        if (file.isDirectory()) await stage(item);
        else if (file.isFile() && extensions.has(path.extname(file.name).toLowerCase())) {
            await mkdir(path.dirname(path.join(destination, item)), { recursive: true });
            await copyFile(path.join('public', item), path.join(destination, item));
        }
    }
}
for (const directory of ['css','js','fonts','webfonts','browser-vendor','adminlte','core','frontent_form','error','img','zencare']) await stage(directory);
for (const file of ['favicon.ico','robots.txt','style.css','style1.css']) await copyFile(path.join('public', file), path.join(destination, file));
await writeFile(path.join(destination, '_headers'), '/*\n  X-Content-Type-Options: nosniff\n  Cache-Control: public, max-age=0, must-revalidate\n');
console.log('Staged public assets; application pages and uploads remain outside static delivery.');
