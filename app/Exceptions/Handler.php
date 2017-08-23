<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        AuthenticationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $e
     * @return void
     */
    public function report(Exception $e)
    {
        parent::report($e);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $e
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    {
        // Default to the parent class' implementation of handler
        $response = parent::render($request, $e);

        if ($request->expectsJson() || (empty($response->headers->get('Content-Type')))) {
            $code = $response->getStatusCode();
            if ($code == 500) {
                if (is_a($e, AuthenticationException::class)) $code = 401;
            }

            // Define the response
            $json = [
                'success' => false,
                'error' => [
                'message' => $e->getMessage(),
                'code' => $code
            ]];

            // If the app is in debug mode
            if (env('APP_DEBUG', false)) {
                // Add the exception class name, message and stack trace to response
                $json['error']['exception'] = get_class($e); // Reflection might be better here
                $json['error']['traces'] = $e->getTrace();
            }

            // Return a JSON response with the response array and status code
            return response()->json($json, $code);
        } elseif ($e instanceof ValidationException) {
            $json = [
              'success' => false,
              'validation_error' => $e->validator->getMessageBag(),
              'code' => 400,
            ];
            return response()->json($json, $response->getStatusCode());
        }

        return $response;
    }
}
