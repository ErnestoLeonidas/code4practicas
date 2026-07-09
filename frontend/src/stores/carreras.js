import { defineStore } from 'pinia'
import { api } from '../services/api'

// Store de carreras — v0.4.0.
export const useCarrerasStore = defineStore('carreras', {
  state: () => ({
    lista: [],       // array de carreras activas
    cargando: false,
    error: null,
  }),
  actions: {
    async cargar() {
      this.cargando = true
      this.error = null
      try {
        const data = await api.get('/carreras')
        this.lista = data.data
      } catch (e) {
        this.error = e.message
        this.lista = []
      } finally {
        this.cargando = false
      }
    },
    async crear(datos) {
      const data = await api.post('/carreras', datos)
      await this.cargar()
      return data
    },
    async actualizar(id, datos) {
      const data = await api.put(`/carreras/${id}`, datos)
      await this.cargar()
      return data
    },
    async desactivar(id) {
      const data = await api.delete(`/carreras/${id}`)
      await this.cargar()
      return data
    },
  },
})
