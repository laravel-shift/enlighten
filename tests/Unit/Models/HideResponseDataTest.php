<?php

namespace Tests\Unit\Models;

use Styde\Enlighten\Models\ExampleRequest;
use Tests\TestCase;

class HideResponseDataTest extends TestCase
{
    /** @test */
    function can_hide_and_overwrite_response_headers()
    {
        $httpData = new ExampleRequest([
            'response_headers' => [
                'secret-token' => 'this-should-be-removed',
                'token' => 'this-value-should-be-replaced',
                'content-type' => 'application/json',
            ],
        ]);

        config([
            'enlighten.response.headers' => [
                'hide' => ['secret-token'],
                'overwrite' => [
                    'secret-token' => 'this-should-not-be-present',
                    'token' => '******',
                    'key-not-present' => 'this-value-should-not-be-included',
                ],
            ]
        ]);

        $this->assertSame([
            'token' => '******',
            'content-type' => 'application/json',
        ], $httpData->response_headers);
    }

    /** @test */
    function can_hide_and_overwrite_data_from_a_json_response_body()
    {
        $httpData = new ExampleRequest([
            'response_headers' => ['content-type' => ['application/json']],
            'response_body' => json_encode([
                'message' => 'There was an error',
                'file' => 'confidential-file.php',
                'trace' => ['confidential data'],
                'token' => 'very-secret-token',
            ]),
        ]);

        config([
            'enlighten.response.body' => [
                'hide' => ['file', 'trace'],
                'overwrite' => [
                    'token' => 'demo-token',
                ],
            ]
        ]);

        $this->assertSame([
            'message' => 'There was an error',
            'token' => 'demo-token',
        ], $httpData->response_body);
    }
}
