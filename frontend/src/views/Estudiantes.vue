<script setup>
import { ref, computed, onMounted } from 'vue'
import { useEstudiantesStore } from '../stores/estudiantes'
import { useCarrerasStore } from '../stores/carreras'
import { useAuthStore } from '../stores/auth'
import { api } from '../services/api'
import EstudianteModal from '../components/EstudianteModal.vue'

const estudiantes = useEstudiantesStore()
const carreras = useCarrerasStore()
const auth = useAuthStore()

// Buscador local: no dispara petición en cada tecla, sólo al Enter/botón.
const busqueda = ref(estudiantes.filtros.q)

// Docentes para el modal (se cargan una vez).
const docentes = ref([])

// Modal crear/editar.
const modalAbierto = ref(false)
const estudianteEditando = ref(null)
const guardando = ref(false)
const errorModal = ref(null)

// Errores de acciones de fila.
const errorAccion = ref(null)

const totalPaginas = computed(() =>
  Math.max(1, Math.ceil(estudiantes.total / estudiantes.perPage)),
)

// Semestres únicos presentes en la lista actual.
const semestresDisponibles = computed(() => {
  const set = new Set()
  for (const e of estudiantes.lista) {
    if (e.semestre_ingreso_practica) set.add(e.semestre_ingreso_practica)
  }
  return [...set].sort().reverse()
})

const hayFiltrosActivos = computed(() =>
  busqueda.value.trim() !== '' ||
  estudiantes.filtros.semestre !== '' ||
  estudiantes.filtros.carrera_id !== '',
)

onMounted(async () => {
  await Promise.all([estudiantes.cargar(), carreras.cargar()])
  await cargarDocentes()
})

async function cargarDocentes() {
  try {
    const data = await api.get('/usuarios?rol=docente&activo=1&per_page=100')
    docentes.value = data.data || []
  } catch {
    docentes.value = []
  }
}

function buscar() {
  estudiantes.filtros.q = busqueda.value.trim()
  estudiantes.page = 1
  estudiantes.cargar()
}

function aplicarFiltros() {
  estudiantes.page = 1
  estudiantes.cargar()
}

function limpiarFiltros() {
  busqueda.value = ''
  estudiantes.filtros.q = ''
  estudiantes.filtros.semestre = ''
  estudiantes.filtros.carrera_id = ''
  estudiantes.page = 1
  estudiantes.cargar()
}

function paginaAnterior() {
  if (estudiantes.page > 1) {
    estudiantes.page--
    estudiantes.cargar()
  }
}

function paginaSiguiente() {
  if (estudiantes.page < totalPaginas.value) {
    estudiantes.page++
    estudiantes.cargar()
  }
}

// --- Modal ---
function abrirCrear() {
  estudianteEditando.value = null
  errorModal.value = null
  modalAbierto.value = true
}

function abrirEditar(e) {
  estudianteEditando.value = e
  errorModal.value = null
  modalAbierto.value = true
}

function cerrarModal() {
  modalAbierto.value = false
  estudianteEditando.value = null
  errorModal.value = null
}

async function guardarEstudiante(datos) {
  guardando.value = true
  errorModal.value = null
  try {
    if (estudianteEditando.value === null) {
      await estudiantes.crear(datos)
    } else {
      await estudiantes.actualizar(estudianteEditando.value.id, datos)
    }
    cerrarModal()
  } catch (e) {
    errorModal.value = e.message
  } finally {
    guardando.value = false
  }
}

// --- Acciones de fila ---
async function desactivar(e) {
  errorAccion.value = null
  if (!confirm(`¿Desactivar a ${e.nombre} ${e.apellido}?`)) return
  try {
    await estudiantes.desactivar(e.id)
  } catch (err) {
    errorAccion.value = err.message
  }
}

async function activar(e) {
  errorAccion.value = null
  try {
    await estudiantes.actualizar(e.id, {
      nombre: e.nombre,
      apellido: e.apellido,
      rut: e.rut,
      correo_duoc: e.correo_duoc,
      telefono: e.telefono,
      carrera_id: e.carrera_id,
      semestre_ingreso_practica: e.semestre_ingreso_practica,
      docente_id: e.docente_id,
      activo: true,
    })
  } catch (err) {
    errorAccion.value = err.message
  }
}
</script>

