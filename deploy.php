<?php

namespace Deployer;

require 'recipe/common.php';

/*
 * Host data is NOT part of this file — the repository is public.
 *
 * Every target reads its connection from a local, git-ignored env file in the
 * project root: `.env` for live, `.env.staging` for stage (see .env.example):
 *
 *   REMOTE_SSH_ALIAS     Host alias from ~/.ssh/config (hostname, user, key)
 *   REMOTE_PROJECT_ROOT  Deployer's deploy_path on the server
 *   DOMAIN               Public domain without scheme
 *   PHP_BIN              Optional: PHP binary on the server, if /usr/bin/php is too old
 */
function targetEnv(string $file): array
{
    $path = __DIR__ . '/' . $file;
    if (!is_file($path)) {
        return [];
    }
    // RAW: values such as argon2 hashes contain "$" and must stay untouched
    return parse_ini_file($path, false, INI_SCANNER_RAW) ?: [];
}

function targetHost(string $alias, string $envFile, string $branch, string $context, int $keepReleases): void
{
    $env = targetEnv($envFile);
    $sshAlias = $env['REMOTE_SSH_ALIAS'] ?? '';

    host($alias)
        // Without an alias the host still exists, so `dep` lists it, but every
        // remote command fails — the check task below names the missing file.
        ->setHostname($sshAlias !== '' ? $sshAlias : 'unconfigured.invalid')
        ->set('ssh_alias', $sshAlias)
        ->set('env_file', $envFile)
        ->set('labels', ['stage' => $alias])
        ->set('branch', $branch)
        ->set('typo3_context', $context)
        ->set('deploy_path', $env['REMOTE_PROJECT_ROOT'] ?? '')
        ->set('public_url', ($env['DOMAIN'] ?? '') !== '' ? 'https://' . $env['DOMAIN'] : '')
        ->set('php_bin', $env['PHP_BIN'] ?? '')
        ->set('keep_releases', $keepReleases);
}

set('application', 'KJRS 2026');
set('repository', 'git@github.com:hempelta/kjrs-2026.git');
set('keep_releases', 10);
set('http_user', 'www-data');
set('writable_mode', 'chmod');
set('ssh_multiplexing', false);
set('release_name', date('Y-m-d_H-i-s'));
set('typo3_context', 'Production');

targetHost('live', '.env', 'main', 'Production', 10);
targetHost('stage', '.env.staging', 'develop', 'Production/Staging', 6);

set('bin/php', function () {
    return get('php_bin') !== '' ? get('php_bin') : which('php');
});

/*
 * Composer triggers TYPO3's post-autoload scripts, which load
 * config/system/additional.php. Without TYPO3_CONTEXT that file reads the wrong
 * env file for the target and aborts with "These environment variables must
 * not be empty" — the message points at the env file, but the cause is the
 * missing context.
 */
set('bin/composer', function () {
    return 'TYPO3_CONTEXT={{typo3_context}} {{bin/php}} ' . which('composer');
});

/*
 * URL used to reset the opcache. Defaults to the public URL. A target behind
 * an HTTP auth prompt either exempts `_dep_opcache_reset_*.php` from the prompt
 * in its shared public/.htaccess, or the deploying machine sets
 * DEPLOY_HTTP_AUTH (user:password) — never put the password in this file.
 */
set('opcache_url', function () {
    return get('public_url');
});

set('typo3_webroot', 'public');

set('shared_dirs', [
    '{{typo3_webroot}}/fileadmin',
    '{{typo3_webroot}}/typo3temp',
    /*
     * Downloaded backend language packs. var/ is writable but NOT shared, so
     * every release starts with an empty var/ — without this entry the packs
     * vanish on each deploy and labels silently fall back to English.
     * Deliberately only var/labels: cache, logs and locks should be fresh
     * per release.
     */
    'var/labels',
]);

/*
 * Files that differ per environment or carry credentials. They live once in
 * shared/ and are linked into every release. The .htaccess is shared so a
 * stage can carry its own access protection without the repository needing
 * environment-specific files.
 *
 * config/system/additional.php is NOT shared: in this project it is versioned
 * and identical for all environments; it picks the env file by context.
 */
set('shared_files', [
    'auth.json',
    '.env',
    '.env.staging',
    '{{typo3_webroot}}/.htaccess',
    '.htpasswd',
]);

set('writable_dirs', [
    'config',
    'var',
    '{{typo3_webroot}}/fileadmin',
    '{{typo3_webroot}}/typo3temp',
]);

