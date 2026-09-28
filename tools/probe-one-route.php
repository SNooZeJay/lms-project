<?php

/*
 | Reproduces one route as one role and prints the failure in full.
 |
 | tools/probe-routes.php reports a status. This prints the reason behind it,
 | which is the only thing that can be acted on. The exception message and the
 | first few frames are shown; the whole stack would be noise, and the file and
 | line that appear at the top are almost always the ones that matter.
 |
 | Run: php tools/probe-one-route.php <routeName> [role] [param=value ...]
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate'.'\\Contracts'.'\\Console'.'\\Kernel')->bootstrap();

$db = 'Illuminate'.'\\Support'.'\\Facades'.'\\DB';
$userModel = 'App'.'\\Models'.'\\User';

$name = $argv[1] ?? null;

if ($name === null) {
    fwrite(STDERR, "usage: php tools/probe-one-route.php <routeName> [role] [param=value ...]\n");
    exit(1);
}

$role = $argv[2] ?? 'student';
$parameters = [];

foreach (array_slice($argv, 3) as $pair) {
    [$key, $value] = array_pad(explode('=', $pair, 2), 2, null);
    $parameters[$key] = $value;
}

$route = $app->make('router')->getRoutes()->getByName($name);

if ($route === null) {
    fwrite(STDERR, "  no route named {$name}\n");
    exit(1);
}

$userId = $role === 'guest'
    ? null
    : $db::table('profiles')->where('role', $role)->orderBy('user_id')->value('user_id');

$guard = $app['auth']->guard('web');

if ($userId === null) {
    $guard->forgetUser();
} else {
    $guard->setUser($userModel::query()->find($userId));
}

/*
 | The placeholders are substituted into the address and the route is bound.
 |
 | Passing values to Request::create as a third argument puts them in the request
 | body, not in the route, so route model binding finds nothing and every
 | parameterised route answers 404 regardless of who is asking.
 */
$uri = $route->uri();

foreach ($parameters as $key => $value) {
    $uri = str_replace(
        ['{'.$key.'}', '{'.$key.'?'],
        $value === null || $value === '' ? 'no-such-record' : (string) $value,
        $uri
    );
}

echo '  route   : '.$name."\n";
echo '  uri     : '.$uri."\n";
echo '  role    : '.$role.' (user id '.($userId ?? 'none').")\n";
echo '  params  : '.json_encode($parameters)."\n";
echo '  as      : '.$route->getActionName()."\n\n";

$request = $app->make('Illuminate'.'\\Http'.'\\Request')->create('/'.$uri, 'GET');
$request->setRouteResolver(fn () => $route);
$route->bind($request);

try {
    $response = $app->make('Illuminate'.'\\Contracts'.'\\Http'.'\\Kernel')->handle($request);

    echo '  status  : '.$response->getStatusCode()."\n";
} catch (Throwable $thrown) {
    echo '  THREW   : '.$thrown::class."\n";
    echo '  message : '.$thrown->getMessage()."\n";
    echo '  thrown  : '.basename($thrown->getFile()).':'.$thrown->getLine()."\n\n";

    echo "  first frames that belong to this application\n";

    $shown = 0;

    foreach ($thrown->getTrace() as $frame) {
        $file = $frame['file'] ?? '';

        // Vendor frames are noise here. The application's own frames are what
        // say where this went wrong.
        if ($file === '' || str_contains(str_replace('\\', '/', $file), '/vendor/')) {
            continue;
        }

        echo '    '.str_replace(base_path().'\\', '', $file).':'.($frame['line'] ?? '?')
            .'  '.($frame['function'] ?? '')."\n";

        if (++$shown >= 8) {
            break;
        }
    }
}
