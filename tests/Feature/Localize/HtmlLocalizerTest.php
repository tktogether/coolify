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
        ->and(HtmlLocalizer::translate('<div>Create a New Project now</div>', $map))->toBe('<div>Create a 新项目 now</div>');
});

test('attribute values get the exact tier too', function () {
    $map = ['Dashboard' => '仪表盘'];
    expect(HtmlLocalizer::translate('<input placeholder="Dashboard">', $map))->toBe('<input placeholder="仪表盘">')
        ->and(HtmlLocalizer::translate('<input aria-label="Dashboards overview">', $map))->toBe('<input aria-label="Dashboards overview">');
});
