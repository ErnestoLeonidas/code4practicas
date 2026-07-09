<script setup>
import { ref } from 'vue'

// Modal crear/editar supervisor — v0.5.0.
// La lógica de API vive en la vista padre; aquí solo se recogen los datos del formulario y se emiten.
const props = defineProps({
  supervisor: { type: Object, default: null }, // null → crear
  empresaId: { type: [Number, String], required: true },
  guardando: { type: Boolean, default: false },
  error: { type: String, default: null },
})
const emit = defineEmits(['guardar', 'cerrar'])

const nombre = ref(props.supervisor?.nombre || '')
const apellido = ref(props.supervisor?.apellido || '')
const profesion = ref(props.supervisor?.profesion || '')
const cargo = ref(props.supervisor?.cargo || '')
const telefono = ref(props.supervisor?.telefono || '')
const correo = ref(props.supervisor?.correo || '')

function enviar() {
  emit('guardar', {
    nombre: nombre.value.trim(),
    apellido: apellido.value.trim(),
    profesion: profesion.value.trim() || null,
    cargo: cargo.value.trim() || null,
    telefono: telefono.value.trim() || null,
    correo: correo.value.trim() || null,
  })
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
    <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-lg">
      <h3 class="text-lg font-semibold text-slate-900 mb-4">
        {{ supervisor ? 'Editar supervisor' : 'Agregar supervisor' }}
      </h3>

      <form class="space-y-4" @submit.prevent="enviar">
        <!-- Nombre y Apellido en fila -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="sv-nombre" class="block text-sm font-medium text-slate-700 mb-1">
              Nombre <span class="text-red-500">*</span>
            </label>
            <input
              id="sv-nombre"
              v-model="nombre"
              type="text"
              required
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
            />
          </div>

          <div>
            <label for="sv-apellido" class="block text-sm font-medium text-slate-700 mb-1">
              Apellido <span class="text-red-500">*</span>
            </label>
            <input
              id="sv-apellido"
              v-model="apellido"
              type="text"
              required
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
            />
          </div>
        </div>

        <!-- Profesión y Cargo en fila -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="sv-profesion" class="block text-sm font-medium text-slate-700 mb-1">
              Profesión
            </label>
            <input
              id="sv-profesion"
              v-model="profesion"
              type="text"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
            />
          </div>

          <div>
            <label for="sv-cargo" class="block text-sm font-medium text-slate-700 mb-1">
              Cargo
            </label>
            <input
              id="sv-cargo"
              v-model="cargo"
              type="text"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
            />
          </div>
        </div>

        <!-- Teléfono -->
        <div>
          <label for="sv-telefono" class="block text-sm font-medium text-slate-700 mb-1">
            Teléfono
          </label>
          <input
            id="sv-telefono"
            v-model="telefono"
            type="text"
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          />
        </div>

        <!-- Correo -->
        <div>
          <label for="sv-correo" class="block text-sm font-medium text-slate-700 mb-1">
            Correo
          </label>
          <input
            id="sv-correo"
            v-model="correo"
            type="email"
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          />
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
