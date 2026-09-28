@extends('layouts.app')

@section('title', 'Server is not set up correctly')

@section('content')
    {{--
        A deployment fault, not a fault in a request.

        A server rooted at the project directory instead of its public directory
        makes .env, the git repository, the uploaded files, and the source all
        readable by anyone who asks for them. The application cannot prevent
        that, because the web server sends those files without the application
        running at all, so the only safe response is to refuse and say why.

        The wording is for whoever is setting the server up, so it names the
        mistake and the fix. It deliberately carries no path, no configuration
        value, and no file name, because this page can be reached from a public
        address and a stranger reading it should learn nothing beyond the fact
        that something is misconfigured.
    --}}
    <x-error-state
        code="500"
        icon="alert"
        title="Server is not set up correctly"
        message="This server is serving the whole project folder instead of only the public folder inside it. That would let anyone read the configuration file, the uploaded files, and the source code, so the application has stopped answering until it is corrected. Point the web server's document root at the public folder, then reload."
    />
@endsection