task('deploy:check', function () {
    $missing = [];
    foreach (['ssh_alias' => 'REMOTE_SSH_ALIAS', 'deploy_path' => 'REMOTE_PROJECT_ROOT', 'public_url' => 'DOMAIN'] as $key => $name) {
        if (get($key) === '') {
            $missing[] = $name;
        }
    }
    if ($missing !== []) {
        throw new \RuntimeException(sprintf(
            'Target "%s" is not configured: %s missing in %s (see .env.example).',
            currentHost()->getAlias(),
            implode(', ', $missing),
            get('env_file')
        ));
    }
})->desc('Fail early when the local env file lacks the target configuration');

/*
 * Some managed hosts point /usr/bin/php at an older version than composer.json
 * requires, and let the account repoint it inside its own view. bin/composer
 * already runs under PHP_BIN, but vendor/bin/typo3 — started by Composer's
 * post-autoload scripts — resolves /usr/bin/php through its shebang.
 *
 * Only runs when PHP_BIN is set, and only touches the link when it does not
 * already point there.
 */
task('server:php', function () {
    $phpBin = get('php_bin');
    if ($phpBin === '') {
        writeln('  PHP_BIN not set — /usr/bin/php is used as is.');
        return;
    }
    if (test("[ \"$(readlink -f /usr/bin/php)\" = \"$(readlink -f $phpBin)\" ]")) {
        writeln('  /usr/bin/php already points to ' . $phpBin . '.');
        return;
    }
    run("ln -sfn $phpBin /usr/bin/php");
    run('/usr/bin/php -v | head -1');
})->desc('Point /usr/bin/php at PHP_BIN when the host allows it');

/*
 * The frontend build runs INSIDE DDEV. The webpack chain needs Node >= 20.9
 * (Array.prototype.toSorted); an older Node on the deploying machine breaks the
 * build with "toSorted is not a function". Through DDEV the release no longer
 * depends on the local Node version. The upload stays on the machine: the SSH
 * key is there, not in the container.
 */
task('build:frontend', function () {
    runLocally('ddev composer fe-build', ['timeout' => 600]);
})->desc('Build frontend assets in DDEV');

/*
 * Ship the frontend build result.
 *
 * Website/SVG holds the complete Font Awesome Pro icon set copied by webpack —
 * tens of thousands of files that only change with a library update. Everything
 * else (styles, scripts, fonts, logos) is small and changes with every build.
 * Streaming all of it over one SSH connection on every deploy is slow and a
 * single network hiccup aborts it.
 *
 * 1. If the icon set in the RUNNING release (`current`) has the same
 *    fingerprint as the local one, it is taken over by hardlink (`cp -al`):
 *    no transfer, no disk space.
 * 2. Then only the small parts are uploaded.
 * 3. Without a running release, or with a different fingerprint, everything
 *    is streamed.
 *
 * Compared against `current`, NOT `previous_release`: Deployer sets
 * previous_release to the last CREATED release, including an aborted one with
 * a half-transferred icon set. `current` only moves after a complete deploy.
 *
 * Hardlinks are only safe because nothing writes INTO the files: the small
 * parts are deleted and recreated, never overwritten, otherwise tar would write
 * through the links into the previous release.
 *
 * The fingerprint is a checksum over the file LIST, not the contents — it
 * detects a library update, not a changed icon under the same name. LC_ALL=C
 * is required: macOS and Linux sort differently under other locales, which
 * would make the fingerprints never match.
 */
