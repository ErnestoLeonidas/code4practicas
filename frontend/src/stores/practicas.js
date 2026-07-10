import { defineStore } from 'pinia'
import { api } from '../services/api'

export const usePracticasStore = defineStore('practicas', {
  state: () => ({
    lista: [],
    total: 0,
    page: 1,
    perPage: 15,
    filtros: { q: '', estado: '', semestre: '' },
    cargando: false,
    error: null,
    practicaActual: null,
    cargandoDetalle: false,
    errorDetalle: null,
  }),
  actions: {
    async cargar() {
      this.cargando = true
      this.error = null
      try {
        const params = new URLSearchParams()
        params.set('page', String(this.page))
        params.set('per_page', String(this.perPage))
        if (this.filtros.q) params.set('q', this.filtros.q)
        if (this.filtros.estado) params.set('estado', this.filtros.estado)
        if (this.filtros.semestre) params.set('semestre', this.filtros.semestre)

        const data = await api.get(`/practicas?${params.toString()}`)
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
    async cargarUna(id) {
      this.cargandoDetalle = true
      this.errorDetalle = null
      try {
        const data = await api.get(`/practicas/${id}`)
        this.practicaActual = data.practica
      } catch (e) {
        this.practicaActual = null
        this.errorDetalle = e.message
        throw e
      } finally {
        this.cargandoDetalle = false
      }
    },
    async crear(datos) {
      const data = await api.post('/practicas', datos)
      await this.cargar()
      return data
    },
    async actualizar(id, datos) {
      const data = await api.put(`/practicas/${id}`, datos)
      await this.cargar()
      return data
    },
    async cambiarEstado(id, estado) {
      const data = await api.patch(`/practicas/${id}/estado`, { estado })
      await this.cargarUna(id)
      await this.cargar()
      return data
    },
    async guardarSeguimiento(id, semana, datos) {
      const data = await api.put(`/practicas/${id}/seguimiento/${semana}`, datos)
      if (this.practicaActual?.id === id) {
        const seguimiento = Array.isArray(this.practicaActual?.seguimiento) ? [...this.practicaActual.seguimiento] : []
        const index = seguimiento.findIndex((item) => Number(item.semana) === Number(semana))
        if (index >= 0) {
          seguimiento[index] = { ...seguimiento[index], ...data.semana }
        }
        this.practicaActual = { ...this.practicaActual, seguimiento, resumen: data.resumen }
      }
      return data
    },
    async guardarEntrega(id, tipo, datos) {
      const data = await api.put(`/practicas/${id}/entregas/${tipo}`, datos)
      if (this.practicaActual?.id === id) {
        const entregas = Array.isArray(this.practicaActual?.entregas) ? [...this.practicaActual.entregas] : []
        const index = entregas.findIndex((item) => item.tipo === tipo)
        if (index >= 0) {
          entregas[index] = { ...entregas[index], ...data.entrega }
        }
        this.practicaActual = {
          ...this.practicaActual,
          ...data.practica,
          entregas,
          resumen_entregas: data.resumen_entregas,
        }
      }
      await this.cargar()
      return data
    },
  },
})
