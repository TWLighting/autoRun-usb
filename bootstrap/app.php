<?php

require_once __DIR__.'/../vendor/autoload.php';

try {
    (new Dotenv\Dotenv(dirname(__DIR__)))->load();
} catch (Dotenv\Exception\InvalidPathException $e) {
    //
}

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| Here we will load the environment and create the application instance
| that serves as the central piece of this framework. We'll use this
| application as an "IoC" container and router for this framework.
|
*/

$app = new Laravel\Lumen\Application(
    dirname(__DIR__)
);

$app->withFacades();

// $app->withEloquent();

/*
|--------------------------------------------------------------------------
| Register Container Bindings
|--------------------------------------------------------------------------
|
| Now we will register a few bindings in the service container. We will
| register the exception handler and the console kernel. You may add
| your own bindings here if you like or you can make another file.
|
*/

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);


$app->singleton(Illuminate\Session\SessionManager::class, function () use ($app) {
    return new Illuminate\Session\SessionManager($app);
});
/*
|--------------------------------------------------------------------------
| Register Middleware
|--------------------------------------------------------------------------
|
| Next, we will register the middleware with the application. These can
| be global middleware that run before and after each request into a
| route or middleware that'll be assigned to some specific routes.
|
*/

// $app->middleware([
    // Illuminate\Session\Middleware\StartSession::class,
// ]);

// $app->routeMiddleware([
//     'auth' => App\Http\Middleware\Authenticate::class,
// ]);

$app->routeMiddleware([
    'decryption' => App\Http\Middleware\DecryptionMiddleware::class,
    'adminAuth' => App\Http\Middleware\AdminAuthenticate::class,
    'userAuth' => App\Http\Middleware\UserAuthenticate::class,
    'managerAuth' => App\Http\Middleware\ManagerAuth::class,
    'session' => Illuminate\Session\Middleware\StartSession::class,
    'cors' => Barryvdh\Cors\HandleCors::class,
    'adminDecryption' => App\Http\Middleware\AdminDecryptionMiddleware::class,
]);

/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
|
| Here we will register all of the application's service providers which
| are used to bind services into the container. Service providers are
| totally optional, so you are not required to uncomment this line.
|
*/


// $app->register(App\Providers\AppServiceProvider::class);
// $app->register(App\Providers\AuthServiceProvider::class);
$app->register(App\Providers\EventServiceProvider::class);

$app->register(Illuminate\Session\SessionServiceProvider::class);
$app->register(Illuminate\Redis\RedisServiceProvider::class);
$app->register(Barryvdh\Cors\ServiceProvider::class);

/*
|--------------------------------------------------------------------------
| Load The Application Routes
|--------------------------------------------------------------------------
|
| Next we will include the routes file so that they can all be added to
| the application. This will provide all of the URLs the application
| can respond to, as well as the controllers that may handle them.
|
*/
$app->configure('database');
$app->configure('session');
$app->configure('cors');
$app->configure('admin');
$app->configure('autorun');

$app->router->group([
    'namespace' => 'App\Http\Controllers',
], function ($router) {
    require __DIR__.'/../routes/web.php';
});

$app->router->group([
    'prefix' => 'admin',
    'namespace' => 'App\Http\Controllers\Admin',
    'middleware' => ['session', 'cors', 'adminDecryption'],
], function ($router) {
    require __DIR__.'/../routes/admin.php';
});

// public api
$app->router->group([
    'prefix' => 'api',
    'namespace' => 'App\Http\Controllers\PublicApi',
], function ($router) {
    require __DIR__.'/../routes/public_api.php';
});

return $app;
