<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const correo = ref('')
const password = ref('')

async function enviar() {
  const ok = await auth.login(correo.value, password.value)
  if (ok) {
    router.push(route.query.redirect || '/')
  }
}
</script>

<template>
  <section class="max-w-sm mx-auto">
    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
      <h2 class="text-xl font-semibold text-slate-900 mb-1">Iniciar sesión</h2>
      <p class="text-sm text-slate-500 mb-6">Seguimiento de Prácticas — Duoc UC</p>

      <form class="space-y-4" @submit.prevent="enviar">
        <div>
          <label for="correo" class="block text-sm font-medium text-slate-700 mb-1">
            Correo
          </label>
          <input
            id="correo"
            v-model="correo"
            type="email"
            autocomplete="email"
            required
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          />
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-slate-700 mb-1">
            Contraseña
          </label>
          <input
            id="password"
            v-model="password"
            type="password"
            autocomplete="current-password"
            required
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          />
        </div>

        <div
          v-if="auth.error"
          class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600"
        >
          {{ auth.error }}
        </div>

        <button
          type="submit"
          class="w-full inline-flex items-center justify-center rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
          :disabled="auth.cargando"
        >
          {{ auth.cargando ? 'Entrando…' : 'Entrar' }}
        </button>
      </form>

      <p class="mt-4 text-center text-sm text-slate-500">
        <router-link
          to="/recuperar-password"
          class="text-slate-500 underline underline-offset-2 hover:text-slate-700"
        >
          ¿Olvidaste tu contraseña?
        </router-link>
      </p>
    </div>
  </section>
</template>
