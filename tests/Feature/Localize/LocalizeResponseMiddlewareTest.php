<?php

use App\Http\Middleware\LocalizeResponse;
use App\Models\InstanceSettings;
use App\Models\User;
use App\Services\LocalizeDictionary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    // The login view redirects to /register while no user exists.
    User::factory()->create();
    // phpunit.xml sets LOCALIZE_OFF for the whole suite; this is the only
    // Localize test that exercises the HTTP path through the middleware.
    config()->set('localize.enabled', true);
    LocalizeDictionary::flush();
    LocalizeDictionary::merge([
        'Login' => '登录',
        'Sign in to manage your applications and infrastructure.' => '登录以管理你的应用与基础设施。',
    ]);
});

test('login page is translated', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('登录')
        ->assertSee('登录以管理你的应用与基础设施。');
});

test('localize off keeps upstream output', function () {
    config()->set('localize.enabled', false);
    $this->get('/login')->assertOk()->assertSee('Login')->assertDontSee('登录');
});

test('non html and non json responses pass through untouched', function () {
    LocalizeDictionary::flush();
    LocalizeDictionary::merge(['Secret text' => '机密']);
    $middleware = new LocalizeResponse;
    foreach (['application/octet-stream', 'text/csv'] as $contentType) {
        $response = new Response('Secret text', 200, ['Content-Type' => $contentType]);
        $out = $middleware->handle(Request::create('/x'), fn () => $response);
        expect($out->getContent())->toBe('Secret text');
    }
});

test('json responses go through the livewire payload localizer', function () {
    $payload = json_encode([
        'components' => [
            [
                'snapshot' => 'unused',
                'memo' => [],
                'effects' => [
                    'dispatches' => [
                        ['name' => 'toast', 'params' => ['Login']],
                    ],
                ],
            ],
        ],
    ]);
    $response = new Response($payload, 200, ['Content-Type' => 'application/json']);
    $out = (new LocalizeResponse)->handle(Request::create('/x'), fn () => $response);
    $data = json_decode($out->getContent(), true);
    expect($data['components'][0]['effects']['dispatches'][0]['params'][0])->toBe('登录')
        ->and($data['components'][0]['snapshot'])->toBe('unused');
});
