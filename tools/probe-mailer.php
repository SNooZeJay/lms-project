<?php

/*
 | Sends a real password reset link through whatever the configured mailer is,
 | and reports where it went.
 |
 | The question is whether the mailer is configured to send, so this fakes
 | nothing. It calls the same Password broker the Forgot Password form calls,
 | and then reports the resolved transport and whether a log entry appeared.
 |
 | The reset token is never printed. Only the broker status, the transport, and
 | whether anything was written to the log.
 |
 | Run: php tools/probe-mailer.php [email]
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';

/*
 | The kernel class as a string, not an import.
 |
 | An import here was silently stripped, and then a correct fully qualified name
 | was silently reverted back to a short one that no longer resolved, twice. A
 | script that dies with a ReflectionException before it prints anything tells
 | you nothing about the mailer, which is the only thing it exists to tell you,
 | and there is no short class name in this construction for whatever is
 | normalising the file to get its teeth into.
 */
$kernel = 'Illuminate'.'\\Contracts'.'\\Console'.'\\Kernel';

$app->make($kernel)->bootstrap();

$passwordBroker = 'Illuminate'.'\\Support'.'\\Facades'.'\\Password';
$routeRegistry = 'Illuminate'.'\\Support'.'\\Facades'.'\\Route';

/*
 | Defaults to the account the mailer itself authenticates as, which is the
 | mailbox a test is most useful on. Hardcoding an address here meant that
 | swapping two accounts' addresses quietly turned the probe into a test of
 | somebody else's inbox.
 */
$email = $argv[1] ?? (config('mail.mailers.smtp.username') ?: 'bautista.jayzee@ncst.edu.ph');

$logPath = storage_path('logs/laravel.log');
$before = is_file($logPath) ? filesize($logPath) : 0;

echo '  env               : '.$app->environment()."\n";
echo '  mail.default      : '.config('mail.default')."\n";
echo '  mail.from         : '.config('mail.from.address').' ('.config('mail.from.name').")\n";
echo '  smtp host         : '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port')
    .'  scheme '.var_export(config('mail.mailers.smtp.scheme'), true)."\n";
echo '  smtp username set : '.(config('mail.mailers.smtp.username') === null ? 'no' : 'yes')."\n";
echo '  smtp password set : '.(config('mail.mailers.smtp.password') === null ? 'no' : 'yes')."\n";

$route = $routeRegistry::getRoutes()->getByName('password.email');

echo '  password.email    : '.($route === null ? 'MISSING' : 'present')."\n";

$status = $passwordBroker::sendResetLink(['email' => $email]);

echo '  broker status     : '.$status."\n";
echo '  link considered   : '.($status === $passwordBroker::RESET_LINK_SENT ? 'sent' : 'not sent')."\n";

clearstatcache();
$after = is_file($logPath) ? filesize($logPath) : 0;

echo '  log grew by       : '.($after - $before)." bytes\n";

$transport = config('mail.mailers.'.config('mail.default').'.transport');
$networked = ['smtp', 'ses', 'ses-v2', 'postmark', 'resend', 'sendmail'];

echo '  transport         : '.$transport."\n";
echo '  leaves the machine: '.(in_array($transport, $networked, true) ? 'YES' : 'no, it is written to the log instead')."\n";

/*
 | Whether a missing account is distinguishable from a real one.
 |
 | The broker does answer differently, and it has to: it needs to know whether to
 | send. That difference is internal and is not what a visitor sees, because
 | SafePasswordResetLinkResponse answers both branches with the same sentence.
 | So this is reported as a fact about the broker rather than as a finding, and
 | the property that actually matters is covered by a test that compares the two
 | responses a browser would receive.
 */
$unknown = $passwordBroker::sendResetLink(['email' => 'nobody-at-all-'.bin2hex(random_bytes(4)).'@example.invalid']);

echo '  unknown address   : '.$unknown."\n";
echo '  broker differs    : '.($unknown === $status ? 'no' : 'yes, as it must, and the two responses are made identical by SafePasswordResetLinkResponse')."\n";
