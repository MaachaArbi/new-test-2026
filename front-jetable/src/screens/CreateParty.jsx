import { useState } from 'react'
import { apiFetch } from '../api'

export default function CreateParty({ token, onUnauthorized, onCreated, onCancel }) {
  const [nature, setNature] = useState('person')
  const [displayName, setDisplayName] = useState('')
  const [email, setEmail] = useState('')
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [loading, setLoading] = useState(false)

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setSuccess('')
    setLoading(true)
    try {
      const body = { nature, displayName }
      if (email.trim()) body.email = email.trim()

      const { data } = await apiFetch('/party-accounts', {
        method: 'POST',
        body,
        token,
        onUnauthorized,
      })
      setSuccess(`Créé : ${data.displayName} (${data.publicId})`)
      setTimeout(() => onCreated(), 600)
    } catch (err) {
      if (err.status === 401) return
      setError(err.message || 'Erreur création')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div>
      <h1>Créer un compte</h1>
      <form onSubmit={handleSubmit}>
        <fieldset style={{ border: '1px solid #ccc', padding: '0.75rem' }}>
          <legend>Nature</legend>
          <label style={{ flexDirection: 'row', alignItems: 'center', gap: '0.4rem' }}>
            <input
              type="radio"
              name="nature"
              value="person"
              checked={nature === 'person'}
              onChange={() => setNature('person')}
            />
            person
          </label>
          <label style={{ flexDirection: 'row', alignItems: 'center', gap: '0.4rem' }}>
            <input
              type="radio"
              name="nature"
              value="organization"
              checked={nature === 'organization'}
              onChange={() => setNature('organization')}
            />
            organization
          </label>
        </fieldset>

        <label>
          displayName
          <input
            type="text"
            value={displayName}
            onChange={(e) => setDisplayName(e.target.value)}
            required
          />
        </label>

        <label>
          email (optionnel)
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
        </label>

        {error && <div className="error">{error}</div>}
        {success && <div className="success">{success}</div>}

        <div className="toolbar">
          <button type="submit" disabled={loading}>
            {loading ? 'Création…' : 'Créer'}
          </button>
          <button type="button" onClick={onCancel} disabled={loading}>
            Annuler
          </button>
        </div>
      </form>
    </div>
  )
}
