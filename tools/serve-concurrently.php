<?php

/*
 | Serves the application from a pool of workers behind Apache.
 |
 | WHY THIS EXISTS
 |
 | The application was being served by PHP's built in web server, one process, on
 | one thread. That server answers one request at a time and cannot be given
 | more: the setting that would do it, PHP_CLI_SERVER_WORKERS, needs fork(),
 | and Windows has no fork(). PHP says so itself, in as many words:
 |
 |     forking is not supported on this platform
 |
 | The consequence was measured, not guessed. Throughput was about 23 requests a
 | second for the sign in page and about 9 for a signed in dashboard, and it was
 | the same at every level of concurrency: adding simultaneous requests bought
 | exactly nothing but waiting, and the time each one took grew in a straight
 | line with the number waiting. One page view costs four requests, so ten quick
 | refreshes put forty requests into a queue nine deep. Through a tunnel, those
 | multi second answers trip the tunnel's own timeouts, and what a browser shows
 | is a page that never finished. Stop refreshing and the queue drains, which is
 | why reloading sometimes brought it back.
 |
 | The fix is not to make the page smaller. It is to stop insisting on one
 | request at a time.
 |
 | WHAT THIS DOES
 |
 |   Apache              many connections at once, and it answers the
 |                       stylesheet, the script, the images and the icon from
 |                       disk without occupying a worker at all
 |     |
 |     +-- worker 1      php -S, one process, one request at a time
 |     +-- worker 2      the same application, the same router
 |     +-- ...           as many as the machine has processors for, plus a few
 |     +-- worker n      because a request waits on the database as often as
 |                       it burns a core
 |
 | Nothing here is new to this project. The workers are the same command the
 | project has always been served with. What is new is that there are several of
 | them and that one address in front of them, which is what turns a queue into
 | a queue with more than one place to stand.
 |
 | TWO FAULTS WORTH NAMING, BOTH FOUND BY MEASURING AND NOT BY READING
 |
 | First attempt was Apache proxying to php-cgi over FastCGI. It worked, and
 | six of them lifted throughput from 23 a second to 84. It then collapsed past
 | sixteen concurrent requests, answering 503, with a log full of "Got bogus
 | version 0". php-cgi is not a FastCGI server: it answers one request on a
 | connection and then misreads whatever arrives next on it, and Apache reuses
 | connections. The documented remedy, ProxySet keepalive=Off, is refused by
 | this Apache build, in the virtual host and in a per member section alike. A
 | ceiling that moves is not a ceiling that is gone, so php-cgi was dropped.
 |
 | Second fault was found by the browser test and not by any status code. With
 | the pool in place every page returned 200, and every page arrived with no
 | stylesheet. Apache replaces the Host header with the worker's own address
 | unless told not to, so every asset address the application generated pointed
 | at 127.0.0.1:8101, and the content security policy, which is doing its job,
 | refused them. ProxyPreserveHost On is the whole fix. Nothing in the test
 | suite and nothing in tools/probe-routes.php would ever have seen it, because
 | both of those run the application without a web server in front of it.
 |
 | Usage
 |
 |   php tools/serve-concurrently.php start  [--port=8000] [--workers=6] [--base=9100]
 |   php tools/serve-concurrently.php stop
 |   php tools/serve-concurrently.php status
 |   php tools/serve-concurrently.php check
 |
 | Options
 |
 |   --port=N     the address the tunnel should point at. Default 8000.
 |   --workers=N  how many application workers. Default 6.
 |   --base=N     first port for the workers. Default 9100.
 |   --apache=DIR where httpd.exe and conf/httpd.conf live. Default C:\xampp\apache.
 |   --local      local plain HTTP only. Marks session cookies not secure, so a
 |                probe can hold a session over http://127.0.0.1. Never use this
 |                for the public address, which is https.
 |
 | The Apache configuration it writes lives in storage/app/apache/PORT/ and is
 | generated, because it depends on which modules this machine's Apache has.
 | Committing a copy would guarantee it was wrong somewhere.
 */

