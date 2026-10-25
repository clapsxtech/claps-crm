const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.join(__dirname, '../..');
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
const composer = JSON.parse(read('composer.json'));
const lock = JSON.parse(read('composer.lock'));
const docker = read('Dockerfile.cloudflare');

// Deliberately support only the constraint forms currently present in this lock.
// Fail closed on new syntax instead of pretending to be Composer's full solver.
function version(value) {
    assert.match(value, /^\d+(?:\.\d+){0,2}$/);
    return value.split('.').map(Number).concat([0, 0]).slice(0, 3);
}
function compare(a, b) {
    for (let i = 0; i < 3; i++) if (a[i] !== b[i]) return Math.sign(a[i] - b[i]);
    return 0;
}
function admitsPhp(constraint, target) {
    const current = version(target);
    return constraint.split(/\|\|?/).map(part => {
        const range = part.trim();
        let match = range.match(/^(>=|\^)(\d+(?:\.\d+){0,2})$/);
        if (match) {
            const lower = version(match[2]);
            assert.ok(lower[0] > 0, 'Unsupported zero-major constraint: ' + range);
            return compare(current, lower) >= 0 && (match[1] === '>=' || current[0] === lower[0]);
        }
        match = range.match(/^(\d+(?:\.\d+){0,2}) - (\d+(?:\.\d+){0,2})$/);
        assert.ok(match, 'Review newly introduced PHP constraint syntax: ' + range);
        const upper = version(match[2]);
        const parts = match[2].split('.').length;
        if (parts < 3) upper[parts - 1]++;
        return compare(current, version(match[1])) >= 0
            && (parts < 3 ? compare(current, upper) < 0 : compare(current, upper) <= 0);
    }).some(Boolean);
}

test('vendor stage installs required bcmath before Composer without bypassing platform checks', () => {
    const vendor = docker.match(/^FROM composer:2 AS vendor\n([\s\S]*?)(?=^FROM )/m)?.[1];
    assert.ok(vendor, 'Expected separate Composer vendor stage');
    assert.equal(composer.require['ext-bcmath'], '*');
    const extension = vendor.search(/^RUN docker-php-ext-install\b[^\n]*\bbcmath\b/m);
    const install = vendor.search(/^RUN composer install\b/m);
    assert.ok(extension >= 0 && install > extension, 'bcmath must exist before composer install');
    assert.doesNotMatch(vendor, /--ignore-platform-req/);
});

test('asset stage copies the esbuild entrypoint before building rather than obsolete webpack files', () => {
    const assets = docker.split(/^FROM composer:/m)[0];
    assert.match(assets, /^COPY scripts \.\/scripts$/m);
    assert.ok(assets.indexOf('COPY scripts ./scripts') < assets.indexOf('RUN npm run production'));
    assert.doesNotMatch(assets, /webpack\.mix\.js/);
    assert.match(JSON.parse(read('package.json')).scripts.production, /node scripts\/build-assets\.mjs --production/);
    assert.ok(fs.existsSync(path.join(root, 'scripts/build-assets.mjs')));
});

test('Composer resolution target matches PHP 8.2 Apache runtime and lock metadata', () => {
    const target = composer.config.platform.php;
    assert.match(target, /^8\.2\.\d+$/);
    assert.match(docker, /^FROM php:8\.2-apache-bookworm$/m);
    assert.equal(lock['platform-overrides'].php, target);
    assert.equal(lock.platform.php, composer.require.php);
    assert.ok(admitsPhp(composer.require.php, target));
});

test('every locked production and development package admits the configured PHP 8.2 target', () => {
    for (const dependency of [...lock.packages, ...lock['packages-dev']]) {
        const requirement = dependency.require?.php;
        if (requirement) assert.ok(admitsPhp(requirement, composer.config.platform.php),
            `${dependency.name} ${dependency.version} requires PHP ${requirement}`);
    }
});

test('PHP constraint guard rejects newer minimums and unsupported syntax', () => {
    for (const range of ['^8.3', '>=8.3', '^8.4|^8.5', '8.3 - 8.6', '^7.4']) {
        assert.equal(admitsPhp(range, '8.2.30'), false, range);
    }
    for (const range of ['^8.2', '>=8.1', '^7.4 || ^8.0', '8.2 - 8.6']) {
        assert.equal(admitsPhp(range, '8.2.30'), true, range);
    }
    assert.throws(() => admitsPhp('unknown', '8.2.30'));
    assert.throws(() => admitsPhp('>=8.0 <8.2', '8.2.30'));
});
