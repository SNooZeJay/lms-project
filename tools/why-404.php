<?php

/*
 | Shows the body a probe would have thrown away.
 |
 | A status code says that something went wrong. The body says what, and for a
 | 404 the difference between "the router found nothing", "the model binding
 | found nothing", and "the controller decided to hide this" is the whole
 | diagnosis. A custom 404 page hides that difference, so the reason is
 | recovered by asking the controller directly as well.
 |
 | Run: php tools/why-404.php <routeName> [role] [param=value ...]
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate'.'\\Contracts'.'\\Console'.'\\Kernel')->bootstrap();

$db = 'Illuminate'.'\\Support'.'\\Facades'.'\\DB';
$userModel = 'App'.'\\Models'.'\\User';

$name = $argv[1] ?? null;

if ($name === null) {
    fwrite(STDERR, "usage: php tools/why-404.php <routeName> [role] [param=value ...]\n");
    exit(1);
}

$role = $argv[2] ?? 'guest';
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

$uri = $route->uri();

foreach ($parameters as $key => $value) {
    $uri = str_replace(['{'.$key.'}', '{'.$key.'?'], (string) $value, $uri);
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

$request = $app->make('Illuminate'.'\\Http'.'\\Request')->create('/'.ltrim($uri, '/'), 'GET');
$request->setRouteResolver(fn () => $route);
$route->bind($request);

$response = $app->make('Illuminate'.'\\Contracts'.'\\Http'.'\\Kernel')->handle($request);

echo '  uri     : /'.ltrim($uri, '/')."\n";
echo '  status  : '.$response->getStatusCode()."\n";
echo '  headers : '.json_encode(array_map(fn ($v) => implode(',', (array) $v), $response->headers->all()))."\n\n";

$body = (string) $response->getContent();

// Stripped of markup, because the question is which words are in the page, not
// how it is styled.
$text = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');

echo '  body length: '.strlen($body)."\n";
echo '  visible text: '.substr($text, 0, 400)."\n";
