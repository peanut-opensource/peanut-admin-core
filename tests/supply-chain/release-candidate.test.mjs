import assert from 'node:assert/strict'
import { execFileSync, spawnSync } from 'node:child_process'
import { createHash } from 'node:crypto'
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join, resolve } from 'node:path'
import test from 'node:test'

const source = resolve('scripts/check-release-candidate')
const version = '0.1.0-alpha.13'
const recordPath = `docs/releases/qualifications/${version}.json`
const hash = bytes => createHash('sha256').update(bytes).digest('hex')

// Real isolated Git history, tags and subprocesses; no registries or qualification resources.
function fixture(run) {
  const root = mkdtempSync(join(tmpdir(), 'peanut-release-gate-'))
  const git = (...args) => execFileSync('git', args, { cwd: root, stdio: ['ignore', 'pipe', 'pipe'] }).toString().trim()
  const write = (path, content) => {
    mkdirSync(dirname(join(root, path)), { recursive: true })
    writeFileSync(join(root, path), typeof content === 'string' || Buffer.isBuffer(content) ? content : `${JSON.stringify(content, null, 2)}\n`)
  }
  const commit = message => { git('add', '.'); git('commit', '-qm', message); return git('rev-parse', 'HEAD') }
  const execute = (...args) => spawnSync(process.execPath, [join(root, 'scripts/check-release-candidate'), ...args], { cwd: root, encoding: 'utf8' })
  try {
    git('init', '-q')
    git('config', 'user.name', 'Release gate fixture')
    git('config', 'user.email', 'release-gate@example.test')
    write('package.json', { type: 'module' })
    write('scripts/check-release-candidate', readFileSync(source))
    write('packages/php/composer.json', { name: 'peanut-admin/core', version })
    write('packages/web/package.json', { name: '@peanut-admin/admin', version })
    write('backend/runtime.php', '<?php // qualified runtime\n')
    const candidate = commit('fixed candidate')
    const identityRun = execute('--identity', candidate)
    assert.equal(identityRun.status, 0, identityRun.stderr)
    const identity = JSON.parse(identityRun.stdout)
    const record = { schema_version: 1, version, status: 'pass', ...identity }
    for (const name of ['q01', 'd05']) {
      const evidencePath = `docs/releases/evidence/${version}/${name}.json`
      const evidence = JSON.stringify({ candidate, status: 'pass', fixture: true }) + '\n'
      write(evidencePath, evidence)
      record[name] = { status: 'pass', candidate, evidence_path: evidencePath, evidence_sha256: hash(evidence) }
    }
    record.q01.command = './scripts/check'
    record.d05.roles = Array.from({ length: 9 }, (_, index) => ({ id: index + 1, status: 'pass' }))
    run({ root, git, write, commit, execute, candidate, record })
  } finally {
    rmSync(root, { recursive: true, force: true })
  }
}

function seal(f, record = f.record) {
  f.write(recordPath, record)
  f.commit('committed qualification evidence')
  f.git('tag', '-a', '-m', 'fixture release', `v${version}`)
  return f.execute(`v${version}`)
}

test('passes a later evidence commit and computes stable content digests', () => fixture(f => {
  const result = seal(f)
  assert.equal(result.status, 0, result.stderr)
  assert.match(result.stdout, new RegExp(`candidate=${f.candidate}`))
  const later = JSON.parse(f.execute('--identity', f.git('rev-parse', 'HEAD')).stdout)
  assert.deepEqual(later.projections, f.record.projections)
}))

for (const [name, mutate] of [
  ['pending qualification', record => { record.status = 'pending' }],
  ['abbreviated candidate', record => { record.candidate = record.candidate.slice(0, 7) }],
  ['failed Q01', record => { record.q01.status = 'fail' }],
  ['Q01 bound to another candidate', record => { record.q01.candidate = 'a'.repeat(40) }],
  ['missing D05 role', record => { record.d05.roles.pop() }],
  ['duplicate D05 role', record => { record.d05.roles[8].id = 1 }],
  ['failed D05 role', record => { record.d05.roles[4].status = 'fail' }],
  ['wrong Composer projection', record => { record.projections.composer.sha256 = '0'.repeat(64) }],
  ['wrong npm projection', record => { record.projections.npm.sha256 = '0'.repeat(64) }],
  ['tampered evidence digest', record => { record.d05.evidence_sha256 = '0'.repeat(64) }],
]) {
  test(`rejects ${name}`, () => fixture(f => {
    mutate(f.record)
    assert.equal(seal(f).status, 1)
  }))
}

test('rejects a missing committed qualification record', () => fixture(f => {
  f.commit('evidence without qualification record')
  f.git('tag', '-a', '-m', 'fixture release', `v${version}`)
  const result = f.execute(`v${version}`)
  assert.equal(result.status, 1)
  assert.match(result.stderr, /Not a committed regular file/)
}))

for (const path of ['backend/runtime.php', 'packages/php/extra.php', 'packages/web/extra.js', '.github/workflows/release.yml']) {
  test(`rejects post-candidate change to ${path}, even reverted`, () => fixture(f => {
    f.write(path, 'unqualified change\n')
    f.commit('unqualified intermediate change')
    f.git('revert', '--no-edit', 'HEAD')
    const result = seal(f)
    assert.equal(result.status, 1)
    assert.match(result.stderr, /Unqualified change after candidate/)
  }))
}

test('rejects uncommitted record substitution', () => fixture(f => {
  const valid = seal(f)
  assert.equal(valid.status, 0, valid.stderr)
  f.write(recordPath, { ...f.record, status: 'pending' })
  const result = f.execute(`v${version}`)
  assert.equal(result.status, 1)
  assert.match(result.stderr, /must be clean/)
}))
