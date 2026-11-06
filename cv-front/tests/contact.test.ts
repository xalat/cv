import assert from 'node:assert/strict'
import { test } from 'node:test'
import { sendContact } from '../src/contact.ts'

const fields = { name: 'Visitor', email: 'visitor@example.com', message: 'Hello', website: '' }

test('contact sends JSON to the API without specifying a recipient', async (t) => {
  t.mock.method(globalThis, 'fetch', async (url: string, options: RequestInit) => {
    assert.equal(url, '/api/contact')
    assert.equal(options.method, 'POST')
    assert.deepEqual(options.headers, { 'Content-Type': 'application/json', Accept: 'application/json' })
    assert.deepEqual(JSON.parse(String(options.body)), fields)
    return Response.json({ message: 'Transmis.' })
  })
  assert.deepEqual(await sendContact(fields), { status: 'success', message: 'Transmis.' })
})

test('validation errors retain only known fields with text messages', async (t) => {
  t.mock.method(globalThis, 'fetch', async () => Response.json({
    message: 'Vérifiez les champs.',
    errors: { email: 'Adresse invalide.', name: null, to: 'Private recipient' },
  }, { status: 422 }))
  assert.deepEqual(await sendContact(fields), {
    status: 'error', message: 'Vérifiez les champs.', errors: { email: 'Adresse invalide.' },
  })
})

test('SMTP failures and rate limits are not reported as success', async (t) => {
  for (const status of [429, 503]) {
    const mock = t.mock.method(globalThis, 'fetch', async () => Response.json({ message: 'Réessayez plus tard.' }, { status }))
    assert.deepEqual(await sendContact(fields), { status: 'error', message: 'Réessayez plus tard.', errors: {} })
    mock.mock.restore()
  }
})

test('network errors explicitly leave delivery unconfirmed', async (t) => {
  t.mock.method(globalThis, 'fetch', async () => { throw new TypeError('Network unavailable') })
  const result = await sendContact(fields)
  assert.equal(result.status, 'error')
  assert.match(result.message, /Impossible de confirmer/)
})

test('invalid and malformed responses never confirm delivery', async (t) => {
  for (const response of [new Response('<html>Error</html>'), Response.json({}), Response.json(null)]) {
    const mock = t.mock.method(globalThis, 'fetch', async () => response)
    const result = await sendContact(fields)
    assert.equal(result.status, 'error')
    mock.mock.restore()
  }
})
