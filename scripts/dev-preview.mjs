#!/usr/bin/env node

import { spawn, spawnSync } from 'node:child_process'
import { createHash } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { promises as fs } from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const runnerPath = path.join(root, 'scripts', 'dev-preview-runner.mjs')
const cacheRoot = path.resolve(
  process.env.HOMEPAGE_DEV_PREVIEW_CACHE ||
    path.join(process.env.XDG_CACHE_HOME || path.join(os.homedir(), '.cache'), 'jack-homepage', 'dev-previews'),
)
const sha256 = (value) => createHash('sha256').update(value).digest('hex')
const idFor = (name) => `preview-${sha256(`${root}\0${name}`).slice(0, 16)}`
const statePath = (id) => path.join(cacheRoot, `${id}.json`)

function redact(value) {
  return String(value)
    .replace(/(authorization\s*[:=]\s*bearer\s+)[^\s,;]+/gi, '$1[REDACTED]')
    .replace(/((?:session)?(?:token|secret|credential|password|api[-_]?key)\s*[:=]\s*)(?:"[^"]*"|'[^']*'|[^\s,;]+)/gi, '$1[REDACTED]')
    .replace(/(["']?(?:session)?(?:token|secret|credential|password|api[-_]?key)["']?\s*:\s*)(?:"[^"]*"|'[^']*'|[^,}\s]+)/gi, '$1"[REDACTED]"')
}

function sourceInfo() {
  const git = (args) => {
    const result = spawnSync('git', args, { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] })
    if (result.status !== 0) throw new Error('Cannot read Git provenance for this preview.')
    return result.stdout.trim()
  }
  const sha = git(['rev-parse', 'HEAD'])
  const branch = git(['branch', '--show-current']) || 'detached'
  const dirty = git(['status', '--porcelain', '--untracked-files=all'])
  const diff = spawnSync('git', ['diff', '--binary', 'HEAD'], { cwd: root, encoding: 'buffer', stdio: ['ignore', 'pipe', 'ignore'] })
  if (diff.status !== 0) throw new Error('Cannot fingerprint the preview worktree.')
  const untracked = git(['ls-files', '--others', '--exclude-standard']).split(/\r?\n/).filter(Boolean)
    .filter((file) => !/(^|\/)\.env(?:\.|$)|\.(?:pem|key|p12|pfx|crt)$/i.test(file)).sort()
  const parts = [Buffer.from(diff.stdout)]
  for (const file of untracked) {
    try { parts.push(Buffer.from(`\0${file}\0`), readFileSync(path.join(root, file))) } catch {}
  }
  return { sha, branch: redact(branch), dirty: Boolean(dirty), worktreeDigest: sha256(Buffer.concat(parts)) }
}

function readProcessStat(file) {
  return readFileSync(file)
}

function processIdentity(pid) {
  try {
    const stat = readProcessStat(`/proc/${pid}/stat`).toString()
    const fields = stat.slice(stat.lastIndexOf(')') + 2).trim().split(/\s+/)
    return { processGroup: Number(fields[2]), startToken: fields[19] }
  } catch { return null }
}

function processMatches(entry) {
  const actual = processIdentity(entry.pid)
  return Boolean(actual && actual.processGroup === entry.processGroup && actual.startToken === entry.startToken)
}

async function ensureCache() {
  await fs.mkdir(cacheRoot, { recursive: true, mode: 0o700 })
  if (process.platform !== 'win32') await fs.chmod(cacheRoot, 0o700)
}

async function withLock(id, action) {
  const lock = `${statePath(id)}.lock`
  await ensureCache()
  const ownIdentity = processIdentity(process.pid)
  if (!ownIdentity) throw new Error('Could not establish command process ownership.')
  for (;;) {
    try {
      await fs.mkdir(lock, { mode: 0o700 })
      await fs.writeFile(path.join(lock, 'owner.json'), JSON.stringify({ pid: process.pid, ...ownIdentity }), { mode: 0o600, flag: 'wx' })
      break
    } catch (error) {
      if (error.code !== 'EEXIST') {
        await fs.rm(lock, { recursive: true, force: true })
        throw error
      }
      let owner
      try { owner = JSON.parse(await fs.readFile(path.join(lock, 'owner.json'), 'utf8')) } catch {}
      if (!owner) {
        await new Promise((resolve) => setTimeout(resolve, 100))
        try { await fs.access(path.join(lock, 'owner.json')); continue } catch {
          await fs.rm(lock, { recursive: true, force: true })
          continue
        }
      } else if (!processMatches(owner)) {
        await fs.rm(lock, { recursive: true, force: true })
        continue
      }
      throw new Error('Another preview command is updating this environment; retry shortly.')
    }
  }
  try { return await action() } finally {
    try {
      const owner = JSON.parse(await fs.readFile(path.join(lock, 'owner.json'), 'utf8'))
      if (owner.pid === process.pid && owner.startToken === ownIdentity.startToken) await fs.rm(lock, { recursive: true, force: true })
    } catch {}
  }
}

async function readState(id) {
  try { return JSON.parse(await fs.readFile(statePath(id), 'utf8')) }
  catch (error) { if (error.code === 'ENOENT') return null; throw error }
}

async function writeState(state) {
  const target = statePath(state.id)
  const temporary = `${target}.${process.pid}.tmp`
  await fs.writeFile(temporary, `${JSON.stringify(state, null, 2)}\n`, { mode: 0o600, flag: 'wx' })
  await fs.rename(temporary, target)
}

async function allocatePort(used) {
  const server = await import('node:net').then(({ createServer }) => createServer())
  await new Promise((resolve, reject) => {
    server.once('error', reject)
    server.listen({ host: '127.0.0.1', port: 0 }, resolve)
  })
  const port = server.address().port
  await new Promise((resolve) => server.close(resolve))
  if (used.has(port)) return allocatePort(used)
  used.add(port)
  return port
}

function safeEnvironment(overrides = {}) {
  const allowed = new Set(['PATH', 'HOME', 'USER', 'LOGNAME', 'SHELL', 'TMPDIR', 'TMP', 'TEMP', 'TERM', 'LANG', 'LC_ALL', 'LC_CTYPE', 'XDG_CACHE_HOME', 'PHP_INI_SCAN_DIR', 'SystemRoot', 'PATHEXT', 'ComSpec'])
  return Object.fromEntries([...Object.entries(process.env).filter(([key]) => allowed.has(key) || /^LC_/i.test(key)), ...Object.entries(overrides)])
}

async function startProcess(state, name, command, args, port, url, extraEnv = {}) {
  const logPath = path.join(state.directory, `${name}.log`)
  const runnerConfigPath = path.join(state.directory, `${name}.runner.json`)
  const config = { command, args, cwd: root, logPath }
  await fs.writeFile(runnerConfigPath, `${JSON.stringify(config)}\n`, { mode: 0o600 })
  const child = spawn('bun', ['--no-env-file', runnerPath, runnerConfigPath], {
    cwd: root,
    env: safeEnvironment(extraEnv),
    detached: true,
    shell: false,
    stdio: 'ignore',
  })
  await new Promise((resolve, reject) => { child.once('spawn', resolve); child.once('error', reject) })
  child.unref()
  const identity = processIdentity(child.pid)
  if (!identity) throw new Error(`Could not establish ownership for ${name}.`)
  const entry = { name, pid: child.pid, ...identity, port, url, logPath, runnerConfigPath }
  state.processes.push(entry)
  await writeState(state)
  return entry
}

async function waitReady(entry, timeoutMs = 120_000) {
  const deadline = Date.now() + timeoutMs
  while (Date.now() < deadline) {
    if (!processMatches(entry)) throw new Error(`${entry.name} exited before becoming ready.\n${await tail(entry.logPath, 25)}`)
    try {
      const response = await fetch(entry.url, { redirect: 'manual', signal: AbortSignal.timeout(1000) })
      if (response.status < 500) return
    } catch {}
    await new Promise((resolve) => setTimeout(resolve, 250))
  }
  const recentLog = await tail(entry.logPath, 25)
  throw new Error(`${entry.name} did not become ready in ${Math.round(timeoutMs / 1000)} seconds.${recentLog ? `\nRecent log output:\n${recentLog}` : ''}`)
}

async function tail(file, count = 60) {
  let handle
  try {
    handle = await fs.open(file, 'r')
    const { size } = await handle.stat()
    const start = Math.max(0, size - 64 * 1024)
    const buffer = Buffer.alloc(size - start)
    await handle.read(buffer, 0, buffer.length, start)
    let content = buffer.toString('utf8')
    if (start > 0) {
      const firstLine = content.indexOf('\n')
      content = firstLine < 0 ? '' : content.slice(firstLine + 1)
    }
    return redact(content.split(/\r?\n/).slice(-count).join('\n').trim())
  } catch { return '' } finally { await handle?.close() }
}

async function stopState(state, { preserveArtifacts = false } = {}) {
  const failures = []
  for (const entry of [...state.processes].reverse()) {
    if (!processMatches(entry)) continue
    try {
      process.kill(-entry.processGroup, 'SIGTERM')
      const end = Date.now() + 3000
      while (Date.now() < end && processMatches(entry)) await new Promise((resolve) => setTimeout(resolve, 100))
      if (processMatches(entry)) process.kill(-entry.processGroup, 'SIGKILL')
    } catch (error) { if (error.code !== 'ESRCH') failures.push(`${entry.name}: ${redact(error.message)}`) }
  }
  if (preserveArtifacts) {
    if (failures.length) {
      state.status = 'cleanup-failed'
      state.failures = [...(state.failures || []), ...failures]
    }
    await writeState(state)
  } else if (!failures.length) {
    await fs.rm(statePath(state.id), { force: true })
    await fs.rm(state.directory, { recursive: true, force: true })
  }
  return failures
}

async function up(name) {
  const id = idFor(name)
  return withLock(id, async () => {
    const source = sourceInfo()
    const fingerprint = sha256(JSON.stringify({ source, surface: 'laravel-vite-web' }))
    const existing = await readState(id)
    if (existing) {
      if (existing.fingerprint !== fingerprint) throw new Error(`Preview "${redact(name)}" already runs different source or options. Run preview down ${name} first.`)
      const currentStatus = await statusFor(existing)
      if (existing.processes.length && currentStatus.processes.every((entry) => entry.process === 'running' && entry.healthy)) {
        console.log(JSON.stringify(currentStatus, null, 2))
        return
      }
      const errors = await stopState(existing)
      if (errors.length) throw new Error(errors.join('; '))
    }
    const used = new Set()
    const appPort = await allocatePort(used)
    const vitePort = await allocatePort(used)
    const config = { appPort, vitePort }
    const directory = path.join(cacheRoot, id)
    await fs.mkdir(directory, { recursive: true, mode: 0o700 })
    const hotFile = path.join(directory, 'vite.hot')
    const envDirectory = path.join(directory, 'empty-env')
    await fs.mkdir(envDirectory, { recursive: true, mode: 0o700 })
    const state = {
      version: 1, id, name: redact(name), status: 'starting', source, config, fingerprint,
      directory, localUrls: { app: `http://127.0.0.1:${appPort}`, vite: `http://127.0.0.1:${vitePort}` },
      processes: [], createdAt: new Date().toISOString(),
    }
    await writeState(state)
    try {
      const safeArgs = { HOMEPAGE_PREVIEW_HOT_FILE: hotFile, HOMEPAGE_PREVIEW_ENV_DIR: envDirectory }
      await startProcess(state, 'vite', 'bun', ['--no-env-file', 'run', 'dev', '--', '--host', '127.0.0.1', '--port', String(vitePort), '--strictPort'], vitePort, `${state.localUrls.vite}/@vite/client`, { ...safeArgs, HOMEPAGE_PREVIEW_HOST: '127.0.0.1', HOMEPAGE_PREVIEW_VITE_PORT: String(vitePort) })
      await waitReady(state.processes.at(-1))
      await startProcess(state, 'laravel', 'php', ['artisan', 'serve', '--host=127.0.0.1', `--port=${appPort}`], appPort, state.localUrls.app, safeArgs)
      await waitReady(state.processes.at(-1))
      state.status = 'running'
      await writeState(state)
      console.log(JSON.stringify(await statusFor(state), null, 2))
    } catch (error) {
      state.status = 'failed'
      state.failures = [redact(error.message)]
      await stopState(state, { preserveArtifacts: true })
      throw new Error(`${redact(error.message)} Preview diagnostics retained at ${state.directory}.`)
    }
  })
}

async function statusFor(state) {
  const processes = await Promise.all(state.processes.map(async (entry) => {
    let healthy = false
    if (processMatches(entry)) {
      try { const response = await fetch(entry.url, { signal: AbortSignal.timeout(1000) }); healthy = response.status < 500 } catch {}
    }
    return { name: entry.name, pid: entry.pid, process: processMatches(entry) ? 'running' : 'stopped', endpoint: entry.url, healthy }
  }))
  return { id: state.id, name: state.name, status: state.status, source: state.source, urls: state.localUrls, processes, cleanup: `bun run preview -- down --id ${state.id}` }
}

async function main() {
  const [command, ...raw] = process.argv.slice(2)
  const idIndex = raw.indexOf('--id')
  if (idIndex >= 0 && (!raw[idIndex + 1] || raw[idIndex + 1].startsWith('--'))) {
    throw new Error('Missing value for --id.')
  }
  const requestedId = idIndex >= 0 ? raw[idIndex + 1] : null
  if (requestedId && !/^preview-[a-f0-9]{16}$/.test(requestedId)) throw new Error('Preview ID must be a generated preview ID.')
  const name = raw.find((arg, index) => !arg.startsWith('--') && (idIndex < 0 || index !== idIndex + 1)) || sourceInfo().branch || 'preview'
  if (!['up', 'status', 'logs', 'down'].includes(command)) {
    throw new Error('Usage: bun run preview -- <up|status|logs|down> [name|--id preview-<hex>]')
  }
  if (process.platform !== 'linux') throw new Error('This initial port uses Linux /proc process ownership checks.')
  if (command === 'up' && requestedId) throw new Error('A preview ID cannot be combined with up.')
  const id = requestedId || idFor(name)
  if (command === 'up') return up(name)
  return withLock(id, async () => {
    const state = await readState(id)
    if (!state) throw new Error(requestedId ? `No preview with ID ${requestedId} is recorded.` : `No preview named "${redact(name)}" is recorded.`)
    if (command === 'status') console.log(JSON.stringify(await statusFor(state), null, 2))
    else if (command === 'logs') {
      for (const entry of state.processes) console.log(`--- ${entry.name} ---\n${await tail(entry.logPath)}`)
    } else {
      const failures = await stopState(state)
      if (failures.length) throw new Error(failures.join('; '))
      console.log(`Stopped preview ${redact(state.name)}.`)
    }
  })
}

main().catch((error) => { console.error(redact(error.message)); process.exitCode = 1 })
