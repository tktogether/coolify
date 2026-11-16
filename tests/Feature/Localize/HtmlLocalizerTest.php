<?php

use App\Services\Localize\HtmlLocalizer;

$map = ['Deploy' => '部署', 'Save changes' => '保存更改', 'Log in' => '登录'];

test('translates text nodes', function () use ($map) {
    expect(HtmlLocalizer::translate('<div>Deploy</div>', $map))->toBe('<div>部署</div>');
});

test('leaves unknown text untouched', function () use ($map) {
    expect(HtmlLocalizer::translate('<div>Unknown text</div>', $map))->toBe('<div>Unknown text</div>');
});

test('skips script style pre code textarea and comments', function () use ($map) {
    $html = '<script>var x = "Deploy";</script><style>.a{}</style><pre>Deploy</pre><code>Deploy</code><textarea>Deploy</textarea><!-- Deploy -->';
    expect(HtmlLocalizer::translate($html, $map))->toBe($html);
});

test('translates whitelisted attributes only', function () use ($map) {
    $html = '<input placeholder="Save changes" title="Save changes" aria-label="Save changes" name="Save changes">';
    expect(HtmlLocalizer::translate($html, $map))
        ->toBe('<input placeholder="保存更改" title="保存更改" aria-label="保存更改" name="Save changes">');
});

test('leaves wire snapshot and non whitelist attributes untouched', function () use ($map) {
    $snap = 'TzozNDoiTGl2ZXdpcmVcU25hcHNob3RcU3RhdGUiOntzOjY6IkRlcGxveSI7fQ==';
    $html = "<div wire:snapshot=\"{$snap}\" data-value=\"Save changes\" onclick=\"return 'Save changes'\">x</div>";
    expect(HtmlLocalizer::translate($html, $map))->toBe($html);
});

test('keeps attribute values with html entities verbatim', function () use ($map) {
    $html = '<input title="Save &amp; exit">';
    expect(HtmlLocalizer::translate($html, $map))->toBe($html);
});

test('handles nested tags and mixed content', function () use ($map) {
    $html = '<div><span>Deploy</span> and <button>Save changes</button></div>';
    expect(HtmlLocalizer::translate($html, $map))->toBe('<div><span>部署</span> and <button>保存更改</button></div>');
});

test('keeps skip region content after a comment untouched', function () use ($map) {
    $html = '<script>var a = "Deploy"; <!-- note --> var b = "Deploy";</script>';
    expect(HtmlLocalizer::translate($html, $map))->toBe($html);
});

test('keeps same-name nested skip tags skipped until the outer close', function () use ($map) {
    $html = '<code><code>Deploy</code>Deploy</code>';
    expect(HtmlLocalizer::translate($html, $map))->toBe($html);
});

test('keeps tags with quoted attribute values containing gt intact', function () use ($map) {
    $html = '<input title="a>b" name="Deploy">';
    expect(HtmlLocalizer::translate($html, $map))->toBe($html);
});

test('keeps tags with unclosed quoted values intact', function () use ($map) {
    $html = '<div data-x="Deploy>y</div>';
    expect(HtmlLocalizer::translate($html, $map))->toBe($html);
});

test('single word keys match whole nodes only and never substrings', function () {
    $map = ['Dashboard' => '仪表盘'];
    expect(HtmlLocalizer::translate('<span>Dashboard</span>', $map))->toBe('<span>仪表盘</span>')
        ->and(HtmlLocalizer::translate('<span>Dashboards</span>', $map))->toBe('<span>Dashboards</span>')
        ->and(HtmlLocalizer::translate('<div>Dashboard | Coolify</div>', $map))->toBe('<div>Dashboard | Coolify</div>');
});

test('exact match keeps surrounding whitespace of the segment', function () {
    $map = ['Servers' => '服务器'];
    expect(HtmlLocalizer::translate('<span> Servers </span>', $map))->toBe('<span> 服务器 </span>');
});

test('exact match does not bleed into longer words inside a segment', function () {
    $map = ['Add' => '添加', 'Address' => '地址'];
    expect(HtmlLocalizer::translate('<span>Address</span>', $map))->toBe('<span>地址</span>');
});

test('phrase keys still apply inside longer segments', function () {
    $map = ['New Project' => '新项目', 'New Projects' => '新项目列表'];
    expect(HtmlLocalizer::translate('<span>New Projects</span>', $map))->toBe('<span>新项目列表</span>')
        // A key followed by more lowercase words is the start of a longer
        // sentence, not a phrase to translate: replacing it left the rest English.
        ->and(HtmlLocalizer::translate('<div>Create a New Project now</div>', $map))->toBe('<div>Create a New Project now</div>');
});

test('a phrase key does not translate the start of a longer sentence', function () {
    $map = ['This resource' => '此资源'];

    expect(HtmlLocalizer::translate('<li>This resource cannot be restored after deletion.</li>', $map))
        ->toBe('<li>This resource cannot be restored after deletion.</li>')
        ->and(HtmlLocalizer::translate('<span>This resource</span>', $map))
        ->toBe('<span>此资源</span>')
        ->and(HtmlLocalizer::translate('<li>This resource, and its volumes, stay.</li>', $map))
        ->toBe('<li>此资源, and its volumes, stay.</li>');
});

test('a whole sentence key wins over the fragment it contains', function () {
    $map = [
        'This resource' => '此资源',
        'This resource cannot be restored after deletion.' => '此资源在删除后无法从 Coolify 恢复。',
    ];

    expect(HtmlLocalizer::translate('<li>This resource cannot be restored after deletion.</li>', $map))
        ->toBe('<li>此资源在删除后无法从 Coolify 恢复。</li>');
});

