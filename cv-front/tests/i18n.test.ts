import assert from 'node:assert/strict'
import { test } from 'node:test'
import { parseCv } from '../src/cv.ts'
import { messages, parseLocale } from '../src/i18n.ts'

const cv = { name: 'Example', title: 'Developer', experiences: [] }

test('CV locale defaults to French for older API responses', () => {
  assert.equal(parseCv(cv).locale, 'fr')
  assert.equal(parseCv({ ...cv, locale: 'fr' }).locale, 'fr')
})

test('English CVs select the English dictionary without changing content', () => {
  const parsed = parseCv({ ...cv, locale: 'en', skills: ['PHP'], education: ['Degree'] })
  assert.equal(parsed.locale, 'en')
  assert.equal(parsed.title, cv.title)
  assert.deepEqual(parsed.skills, ['PHP'])
  assert.deepEqual(parsed.education, ['Degree'])
  assert.equal(messages[parsed.locale].skills, 'Skills')
  assert.equal(messages[parsed.locale].languages, 'Languages')
  assert.equal(messages[parsed.locale].education, 'Education')
  assert.equal(messages[parsed.locale].professionalCareer, 'Professional experience')
  assert.equal(messages[parsed.locale].print, 'Print / PDF')
  assert.equal(messages.fr.skills, 'Compétences')
})

test('both dictionaries contain all labels, including accessible labels', () => {
  assert.deepEqual(Object.keys(messages.en).sort(), Object.keys(messages.fr).sort())
  for (const dictionary of Object.values(messages)) {
    for (const value of Object.values(dictionary)) assert.ok(value.trim())
  }
})

test('CV consultation labels are translated', () => {
  assert.equal(messages.fr.consult, 'Consulter le CV')
  assert.equal(messages.en.consult, 'View CV')
})

test('unsupported or malformed locales are rejected explicitly', () => {
  for (const locale of ['de', 'en-US', '', null, 123]) {
    assert.throws(() => parseLocale(locale), /langue du CV/)
    assert.throws(() => parseCv({ ...cv, locale }), /langue du CV/)
  }
})
