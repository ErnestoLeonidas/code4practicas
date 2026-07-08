<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

// Capturamos si el cambio es obligatorio al entrar: tras el éxito el flag se
// pone en false, así que necesitamos recordar el estado inicial para redirigir.
const veniaForzado = ref(auth.debeCambiarPassword)

const passwordActual = ref('')
const passwordNueva = ref('')
const passwordConfirmar = ref('')
const errorLocal = ref(null)
const exito = ref(false)

async function enviar() {
  errorLocal.value = null
  exito.value = false
  auth.error = null

  if (passwordNueva.value.length < 8) {
    errorLocal.value = 'La nueva contraseña debe tener al menos 8 caracteres.'
    return
  }
  if (passwordNueva.value !== passwordConfirmar.value) {
    errorLocal.value = 'La confirmación no coincide con la nueva contraseña.'
    return
  }

  const ok = await auth.cambiarPassword(passwordActual.value, passwordNueva.value)
  if (ok) {
    if (veniaForzado.value) {
      router.push('/')
    } else {
      exito.value = true
      passwordActual.value = ''
      passwordNueva.value = ''
      passwordConfirmar.value = ''
    }
  }
}
</script>

<template>
  <section class="max-w-sm mx-auto">
    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
      <h2 class="text-xl font-semibold text-slate-900 mb-1">Cambiar contraseña</h2>
      <p class="text-sm text-slate-500 mb-6">Seguimiento de Prácticas — Duoc UC</p>

      <div
        v-if="auth.debeCambiarPassword"
        class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700"
      >
        Debes cambiar tu contraseña antes de continuar.
      </div>

      <form class="space-y-4" @submit.prevent="enviar">
        <div>
          <label for="actual" class="block text-sm font-medium text-slate-700 mb-1">
            Contraseña actual
          </label>
          <input
            id="actual"
            v-model="passwordActual"
            type="password"
            autocomplete="current-password"
            required
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          />
        </div>

        <div>
          <label for="nueva" class="block text-sm font-medium text-slate-700 mb-1">
            Nueva contraseña
          </label>
          <input
            id="nueva"
            v-model="passwordNueva"
            type="password"
            autocomplete="new-password"
            required
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          />
          <p class="text-xs text-slate-500 mt-1">Mínimo 8 caracteres.</p>
        </div>

        <div>
          <label for="confirmar" class="block text-sm font-medium text-slate-700 mb-1">
            Confirmar nueva contraseña
          </label>
          <input
            id="confirmar"
            v-model="passwordConfirmar"
            type="password"
            autocomplete="new-password"
            required
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          />
        </div>

        <div
          v-if="errorLocal || auth.error"
          class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600"
        >
          {{ errorLocal || auth.error }}
        </div>

        <div
          v-if="exito"
          class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"
        >
          Contraseña actualizada correctamente.
        </div>

        <button
          type="submit"
          class="w-full inline-flex items-center justify-center rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
          :disabled="auth.cargando"
        >
          {{ auth.cargando ? 'Guardando…' : 'Guardar' }}
        </button>
      </form>
    </div>
  </section>
</template>