const DEFAULT_PORT = 8000;
const DEFAULT_WORKERS = 6;
const DEFAULT_BASE = 9100;

$root = dirname(__DIR__);
$state = [
    'port' => DEFAULT_PORT,
    'workers' => DEFAULT_WORKERS,
    'base' => DEFAULT_BASE,
    'apache' => 'C:\\xampp\\apache',
    'local' => false,
];

foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--port=(\d+)$/', $argument, $m)) {
        $state['port'] = (int) $m[1];
    } elseif (preg_match('/^--workers=(\d+)$/', $argument, $m)) {
        $state['workers'] = (int) $m[1];
    } elseif (preg_match('/^--base=(\d+)$/', $argument, $m)) {
        $state['base'] = (int) $m[1];
    } elseif (preg_match('/^--apache=(.+)$/', $argument, $m)) {
        // Normalised to forward slashes, which is what the rest of the file uses
        // and what Apache expects in a directive.
        $state['apache'] = rtrim(str_replace('\\', '/', $m[1]), '/');
    } elseif ($argument === '--local') {
        $state['local'] = true;
    }
}

$command = $argv[1] ?? 'status';

$workerPorts = [];
for ($index = 0; $index < $state['workers']; $index++) {
    $workerPorts[] = $state['base'] + $index;
}

$router = $root.'/tools/server-router.php';
$public = $root.'/public';
// The configuration and everything Apache writes at runtime live under a
// directory named for the port. One shared directory cannot host two instances,
// because the balancer's shared memory file is created once and refuses to be
// created again.
$runtimeDirectory = $root.'/storage/app/apache/'.$state['port'];
$configDirectory = $runtimeDirectory;
$config = $runtimeDirectory.'/httpd-lms.conf';
$httpd = $state['apache'].'/bin/httpd.exe';
$httpdConf = $state['apache'].'/conf/httpd.conf';
$php = PHP_BINARY;

$separator = '  ';

// --- small helpers ---------------------------------------------------------

function line(string $text = ''): void
{
    global $separator;
    echo $separator.$text."\n";
}

function fail(string $text): never
{
    global $separator;
    fwrite(STDERR, $separator.$text."\n");
    exit(1);
}

/** The pids listening on a port, read from the operating system rather than remembered. */
function listenersOn(int $port): array
{
    $output = [];
    exec('netstat -ano -p tcp 2>NUL', $output, $ignored);

    $pids = [];
    foreach ($output as $row) {
        if (! preg_match('/^\s*TCP\s+\S+:'.preg_quote((string) $port, '/').'\s+\S+\s+LISTENING\s+(\d+)/i', $row, $m)) {
            continue;
        }
        $pids[(int) $m[1]] = true;
    }

    return array_keys($pids);
}

function commandLineOf(int $pid): string
{
    $output = [];
    $quoted = (int) $pid;
    exec(
        'powershell -NoProfile -NonInteractive -Command '
        .'"(Get-CimInstance Win32_Process -Filter \'ProcessId = '.$quoted.'\').CommandLine" 2>NUL',
        $output
    );

    return trim(implode(' ', $output));
}

function killPid(int $pid): bool
{
    exec('taskkill /F /PID '.(int) $pid.' >NUL 2>&1', $output, $code);

    return $code === 0;
}

function waitForPort(int $port, float $seconds = 12.0): bool
{
    $deadline = microtime(true) + $seconds;
    while (microtime(true) < $deadline) {
        if (listenersOn($port) !== []) {
            return true;
        }
        usleep(200000);
    }

    return false;
}

/** Start something that should outlive this script, on Windows. */
function startDetached(string $command): void
{
    // popen and pclose, not exec. Both shell out to `start /B`, but exec waits
    // for the process it started and the process it started is the worker, which
    // is meant to run for hours. Measured on this machine: exec returned after
    // 3.08 seconds for a command that slept three, and popen and pclose
    // returned in 0.03. The difference is one line and it is the difference
    // between a tool that starts and one that appears to hang.
    pclose(popen('start "" /B '.$command.' <NUL >NUL 2>&1', 'r'));
}

// --- the Apache configuration ---------------------------------------------

