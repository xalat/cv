export type ContactFields = { name: string; email: string; message: string; website: string }
export type ContactResult =
  | { status: 'success'; message: string }
  | { status: 'error'; message: string; errors: Partial<Record<keyof ContactFields, string>> }

export async function sendContact(fields: ContactFields): Promise<ContactResult> {
  let response: Response
  try {
    response = await fetch('/api/contact', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(fields),
    })
  } catch {
    return { status: 'error', message: 'Impossible de confirmer l’envoi. Vérifiez votre connexion avant de réessayer.', errors: {} }
  }

  let data: unknown
  try {
    data = await response.json()
  } catch {
    return { status: 'error', message: 'Le serveur a renvoyé une réponse illisible. L’envoi ne peut pas être confirmé.', errors: {} }
  }
  if (typeof data !== 'object' || data === null || !('message' in data) || typeof data.message !== 'string') {
    return { status: 'error', message: 'La réponse du serveur ne permet pas de confirmer l’envoi.', errors: {} }
  }
  if (response.ok) return { status: 'success', message: data.message }

  const errors: Partial<Record<keyof ContactFields, string>> = {}
  if ('errors' in data && typeof data.errors === 'object' && data.errors !== null) {
    for (const [field, value] of Object.entries(data.errors)) {
      if ((field === 'name' || field === 'email' || field === 'message' || field === 'website') && typeof value === 'string') {
        errors[field] = value
      }
    }
  }
  return { status: 'error', message: data.message, errors }
}
