<?php
// https://laravel.com/docs/5.7/http-tests
// https://lumen.laravel.com/docs/5.7/testing

use Tests\InteractsWithSession;
use PHPUnit\Framework\Assert as PHPUnit;

abstract class ApiTest extends TestCase
{
    use InteractsWithSession;

    protected $fakeAdminSession = [
        'isLogin' => true,
        'accountId' => 7,
        'account' => 'jordan',
        'accountType' => 'admin',
        'topAccountId' => 7,
        'permission' => 1,
    ];

    protected $fakeUserSession = [
        'isLogin' => true,
        'accountId' => 6,
        'account' => 'test',
        'accountType' => 'user',
        'topAccountId' => 6,
        'permission' => 1,
    ];

    public function setUp()
    {
        parent::setUp();
        Cache::store('file')->flush();
        $this->session_id = $this->app['session']->getId();
        $this->session_cookie = [$this->app['session']->getName() => $this->session_id];
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        // set session_id cookie
        $cookies = array_merge($cookies, $this->session_cookie);
        parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    public function decodeResponseJson($key = null)
    {
        $decodedResponse = json_decode($this->response->getContent(), true);
        if (is_null($decodedResponse) || $decodedResponse === false) {
            if ($this->exception) {
                throw $this->exception;
            } else {
                PHPUnit::fail('Invalid JSON was returned from the route.');
            }
        }
        return data_get($decodedResponse, $key);
    }

    public function assertJsonStructure(array $structure = null, $responseData = null)
    {
        if (is_null($structure)) {
            return $this->assertExactJson($this->json());
        }
        if (is_null($responseData)) {
            $responseData = $this->decodeResponseJson();
        }
        foreach ($structure as $key => $value) {
            if (is_array($value) && $key === '*') {
                PHPUnit::assertInternalType('array', $responseData);
                foreach ($responseData as $responseDataItem) {
                    $this->assertJsonStructure($structure['*'], $responseDataItem);
                }
            } elseif (is_array($value)) {

                PHPUnit::assertArrayHasKey($key, $responseData);
                $this->assertJsonStructure($structure[$key], $responseData[$key]);
            } else {
                PHPUnit::assertArrayHasKey($value, $responseData);
            }
        }
        return $this;
    }
}