function buildConfiguration(array $state, array $workerPorts, string $apache, string $public, string $root): string
{
    $listen = $state['port'];
    $httpdConf = $apache.'/conf/httpd.conf';
    $source = file_get_contents($httpdConf);

    if ($source === false) {
        fail('could not read '.$httpdConf);
    }

    // The module list of the configuration that demonstrably works on this
    // machine, plus the proxy and balancer modules this arrangement needs.
    // Deriving it rather than writing it out is the difference between a file
    // that works here and a file that worked once.
    $wanted = [
        'proxy_module' => 'modules/mod_proxy.so',
        'proxy_http_module' => 'modules/mod_proxy_http.so',
        'proxy_balancer_module' => 'modules/mod_proxy_balancer.so',
        'lbmethod_byrequests_module' => 'modules/mod_lbmethod_byrequests.so',
        'slotmem_shm_module' => 'modules/mod_slotmem_shm.so',
    ];

    preg_match_all('/^\s*LoadModule\s+(\S+)\s+(modules\/\S+)/m', $source, $found, PREG_SET_ORDER);

    $modules = [];
    foreach ($found as $match) {
        if (isset($wanted[$match[1]])) {
            continue;
        }
        $modules[] = 'LoadModule '.$match[1].' '.$match[2];
    }
    foreach ($wanted as $name => $path) {
        if (is_file($apache.'/'.$path)) {
            $modules[] = 'LoadModule '.$name.' '.$path;
        }
    }

    // What Apache answers itself, read from the document root so that a new top
    // level file cannot quietly be handed to a worker.
    $servedHere = [];
    foreach (scandir($public) as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === 'index.php' || $entry === '.htaccess') {
            continue;
        }
        $servedHere[] = is_dir($public.'/'.$entry) ? '/'.$entry.'/' : '/'.$entry;
    }
    sort($servedHere);

    $forward = str_replace('\\', '/', $root);
    $publicForward = $forward.'/public';

    $out = [];
    $out[] = '# Generated by tools/serve-concurrently.php. Do not edit by hand.';
    $out[] = '#';
    $out[] = '# Regenerated every time the application is started, because it depends';
    $out[] = '# on which modules this machine\'s Apache has and on what is in public/.';
    $out[] = '';
    $out[] = '# Everything writable is under a directory named for the port, so two';
    $out[] = '# instances never fight. mod_proxy_balancer keeps its membership in a';
    $out[] = '# shared memory file inside DefaultRuntimeDir and refuses to start when';
    $out[] = '# that file already exists, so one shared directory means a second';
    $out[] = '# instance fails to start with "balancer slotmem_create failed" and';
    $out[] = '# nothing in the message says why. A probe instance on its own port is';
    $out[] = '# the ordinary reason to want two, so this is not a corner case.';
    $out[] = 'ServerRoot "'.$apache.'"';
    $out[] = 'Listen '.$listen;
    $out[] = 'TypesConfig conf/mime.types';
    $out[] = 'DefaultRuntimeDir "'.$forward.'/storage/app/apache/'.$listen.'"';
    $out[] = 'PidFile "'.$forward.'/storage/app/apache/'.$listen.'/httpd.pid"';
    $out[] = 'ErrorLog "'.$forward.'/storage/app/apache/'.$listen.'/error.log"';
    $out[] = 'CustomLog "'.$forward.'/storage/app/apache/'.$listen.'/access.log" common';
    $out[] = 'LogLevel warn';
    $out[] = 'ServerName localhost';
    $out[] = 'DirectoryIndex index.php';
    $out[] = '';
    foreach ($modules as $module) {
        $out[] = $module;
    }
    $out[] = '';
    $out[] = '# The pool. Spread by request count, so a slow page is not handed to the';
    $out[] = '# same worker twice in a row by chance alone.';
    $out[] = '<Proxy balancer://lmsworkers>';
    foreach ($workerPorts as $port) {
        $out[] = '    BalancerMember http://127.0.0.1:'.$port.' retry=1';
    }
    $out[] = '    ProxySet lbmethod=byrequests timeout=60';
    $out[] = '</Proxy>';
    $out[] = '';
    $out[] = '<VirtualHost *:'.$listen.'>';
    $out[] = '    ServerName localhost';
    $out[] = '    DocumentRoot "'.$publicForward.'"';
    $out[] = '';
    $out[] = '    <Directory "'.$publicForward.'">';
    $out[] = '        Options -Indexes +FollowSymLinks';
    $out[] = '        # The application\'s own .htaccess refuses dotfiles and project';
    $out[] = '        # files, and that is worth keeping in force for the files Apache';
    $out[] = '        # answers itself.';
    $out[] = '        AllowOverride All';
    $out[] = '        Require all granted';
    $out[] = '    </Directory>';
    $out[] = '';
    if ($state['local']) {
        $out[] = '    # LOCAL PLAIN HTTP ONLY. The public address is https, where the Secure';
        $out[] = '    # flag on the session cookie is correct and is left alone there.';
        $out[] = '    SetEnv SESSION_SECURE_COOKIE false';
        $out[] = '';
    }
    $out[] = '    # Static files are answered from disk here, so a page view does not spend';
    $out[] = '    # a worker on its stylesheet, its script and its images.';
    foreach ($servedHere as $path) {
        $out[] = '    ProxyPass '.$path.' !';
    }
    $out[] = '    # Everything else is a worker. A worker runs the project\'s own router,';
    $out[] = '    # so the front controller is resolved there and not here.';
    $out[] = '    ProxyPass / balancer://lmsworkers/';
    $out[] = '';
    $out[] = '    # Keep the host the visitor asked for. Without this Apache replaces the';
    $out[] = '    # Host header with the worker\'s own address, every asset address the';
    $out[] = '    # application then generates points at 127.0.0.1:9100 or whichever';
    $out[] = '    # worker answered, and the content security policy refuses them, so every';
    $out[] = '    # page arrives with no stylesheet. It looks like a rendering fault in the';
    $out[] = '    # browser and is entirely a header fault here.';
    $out[] = '    ProxyPreserveHost On';
    $out[] = '';
    $out[] = '</VirtualHost>';
    $out[] = '';

    return implode("\r\n", $out);
}

