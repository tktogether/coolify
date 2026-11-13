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
