const assert = require('node:assert/strict');
const fs = require('node:fs');
const { spawnSync } = require('node:child_process');
const path = require('node:path');
const shell = process.platform === 'win32' ? 'C:/Program Files/Git/bin/bash.exe' : '/bin/sh';
const source = fs.readFileSync(path.join(__dirname, '../docker/start-apache.sh'), 'utf8').replace(/\r\n/g, '\n');
const startup = source.slice(source.indexOf('# Provision accounts'));
function checkStartup(render, phpStatus) {
    // Stub processes only; run the actual deployment branch and retry loop.
    const script = `set -eu
calls=0
php() { calls=$((calls + 1)); echo BOOTSTRAP_CALL; return ${phpStatus}; }
sleep() { :; }
apache2-foreground() { echo APACHE_STARTED; }
${startup.replace('exec apache2-foreground', 'apache2-foreground')}`;
    return spawnSync(shell, ['-s'], { input: script, encoding: 'utf8', env: { ...process.env, RENDER: render } });
}
const success = checkStartup('true', 0);
assert.equal(success.status, 0, success.stderr);
assert.equal((success.stdout.match(/BOOTSTRAP_CALL/g) || []).length, 1);
assert.match(success.stdout, /APACHE_STARTED/);
const failure = checkStartup('true', 1);
assert.equal(failure.status, 1, failure.stderr);
assert.equal((failure.stdout.match(/BOOTSTRAP_CALL/g) || []).length, 20);
assert.doesNotMatch(failure.stdout, /APACHE_STARTED/);
const other = checkStartup('false', 0);
assert.equal(other.status, 0, other.stderr);
assert.doesNotMatch(other.stdout, /BOOTSTRAP_CALL/);
const php = process.platform === 'win32' ? 'C:/xampppp/php/php.exe' : 'php';
const guard = spawnSync(php, [path.join(__dirname, '../scripts/bootstrap_render.php')], {
    encoding: 'utf8', env: { ...process.env, RENDER: 'false' },
});
assert.equal(guard.status, 1);
assert.match(guard.stderr, /must run inside the Render service/);
console.log('Render startup success, 20-attempt failure, no premature Apache start, and non-Render provisioning guard passed.');