<template>
  <div>
    <!-- Encabezado -->
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-semibold text-slate-900">Estudiantes</h2>
        <p class="text-sm text-slate-500">Gestión de estudiantes en práctica.</p>
      </div>
      <button
        v-if="auth.esAdmin"
        class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        @click="abrirCrear"
      >
        Nuevo estudiante
      </button>
    </div>

    <!-- Barra de filtros -->
    <div class="mb-4 flex flex-wrap items-end gap-3">
      <!-- Búsqueda -->
      <div class="flex items-end gap-2">
        <div>
          <label for="q" class="block text-xs font-medium text-slate-600 mb-1">Buscar</label>
          <input
            id="q"
            v-model="busqueda"
            type="text"
            placeholder="Buscar por nombre o RUT…"
            class="w-64 rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
            @keyup.enter="buscar"
          />
        </div>
        <button
          class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
          @click="buscar"
        >
          Buscar
        </button>
      </div>

      <!-- Semestre -->
      <div>
        <label for="semestre" class="block text-xs font-medium text-slate-600 mb-1">Semestre</label>
        <select
          id="semestre"
          v-model="estudiantes.filtros.semestre"
          class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          @change="aplicarFiltros"
        >
          <option value="">Todos los semestres</option>
          <option v-for="s in semestresDisponibles" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>

      <!-- Carrera -->
      <div>
        <label for="carrera" class="block text-xs font-medium text-slate-600 mb-1">Carrera</label>
        <select
          id="carrera"
          v-model="estudiantes.filtros.carrera_id"
          class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          @change="aplicarFiltros"
        >
          <option value="">Todas las carreras</option>
          <option v-for="c in carreras.lista" :key="c.id" :value="c.id">{{ c.nombre }}</option>
        </select>
      </div>

      <!-- Botón limpiar -->
      <button
        v-if="hayFiltrosActivos"
        class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100"
        @click="limpiarFiltros"
      >
        Limpiar
      </button>
    </div>

    <!-- Banner de error de carga -->
    <div
      v-if="estudiantes.error"
      class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600"
    >
      {{ estudiantes.error }}
    </div>

    <!-- Error de acciones de fila -->
    <div
      v-if="errorAccion"
      class="mb-4 flex items-start justify-between gap-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600"
    >
      <span>{{ errorAccion }}</span>
      <button class="text-red-500 hover:text-red-700" @click="errorAccion = null">Cerrar</button>
    </div>

    <!-- Tabla -->
    <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-3">Nombre completo</th>
            <th class="px-4 py-3">RUT</th>
            <th class="px-4 py-3">Carrera</th>
            <th class="px-4 py-3">Semestre</th>
            <th class="px-4 py-3">Docente asignado</th>
            <th class="px-4 py-3">Estado</th>
            <th class="px-4 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <!-- Cargando -->
          <tr v-if="estudiantes.cargando">
            <td class="px-4 py-6 text-center text-slate-500" colspan="7">Cargando…</td>
          </tr>
          <!-- Sin resultados -->
          <tr v-else-if="estudiantes.lista.length === 0">
            <td class="px-4 py-6 text-center text-slate-500" colspan="7">
              No hay estudiantes que coincidan con los filtros.
            </td>
          </tr>
          <!-- Filas -->
          <tr v-for="e in estudiantes.lista" v-else :key="e.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 font-medium text-slate-900">{{ e.nombre }} {{ e.apellido }}</td>
            <td class="px-4 py-3 font-mono text-slate-600">{{ e.rut }}</td>
            <td class="px-4 py-3 text-slate-600">{{ e.carrera_nombre || '—' }}</td>
            <td class="px-4 py-3 text-slate-600">{{ e.semestre_ingreso_practica || '—' }}</td>
            <td class="px-4 py-3 text-slate-600">{{ e.docente_nombre || '—' }}</td>
            <td class="px-4 py-3">
              <span
                class="inline-flex rounded px-2 py-0.5 text-xs font-medium"
                :class="e.activo ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'"
              >
                {{ e.activo ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-3">
                <!-- Admin: editar + desactivar/activar -->
                <template v-if="auth.esAdmin">
                  <button
                    class="text-slate-700 hover:text-slate-900 hover:underline"
                    @click="abrirEditar(e)"
                  >
                    Editar
                  </button>
                  <button
                    v-if="e.activo"
                    class="text-red-600 hover:text-red-700 hover:underline"
                    @click="desactivar(e)"
                  >
                    Desactivar
                  </button>
                  <button
                    v-else
                    class="text-green-700 hover:text-green-800 hover:underline"
                    @click="activar(e)"
                  >
                    Activar
                  </button>
                </template>
                <!-- Docente: solo ver detalle -->
                <template v-else>
                  <router-link
                    :to="`/estudiantes/${e.id}`"
                    class="text-indigo-600 hover:text-indigo-800 hover:underline"
                  >
                    Ver detalle
                  </router-link>
                </template>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <div class="mt-4 flex items-center justify-between text-sm text-slate-600">
      <span>
        Página {{ estudiantes.page }} de {{ totalPaginas }} &bull; Total {{ estudiantes.total }}
      </span>
      <div class="flex items-center gap-2">
        <button
          class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-50"
          :disabled="estudiantes.page <= 1 || estudiantes.cargando"
          @click="paginaAnterior"
        >
          Anterior
        </button>
        <button
          class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-50"
          :disabled="estudiantes.page >= totalPaginas || estudiantes.cargando"
          @click="paginaSiguiente"
        >
          Siguiente
        </button>
      </div>
    </div>

    <!-- Modal crear/editar -->
    <EstudianteModal
      v-if="modalAbierto"
      :estudiante="estudianteEditando"
      :carreras="carreras.lista"
      :docentes="docentes"
      :guardando="guardando"
      :error="errorModal"
      @guardar="guardarEstudiante"
      @cerrar="cerrarModal"
    />
  </div>
</template>
