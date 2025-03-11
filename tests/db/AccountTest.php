<?php

class AccountTest extends TestCase
{
    public function testColumns()
    {
        $columns = DB::getSchemaBuilder()->getColumnListing('account');
        $this->assertContains('id', $columns);
        $this->assertContains('account', $columns);
        $this->assertContains('password', $columns);
        $this->assertContains('pay_password', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('is_admin', $columns);
        $this->assertContains('login_ip', $columns);
        $this->assertContains('status', $columns);
        $this->assertContains('telegram_path', $columns);
        $this->assertContains('telegram_chatid', $columns);
        $this->assertContains('telegram_code', $columns);
        $this->assertContains('frequence', $columns);
        $this->assertContains('created_at', $columns);
        $this->assertContains('updated_at', $columns);
        $this->assertContains('last_login_time', $columns);
    }
}
