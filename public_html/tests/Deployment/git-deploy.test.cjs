const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

test('Git-connected deploy checks the token, deploys, then restarts the container', () => {
    const scripts = JSON.parse(read('package.json')).scripts;
    assert.equal(
        scripts['cf:deploy:git'],
        'node scripts/restart-container.mjs --check && npm run cf:deploy && node scripts/restart-container.mjs',
    );
    assert.match(scripts['cf:deploy'], /wrangler deploy/);
});

test('restart script never logs the internal token', () => {
    const source = read('scripts/restart-container.mjs');
    assert.match(source, /Authorization: `Bearer \$\{token\}`/);
    for (const line of source.split('\n').filter((l) => /console\.(log|error)/.test(l))) {
        assert.doesNotMatch(line, /\$\{token\}|\btoken\b(?!\s*does)/, line);
    }
});
