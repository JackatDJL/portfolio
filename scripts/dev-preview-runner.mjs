#!/usr/bin/env node

import { spawn } from 'node:child_process'
import { promises as fs } from 'node:fs'

function redact(value) {
  return String(value)
    .replace(/(authorization\s*[:=]\s*bearer\s+)[^\s,;]+/gi, '$1[REDACTED]')
    .replace(/((?:session)?(?:token|secret|credential|password|api[-_]?key)\s*[:=]\s*)(?:"[^"]*"|'[^']*'|[^\s,;]+)/gi, '$1[REDACTED]')
    .replace(/(["']?(?:session)?(?:token|secret|credential|password|api[-_]?key)["']?\s*:\s*)(?:"[^"]*"|'[^']*'|[^,}\s]+)/gi, '$1"[REDACTED]"')
}

async function main() {
  const [configPath] = process.argv.slice(2)
  if (!configPath) throw new Error('Runner configuration is required.')
  const config = JSON.parse(await fs.readFile(configPath, 'utf8'))
  const log = await fs.open(config.logPath, 'a', 0o600)
  const child = spawn(config.command, config.args, {
    cwd: config.cwd,
    env: process.env,
    shell: false,
    stdio: ['ignore', 'pipe', 'pipe'],
  })
  let queue = Promise.resolve()
  const append = (line) => {
    queue = queue.then(() => log.appendFile(`${redact(line)}\n`)).catch(() => {})
  }
  for (const stream of [child.stdout, child.stderr]) {
    stream.setEncoding('utf8')
    let pending = ''
    stream.on('data', (chunk) => {
      const lines = `${pending}${chunk}`.split(/\r?\n/)
      pending = lines.pop() || ''
      lines.forEach(append)
      if (pending.length > 4096) { append(pending); pending = '' }
    })
    stream.on('end', () => { if (pending) append(pending) })
  }
  const forward = (signal) => { try { child.kill(signal) } catch {} }
  process.on('SIGTERM', () => forward('SIGTERM'))
  process.on('SIGINT', () => forward('SIGINT'))
  const code = await new Promise((resolve) => {
    child.once('error', (error) => { append(error.message); resolve(1) })
    child.once('close', (exitCode, signal) => {
      if (exitCode !== 0) append(`process exited with code ${exitCode ?? 'unknown'}${signal ? ` (${signal})` : ''}`)
      resolve(exitCode ?? 1)
    })
  })
  await queue
  await log.close()
  process.exitCode = code
}

main().catch((error) => { console.error(redact(error.message)); process.exitCode = 1 })
