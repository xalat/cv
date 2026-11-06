import { useEffect, useState } from 'react'
import { parseCv, type Cv } from './cv'
import { messages, parseLocale, type Locale } from './i18n'
import ContactPage from './ContactPage'
import './App.css'

type State = { status: 'loading' } | { status: 'error'; message: string } | { status: 'ready'; cv: Cv }

function Tags({ items }: { items: string[] }) {
  return <ul className="tags">{items.map((item, index) => <li key={`${item}-${index}`}>{item}</li>)}</ul>
}

function CvPage({ id }: { id: string }) {
  const [state, setState] = useState<State>({ status: 'loading' })
  const [attempt, setAttempt] = useState(0)

  useEffect(() => {
    const controller = new AbortController()
    async function load() {
      setState({ status: 'loading' })
      try {
        const response = await fetch(`/api/cvs/${id}`, {
          headers: { Accept: 'application/json' },
          signal: controller.signal,
        })
        if (!response.ok) {
          throw new Error(response.status === 404
            ? 'Ce CV n’est pas encore disponible.'
            : `Le CV ne peut pas être chargé (erreur ${response.status}).`)
        }
        const cv = parseCv(await response.json())
        if (!controller.signal.aborted) {
          setState({ status: 'ready', cv })
          document.title = `${cv.name} — ${messages[cv.locale].curriculumVitae}`
          document.documentElement.lang = cv.locale
        }
      } catch (error) {
        if (!controller.signal.aborted) {
          setState({
            status: 'error',
            message: error instanceof Error ? error.message : 'Impossible de contacter le serveur.',
          })
        }
      }
    }
    void load()
    return () => controller.abort()
  }, [attempt, id])

  if (state.status !== 'ready') {
    return (
      <main className="status-panel">
        <p className="eyebrow">Curriculum vitae</p>
        <a href="/">Tous les CV</a>
        {state.status === 'loading' ? <p role="status">Chargement du parcours…</p> : (
          <>
            <h1>Le CV est momentanément indisponible</h1>
            <p role="alert">{state.message}</p>
            <button onClick={() => setAttempt((value) => value + 1)}>Réessayer</button>
          </>
        )}
      </main>
    )
  }

  const { cv } = state
  const t = messages[cv.locale]
  const initials = cv.name.split(/\s+/).filter(Boolean).map((part) => part[0]).slice(0, 2).join('')

  return (
    <>
      <a className="skip-link" href="#parcours">{t.skipToCareer}</a>
      <div className="page">
        <nav className="topbar" aria-label={t.mainNavigation}>
          <a className="signature" href="/"><span aria-hidden="true">{initials}</span> {t.allCvs}</a>
          <div className="actions">
            <a className="contact-link" href="/contact">{t.contact}</a>
            <a href="#parcours">{t.career}</a>
            <button className="print-button" onClick={() => window.print()}>{t.print} <span aria-hidden="true">↗</span></button>
          </div>
        </nav>
        <main id="profil">
          <header className="hero">
            <div className="hero-main">
              <p className="eyebrow">{t.expertise}</p>
              <h1>{cv.name}</h1>
              <p className="professional-title">{cv.title}</p>
              {cv.location && <p className="location"><span aria-hidden="true">◎</span> {cv.location}</p>}
              {cv.links.length > 0 && (
                <nav className="professional-links" aria-label={t.professionalLinks}>
                  {cv.links.map(({ label, url }) => <a href={url} key={label} target="_blank" rel="noopener noreferrer">{label} <span aria-hidden="true">↗</span><span className="sr-only">{t.newTab}</span></a>)}
                </nav>
              )}
            </div>
            <div className="monogram" aria-hidden="true">{initials}<span>.</span></div>
          </header>
          <div className="cv-layout">
            <aside aria-label={t.skillsAndEducation}>
              {cv.skills.length > 0 && <section className="sidebar-section"><h2>{t.skills}</h2><Tags items={cv.skills} /></section>}
              {cv.languages.length > 0 && <section className="sidebar-section"><h2>{t.languages}</h2><ul className="plain-list">{cv.languages.map((item, index) => <li key={index}>{item}</li>)}</ul></section>}
              {cv.education.length > 0 && <section className="sidebar-section"><h2>{t.education}</h2><ul className="plain-list">{cv.education.map((item, index) => <li key={index}>{item}</li>)}</ul></section>}
            </aside>
            <section className="career" id="parcours" aria-labelledby="career-heading">
              <div className="section-heading"><h2 id="career-heading">{t.professionalCareer}</h2><span>{t.experiences}</span></div>
              {cv.experiences.length === 0 ? <p>{t.noExperiences}</p> : (
                <ol className="timeline">
                  {cv.experiences.map((experience, index) => (
                    <li className="timeline-item" key={index}>
                      <article>
                        {(experience.start || experience.end) && <p className="dates">{experience.start}{experience.end && experience.end !== experience.start ? ` — ${experience.end}` : ''}</p>}
                        <h3>{experience.company || experience.title || t.professionalExperience}</h3>
                        {experience.company && experience.title && <p className="role">{experience.title}</p>}
                        {experience.context && <p className="context">{experience.context}</p>}
                        {experience.missions.length > 0 && <ul className="missions">{experience.missions.map((mission, i) => <li key={i}>{mission}</li>)}</ul>}
                        {experience.technologies.length > 0 && <Tags items={experience.technologies} />}
                      </article>
                    </li>
                  ))}
                </ol>
              )}
            </section>
          </div>
        </main>
        <footer>
          <span>{cv.name} · {t.curriculumVitae}</span>
          <nav className="footer-links" aria-label={t.resources}>
            <a href="/contact">{t.contact}</a>
            <a href="/api/docs">{t.apiDocumentation}</a>
            <a href="#profil">{t.backToTop} ↑</a>
          </nav>
        </footer>
      </div>
    </>
  )
}

