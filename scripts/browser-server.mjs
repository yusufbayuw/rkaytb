import { spawn, spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));
process.chdir(root);
fs.mkdirSync('.tools', { recursive: true });
const php = process.env.PHP_BINARY || (process.platform === 'win32' && fs.existsSync('.tools/php84/php.exe')
    ? path.join(root, '.tools/php84/php.exe') : 'php');
const env = {
    ...process.env,
    APP_ENV: 'browser-testing', APP_DEBUG: 'false', APP_URL: 'http://127.0.0.1:8771', ASSET_URL: 'http://127.0.0.1:8771',
    APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
    DB_CONNECTION: 'sqlite', DB_DATABASE: path.join(root, '.tools/rka_browser.sqlite'),
    CACHE_DRIVER: 'file', SESSION_DRIVER: 'file', SESSION_SECURE_COOKIE: 'false',
    QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array', BCRYPT_ROUNDS: '4',
    RKA_TASK_REMINDER_MAIL_ENABLED: 'false', RKA_ENABLE_OFFICIAL_PUBLICATION: 'false',
    LARAVEL_STORAGE_PATH: path.join(root, '.tools/browser-storage'),
};
for (const directory of ['logs', 'app', 'framework/views', 'framework/sessions', 'framework/cache/data']) {
    fs.mkdirSync(path.join(env.LARAVEL_STORAGE_PATH, directory), { recursive: true });
}
fs.closeSync(fs.openSync(env.DB_DATABASE, 'a'));
for (const args of [
    ['artisan', 'migrate:fresh', '--force', '--no-interaction'],
    ['artisan', 'db:seed', '--class=Database\\Seeders\\BrowserTestSeeder', '--force', '--no-interaction'],
]) {
    const result = spawnSync(php, args, { env, stdio: 'inherit', cwd: root });
    if (result.error) throw result.error;
    if (result.status !== 0) process.exit(result.status ?? 1);
}
const server = spawn(php, ['-d', 'upload_max_filesize=12M', '-d', 'post_max_size=16M', '-d', 'max_execution_time=180',
    '-d', `error_log=${path.join(root, '.tools/browser-php-errors.log')}`, '-S', '127.0.0.1:8771', '-t', path.join(root, 'public'),
    path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
    { env, stdio: 'inherit', cwd: path.join(root, 'public') });
const serverMetadata = path.join(root, '.tools/browser-server.json');
const stopFile = path.join(root, '.tools/browser-server-stop.json');
const stopToken = randomBytes(24).toString('hex');
fs.writeFileSync(serverMetadata, JSON.stringify({ stopToken }));
let stopping = false;
const stop = () => { stopping = true; server.kill(); };
// Windows can retain the PHP child during Playwright's process-tree cleanup.
// Teardown signals this parent, which only terminates its own child process.
const stopWatcher = setInterval(() => {
    try {
        if (JSON.parse(fs.readFileSync(stopFile, 'utf8')).stopToken === stopToken) stop();
    } catch { /* No matching stop request. */ }
}, 200);
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, stop);
server.on('exit', code => {
    clearInterval(stopWatcher);
    for (const file of [serverMetadata, stopFile]) {
        try {
            if (JSON.parse(fs.readFileSync(file, 'utf8')).stopToken === stopToken) fs.unlinkSync(file);
        } catch { /* Another run owns the file, or it is already absent. */ }
    }
    process.exit(stopping ? 0 : (code ?? 0));
});
