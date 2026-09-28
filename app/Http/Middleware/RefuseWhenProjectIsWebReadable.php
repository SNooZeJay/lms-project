<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses to serve anything when the server is rooted at the wrong directory.
 *
 * A Laravel application is meant to be published with public/ as the document
 * root. When the server is rooted one level higher, at the project directory, the
 * entire project becomes web readable: .git, .env, storage/, the migrations, and
 * the source. Nothing in the application can prevent that, because the web
 * server hands the file over without the framework ever running. The .htaccess
 * that would refuse those paths is also inside the directory being served, and
 * is ignored outright when AllowOverride is none.
 *
 * This is the tripwire for that mistake. It compares the document root the
 * server is actually using against the directory this application publishes,
 * and it fails closed: if the project directory is anywhere inside the document
 * root, every request is refused rather than answered. Refusing loudly is the
 * point. A visitor who cannot reach the site reports a broken deployment, which
 * is a five minute fix. A visitor who can read the repository is a disclosure
 * that nobody notices until much later.
 *
 * It is deliberately one comparison and not a list of paths to block. Refusing
 * .git by name is defeated by .gitignore, by a different directory, or by the
 * next file nobody thought of. Getting the document root right is the only
 * version that cannot be worked around.
 *
 * The check is skipped when the document root is unknown or is not a real path,
 * which is the case for a command line process and for some proxy
 * arrangements. Skipping avoids breaking a correct deployment in order to
 * enforce a rule that only the web server can be sure about.
 */
class RefuseWhenProjectIsWebReadable
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->projectIsWebReadable()) {
            return response(view('errors.misconfigured'), 500);
        }

        return $next($request);
    }

    /**
     * Whether the project directory sits inside the served directory.
     */
    private function projectIsWebReadable(): bool
    {
        $documentRoot = $this->documentRoot();

        if ($documentRoot === null) {
            return false;
        }

        $public = $this->normalise(public_path());

        // A document root that is not the published directory, and that contains
        // it, means the whole project is reachable. Anything else, including a
        // document root somewhere entirely different such as a reverse proxy
        // that does not report one, is left alone.
        return $documentRoot !== $public && str_starts_with($public, $documentRoot);
    }

    /**
     * The directory the web server is serving, or null when it cannot be known.
     */
    private function documentRoot(): ?string
    {
        $root = $_SERVER['DOCUMENT_ROOT'] ?? null;

        if (! is_string($root) || trim($root) === '') {
            return null;
        }

        $resolved = realpath($root);

        // A document root that does not exist on this machine is a proxy
        // artefact or a container path, not something to refuse a request over.
        return $resolved === false ? null : $this->normalise($resolved);
    }

    /**
     * Compare paths the way a filesystem does, not the way a string does.
     *
     * Separators and trailing slashes differ between servers, and on Windows a
     * case difference is not a different directory. Getting this wrong in the
     * strict direction would refuse a correct deployment.
     */
    private function normalise(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Kept so a caller can read the answer without sending a request.
     */
    public function exposesProjectDirectory(): bool
    {
        return $this->projectIsWebReadable();
    }
}