// --- commands --------------------------------------------------------------

function describe(array $state, array $workerPorts, string $httpd, string $config): void
{
    line();
    line('the front door  : http://127.0.0.1:'.$state['port']);
    line('the workers      : '.count($workerPorts).' on '.implode(', ', $workerPorts));
    line('the apache       : '.$httpd);
    line('the configuration: '.$config);
    line();
}

if ($command === 'start') {
    if (! is_file($php)) {
        fail('no PHP binary at '.$php);
    }
    if (! is_file($httpd)) {
        fail('no httpd.exe at '.$httpd.'. Pass --apache=DIR.');
    }
    if (! is_file($router)) {
        fail('no router at '.$router);
    }

    // Clear the ports this arrangement owns, so starting twice does not leave a
    // pool behind the new one.
    foreach (array_merge([$state['port']], $workerPorts) as $port) {
        foreach (listenersOn($port) as $pid) {
            $running = commandLineOf($pid);
            $ours = str_contains($running, 'httpd-lms.conf') || str_contains($running, 'server-router.php');
            if (! $ours) {
                line('  port '.$port.' is held by something else, and left alone:');
                line('    '.$running);

                continue;
            }
            killPid($pid);
            line('  released port '.$port.' from a previous run');
        }
    }

    if (! is_dir($configDirectory)) {
        mkdir($configDirectory, 0777, true);
    }
    file_put_contents($config, buildConfiguration($state, $workerPorts, $state['apache'], $public, $root));

    $check = [];
    exec('"'.$httpd.'" -f "'.$config.'" -t 2>&1', $check, $code);
    if ($code !== 0) {
        line('  Apache rejected the configuration it was just given:');
        foreach ($check as $row) {
            line('    '.$row);
        }
        exit(1);
    }
    line('  the configuration parses');

    foreach ($workerPorts as $port) {
        startDetached(
            '"'.$php.'" -S 127.0.0.1:'.$port.' -t "'.$public.'" "'.$router.'"'
        );
    }
    line('  asked for '.count($workerPorts).' workers');

    $missing = [];
    foreach ($workerPorts as $port) {
        if (! waitForPort($port, 10.0)) {
            $missing[] = $port;
        }
    }
    if ($missing !== []) {
        line('  these workers never came up: '.implode(', ', $missing));
        line('  check that nothing else has taken those ports');
        exit(1);
    }

    startDetached('"'.$httpd.'" -f "'.$config.'"');
    if (! waitForPort($state['port'], 15.0)) {
        line('  Apache never took port '.$state['port']);
        line('  its own error log is at storage/app/apache/'.$state['port'].'/error.log');
        exit(1);
    }

    describe($state, $workerPorts, $httpd, $config);

    // Prove it answers before claiming it works.
    $context = stream_context_create(['http' => ['timeout' => 20, 'ignore_errors' => true]]);
    $body = @file_get_contents('http://127.0.0.1:'.$state['port'].'/login', false, $context);
    $status = $http_response_header[0] ?? 'no response';

    line('  the sign in page: '.$status.', '.strlen((string) $body).' bytes');

    if (str_contains((string) $body, 'name="password"')) {
        line('  it is a real rendered page, not a stylesheet served as text');
    } else {
        line('  it did not look like the sign in page. Stopping here rather than');
        line('  reporting a start that did not work.');
        exit(1);
    }

    line();
    line('  point the tunnel at port '.$state['port'].' and nothing needs changing else.');
    line();

    exit(0);
}

