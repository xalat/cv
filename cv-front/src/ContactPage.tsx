import { useEffect, useRef, useState, type FormEvent } from 'react'
import { sendContact, type ContactFields, type ContactResult } from './contact'

const emptyFields: ContactFields = { name: '', email: '', message: '', website: '' }

export default function ContactPage() {
  const [fields, setFields] = useState<ContactFields>(emptyFields)
  const [pending, setPending] = useState(false)
  const [result, setResult] = useState<ContactResult | null>(null)
  const feedback = useRef<HTMLDivElement>(null)

  useEffect(() => {
    document.title = 'Contact — Curriculum vitae'
    document.documentElement.lang = 'fr'
  }, [])

  useEffect(() => {
    if (result) feedback.current?.focus()
  }, [result])

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (pending) return
    setPending(true)
    setResult(null)
    const outcome = await sendContact(fields)
    setResult(outcome)
    setPending(false)
    if (outcome.status === 'success') setFields(emptyFields)
  }

  const errors = result?.status === 'error' ? result.errors : {}
  function update(field: keyof ContactFields, value: string) {
    setFields((current) => ({ ...current, [field]: value }))
  }

  return (
    <div className="page">
      <a className="skip-link" href="#contact-form">Aller au formulaire</a>
      <nav className="topbar" aria-label="Navigation principale">
        <a className="signature" href="/">Tous les CV</a>
        <a href="/contact" aria-current="page">Contact</a>
      </nav>
      <main>
        <header className="hero">
          <div className="hero-main">
            <p className="eyebrow">Échangeons</p>
            <h1>Contact</h1>
            <p className="professional-title">Un projet, une opportunité ou une question ? Envoyez-moi un message.</p>
          </div>
        </header>
        <section className="contact-section" aria-label="Formulaire de contact">
          <p id="contact-help">Tous les champs sont obligatoires.</p>
          <form id="contact-form" onSubmit={(event) => { void submit(event) }} aria-describedby="contact-help contact-privacy" aria-busy={pending}>
            <fieldset disabled={pending} className="contact-fields">
              <legend className="sr-only">Votre message</legend>
              <div className="contact-field">
                <label htmlFor="contact-name">Votre nom</label>
                <input id="contact-name" name="name" autoComplete="name" required maxLength={100} value={fields.name} onChange={(event) => update('name', event.target.value)} aria-invalid={!!errors.name} aria-describedby={errors.name ? 'contact-name-error' : undefined} />
                {errors.name && <p className="field-error" id="contact-name-error">{errors.name}</p>}
              </div>
              <div className="contact-field">
                <label htmlFor="contact-email">Votre adresse e-mail</label>
                <input id="contact-email" name="email" type="email" autoComplete="email" required maxLength={254} value={fields.email} onChange={(event) => update('email', event.target.value)} aria-invalid={!!errors.email} aria-describedby={errors.email ? 'contact-email-error' : undefined} />
                {errors.email && <p className="field-error" id="contact-email-error">{errors.email}</p>}
              </div>
              <div className="contact-field">
                <label htmlFor="contact-message">Votre message</label>
                <textarea id="contact-message" name="message" required maxLength={5000} rows={8} value={fields.message} onChange={(event) => update('message', event.target.value)} aria-invalid={!!errors.message} aria-describedby={`contact-message-help${errors.message ? ' contact-message-error' : ''}`} />
                <p className="field-help" id="contact-message-help">5 000 caractères maximum.</p>
                {errors.message && <p className="field-error" id="contact-message-error">{errors.message}</p>}
              </div>
              <div className="contact-trap" aria-hidden="true">
                <label htmlFor="contact-website">Laissez ce champ vide</label>
                <input id="contact-website" name="website" tabIndex={-1} autoComplete="off" value={fields.website} onChange={(event) => update('website', event.target.value)} />
              </div>
              <p className="field-help" id="contact-privacy">Votre nom, votre adresse e-mail et votre message sont transmis par e-mail uniquement pour répondre à votre demande. Ils ne sont pas enregistrés dans la base du site. Les échanges sont conservés dans la boîte mail le temps de traiter votre demande. Vous pouvez demander leur suppression en utilisant ce formulaire.</p>
              <button className="contact-submit" type="submit">{pending ? 'Envoi en cours…' : 'Envoyer le message'}</button>
            </fieldset>
          </form>
          {pending && <p role="status">Transmission du message en cours…</p>}
          {result && <div ref={feedback} tabIndex={-1} role={result.status === 'error' ? 'alert' : 'status'} className={`contact-feedback ${result.status}`}><p>{result.message}</p></div>}
        </section>
      </main>
      <footer><span>Curriculum vitae</span><a href="/">Tous les CV</a></footer>
    </div>
  )
}
