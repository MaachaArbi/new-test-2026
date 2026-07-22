import { useEffect, useState } from 'react'
import { apiFetch } from '../api'

function truncateId(id) {
  if (!id) return ''
  return id.length > 12 ? `${id.slice(0, 8)}…` : id
}

export default function PartyList({ token, onUnauthorized, onLogout, onCreate }) {
  const [nature, setNature] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [rows, setRows] = useState([])
  const [meta, setMeta] = useState({ page: 1, limit: 20, total: 0, totalPages: 1 })
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    let cancelled = false
    async function load() {
      setLoading(true)
      setError('')
      try {
        const params = new URLSearchParams()
        params.set('page', String(page))
        params.set('limit', '20')
        if (nature) params.set('nature', nature)
        if (search.trim()) params.set('search', search.trim())

        const { data } = await apiFetch(`/party-accounts?${params}`, {
          token,
          onUnauthorized,
        })
        if (cancelled) return
        setRows(data?.data ?? [])
        setMeta(data?.meta ?? { page, limit: 20, total: 0, totalPages: 1 })
      } catch (err) {
        if (cancelled) return
        if (err.status === 401) return
        setError(err.message || 'Erreur chargement')
        setRows([])
      } finally {
        if (!cancelled) setLoading(false)
      }
    }
    load()
    return () => {
      cancelled = true
    }
  }, [token, page, nature, search, onUnauthorized])

  return (
    <div>
      <h1>Liste des tiers</h1>
      <div className="toolbar">
        <button type="button" onClick={onCreate}>
          Créer un compte
        </button>
        <button type="button" onClick={onLogout}>
          Déconnexion
        </button>
      </div>

      <div className="row">
        <label>
          Nature
          <select
            value={nature}
            onChange={(e) => {
              setPage(1)
              setNature(e.target.value)
            }}
          >
            <option value="">tous</option>
            <option value="person">person</option>
            <option value="organization">organization</option>
          </select>
        </label>
        <label>
          Recherche
          <input
            type="text"
            value={search}
            onChange={(e) => {
              setPage(1)
              setSearch(e.target.value)
            }}
            placeholder="displayName / email…"
          />
        </label>
      </div>

      {error && <div className="error">{error}</div>}
      {loading && <p className="muted">Chargement…</p>}

      <table>
        <thead>
          <tr>
            <th>publicId</th>
            <th>nature</th>
            <th>displayName</th>
            <th>email</th>
          </tr>
        </thead>
        <tbody>
          {rows.length === 0 && !loading ? (
            <tr>
              <td colSpan={4} className="muted">
                Aucun résultat
              </td>
            </tr>
          ) : (
            rows.map((r) => (
              <tr key={r.publicId}>
                <td title={r.publicId}>{truncateId(r.publicId)}</td>
                <td>{r.nature}</td>
                <td>{r.displayName}</td>
                <td>{r.email ?? ''}</td>
              </tr>
            ))
          )}
        </tbody>
      </table>

      <div className="row" style={{ marginTop: '1rem' }}>
        <button
          type="button"
          disabled={page <= 1 || loading}
          onClick={() => setPage((p) => Math.max(1, p - 1))}
        >
          Précédent
        </button>
        <span className="muted">
          page {meta.page} / {meta.totalPages || 1} — total {meta.total}
        </span>
        <button
          type="button"
          disabled={page >= (meta.totalPages || 1) || loading}
          onClick={() => setPage((p) => p + 1)}
        >
          Suivant
        </button>
      </div>
    </div>
  )
}
