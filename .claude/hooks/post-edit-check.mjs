#!/usr/bin/env node
// PostToolUse hook: php -l for edited .php, JSON.parse for edited .json.
// Silent on success (zero tokens). On error: exit 2 + short stderr so Claude fixes it.
import { execFileSync } from 'node:child_process'
import { existsSync, readFileSync } from 'node:fs'

const PHP_CANDIDATES = [process.env.PHP_BIN, 'C:/xampp/php/php.exe', 'php'].filter(Boolean)

let input = ''
process.stdin.on('data', (c) => (input += c))
process.stdin.on('end', () => {
  let file
  try {
    file = JSON.parse(input)?.tool_input?.file_path
  } catch {
    process.exit(0)
  }
  if (!file || !existsSync(file) || /[\\/]vendor[\\/]/.test(file)) process.exit(0)

  if (file.endsWith('.json')) {
    try {
      JSON.parse(readFileSync(file, 'utf8'))
    } catch (e) {
      process.stderr.write(`Invalid JSON in ${file}: ${e.message}\n`)
      process.exit(2)
    }
    process.exit(0)
  }

  if (!file.endsWith('.php')) process.exit(0)

  for (const php of PHP_CANDIDATES) {
    if (php !== 'php' && !existsSync(php)) continue
    try {
      execFileSync(php, ['-l', file], { stdio: ['ignore', 'pipe', 'pipe'], timeout: 15000 })
      process.exit(0)
    } catch (err) {
      if (err.code === 'ENOENT') continue // binary not found, try next
      const msg = `${err.stdout || ''}${err.stderr || ''}`.trim().split('\n').slice(0, 5).join('\n')
      if (!msg) process.exit(0)
      process.stderr.write(`php -l failed:\n${msg}\n`)
      process.exit(2)
    }
  }
  process.exit(0) // no PHP binary available — don't block
})
