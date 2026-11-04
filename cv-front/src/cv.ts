import { parseLocale, type Locale } from './i18n.ts'

export type Experience = {
  company?: string
  title?: string
  start?: string
  end?: string
  context?: string
  missions: string[]
  technologies: string[]
}

export type Cv = {
  locale: Locale
  name: string
  title: string
  location?: string
  skills: string[]
  languages: string[]
  education: string[]
  links: { label: string; url: string }[]
  experiences: Experience[]
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function record(value: unknown): Record<string, unknown> {
  if (!isRecord(value)) {
    throw new Error('Les données reçues ne correspondent pas au format du CV.')
  }
  return value
}

function text(value: unknown): string | undefined {
  return typeof value === 'string' ? value : undefined
}

function list(value: unknown): string[] {
  if (value === undefined || value === null) return []
  if (!Array.isArray(value) || !value.every((item) => typeof item === 'string')) {
    throw new Error('Une liste du CV contient des données non prises en charge.')
  }
  return value
}

export function parseCv(value: unknown): Cv {
  const data = record(value)
  const name = text(data.name)
  const title = text(data.title)
  if (!name || !title || !Array.isArray(data.experiences)) {
    throw new Error('Le CV reçu est incomplet.')
  }

  const links: Cv['links'] = []
  for (const [field, label] of [['github', 'GitHub'], ['linkedin', 'LinkedIn'], ['portfolio', 'Portfolio']]) {
    const url = text(data[field])
    if (!url) continue
    const parsed = new URL(url)
    if (!['https:', 'http:'].includes(parsed.protocol)) {
      throw new Error('Un lien professionnel utilise un protocole non pris en charge.')
    }
    links.push({ label, url })
  }

  return {
    locale: parseLocale(data.locale),
    name,
    title,
    location: text(data.location),
    skills: list(data.skills),
    languages: list(data.languages),
    education: list(data.education),
    links,
    experiences: data.experiences.map((value) => {
      const item = record(value)
      const description = item.missions ?? item.description
      return {
        company: text(item.entreprise ?? item.company),
        title: text(item.poste ?? item.title),
        start: text(item.anneeDebut ?? item.startDate),
        end: text(item.anneeFin ?? item.endDate),
        context: text(item.contexte ?? item.context),
        missions: typeof description === 'string' ? [description] : list(description),
        technologies: list(item.technologies ?? item.stack),
      }
    }),
  }
}