task('upload:frontend', function () {
    $source = 'vendor/oliverthiele/ot-febuild/Resources/Public/Assets';
    $remotePath = '{{release_path}}/vendor/oliverthiele/ot-febuild/Resources/Public/Assets';
    $current = '{{deploy_path}}/current/vendor/oliverthiele/ot-febuild/Resources/Public/Assets';
    $ssh = 'ssh -o ServerAliveInterval=15 -o ServerAliveCountMax=8 {{ssh_alias}}';

    // No nested quotes: they do not survive SSH plus Deployer's own escaping
    $fingerprint = static fn(string $path): string => "cd {$path} 2>/dev/null && find . -type f | LC_ALL=C sort | cksum || echo none";

    $local = trim(runLocally($fingerprint("{$source}/Website/SVG")));
    $remote = trim(run($fingerprint("{$current}/Website/SVG")));

    if ($local !== 'none' && $local === $remote) {
        writeln("  Icon set unchanged ({$local}) — hardlinked from the running release.");
        run("rm -rf {$remotePath} && mkdir -p $(dirname {$remotePath}) && cp -al {$current} {$remotePath}");

        // Everything except Website/SVG, derived from the local build
        $parts = [];
        foreach (glob($source . '/*', GLOB_ONLYDIR) as $dir) {
            if (basename($dir) !== 'Website') {
                $parts[] = basename($dir);
                continue;
            }
            foreach (glob($dir . '/*', GLOB_ONLYDIR) as $sub) {
                if (basename($sub) !== 'SVG') {
                    $parts[] = 'Website/' . basename($sub);
                }
            }
        }

        foreach ($parts as $part) {
            // Delete, do NOT overwrite — see the hardlink note above
            run("rm -rf {$remotePath}/{$part} && mkdir -p {$remotePath}/{$part}");
            runLocally("tar czf - -C {$source}/{$part} . | {$ssh} 'tar xzf - -C {$remotePath}/{$part}'");
        }
    } else {
        writeln("  Icon set differs (local {$local}, server {$remote}) — full transfer.");
        $remoteCmd = "rm -rf {$remotePath} && mkdir -p {$remotePath} && tar xzf - -C {$remotePath}";
        runLocally("tar czf - -C {$source} . | {$ssh} '{$remoteCmd}'");
    }

    run("find {$remotePath} -type d -exec chmod 2775 {} + -o -type f -exec chmod 0664 {} +");
})->desc('Upload frontend build assets (hardlinks the unchanged icon set from the running release)');

/*
 * Preserve backend changes to config/sites before the release overwrites them.
 *
 * config/sites is NOT shared, so every release gets the repository version.
 * Saving in the backend's Site Management writes into the RUNNING release
 * (config is writable): the change works immediately and is gone with the next
 * deploy, without warning.
 *
 * This task does not prevent the overwrite — the repository stays the source,
 * by decision. It removes the silence: if the running release differs from the
 * new one, the running version is backed up and the difference reported.
 *
 * No abort: a deploy that stops because someone changed a setting in the
 * backend would stop at the worst moment, and nothing is lost after the backup.
 *
 * Why not share config/sites: backend changes would survive, but repository
 * changes would no longer arrive — "the repository wins" would silently become
 * "the server wins".
 *
 * Why not lock the backend module: it is admin-only in the core, and module
 * restrictions do not bind administrators.
 */
task('deploy:siteconfig', function () {
    $old = '{{deploy_path}}/current/config/sites';
    $new = '{{release_path}}/config/sites';

    if (!test("[ -d $old ]")) {
        writeln('  Site configuration: no running release — nothing to compare.');
        return;
    }

    $diff = run("diff -rq $old $new 2>&1 || true");
    if (trim($diff) === '') {
        writeln('  Site configuration: unchanged.');
        return;
    }

    $backup = '{{deploy_path}}/shared/siteconfig-backups/' . date('Y-m-d_H-i-s');
    run("mkdir -p $backup && cp -a $old/. $backup/");

    writeln('');
    writeln('  ⛔ THE SITE CONFIGURATION IN THE RUNNING RELEASE DIFFERS.');
    writeln('     Probably a backend change in Site Management.');
    writeln('');
    foreach (explode("\n", trim($diff)) as $line) {
        writeln('     ' . $line);
    }
    writeln('');
    writeln("     Backed up to: $backup");
    writeln('     The release now overwrites it with the repository version.');
    writeln('     Whatever should stay belongs in the repository:');
    writeln("       diff -u $backup/main/settings.yaml config/sites/main/settings.yaml");
    writeln('');
    /*
     * TYPO3 only writes back what its form knows: keys it does not know and
     * all comments are lost on save. The backup is therefore not automatically
     * the better version — hence a manual diff, no automatic takeover.
     */
})->desc('Preserve backend changes to config/sites before the release overwrites them');

/*
 * database:updateschema "safe": additive only, never drops on production.
 * upgrade:run is idempotent; only pending wizards execute.
 */
task('deploy:typo3', function () {
    $typo3 = 'cd {{release_path}} && TYPO3_CONTEXT={{typo3_context}} {{bin/php}} {{release_path}}/vendor/bin/typo3';

    run("$typo3 database:updateschema safe --no-interaction");
    run("$typo3 upgrade:run --no-interaction");
    run("$typo3 cache:flush");
})->desc('Apply TYPO3 schema updates, run upgrade wizards and flush caches');

