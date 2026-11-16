import test from 'node:test';
import assert from 'node:assert/strict';
import { applyClientRules } from '../../public/js/localize-rules.js';

test('translates the not-scanned sentence with any counts', () => {
    assert.equal(
        applyClientRules('1 resource on 1 server was not scanned, so Coolify cannot tell.'),
        '1 个资源，位于 1 台服务器上未被扫描，Coolify 无法判断。',
    );
    assert.equal(
        applyClientRules('3 resources on 2 servers were not scanned, so Coolify cannot tell.'),
        '3 个资源，位于 2 台服务器上未被扫描，Coolify 无法判断。',
    );
    assert.equal(
        applyClientRules('1 resource on 4 servers was not scanned, so Coolify cannot tell.'),
        '1 个资源，位于 4 台服务器上未被扫描，Coolify 无法判断。',
    );
    assert.equal(
        applyClientRules('25 resources on 1 server were not scanned, so Coolify cannot tell.'),
        '25 个资源，位于 1 台服务器上未被扫描，Coolify 无法判断。',
    );
    assert.equal(
        applyClientRules('1,204 resources on 12 servers were not scanned, so Coolify cannot tell.'),
        '1,204 个资源，位于 12 台服务器上未被扫描，Coolify 无法判断。',
    );
});

test('translates the not-scanned sentence without a server count', () => {
    assert.equal(
        applyClientRules('2 resources were not scanned, so Coolify cannot tell.'),
        '2 个资源未被扫描，Coolify 无法判断。',
    );
});

test('translates a whole node that is only a count and a unit', () => {
    assert.equal(applyClientRules('12 servers'), '12 台服务器');
    assert.equal(applyClientRules('3 containers'), '3 个容器');
    assert.equal(applyClientRules('1 backup'), '1 个备份');
});

test('leaves user data untouched', () => {
    const userData = [
        'my-3 servers-app',
        'project-12-servers',
        'backup-2024-01-15.tar.gz',
        '/data/coolify/applications/yrrqrbspwb0duca7dxdtd7yp',
        'postgres://user:pass@db:5432/app',
        '10.0.0.1',
        'nginx:1.2.3',
        'v4.7.5',
        '3 servers-app',
        'my-app-2 services',
    ];

    for (const value of userData) {
        assert.equal(applyClientRules(value), value, `was damaged: ${value}`);
    }
});

test('does not touch text that only looks similar', () => {
    const untouched = [
        'Servers',
        'The server is running',
        '3 servers are offline',
        'resource',
    ];

    for (const value of untouched) {
        assert.equal(applyClientRules(value), value, `should stay: ${value}`);
    }
});

test('the companion script ships the same rules as this module', async () => {
    // public/js/localize-client.js is a classic script, so it carries an inline
    // copy of the rules instead of importing this module. A sample of inputs goes
    // through both, so the copy cannot drift unnoticed.
    const fs = await import('node:fs');
    const vm = await import('node:vm');

    let source = fs.readFileSync(new URL('../../public/js/localize-client.js', import.meta.url), 'utf8');
    source = source.replace(
        /\n\}\)\(\);\s*$/,
        '\n  globalThis.__localizeRules = applyRules;\n})();',
    );

    const sandbox = {
        document: { documentElement: { dataset: {} }, createTreeWalker: () => ({ nextNode: () => null }) },
        MutationObserver: class { observe() {} disconnect() {} },
        NodeFilter: { SHOW_TEXT: 4 },
        setTimeout: () => 0,
        clearTimeout: () => {},
        Set,
        console,
    };
    sandbox.globalThis = sandbox;
    sandbox.window = sandbox;
    vm.createContext(sandbox);
    vm.runInContext(source, sandbox);

    const inline = sandbox.__localizeRules;
    assert.equal(typeof inline, 'function', 'the companion script did not expose its rules');

    const samples = [
        '1 resource on 1 server was not scanned, so Coolify cannot tell.',
        '3 resources on 2 servers were not scanned, so Coolify cannot tell.',
        '2 resources were not scanned, so Coolify cannot tell.',
        '12 servers',
        '3 containers',
        'my-3 servers-app',
        'nginx:1.2.3',
        'Servers',
    ];

    for (const sample of samples) {
        assert.equal(inline(sample), applyClientRules(sample), `drifted on: ${sample}`);
    }
});
