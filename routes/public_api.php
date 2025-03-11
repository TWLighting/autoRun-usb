<?php
$router->group([], function () use ($router) {
    $router->post('/addjob', ['uses' => 'AddJobController@addJob']);
    $router->post('/addMsg', ['uses' => 'AddMsgController@addMsg']);

    $router->post('/callback', [
        'as' => 'callback_request',
        'uses' => 'CallbackController@request'
    ]);
});

