<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;

class AccountListTest extends ApiTest
{
    private $url = '/admin/account/list';

    public function testNoSession()
    {
        $response = $this->post($this->url);
        $this->assertEquals(401, $this->response->status());
    }

    public function testNoPurview()
    {
        $response = $this
            ->withSession($this->fakeUserSession)
            ->post($this->url);
        $this->assertEquals(401, $this->response->status());
    }

    public function testList()
    {
        $response = $this
            ->withSession($this->fakeAdminSession)
            ->post($this->url);

        $this->assertEquals(200, $this->response->status());
        $this->assertJsonStructure([
            'msg_code',
            'msg',
            'data' => [
                'current_page',
                'data' => [
                    '*' => [
                        'id',
                        "account",
                        "name",
                        "is_admin",
                        "login_ip",
                        "status",
                        "last_login_time",
                        "telegram_path",
                        "telegram_chatid",
                        "permission"
                    ],
                ],
                "first_page_url",
                "from",
                "last_page",
                "last_page_url",
                "next_page_url",
                "path",
                "per_page",
                "prev_page_url",
                "to",
                "total"
            ],
        ]);
    }
}
