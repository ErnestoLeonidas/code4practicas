// Wrapper mínimo de fetch para consumir la API.
// Todas las rutas cuelgan de /api (proxy de Vite en dev, mismo dominio en prod).

const BASE = '/api'

async function request(path, options = {}) {
  const res = await fetch(BASE + path, {
    headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
    credentials: 'include', // sesiones PHP (cookie) a partir de v0.1.0
    ...options,
  })

  let data = null
  const text = await res.text()
  if (text) {
    try {
      data = JSON.parse(text)
    } catch {
      data = text
    }
  }

  if (!res.ok) {
    const message = data?.error?.message || `Error HTTP ${res.status}`
    const code = data?.error?.code || 'http_error'
    throw Object.assign(new Error(message), { code, status: res.status })
  }

  return data
}

export const api = {
  get: (path) => request(path),
  post: (path, body) => request(path, { method: 'POST', body: JSON.stringify(body) }),
  put: (path, body) => request(path, { method: 'PUT', body: JSON.stringify(body) }),
  patch: (path, body) => request(path, { method: 'PATCH', body: JSON.stringify(body) }),
  delete: (path) => request(path, { method: 'DELETE' }),
}