if ($command === 'stop') {
    $stopped = 0;
    foreach (array_merge([$state['port']], $workerPorts) as $port) {
        foreach (listenersOn($port) as $pid) {
            $running = commandLineOf($pid);
            if (! str_contains($running, 'httpd-lms.conf') && ! str_contains($running, 'server-router.php')) {
                continue;
            }
            if (killPid($pid)) {
                $stopped++;
                line('  stopped the process holding port '.$port);
            }
        }
    }
    line();
    line('  stopped '.$stopped.' processes. Anything that was already running before is untouched.');
    line();

    exit(0);
}

if ($command === 'status') {
    describe($state, $workerPorts, $httpd, $config);

    $front = listenersOn($state['port']);
    line('  front door : '.(count($front) > 0 ? 'up, pid '.implode(', ', $front) : 'not listening'));

    $up = 0;
    foreach ($workerPorts as $port) {
        if (listenersOn($port) !== []) {
            $up++;
        }
    }
    line('  workers    : '.$up.' of '.count($workerPorts).' listening');

    if (is_file($config)) {
        line('  config     : written '.date('H:i', filemtime($config)));
    } else {
        line('  config     : not written yet, the application has not been started');
    }

    $log = $runtimeDirectory.'/error.log';
    if (is_file($log) && filesize($log) > 0) {
        $recent = [];
        exec('powershell -NoProfile -NonInteractive -Command "Get-Content -Tail 5 -Path \''.str_replace("'", "''", $log).'\'" 2>NUL', $recent);
        line('  recent errors:');
        foreach ($recent as $row) {
            line('    '.$row);
        }
    }

    line();

    exit(0);
}

if ($command === 'check') {
    // Answers one request per role of route and reports what came back, over
    // real HTTP, so the serving layer is measured rather than assumed. It only
    // reports; the route probe in tools/ is the one that judges a policy.
    $context = stream_context_create(['http' => ['timeout' => 25, 'ignore_errors' => true]]);
    $paths = ['/login', '/up', '/build/assets/manifest.json'];

    line();
    line('  asking '.$state['port'].' for a few things:');
    line();

    foreach ($paths as $path) {
        $body = @file_get_contents('http://127.0.0.1:'.$state['port'].$path, false, $context);
        $status = $http_response_header[0] ?? 'no response';
        line('  '.str_pad($path, 34).$status.', '.strlen((string) $body).' bytes');
    }

    line();

    exit(0);
}

fail('unknown command "'.$command.'". Use start, stop, status or check.');
