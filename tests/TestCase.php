<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        $app->make('config')->set([
            'app.debug' => false,
            'database.default' => 'mysql',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => 3307,
            'database.connections.mysql.database' => 'lms_test',
            'database.connections.mysql.username' => 'lms_user',
        ]);

        return $app;
    }
}
