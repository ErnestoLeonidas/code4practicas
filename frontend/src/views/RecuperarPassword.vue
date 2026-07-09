<script setup>
import { ref } from 'vue'
import { api } from '../services/api'

const correo = ref('')
const cargando = ref(false)
const exito = ref(false)
const error = ref(null)

async function enviar() {
  error.value = null
  cargando.value = true
  try {
    await api.post('/auth/recuperar', { correo: correo.value })
    exito.value = true
  } catch (e) {
    error.value = 'Ocurrió un error inesperado. Intenta de nuevo más tarde.'
  } finally {
    cargando.value = false
  }
}
</script>

<template>
  <section class="max-w-sm mx-auto">
    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
      <h2 class="text-xl font-semibold text-slate-900 mb-1">Recuperar contraseña</h2>
      <p class="text-sm text-slate-500 mb-6">Seguimiento de Prácticas — Duoc UC</p>

      <div
        v-if="exito"
        class="rounded-md border border-green-200 bg-green-50 px-3 py-3 text-sm text-green-700"
      >
        Si el correo existe en el sistema, recibirás un enlace para restablecer tu contraseña.
        Revisa tu bandeja de entrada y la carpeta de spam.
      </div>

      <form v-else class="space-y-4" @submit.prevent="enviar">
        <div>
          <label for="correo" class="block text-sm font-medium text-slate-700 mb-1">
            Correo institucional
          </label>
          <input
            id="correo"
            v-model="correo"
            type="email"
            autocomplete="email"
            placeholder="correo@profesor.duoc.cl"
            required
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          />
        </div>

        <div
          v-if="error"
          class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600"
        >
          {{ error }}
        </div>

        <button
          type="submit"
          class="w-full inline-flex items-center justify-center rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
          :disabled="cargando"
        >
          {{ cargando ? 'Enviando…' : 'Enviar instrucciones' }}
        </button>
      </form>

      <p class="mt-4 text-center text-sm text-slate-500">
        <router-link to="/login" class="text-slate-600 underline underline-offset-2 hover:text-slate-900">
          Volver al login
        </router-link>
      </p>
    </div>
  </section>
</template>
