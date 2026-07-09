<script setup>
import { ref } from 'vue'

// Modal crear/editar empresa — v0.5.0.
// La lógica de API vive en la vista padre; aquí solo se recogen los datos del formulario y se emiten.
const props = defineProps({
  empresa: { type: Object, default: null }, // null → crear
  guardando: { type: Boolean, default: false },
  error: { type: String, default: null },
})
const emit = defineEmits(['guardar', 'cerrar'])

const esEdicion = props.empresa !== null

const nombre = ref(props.empresa?.nombre || '')
const rut_empresa = ref(props.empresa?.rut_empresa || '')
const giro = ref(props.empresa?.giro || '')
const direccion = ref(props.empresa?.direccion || '')
const ciudad = ref(props.empresa?.ciudad || '')
const telefono = ref(props.empresa?.telefono || '')
const sitio_web = ref(props.empresa?.sitio_web || '')

function enviar() {
  emit('guardar', {
    nombre: nombre.value.trim(),
    rut_empresa: rut_empresa.value.trim() || null,
    giro: giro.value.trim() || null,
    direccion: direccion.value.trim() || null,
    ciudad: ciudad.value.trim() || null,
    telefono: telefono.value.trim() || null,
    sitio_web: sitio_web.value.trim() || null,
  })
}
</script>

<template>
  <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 px-4">
    <div class="w-full max-w-lg rounded-lg border border-slate-200 bg-white p-6 shadow-lg">
      <h3 class="text-lg font-semibold text-slate-900 mb-4">
        {{ esEdicion ? 'Editar empresa' : 'Nueva empresa' }}
      </h3>

      <form class="space-y-4" @submit.prevent="enviar">
        <!-- Nombre -->
        <div>
          <label for="em-nombre" class="block text-sm font-medium text-slate-700 mb-1">
            Nombre <span class="text-red-500">*</span>
          </label>
          <input
            id="em-nombre"
            v-model="nombre"
            type="text"
            required
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          />
        </div>

        <!-- RUT empresa y Ciudad en fila -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="em-rut" class="block text-sm font-medium text-slate-700 mb-1">
              RUT empresa
            </label>
            <input
              id="em-rut"
              v-model="rut_empresa"
              type="text"
              placeholder="76.123.456-7"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
            />
          </div>

          <div>
            <label for="em-ciudad" class="block text-sm font-medium text-slate-700 mb-1">
              Ciudad
            </label>
            <input
              id="em-ciudad"
              v-model="ciudad"
              type="text"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
            />
          </div>
        </div>

        <!-- Giro -->
        <div>
          <label for="em-giro" class="block text-sm font-medium text-slate-700 mb-1">
            Giro
          </label>
          <input
            id="em-giro"
            v-model="giro"
            type="text"
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          />
        </div>

        <!-- Dirección -->
        <div>
          <label for="em-direccion" class="block text-sm font-medium text-slate-700 mb-1">
            Dirección
          </label>
          <input
            id="em-direccion"
            v-model="direccion"
            type="text"
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          />
        </div>

        <!-- Teléfono y Sitio web en fila -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="em-telefono" class="block text-sm font-medium text-slate-700 mb-1">
              Teléfono
            </label>
            <input
              id="em-telefono"
              v-model="telefono"
              type="text"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
            />
          </div>

          <div>
            <label for="em-web" class="block text-sm font-medium text-slate-700 mb-1">
              Sitio web
            </label>
            <input
              id="em-web"
              v-model="sitio_web"
              type="url"
              placeholder="https://..."
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
            />
          </div>
        </div>

        <!-- Error de API -->
        <div
          v-if="error"
          class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600"
        >
          {{ error }}
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            type="button"
            class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
            :disabled="guardando"
            @click="emit('cerrar')"
          >
            Cancelar
          </button>
          <button
            type="submit"
            class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
            :disabled="guardando"
          >
            {{ guardando ? 'Guardando…' : 'Guardar' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
