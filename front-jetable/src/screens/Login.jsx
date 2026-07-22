import { useState } from 'react'
import { apiFetch } from '../api'

export default function Login({ onLogin }) {
  const [email, setEmail] = useState('booking@mygo.pro')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      const { data } = await apiFetch('/auth/login', {
        method: 'POST',
        body: { email, password },
      })
      if (!data?.token) {
        setError('Réponse login sans token')
        return
      }
      onLogin(data.token)
    } catch (err) {
      setError(err.message || 'Échec du login')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div>
      <h1>OsTravel — Login (jetable)</h1>
      <form onSubmit={handleSubmit}>
        <label>
          Email
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            autoComplete="username"
          />
        </label>
        <label>
          Mot de passe
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            autoComplete="current-password"
          />
        </label>
        {error && <div className="error">{error}</div>}
        <button type="submit" disabled={loading}>
          {loading ? 'Connexion…' : 'Se connecter'}
        </button>
      </form>
    </div>
  )
}
