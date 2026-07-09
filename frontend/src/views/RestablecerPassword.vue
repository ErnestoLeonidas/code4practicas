<script setup>
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../services/api'

const route = useRoute()

const token = computed(() => route.query.token || '')
const tokenAusente = computed(() => !token.value)

const passwordNueva = ref('')
const confirmarPassword = ref('')
const cargando = ref(false)
const exito = ref(false)
const error = ref(null)
const errorCodigo = ref(null)

async function enviar() {
  error.value = null
  errorCodigo.value = null

  if (passwordNueva.value.length < 8) {
    error.value = 'La contraseña debe tener al menos 8 caracteres.'
    return
  }
  if (passwordNueva.value !== confirmarPassword.value) {
    error.value = 'Las contraseñas no coinciden.'
    return
  }

  cargando.value = true
  try {
    await api.post('/auth/restablecer', { token: token.value, password_nueva: passwordNueva.value })
    exito.value = true
  } catch (e) {
    if (e.code === 'token_invalido') {
      errorCodigo.value = 'token_invalido'
    } else {
      error.value = e.message || 'Ocurrió un error inesperado. Intenta de nuevo más tarde.'
    }
  } finally {
    cargando.value = false
  }
}
</script>

<template>
  <section class="max-w-sm mx-auto">
    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
      <h2 class="text-xl font-semibold text-slate-900 mb-1">Restablecer contraseña</h2>
      <p class="text-sm text-slate-500 mb-6">Seguimiento de Prácticas — Duoc UC</p>

      <!-- Sin token en la URL -->
      <div v-if="tokenAusente" class="rounded-md border border-red-200 bg-red-50 px-3 py-3 text-sm text-red-600">
        Enlace inválido. Solicita un nuevo enlace de recuperación.
        <br />
        <router-link to="/login" class="mt-2 inline-block underline underline-offset-2 hover:text-red-800">
          Volver al login
        </router-link>
      </div>

      <!-- Éxito -->
      <div v-else-if="exito" class="space-y-4">
        <div class="rounded-md border border-green-200 bg-green-50 px-3 py-3 text-sm text-green-700">
          Contraseña actualizada correctamente.
        </div>
        <router-link
          to="/login"
          class="w-full inline-flex items-center justify-center rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700"
        >
          Ir al login
        </router-link>
      </div>

      <!-- Error token inválido -->
      <div v-else-if="errorCodigo === 'token_invalido'" class="rounded-md border border-red-200 bg-red-50 px-3 py-3 text-sm text-red-600">
        El enlace ha expirado o ya fue utilizado. Solicita uno nuevo.
        <br />
        <router-link to="/recuperar-password" class="mt-2 inline-block underline underline-offset-2 hover:text-red-800">
          Solicitar nuevo enlace
        </router-link>
      </div>

      <!-- Formulario -->
      <form v-else class="space-y-4" @submit.prevent="enviar">
        <div>
          <label for="password-nueva" class="block text-sm font-medium text-slate-700 mb-1">
            Nueva contraseña
          </label>
          <input
            id="password-nueva"
            v-model="passwordNueva"
            type="password"
            autocomplete="new-password"
            required
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          />
          <p class="text-xs text-slate-500 mt-1">Mínimo 8 caracteres.</p>
        </div>

        <div>
          <label for="confirmar-password" class="block text-sm font-medium text-slate-700 mb-1">
            Confirmar contraseña
          </label>
          <input
            id="confirmar-password"
            v-model="confirmarPassword"
            type="password"
            autocomplete="new-password"
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
          {{ cargando ? 'Guardando…' : 'Restablecer contraseña' }}
        </button>
      </form>

      <p v-if="!tokenAusente && !exito && errorCodigo !== 'token_invalido'" class="mt-4 text-center text-sm text-slate-500">
        <router-link to="/login" class="text-slate-600 underline underline-offset-2 hover:text-slate-900">
          Volver al login
        </router-link>
      </p>
    </div>
  </section>
</template>
