<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorFormatTest extends TestCase
{
    public function test_SRS_ADM_12_every_response_carries_a_request_id(): void
    {
        $response = $this->getJson('/api/v1/settings');

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $response->headers->get('X-Request-Id'));
    }

    public function test_SRS_ADM_12_a_sane_incoming_request_id_is_kept(): void
    {
        $this->getJson('/api/v1/settings', ['X-Request-Id' => 'gateway-abc-12345'])
            ->assertHeader('X-Request-Id', 'gateway-abc-12345');
    }

    public function test_SRS_ADM_12_a_malformed_incoming_request_id_is_replaced(): void
    {
        $response = $this->getJson('/api/v1/settings', ['X-Request-Id' => "bad id\n<script>"]);

        $this->assertNotSame("bad id\n<script>", $response->headers->get('X-Request-Id'));
    }

    public function test_SRS_ADM_13_server_errors_hide_internals_and_return_request_id(): void
    {
        config(['app.debug' => false]);
        Route::get('api/v1/_boom', fn () => throw new \RuntimeException('SQLSTATE secret table name'));

        $response = $this->getJson('/api/v1/_boom')
            ->assertStatus(500)
            ->assertJsonPath('code', 'SERVER_ERROR')
            ->assertJsonMissingPath('debug')
            ->assertJsonMissingPath('trace');

        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringContainsString('Ralat sistem', $response->json('message'));
        $this->assertSame($response->headers->get('X-Request-Id'), $response->json('request_id'));
    }

    public function test_SRS_ADM_13_unauthenticated_and_not_found_use_the_same_shape(): void
    {
        $this->getJson('/api/v1/user')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED')
            ->assertJsonStructure(['message', 'code', 'request_id']);

        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_SRS_ADM_13_validation_errors_keep_field_errors(): void
    {
        $this->postJson('/api/v1/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION')
            ->assertJsonStructure(['message', 'errors' => ['email', 'password'], 'request_id']);
    }
}