/*
 * Language packs: check, and download only when NONE are present.
 *
 * language:update is deliberately not part of every deploy: it fetches from
 * the TYPO3 translation server, and the release would depend on a third-party
 * service each time. Here it only acts on an empty directory — after a fresh
 * setup or after someone cleared it.
 *
 * No abort: missing translations are a defect, not a reason to withhold a
 * finished release. But they are named.
 */
task('deploy:languages', function () {
    $typo3 = 'cd {{release_path}} && TYPO3_CONTEXT={{typo3_context}} {{bin/php}} {{release_path}}/vendor/bin/typo3';
    $count = "find {{deploy_path}}/shared/var/labels -name '*.xlf' 2>/dev/null | wc -l | tr -d ' '";

    $before = (int)trim((string)run($count));
    if ($before > 0) {
        writeln(sprintf('<info>  Language packs: %d files present.</info>', $before));
        return;
    }

    writeln('<comment>  Language packs missing — downloading (language:update de).</comment>');
    $raw = (string)run(
        "$typo3 language:update de --no-progress --no-interaction 2>&1; printf '§%s' \"$?\"",
        ['timeout' => 600]
    );
    $parts = explode('§', $raw);
    $code = trim((string)array_pop($parts));
    $after = (int)trim((string)run($count));

    if ($after > 0) {
        run("$typo3 cache:flush");
        writeln(sprintf('<info>  Language packs downloaded: 0 → %d files, cache flushed.</info>', $after));
        return;
    }

    writeln(sprintf(
        '<comment>  WARNING: language packs STILL missing (exit code %s). Labels from third-party</comment>' . PHP_EOL
        . '<comment>  extensions show in ENGLISH. Record it, do not skip it:</comment>' . PHP_EOL . '%s',
        $code === '' ? '?' : $code,
        trim(implode('§', $parts))
    ));
})->desc('Download language packs when none are present — missing packs show English labels');

/*
 * Reset the PHP-FPM opcache after the symlink switch and PROVE that the web
 * process serves the new release.
 *
 * FPM keeps the previous release compiled; without a reset the site keeps
 * serving old code until the opcache expires. The CLI cannot reset the FPM
 * opcache, so a short-lived script is requested over the public URL.
 *
 * Never fail silently: a reset request answered with 401 or 404 used to be
 * hidden behind `|| true`, while the deploy reported success and old code ran.
 * One request with one status code proves nothing about which release a web
 * process executes — `realpath_cache_ttl` lets a process resolve `current` to
 * the previous release for up to its TTL (120 s by default).
 *
 * Therefore:
 * 1. The script reports WHICH release it runs from, whether opcache_reset()
 *    succeeded, and clears the realpath cache of its process.
 * 2. Retry until the release `current` points to answers — up to 135 s, beyond
 *    the default 120 s realpath TTL.
 * 3. Confirmation round: six consecutive requests must all report the new
 *    release. Each hits a process and resets it.
 * 4. If that fails, cache, database and languages still run (the release is
 *    already live) — but deploy:proof fails the deploy at the end instead of
 *    reporting success.
 *
 * The script goes to `current/public`, NOT `release_path/public`: the web
 * server serves from `current`, a file under release_path does not exist for it.
 *
 * The result goes through a file (.dep/opcache_proof), not a Deployer variable,
 * so it also holds when deploy:opcache is called on its own.
 */
