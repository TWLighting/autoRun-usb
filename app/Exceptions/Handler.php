<?php

namespace App\Exceptions;

use Exception;
use App\Presenter\ApiPresenter;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        $presenter = new ApiPresenter();
        $data = $this->handlerApiException($exception);
        if ($request->is('admin*')) {
            return $presenter->normalJson([], $data['msg'], $data['code']);
        }
        if ($request->is('api*')) {
            return $presenter->normalJson([], $data['msg'], $data['code']);
        }
        return $presenter->json([], $data['msg'], $data['code']);
        // return parent::render($request, $exception);
    }

    private function handlerApiException(Exception $e)
    {
        // $return = ['code' => 500, 'msg' => $e->getMessage()];
        $return = ['code' => '2', 'msg' => $e->getMessage()];
        switch (true) {
            case $e instanceof MethodNotAllowedHttpException:
                // $return['code'] = 405;
                $return['msg'] = 'MethodNotAllowed';
                break;
            case $e instanceof NotFoundHttpException:
                // $return['code'] = 404;
                $return['msg'] = 'NotFound';
                break;
            case $e instanceof ValidationException:
                // $return['code'] = 422;
                $return['msg'] = $e->errors();
                break;
            case $e instanceof QueryException:
                // $return['code'] = 409;
                $return['msg'] = '系统异常';
                break;
            case $e instanceof AuthorizationException:
                // $return['code'] = 401;
                $return['msg'] = 'Authorization';
                break;
        }
        return $return;
    }
}
