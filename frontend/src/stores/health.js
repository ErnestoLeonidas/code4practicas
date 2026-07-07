import { defineStore } from 'pinia'
import { api } from '../services/api'

// Store de estado de la API. Sirve como prueba full-stack en v0.0.1.
export const useHealthStore = defineStore('health', {
  state: () => ({
    status: null, // 'ok' | null
    version: null,
    cargando: false,
    error: null,
  }),
  actions: {
    async comprobar() {
      this.cargando = true
      this.error = null
      try {
        const data = await api.get('/health')
        this.status = data.status
        this.version = data.version
      } catch (e) {
        this.error = e.message
        this.status = null
      } finally {
        this.cargando = false
      }
    },
  },
})
