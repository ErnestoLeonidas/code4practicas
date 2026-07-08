<script setup>
import { ref } from 'vue'

// Modal de crear/editar usuario. La lógica de API vive en la vista padre:
// aquí solo se recogen los datos del formulario y se emiten.
const props = defineProps({
  modo: { type: String, default: 'crear' }, // 'crear' | 'editar'
  usuario: { type: Object, default: null },
  guardando: { type: Boolean, default: false },
  error: { type: String, default: null },
})
const emit = defineEmits(['guardar', 'cerrar'])

const nombre = ref(props.usuario?.nombre || '')
const apellido = ref(props.usuario?.apellido || '')
const correo = ref(props.usuario?.correo || '')
const rol = ref(props.usuario?.rol || 'docente')

function enviar() {
  emit('guardar', {
    nombre: nombre.value.trim(),
    apellido: apellido.value.trim(),
    correo: correo.value.trim(),
    rol: rol.value,
  })
}
</script>

<template>
  <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 px-4">
    <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-lg">
      <h3 class="text-lg font-semibold text-slate-900 mb-4">
        {{ modo === 'crear' ? 'Nuevo usuario' : 'Editar usuario' }}
      </h3>

      <form class="space-y-4" @submit.prevent="enviar">
        <div>
          <label for="m-nombre" class="block text-sm font-medium text-slate-700 mb-1">
            Nombre
          </label>
          <input
            id="m-nombre"
            v-model="nombre"
            type="text"
            required
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          />
        </div>

        <div>
          <label for="m-apellido" class="block text-sm font-medium text-slate-700 mb-1">
            Apellido
          </label>
          <input
            id="m-apellido"
            v-model="apellido"
            type="text"
            required
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          />
        </div>

        <div>
          <label for="m-correo" class="block text-sm font-medium text-slate-700 mb-1">
            Correo institucional
          </label>
          <input
            id="m-correo"
            v-model="correo"
            type="email"
            required
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          />
        </div>

        <div>
          <label for="m-rol" class="block text-sm font-medium text-slate-700 mb-1">Rol</label>
          <select
            id="m-rol"
            v-model="rol"
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          >
            <option value="docente">Docente</option>
            <option value="admin">Administrador</option>
          </select>
        </div>

        <p v-if="modo === 'crear'" class="text-xs text-slate-500">
          La contraseña se genera automáticamente y se mostrará una vez al crear el usuario.
        </p>

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
            class="inline-flex items-center rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
            :disabled="guardando"
          >
            {{ guardando ? 'Guardando…' : 'Guardar' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
