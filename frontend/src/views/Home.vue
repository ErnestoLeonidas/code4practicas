<script setup>
import { onMounted, computed } from 'vue'
import { useHealthStore } from '../stores/health'
import { useAuthStore } from '../stores/auth'

const health = useHealthStore()
const auth = useAuthStore()

const estadoOk = computed(() => health.status === 'ok')

onMounted(() => {
  health.comprobar()
})
</script>

<template>
  <div>
    <div class="mb-8">
      <h2 class="text-xl font-semibold text-slate-900">Hola, {{ auth.nombreCompleto }}</h2>
      <p v-if="auth.debeCambiarPassword" class="text-sm text-amber-600 mt-1">
        Debes cambiar tu contraseña.
      </p>
    </div>

    <section class="max-w-md">
      <h2 class="text-xl font-semibold text-slate-900 mb-1">Estado del sistema</h2>
      <p class="text-sm text-slate-500 mb-6">
        Prueba full-stack: esta vista consume <code class="text-slate-700">/api/health</code>.
      </p>

      <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div v-if="health.cargando" class="flex items-center gap-2 text-slate-500">
          <span class="h-3 w-3 rounded-full bg-slate-300 animate-pulse"></span>
          Comprobando API…
        </div>

        <div v-else-if="health.error" class="text-red-600">
          <p class="font-medium">No se pudo conectar con la API</p>
          <p class="text-sm mt-1">{{ health.error }}</p>
        </div>

        <div v-else class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span
              class="h-3 w-3 rounded-full"
              :class="estadoOk ? 'bg-green-500' : 'bg-slate-300'"
            ></span>
            <span class="font-medium">
              API {{ estadoOk ? 'operativa' : 'sin respuesta' }}
            </span>
          </div>
          <span
            v-if="health.version"
            class="text-xs font-mono text-slate-500 bg-slate-100 px-2 py-1 rounded"
          >
            v{{ health.version }}
          </span>
        </div>
      </div>

      <button
        class="mt-4 inline-flex items-center rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
        :disabled="health.cargando"
        @click="health.comprobar()"
      >
        Volver a comprobar
      </button>
    </section>
  </div>
</template>
