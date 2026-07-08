<script setup>
// Layout base con navbar de sesión (v0.1.0).
import { useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function salir() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-800">
    <header class="bg-white border-b border-slate-200">
      <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between gap-4">
        <div>
          <h1 class="text-lg font-semibold text-slate-900">
            Seguimiento de Prácticas Profesionales
          </h1>
          <p class="text-sm text-slate-500">Duoc UC</p>
        </div>

        <div v-if="auth.autenticado" class="flex items-center gap-3">
          <span class="text-sm font-medium text-slate-900">{{ auth.nombreCompleto }}</span>
          <span class="text-xs font-medium text-slate-600 bg-slate-100 px-2 py-0.5 rounded">
            {{ auth.usuario.rol }}
          </span>
          <button
            class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
            @click="salir"
          >
            Salir
          </button>
        </div>
      </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-8">
      <RouterView />
    </main>
  </div>
</template>
