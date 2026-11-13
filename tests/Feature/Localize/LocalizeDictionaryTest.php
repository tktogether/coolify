<?php

use App\Services\LocalizeDictionary;

test('merges every php file in the dictionary directory', function () {
    $dir = sys_get_temp_dir().'/localize-test-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/a.php', "<?php return ['Deploy' => '部署'];");
    file_put_contents($dir.'/b.php', "<?php return ['Save' => '保存'];");

    LocalizeDictionary::flush();
    expect(LocalizeDictionary::map($dir))->toBe(['Deploy' => '部署', 'Save' => '保存']);
});

test('caches the map until flushed', function () {
    LocalizeDictionary::flush();
    LocalizeDictionary::merge(['Foo' => '浮']);
    expect(LocalizeDictionary::map())->toHaveKey('Foo');
});

test('flush clears merged entries', function () {
    LocalizeDictionary::flush();
    expect(LocalizeDictionary::map())->not->toHaveKey('Foo');
});