task('deploy:opcache', function () {
    // Expected is what `current` points to ON THE SERVER
    $release = trim((string)run('basename "$(readlink {{deploy_path}}/current)"'));
    $script = '_dep_opcache_reset_' . bin2hex(random_bytes(6)) . '.php';
    $target = '{{deploy_path}}/current/public/' . $script;
    run('rm -f {{deploy_path}}/.dep/opcache_proof');
    run("printf '%s' "
        . escapeshellarg('<?php header("Cache-Control: no-store"); $ok = function_exists("opcache_reset") && opcache_reset(); clearstatcache(true); echo basename(dirname(__DIR__)), "|", $ok ? "reset" : "NO-RESET";')
        . " > $target");

    $auth = getenv('DEPLOY_HTTP_AUTH');
    $credentials = $auth ? '-u ' . escapeshellarg($auth) . ' ' : '';
    $url = get('opcache_url');
    $expected = $release . '|reset';

    // One request: status code and (truncated) body, never an abort
    $request = function () use ($credentials, $url, $script): array {
        $response = (string)runLocally(
            "curl -s -m 20 -w '§%{http_code}' $credentials$url/$script"
            . " | tr -d '\\r\\n' | tail -c 300 || echo '§000'"
        );
        $parts = explode('§', $response);
        $code = trim((string)array_pop($parts));
        return [$code, trim(implode('§', $parts))];
    };

    $start = microtime(true);
    $attempts = [];
    $first = null;
    $confirmed = 0;
    $last = ['', ''];
    try {
        while (microtime(true) - $start < 135) {
            [$code, $body] = $request();
            $last = [$code, $body];
            $hit = $code === '200' && $body === $expected;
            // "old": 200, but another release or no reset
            $attempts[] = $hit ? 'ok' : ($code === '200' ? 'old' : $code);

            if ($hit) {
                $first ??= microtime(true) - $start;
                if (++$confirmed >= 6) {
                    break;
                }
                usleep(500000);
                continue;
            }

            $confirmed = 0;
            if ($code === '401') {
                // Auth prompt: waiting does not change it
                break;
            }
            sleep(3);
        }

        $duration = microtime(true) - $start;
        $tally = array_count_values($attempts);
        $history = implode(' ', array_map(
            static fn($k, int $n): string => $k . '×' . $n,
            array_keys($tally),
            $tally
        ));

        if ($confirmed >= 6) {
            run('printf %s ' . escapeshellarg($release) . ' > {{deploy_path}}/.dep/opcache_proof');
            writeln(sprintf(
                '<info>  Opcache reset and proven: %s answers (after %.1f s, %d requests: %s).</info>',
                $release,
                $first,
                count($attempts),
                $history
            ));
            if ($attempts[0] !== 'ok') {
                writeln('<comment>  Not on the first request — only proven through the retry.</comment>');
            }
            return;
        }

        $onServer = trim((string)run(
            "cd {{deploy_path}} && printf 'current -> %s | file: %s' "
            . "\"$(readlink current)\" "
            . "\"$(test -f current/public/$script && echo present || echo MISSING)\""
        ));
        writeln(sprintf(
            '<error>  Opcache NOT proven after %.0f s (%d requests: %s) via %s.</error>',
            $duration,
            count($attempts),
            $history,
            $url
        ));
        writeln(sprintf(
            '<comment>  Last response: HTTP %s — %s</comment>',
            $last[0],
            $last[1] === '' ? '(empty)' : $last[1]
        ));
        writeln(sprintf('<comment>  Seen on the server: %s</comment>', $onServer));
        if ($last[0] === '401') {
            writeln('<comment>  Cause: auth prompt. Fix: set DEPLOY_HTTP_AUTH or exempt '
                . '_dep_opcache_reset_*.php from the prompt in the .htaccess.</comment>');
        }
    } finally {
        run("rm -f $target");
    }
})->desc('Reset PHP-FPM opcache via HTTP and prove the web process runs the new release');

/*
 * Fails the deploy when the opcache reset is not proven — only at the END, so
 * cache, database and languages are still brought up to date. A failure here
 * means: the new release is switched, but it is not proven that the web
 * process executes it.
 */
task('deploy:proof', function () {
    $release = trim((string)run('basename "$(readlink {{deploy_path}}/current)"'));
    $proven = trim((string)run('cat {{deploy_path}}/.dep/opcache_proof 2>/dev/null || true'));
    if ($proven === $release) {
        return;
    }
    throw new \RuntimeException(
        "Release $release is switched, but the web process did not prove that it runs it "
        . '(see deploy:opcache). Check the frontend by hand and repeat the reset: '
        . 'dep deploy:opcache ' . currentHost()->getAlias() . '.'
    );
})->desc('Fail the deploy when deploy:opcache could not prove the new release is served');

task('deploy', [
    'deploy:check',
    'deploy:prepare',
    // Right after deploy:prepare: the new release exists, `current` still
    // points to the old one. Only in this window are both comparable.
    'deploy:siteconfig',
    'server:php',
    'build:frontend',
    'deploy:vendors',
    'upload:frontend',
    // NOT deploy:publish from recipe/common.php: that bundle contains
    // deploy:success and would report "successfully deployed!" here, BEFORE
    // reset and proof. Its other parts run individually; deploy:success is
    // attached via after() at the very end.
    'deploy:symlink',
    'deploy:unlock',
    'deploy:opcache',
    'deploy:typo3',
    'deploy:languages',
    'deploy:cleanup',
    // Last: fails when deploy:opcache could not prove the release
    'deploy:proof',
])->desc('Deploy the project');
after('deploy', 'deploy:success');

after('deploy:failed', 'deploy:unlock');
