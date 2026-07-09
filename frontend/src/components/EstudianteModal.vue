<script setup>
import { ref, computed } from 'vue'

// Modal de crear/editar estudiante — v0.4.0.
// La lógica de API vive en la vista padre; aquí solo se recogen y validan los datos.
const props = defineProps({
  estudiante: { type: Object, default: null },     // null → crear
  carreras: { type: Array, default: () => [] },
  docentes: { type: Array, default: () => [] },
  guardando: { type: Boolean, default: false },
  error: { type: String, default: null },
})
const emit = defineEmits(['guardar', 'cerrar'])

const esEdicion = computed(() => props.estudiante !== null)

// Campos del formulario.
const nombre = ref(props.estudiante?.nombre || '')
const apellido = ref(props.estudiante?.apellido || '')
const rut = ref(props.estudiante?.rut || '')
const correo_duoc = ref(props.estudiante?.correo_duoc || '')
const telefono = ref(props.estudiante?.telefono || '')
const carrera_id = ref(props.estudiante?.carrera_id || '')
const semestre_ingreso_practica = ref(props.estudiante?.semestre_ingreso_practica || '')
const docente_id = ref(props.estudiante?.docente_id || '')

// Errores de validación cliente.
const errores = ref({})

const SEMESTRE_RE = /^\d{4}-[12]$/

function validar() {
  const e = {}
  if (!nombre.value.trim()) e.nombre = 'El nombre es requerido.'
  if (!apellido.value.trim()) e.apellido = 'El apellido es requerido.'
  if (!esEdicion.value && !rut.value.trim()) e.rut = 'El RUT es requerido.'
  if (
    semestre_ingreso_practica.value.trim() &&
    !SEMESTRE_RE.test(semestre_ingreso_practica.value.trim())
  ) {
    e.semestre_ingreso_practica = 'Formato inválido. Usa AAAA-1 o AAAA-2 (ej. 2026-1).'
  }
  errores.value = e
  return Object.keys(e).length === 0
}

function enviar() {
  if (!validar()) return
  const datos = {
    nombre: nombre.value.trim(),
    apellido: apellido.value.trim(),
    correo_duoc: correo_duoc.value.trim() || null,
    telefono: telefono.value.trim() || null,
    carrera_id: carrera_id.value || null,
    semestre_ingreso_practica: semestre_ingreso_practica.value.trim() || null,
    docente_id: docente_id.value || null,
  }
  // RUT solo en creación (no editable).
  if (!esEdicion.value) datos.rut = rut.value.trim()
  emit('guardar', datos)
}
</script>

<template>
  <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 px-4">
    <div class="w-full max-w-lg rounded-lg border border-slate-200 bg-white p-6 shadow-lg">
      <h3 class="text-lg font-semibold text-slate-900 mb-4">
        {{ esEdicion ? 'Editar estudiante' : 'Nuevo estudiante' }}
      </h3>

      <form class="space-y-4" @submit.prevent="enviar">
        <!-- Nombre y Apellido en fila -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="m-nombre" class="block text-sm font-medium text-slate-700 mb-1">
              Nombre <span class="text-red-500">*</span>
            </label>
            <input
              id="m-nombre"
              v-model="nombre"
              type="text"
              class="w-full rounded-md border px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1"
              :class="errores.nombre ? 'border-red-400 focus:ring-red-400' : 'border-slate-300 focus:border-slate-500 focus:ring-slate-500'"
            />
            <p v-if="errores.nombre" class="mt-1 text-xs text-red-500">{{ errores.nombre }}</p>
          </div>

          <div>
            <label for="m-apellido" class="block text-sm font-medium text-slate-700 mb-1">
              Apellido <span class="text-red-500">*</span>
            </label>
            <input
              id="m-apellido"
              v-model="apellido"
              type="text"
              class="w-full rounded-md border px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1"
              :class="errores.apellido ? 'border-red-400 focus:ring-red-400' : 'border-slate-300 focus:border-slate-500 focus:ring-slate-500'"
            />
            <p v-if="errores.apellido" class="mt-1 text-xs text-red-500">{{ errores.apellido }}</p>
          </div>
        </div>

        <!-- RUT -->
        <div>
          <label for="m-rut" class="block text-sm font-medium text-slate-700 mb-1">
            RUT <span class="text-red-500">*</span>
          </label>
          <!-- En edición: solo lectura -->
          <input
            v-if="esEdicion"
            id="m-rut"
            :value="estudiante.rut"
            type="text"
            readonly
            class="w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500 cursor-not-allowed"
          />
          <input
            v-else
            id="m-rut"
            v-model="rut"
            type="text"
            placeholder="12.345.678-9"
            class="w-full rounded-md border px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1"
            :class="errores.rut ? 'border-red-400 focus:ring-red-400' : 'border-slate-300 focus:border-slate-500 focus:ring-slate-500'"
          />
          <p v-if="errores.rut" class="mt-1 text-xs text-red-500">{{ errores.rut }}</p>
        </div>

        <!-- Correo y Teléfono en fila -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="m-correo" class="block text-sm font-medium text-slate-700 mb-1">
              Correo Duoc
            </label>
            <input
              id="m-correo"
              v-model="correo_duoc"
              type="email"
              placeholder="alumno@duocuc.cl"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
            />
          </div>

          <div>
            <label for="m-telefono" class="block text-sm font-medium text-slate-700 mb-1">
              Teléfono
            </label>
            <input
              id="m-telefono"
              v-model="telefono"
              type="text"
              placeholder="+56 9 1234 5678"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
            />
          </div>
        </div>

        <!-- Carrera -->
        <div>
          <label for="m-carrera" class="block text-sm font-medium text-slate-700 mb-1">
            Carrera
          </label>
          <select
            id="m-carrera"
            v-model="carrera_id"
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          >
            <option value="">Sin carrera</option>
            <option v-for="c in carreras" :key="c.id" :value="c.id">{{ c.nombre }}</option>
          </select>
        </div>

        <!-- Semestre -->
        <div>
          <label for="m-semestre" class="block text-sm font-medium text-slate-700 mb-1">
            Semestre de ingreso a práctica
          </label>
          <input
            id="m-semestre"
            v-model="semestre_ingreso_practica"
            type="text"
            placeholder="2026-1"
            class="w-full rounded-md border px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1"
            :class="errores.semestre_ingreso_practica ? 'border-red-400 focus:ring-red-400' : 'border-slate-300 focus:border-slate-500 focus:ring-slate-500'"
          />
          <p class="mt-1 text-xs text-slate-500">Formato: AAAA-1 o AAAA-2</p>
          <p v-if="errores.semestre_ingreso_practica" class="mt-0.5 text-xs text-red-500">
            {{ errores.semestre_ingreso_practica }}
          </p>
        </div>

        <!-- Docente asignado -->
        <div>
          <label for="m-docente" class="block text-sm font-medium text-slate-700 mb-1">
            Docente asignado
          </label>
          <select
            id="m-docente"
            v-model="docente_id"
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          >
            <option value="">Sin asignar</option>
            <option v-for="d in docentes" :key="d.id" :value="d.id">
              {{ d.nombre }} {{ d.apellido }}
            </option>
          </select>
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
