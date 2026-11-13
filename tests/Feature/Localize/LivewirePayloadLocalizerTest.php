<?php

use App\Services\Localize\LivewirePayloadLocalizer;

$map = ['Deployment queued.' => '部署已加入队列。', 'Saved.' => '已保存。'];

function payload(string $snapshotJson, string $html, array $dispatches = []): string
{
    return json_encode([
        'components' => [
            'abc123' => [
                'snapshot' => $snapshotJson,
                'memo' => ['name' => 'server-index'],
                'effects' => ['html' => $html, 'dispatches' => $dispatches],
            ],
        ],
    ]);
}

test('translates html effects and leaves snapshot byte identical', function () use ($map) {
    $snapshot = '{"data":{"name":"Deploy"}}';
    $body = payload($snapshot, '<div>Deployment queued.</div>');
    $out = LivewirePayloadLocalizer::translate($body, $map);

    expect($out)->toContain('部署已加入队列。')
        ->and(json_decode($out, true)['components']['abc123']['snapshot'])
        ->toBe($snapshot);
});

test('translates dispatch params by exact full string match only', function () use ($map) {
    $body = payload('{"x":1}', '<p>ok</p>', [
        ['name' => 'success', 'params' => ['Saved.', 'user-Deploy-42', 42]],
    ]);
    $data = json_decode(LivewirePayloadLocalizer::translate($body, $map), true);

    expect($data['components']['abc123']['effects']['dispatches'][0]['params'])
        ->toBe(['已保存。', 'user-Deploy-42', 42]);
});

test('passthrough on invalid json and missing fields', function () use ($map) {
    expect(LivewirePayloadLocalizer::translate('not-json{', $map))->toBe('not-json{');
    expect(LivewirePayloadLocalizer::translate('{"other":1}', $map))->toBe('{"other":1}');
});