type CvCard = { id: number; name: string; title: string; location?: string; locale: Locale }
type ListState = { status: 'loading' } | { status: 'error'; message: string } | { status: 'ready'; cvs: CvCard[] }

function HomePage() {
  const [state, setState] = useState<ListState>({ status: 'loading' })
  const [attempt, setAttempt] = useState(0)

  useEffect(() => {
    const controller = new AbortController()
    document.title = 'Les CV — Curriculum vitae'
    document.documentElement.lang = 'fr'
    async function load() {
      setState({ status: 'loading' })
      try {
        const response = await fetch('/api/cvs', {
          headers: { Accept: 'application/json' },
          signal: controller.signal,
        })
        if (!response.ok) throw new Error(`Impossible de charger les CV (erreur ${response.status}).`)
        const data: unknown = await response.json()
        if (!Array.isArray(data)) throw new Error('La liste reçue ne correspond pas au format attendu.')
        const cvs = data.map((item: unknown): CvCard => {
          if (typeof item !== 'object' || item === null ||
            !('id' in item) || typeof item.id !== 'number' || !Number.isSafeInteger(item.id) || item.id < 1 ||
            !('name' in item) || typeof item.name !== 'string' ||
            !('title' in item) || typeof item.title !== 'string') {
            throw new Error('Un CV de la liste contient des données invalides.')
          }
          return {
            id: item.id, name: item.name, title: item.title,
            locale: parseLocale('locale' in item ? item.locale : undefined),
            location: 'location' in item && typeof item.location === 'string' ? item.location : undefined,
          }
        })
        if (!controller.signal.aborted) setState({ status: 'ready', cvs })
      } catch (error) {
        if (!controller.signal.aborted) {
          setState({ status: 'error', message: error instanceof Error ? error.message : 'Impossible de contacter le serveur.' })
        }
      }
    }
    void load()
    return () => controller.abort()
  }, [attempt])

  return (
    <div className="page">
      <nav className="topbar" aria-label="Navigation principale">
        <a className="signature" href="/">Curriculum vitae</a>
        <div className="actions">
          <a className="contact-link" href="/contact">Contact</a>
          <a href="/api/docs">Documentation de l’API</a>
        </div>
      </nav>
      <main>
        <header className="hero">
          <div className="hero-main">
            <p className="eyebrow">Expertise & parcours professionnel</p>
            <h1>Les CV</h1>
            <p className="professional-title">Découvrez les parcours et les versions disponibles.</p>
          </div>
        </header>
        <section className="cv-directory" aria-label="CV disponibles">
          {state.status === 'loading' && <p role="status">Chargement des CV…</p>}
          {state.status === 'error' && <div><p role="alert">{state.message}</p><button className="print-button" onClick={() => setAttempt((value) => value + 1)}>Réessayer</button></div>}
          {state.status === 'ready' && (state.cvs.length === 0
            ? <p>Aucun CV disponible pour le moment.</p>
            : <ul className="cv-cards">{state.cvs.map((cv) => (
              <li key={cv.id}>
                <a className="cv-card" href={`/cv/${cv.id}`}>
                  <h2>{cv.name}</h2>
                  <p>{cv.title}</p>
                  {cv.location && <p className="location">{cv.location}</p>}
                  <span className="card-link" lang={cv.locale}>{messages[cv.locale].consult} <span aria-hidden="true">↗</span></span>
                </a>
              </li>
            ))}</ul>)}
        </section>
      </main>
      <footer><span>Curriculum vitae</span><nav className="footer-links" aria-label="Ressources"><a href="/contact">Contact</a><a href="/api/docs">Documentation de l’API</a></nav></footer>
    </div>
  )
}

function App() {
  const path = window.location.pathname
  if (path === '/') return <HomePage />
  if (path === '/contact' || path === '/contact/') return <ContactPage />
  const match = /^\/cv\/([1-9]\d*)\/?$/.exec(path)
  if (match) return <CvPage id={match[1]} />
  return <main className="status-panel"><h1>Page introuvable</h1><p><a href="/">Revenir à la liste des CV</a></p></main>
}

export default App
