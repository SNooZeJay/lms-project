<?php

/*
 | The router that lets PHP's built in web server serve a Laravel application.
 |
 | Without one, `php -S` hands every request to index.php, including requests
 | for a stylesheet or a script. The application then answers those with its 404
 | page, and the browser is handed HTML where it asked for CSS, so the page
 | renders unstyled. The measured symptom was a 200 response with a content type
 | of text/html for a request to a .css file.
 |
 | Returning false tells the built in server to serve the file itself. Anything
 | that is not a real file goes to the front controller as normal, so the
 | application's own front controller decides what a path means. That is the
 | rule a real web server applies too, which is why the two behave the same.
 |
 | Two things are deliberate:
 |
 | A path that contains ".." is refused outright. The request path is the
 | browser's to choose, and the built in server resolves it against the
 | document root. Deciding that here, before any file lookup, is cheaper than
 | relying on the resolution to refuse and easier to be sure about.
 |
 | SCRIPT_NAME is set to the front controller. Without it, PHP reports the
 | script it is running as the value of PHP_SELF and the base path logic in the
 | application can produce a base path of the router file's own directory rather
 | than the document root.
 |
 | This file is used by tools/serve-concurrently.php, which runs several of
 | these behind Apache. It is kept in the repository rather than in a temporary
 | directory so that the arrangement still works after a reboot, and so that
 | there is one copy of the rule rather than one per machine.
 |
 | The project root comes from LMS_ROOT when it is set, and otherwise from this
 | file's own location, which puts it in tools/ one directory below the project
 | root. An earlier copy of this file sat in a temporary directory, where that
 | guess had to reach two directories up, and when the file moved into the
 | repository the guess was still reaching two and the application answered 500
 | with "LMS_ROOT does not point at a directory with a public folder". Setting
 | the variable explicitly is the way to avoid depending on the guess at all.
 */

$root = getenv('LMS_ROOT') ?: dirname(__DIR__);

$publicRoot = realpath($root.'/public');

if ($publicRoot === false) {
    http_response_code(500);
    echo 'LMS_ROOT does not point at a directory with a public folder.';

    return true;
}

$path = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

if (str_contains($path, '..')) {
    http_response_code(400);

    return true;
}

$candidate = realpath($publicRoot.$path);

if ($candidate !== false && is_file($candidate) && str_starts_with($candidate, $publicRoot)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';

require $publicRoot.'/index.php';
