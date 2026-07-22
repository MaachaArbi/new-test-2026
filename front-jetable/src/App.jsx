import { useCallback, useState } from 'react'
import { loadToken, saveToken } from './api'
import Login from './screens/Login.jsx'
import PartyList from './screens/PartyList.jsx'
import CreateParty from './screens/CreateParty.jsx'

export default function App() {
  const [token, setToken] = useState(() => loadToken())
  const [view, setView] = useState(() => (loadToken() ? 'list' : 'login'))
  const [listKey, setListKey] = useState(0)

  const handleLogin = useCallback((newToken) => {
    saveToken(newToken)
    setToken(newToken)
    setView('list')
  }, [])

  const handleLogout = useCallback(() => {
    saveToken(null)
    setToken(null)
    setView('login')
  }, [])

  const handleUnauthorized = useCallback(() => {
    saveToken(null)
    setToken(null)
    setView('login')
  }, [])

  const handleCreated = useCallback(() => {
    setListKey((k) => k + 1)
    setView('list')
  }, [])

  if (!token || view === 'login') {
    return <Login onLogin={handleLogin} />
  }

  if (view === 'create') {
    return (
      <CreateParty
        token={token}
        onUnauthorized={handleUnauthorized}
        onCreated={handleCreated}
        onCancel={() => setView('list')}
      />
    )
  }

  return (
    <PartyList
      key={listKey}
      token={token}
      onUnauthorized={handleUnauthorized}
      onLogout={handleLogout}
      onCreate={() => setView('create')}
    />
  )
}
