import { defineStore } from 'pinia'
import { api } from '../services/api'

// Store de gestión de usuarios (solo admin) — v0.2.0.
export const useUsuariosStore = defineStore('usuarios', {
  state: () => ({
    lista: [],
    total: 0,
    page: 1,
    perPage: 20,
    filtros: { q: '', rol: '', activo: '' },
    cargando: false,
    error: null,
  }),
  actions: {
    // Lista paginada con los filtros actuales.
    async cargar() {
      this.cargando = true
      this.error = null
      try {
        const params = new URLSearchParams()
        params.set('page', String(this.page))
        params.set('per_page', String(this.perPage))
        if (this.filtros.q) params.set('q', this.filtros.q)
        if (this.filtros.rol) params.set('rol', this.filtros.rol)
        if (this.filtros.activo !== '') params.set('activo', this.filtros.activo)

        const data = await api.get(`/usuarios?${params.toString()}`)
        this.lista = data.data
        this.total = data.total
        this.page = data.page
        this.perPage = data.per_page
      } catch (e) {
        this.error = e.message
        this.lista = []
        this.total = 0
      } finally {
        this.cargando = false
      }
    },
    // Crea un usuario. Devuelve la respuesta (incluye password_generada).
    // No captura el error: lo relanza para que la vista lo muestre.
    async crear(datos) {
      const data = await api.post('/usuarios', datos)
      await this.cargar()
      return data
    },
    async actualizar(id, datos) {
      const data = await api.put(`/usuarios/${id}`, datos)
      await this.cargar()
      return data
    },
    async desactivar(id) {
      const data = await api.delete(`/usuarios/${id}`)
      await this.cargar()
      return data
    },
    // Regenera la contraseña. Devuelve la respuesta (incluye password_generada).
    async regenerar(id) {
      const data = await api.post(`/usuarios/${id}/regenerar-password`, {})
      await this.cargar()
      return data
    },
  },
})
