import { defineStore } from 'pinia'
import { api } from '../services/api'

// Store de empresas y supervisores — v0.5.0.
export const useEmpresasStore = defineStore('empresas', {
  state: () => ({
    lista: [],
    total: 0,
    page: 1,
    perPage: 15,
    filtros: { q: '', ciudad: '' },
    cargando: false,
    error: null,
    empresaActual: null, // objeto empresa con .supervisores
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
        if (this.filtros.ciudad) params.set('ciudad', this.filtros.ciudad)

        const data = await api.get(`/empresas?${params.toString()}`)
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
    // Carga una empresa con sus supervisores.
    async cargarUna(id) {
      try {
        const data = await api.get(`/empresas/${id}`)
        this.empresaActual = data.empresa
      } catch (e) {
        this.empresaActual = null
        throw e
      }
    },
    // Crea una empresa. Rethrow si hay error; recarga tras éxito.
    async crear(datos) {
      const data = await api.post('/empresas', datos)
      await this.cargar()
      return data
    },
    // Actualiza una empresa. Rethrow si hay error; recarga tras éxito.
    async actualizar(id, datos) {
      const data = await api.put(`/empresas/${id}`, datos)
      await this.cargar()
      return data
    },
    // Desactiva (delete lógico) una empresa. Rethrow si hay error; recarga tras éxito.
    async desactivar(id) {
      const data = await api.delete(`/empresas/${id}`)
      await this.cargar()
      return data
    },
    // Crea un supervisor para una empresa. Rethrow si hay error; recarga empresa actual.
    async crearSupervisor(empresaId, datos) {
      const data = await api.post(`/empresas/${empresaId}/supervisores`, datos)
      await this.cargarUna(empresaId)
      return data
    },
    // Actualiza un supervisor. Rethrow si hay error.
    async actualizarSupervisor(id, datos) {
      const data = await api.put(`/supervisores/${id}`, datos)
      return data
    },
    // Elimina un supervisor. Rethrow si hay error; recarga empresa actual.
    async desactivarSupervisor(empresaSupervisorId, supervisorId) {
      const data = await api.delete(`/supervisores/${supervisorId}`)
      await this.cargarUna(empresaSupervisorId)
      return data
    },
  },
})
