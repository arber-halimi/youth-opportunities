<?php
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

$backend = dirname(__DIR__, 2);
require $backend.'/vendor/autoload.php';

try {
    $app = require $backend.'/bootstrap/app.php';
    $kernel = $app->make(Kernel::class);
    $app->instance('request', Request::create('/'));
    $kernel->bootstrap();
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'cache.default' => 'array',
        'app.url' => 'http://localhost:8000',
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'auth.frontend_url' => 'http://localhost:5173',
        'jwt.secret' => bin2hex(random_bytes(32)),
        'jwt.ttl' => 60,
        'jwt.blacklist_enabled' => true,
        'jwt.blacklist_grace_period' => 0,
    ]);
    DB::purge('sqlite');
    foreach (glob($backend.'/database/migrations/*_create_users_table.php') as $file) {
        (require $file)->up();
    }
    foreach (glob($backend.'/database/migrations/*_create_password_reset_tokens_table.php') as $file) {
        (require $file)->up();
    }
    Notification::fake();

    $call = function ($method, $path, $status, $data = [], $jwt = null, $ip = '127.0.0.1', $accept = true) use ($app, $kernel) {
        auth()->forgetGuards();
        auth()->setDefaultDriver('web');
        $app['tymon.jwt']->unsetToken();
        $headers = ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => $ip];
        if ($accept) {
            $headers['HTTP_ACCEPT'] = 'application/json';
        }
        if ($jwt !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$jwt;
        }
        $uri = str_starts_with($path, 'http') ? $path : '/api/v1/auth/'.$path;
        $request = Request::create($uri, $method, [], [], [], $headers, json_encode($data));
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        if ($response->getStatusCode() !== $status) {
            throw new RuntimeException($method.' '.strtok($path, '?').' expected '.$status.' got '.$response->getStatusCode());
        }
        return json_decode($response->getContent(), true);
    };

    $email = 'test@example.test';
    $password = 'test-password-one';
    $registration = ['name' => 'Test', 'email' => ' TEST@example.test ', 'password' => $password, 'password_confirmation' => $password];
    $registered = $call('POST', 'register', 201, $registration);
    $call('POST', 'register', 422, $registration);
    $user = User::where('email', $email)->firstOrFail();
    if ($user->password === $password || $registered['result']['email_verified_at'] !== null) {
        throw new RuntimeException('Registration persisted an unexpected value.');
    }

    $call('POST', 'login', 422, ['email' => []]);
    $invalid = $call('POST', 'login', 401, ['email' => $email, 'password' => 'wrong']);
    $unknown = $call('POST', 'login', 401, ['email' => 'unknown@example.test', 'password' => 'wrong']);
    if ($invalid !== $unknown) {
        throw new RuntimeException('Login responses differ by account existence.');
    }
    $login = fn ($secret) => $call('POST', 'login', 200, ['email' => $email, 'password' => $secret])['result']['access_token'];
    $first = $login($password);
    $second = $login($password);
    $me = $call('GET', 'me', 200, [], $first)['result'];
    if (isset($me['auth_version']) || array_key_exists('password', $me)) {
        throw new RuntimeException('UserResource exposed internal fields.');
    }
    $call('GET', 'me', 401, [], null, '127.0.0.1', false);
    $call('GET', 'me?token='.$first, 401);
    $call('GET', 'me', 401, [], 'invalid');
    $call('GET', 'me', 401, [], 'invalid Bearer '.$first);
    $call('POST', 'logout', 204, [], $first);
    $call('GET', 'me', 401, [], $first);
    $call('GET', 'me', 200, [], $second);

    $call('POST', 'email/verification-notification', 204, [], $second);
    $verification = Notification::sent($user, VerifyEmail::class)->last()->toMail($user)->actionUrl;
    parse_str(parse_url($verification, PHP_URL_QUERY), $query);
    $signedUrl = $query['verification_url'];
    if (parse_url($verification, PHP_URL_PORT) !== 5173 || parse_url($signedUrl, PHP_URL_PORT) !== 8000) {
        throw new RuntimeException('The verification link has incorrect origins.');
    }
    $call('POST', $signedUrl.'&tampered=1', 403, [], $second);
    $expiredLink = Illuminate\Support\Facades\URL::temporarySignedRoute(
        'verification.verify', now()->subMinute(),
        ['id' => $user->id, 'hash' => sha1($user->email)], absolute: false
    );
    $call('POST', 'http://localhost:8000'.$expiredLink, 403, [], $second);
    $other = User::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => $password]);
    $otherJwt = $call('POST', 'login', 200, ['email' => $other->email, 'password' => $password])['result']['access_token'];
    $call('POST', $signedUrl, 403, [], $otherJwt);
    $call('POST', $signedUrl, 204, [], $second);
    $call('POST', $signedUrl, 204, [], $second);
    if ($call('GET', 'me', 200, [], $second)['result']['email_verified_at'] === null) {
        throw new RuntimeException('Verification timestamp missing.');
    }

    $knownReset = $call('POST', 'forgot-password', 200, ['email' => $email]);
    $unknownReset = $call('POST', 'forgot-password', 200, ['email' => 'unknown@example.test']);
    if ($knownReset !== $unknownReset) {
        throw new RuntimeException('Forgot password responses differ by account existence.');
    }
    $notification = Notification::sent($user, ResetPassword::class)->sole();
    parse_str(parse_url($notification->toMail($user)->actionUrl, PHP_URL_QUERY), $resetQuery);
    $nextPassword = 'test-password-two';
    $reset = [...$resetQuery, 'password' => $nextPassword, 'password_confirmation' => $nextPassword];
    $third = $login($password);
    $call('POST', 'reset-password', 204, $reset);
    $call('POST', 'reset-password', 422, $reset);
    if ($user->fresh()->auth_version !== 1) {
        throw new RuntimeException('Auth version was not incremented once.');
    }
    $call('GET', 'me', 401, [], $second);
    $call('GET', 'me', 401, [], $third);
    $call('GET', 'me', 200, [], $otherJwt);
    $call('POST', 'login', 401, ['email' => $email, 'password' => $password]);
    $newJwt = $login($nextPassword);
    $call('GET', 'me', 200, [], $newJwt);
    Carbon::setTestNow(now()->addMinutes(61));
    $call('GET', 'me', 401, [], $newJwt);
    Carbon::setTestNow();

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $call('POST', 'login', 401, ['email' => $email, 'password' => 'wrong'], null, '127.0.0.2');
    }
    $call('POST', 'login', 429, ['email' => $email, 'password' => 'wrong'], null, '127.0.0.2');
    echo "PASS: registration, login, Bearer, logout, signed verification, reset, token invalidation, expiry and throttling.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}
