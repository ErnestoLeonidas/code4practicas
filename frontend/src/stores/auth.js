import { defineStore } from 'pinia'
import { api } from '../services/api'

// Store de sesión de usuario: bootstrap del guard y login/logout (v0.1.0).
export const useAuthStore = defineStore('auth', {
  state: () => ({
    usuario: null,
    inicializado: false,
    cargando: false,
    error: null,
  }),
  getters: {
    autenticado: (state) => state.usuario !== null,
    debeCambiarPassword: (state) => state.usuario?.debe_cambiar_password === true,
    nombreCompleto: (state) =>
      state.usuario ? `${state.usuario.nombre} ${state.usuario.apellido}` : '',
  },
  actions: {
    // Se usa una sola vez para el bootstrap del guard de rutas.
    async cargarSesion() {
      try {
        const data = await api.get('/auth/me')
        this.usuario = data.usuario
      } catch {
        // 401 (o cualquier fallo): no hay sesión activa. No es ruido de error.
        this.usuario = null
      } finally {
        this.inicializado = true
      }
    },
    async login(correo, password) {
      this.cargando = true
      this.error = null
      try {
        const data = await api.post('/auth/login', { correo, password })
        this.usuario = data.usuario
        return true
      } catch (e) {
        this.error = e.message
        return false
      } finally {
        this.cargando = false
      }
    },
    async logout() {
      try {
        await api.post('/auth/logout', {})
      } catch {
        // Ignoramos errores de logout; limpiamos la sesión local igualmente.
      }
      this.usuario = null
    },
  },
})