test('a singular key does not eat the plural suffix', function () {
    $map = ['API Token' => 'API 令牌', 'API Tokens' => 'API 令牌列表'];

    expect(HtmlLocalizer::translate('<span>API Tokens</span>', $map))->toBe('<span>API 令牌列表</span>')
        ->and(HtmlLocalizer::translate('<span>API Token</span>', $map))->toBe('<span>API 令牌</span>')
        // With only the singular key, the plural stays untouched instead of
        // becoming "API 令牌s".
        ->and(HtmlLocalizer::translate('<span>API Tokens</span>', ['API Token' => 'API 令牌']))
        ->toBe('<span>API Tokens</span>');
});

test('attribute values get the exact tier too', function () {
    $map = ['Dashboard' => '仪表盘'];
    expect(HtmlLocalizer::translate('<input placeholder="Dashboard">', $map))->toBe('<input placeholder="仪表盘">')
        ->and(HtmlLocalizer::translate('<input aria-label="Dashboards overview">', $map))->toBe('<input aria-label="Dashboards overview">');
});

test('data-tooltip attribute values are translated', function () {
    $map = ['Deployment commands' => '部署命令', 'Connection' => '连接'];
    expect(HtmlLocalizer::translate('<span data-tooltip="Deployment commands">i</span>', $map))
        ->toBe('<span data-tooltip="部署命令">i</span>')
        ->and(HtmlLocalizer::translate('<span data-tooltip="Connection">i</span>', $map))
        ->toBe('<span data-tooltip="连接">i</span>');
});

test('a wrapped sentence matches after collapsing its whitespace runs', function () {
    $map = ['Coolify creates the root account from the ROOT_USER_* values in /data/coolify/source/.env, but it rejected them.' => '翻译'];
    $html = "<p>Coolify creates the root account from the\n                        ROOT_USER_* values in /data/coolify/source/.env, but it rejected them.</p>";
    expect(HtmlLocalizer::translate($html, $map))->toBe('<p>翻译</p>');
});

test('a wrapped sentence keeps its leading and trailing whitespace', function () {
    $map = ['A wrapped sentence.' => '换行句子'];
    $html = "<p>\n    A wrapped\n    sentence.\n</p>";
    expect(HtmlLocalizer::translate($html, $map))->toBe("<p>\n    换行句子\n</p>");
});

test('a wrapped sentence that never matches stays byte-identical', function () {
    $map = ['Something else' => '别的东西'];
    $html = "<p>Line one\n    line two with no key.</p>";
    expect(HtmlLocalizer::translate($html, $map))->toBe($html);
});

test('collapsed matching does not merge separate tags', function () {
    $map = ['First Second' => '合并'];
    $html = '<p>First</p><p>Second</p>';
    expect(HtmlLocalizer::translate($html, $map))->toBe($html);
});

test('single line text is untouched by the collapsed pass', function () {
    $map = ['Dashboard' => '仪表盘'];
    expect(HtmlLocalizer::translate('<div>Dashboard <span>overview</span></div>', $map))
        ->toBe('<div>仪表盘 <span>overview</span></div>');
});

test('translating a page does not scale with the dictionary size', function () {
    // Regression: every text node used to scan every phrase key and compile a
    // regex per key, so a real page took seconds. The matcher now indexes keys by
    // their first word, so the work follows the text, not the dictionary.
    $map = [];
    foreach (glob(resource_path('localization/zh-cn').'/*.php') as $file) {
        $entries = require $file;
        if (is_array($entries)) {
            $map = array_merge($map, $entries);
        }
    }

    expect(count($map))->toBeGreaterThan(3000);

    $nodes = [];
    for ($index = 0; $index < 600; $index++) {
        $nodes[] = 'Unmatched interface text number '.$index.' with a few words';
        $nodes[] = 'Dashboard';
    }
    $html = '<div>'.implode('</div><div>', $nodes).'</div>';

    $start = microtime(true);
    HtmlLocalizer::translate($html, $map);
    $elapsed = microtime(true) - $start;

    // Generous: the indexed pass takes tens of milliseconds, the old per-key scan
    // took seconds for this many nodes.
    expect($elapsed)->toBeLessThan(1.5);
});

test('a key containing an apostrophe matches the escaped blade output', function () {
    // {{ $value }} calls e(), which turns ' into &#039;. The dictionary holds the
    // readable form, so the matcher has to accept the escaped one too — otherwise
    // every sentence with an apostrophe stayed English.
    $map = ["Traefik's ACME storage is empty." => 'Traefik 的 ACME 存储为空。'];

    expect(HtmlLocalizer::translate('<p>Traefik&#039;s ACME storage is empty.</p>', $map))
        ->toBe('<p>Traefik 的 ACME 存储为空。</p>')
        // The unescaped form keeps working for text that never went through e().
        ->and(HtmlLocalizer::translate("<p>Traefik's ACME storage is empty.</p>", $map))
        ->toBe('<p>Traefik 的 ACME 存储为空。</p>');
});

test('a key containing a double quote matches the escaped blade output', function () {
    $map = ['Type "cleanup" to confirm.' => '输入 “cleanup” 以确认。'];

    expect(HtmlLocalizer::translate('<p>Type &quot;cleanup&quot; to confirm.</p>', $map))
        ->toBe('<p>输入 “cleanup” 以确认。</p>');
});

test('a key containing an ampersand still matches its escaped form', function () {
    $map = ['Keys & Tokens' => '密钥与令牌'];

    expect(HtmlLocalizer::translate('<span>Keys &amp; Tokens</span>', $map))
        ->toBe('<span>密钥与令牌</span>');
});
