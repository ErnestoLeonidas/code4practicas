import { defineStore } from 'pinia'
import { api } from '../services/api'

// Store de estudiantes — v0.4.0. Mismo patrón que usuarios.js.
export const useEstudiantesStore = defineStore('estudiantes', {
  state: () => ({
    lista: [],
    total: 0,
    page: 1,
    perPage: 15,
    filtros: { q: '', semestre: '', carrera_id: '', docente_id: '' },
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
        if (this.filtros.semestre) params.set('semestre', this.filtros.semestre)
        if (this.filtros.carrera_id) params.set('carrera_id', this.filtros.carrera_id)
        if (this.filtros.docente_id) params.set('docente_id', this.filtros.docente_id)

        const data = await api.get(`/estudiantes?${params.toString()}`)
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
    async crear(datos) {
      const data = await api.post('/estudiantes', datos)
      await this.cargar()
      return data
    },
    async actualizar(id, datos) {
      const data = await api.put(`/estudiantes/${id}`, datos)
      await this.cargar()
      return data
    },
    async desactivar(id) {
      const data = await api.delete(`/estudiantes/${id}`)
      await this.cargar()
      return data
    },
  },
})
