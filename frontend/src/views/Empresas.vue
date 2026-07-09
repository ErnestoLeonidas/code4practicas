<script setup>
import { ref, computed, onMounted } from 'vue'
import { useEmpresasStore } from '../stores/empresas'
import { useAuthStore } from '../stores/auth'
import EmpresaModal from '../components/EmpresaModal.vue'
import SupervisorModal from '../components/SupervisorModal.vue'

const empresas = useEmpresasStore()
const auth = useAuthStore()

// Buscador local: no dispara petición en cada tecla, solo al Enter/botón.
const busqueda = ref(empresas.filtros.q)
const ciudadFiltro = ref(empresas.filtros.ciudad)

// Errores de acciones de fila.
const errorAccion = ref(null)

// --- Modal empresa ---
const modalEmpresaAbierto = ref(false)
const empresaEditando = ref(null)
const guardandoEmpresa = ref(false)
const errorModalEmpresa = ref(null)

// --- Panel de detalle (modal de ver) ---
const detalleAbierto = ref(false)
const cargandoDetalle = ref(false)
const errorDetalle = ref(null)

// --- Modal supervisor ---
const modalSupervisorAbierto = ref(false)
const supervisorEditando = ref(null)
const guardandoSupervisor = ref(false)
const errorModalSupervisor = ref(null)

const totalPaginas = computed(() =>
  Math.max(1, Math.ceil(empresas.total / empresas.perPage)),
)

const hayFiltrosActivos = computed(
  () => busqueda.value.trim() !== '' || ciudadFiltro.value.trim() !== '',
)

onMounted(() => {
  empresas.cargar()
})

function buscar() {
  empresas.filtros.q = busqueda.value.trim()
  empresas.page = 1
  empresas.cargar()
}

function aplicarCiudad() {
  empresas.filtros.ciudad = ciudadFiltro.value.trim()
  empresas.page = 1
  empresas.cargar()
}

function limpiarFiltros() {
  busqueda.value = ''
  ciudadFiltro.value = ''
  empresas.filtros.q = ''
  empresas.filtros.ciudad = ''
  empresas.page = 1
  empresas.cargar()
}

function paginaAnterior() {
  if (empresas.page > 1) {
    empresas.page--
    empresas.cargar()
  }
}

function paginaSiguiente() {
  if (empresas.page < totalPaginas.value) {
    empresas.page++
    empresas.cargar()
  }
}

// --- Modal empresa (crear/editar) ---
function abrirCrear() {
  empresaEditando.value = null
  errorModalEmpresa.value = null
  modalEmpresaAbierto.value = true
}

function abrirEditar(empresa) {
  empresaEditando.value = empresa
  errorModalEmpresa.value = null
  modalEmpresaAbierto.value = true
}

function cerrarModalEmpresa() {
  modalEmpresaAbierto.value = false
  empresaEditando.value = null
  errorModalEmpresa.value = null
}

async function guardarEmpresa(datos) {
  guardandoEmpresa.value = true
  errorModalEmpresa.value = null
  try {
    if (empresaEditando.value === null) {
      await empresas.crear(datos)
    } else {
      await empresas.actualizar(empresaEditando.value.id, datos)
    }
    cerrarModalEmpresa()
  } catch (e) {
    errorModalEmpresa.value = e.message
  } finally {
    guardandoEmpresa.value = false
  }
}

// --- Desactivar empresa ---
async function desactivar(empresa) {
  errorAccion.value = null
  if (!confirm(`¿Desactivar "${empresa.nombre}"? Se ocultará de las listas activas.`)) return
  try {
    await empresas.desactivar(empresa.id)
  } catch (e) {
    errorAccion.value = e.message
  }
}

// --- Panel de detalle ---
async function verDetalle(empresa) {
  errorDetalle.value = null
  cargandoDetalle.value = true
  detalleAbierto.value = true
  try {
    await empresas.cargarUna(empresa.id)
  } catch (e) {
    errorDetalle.value = e.message
  } finally {
    cargandoDetalle.value = false
  }
}

function cerrarDetalle() {
  detalleAbierto.value = false
  empresas.empresaActual = null
  errorDetalle.value = null
}

// --- Modal supervisor (crear/editar) ---
function abrirCrearSupervisor() {
  supervisorEditando.value = null
  errorModalSupervisor.value = null
  modalSupervisorAbierto.value = true
}

function abrirEditarSupervisor(supervisor) {
  supervisorEditando.value = supervisor
  errorModalSupervisor.value = null
  modalSupervisorAbierto.value = true
}

function cerrarModalSupervisor() {
  modalSupervisorAbierto.value = false
  supervisorEditando.value = null
  errorModalSupervisor.value = null
}

async function guardarSupervisor(datos) {
  if (!empresas.empresaActual) return
  guardandoSupervisor.value = true
  errorModalSupervisor.value = null
  try {
    if (supervisorEditando.value === null) {
      await empresas.crearSupervisor(empresas.empresaActual.id, datos)
    } else {
      await empresas.actualizarSupervisor(supervisorEditando.value.id, datos)
      // Recarga manual para reflejar cambios en el detalle.
      await empresas.cargarUna(empresas.empresaActual.id)
    }
    cerrarModalSupervisor()
  } catch (e) {
    errorModalSupervisor.value = e.message
  } finally {
    guardandoSupervisor.value = false
  }
}

async function eliminarSupervisor(supervisor) {
  if (!empresas.empresaActual) return
  if (!confirm(`¿Eliminar al supervisor ${supervisor.nombre} ${supervisor.apellido}?`)) return
  try {
    await empresas.desactivarSupervisor(empresas.empresaActual.id, supervisor.id)
  } catch (e) {
    errorDetalle.value = e.message
  }
}

// Supervisores activos de la empresa actual.
const supervisoresActivos = computed(() =>
  (empresas.empresaActual?.supervisores || []).filter((s) => s.activo),
)
</script>

<template>
  <div>
    <!-- Encabezado -->
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-semibold text-slate-900">Empresas</h2>
        <p class="text-sm text-slate-500">Empresas colaboradoras y sus supervisores.</p>
      </div>
      <button
        v-if="auth.esAdmin"
        class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        @click="abrirCrear"
      >
        Nueva empresa
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
            placeholder="Nombre o RUT…"
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

      <!-- Ciudad -->
      <div>
        <label for="ciudad" class="block text-xs font-medium text-slate-600 mb-1">Ciudad</label>
        <input
          id="ciudad"
          v-model="ciudadFiltro"
          type="text"
          placeholder="Filtrar por ciudad…"
          class="w-48 rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          @keyup.enter="aplicarCiudad"
          @change="aplicarCiudad"
        />
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
      v-if="empresas.error"
      class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600"
    >
      {{ empresas.error }}
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
            <th class="px-4 py-3">Nombre</th>
            <th class="px-4 py-3">RUT empresa</th>
            <th class="px-4 py-3">Ciudad</th>
            <th class="px-4 py-3">Giro</th>
            <th class="px-4 py-3">Supervisores</th>
            <th class="px-4 py-3">Estado</th>
            <th class="px-4 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <!-- Cargando -->
          <tr v-if="empresas.cargando">
            <td class="px-4 py-6 text-center text-slate-500" colspan="7">Cargando…</td>
          </tr>
          <!-- Sin resultados -->
          <tr v-else-if="empresas.lista.length === 0">
            <td class="px-4 py-6 text-center text-slate-500" colspan="7">
              No hay empresas que coincidan con los filtros.
            </td>
          </tr>
          <!-- Filas -->
          <tr v-for="emp in empresas.lista" v-else :key="emp.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 font-medium text-slate-900">{{ emp.nombre }}</td>
            <td class="px-4 py-3 font-mono text-slate-600">{{ emp.rut_empresa || '—' }}</td>
            <td class="px-4 py-3 text-slate-600">{{ emp.ciudad || '—' }}</td>
            <td class="px-4 py-3 text-slate-600">{{ emp.giro || '—' }}</td>
            <td class="px-4 py-3">
              <span
                class="inline-flex rounded px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-700"
              >
                {{ emp.supervisor_count ?? 0 }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span
                class="inline-flex rounded px-2 py-0.5 text-xs font-medium"
                :class="emp.activo ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'"
              >
                {{ emp.activo ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-3">
                <!-- Ver (todos) -->
                <button
                  class="text-indigo-600 hover:text-indigo-800 hover:underline"
                  @click="verDetalle(emp)"
                >
                  Ver
                </button>
                <!-- Admin: editar + desactivar -->
                <template v-if="auth.esAdmin">
                  <button
                    class="text-slate-700 hover:text-slate-900 hover:underline"
                    @click="abrirEditar(emp)"
                  >
                    Editar
                  </button>
                  <button
                    v-if="emp.activo"
                    class="text-red-600 hover:text-red-700 hover:underline"
                    @click="desactivar(emp)"
                  >
                    Desactivar
                  </button>
                  <button
                    v-else
                    class="text-green-700 hover:text-green-800 hover:underline"
                    @click="abrirEditar(emp)"
                  >
                    Activar
                  </button>
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
        Página {{ empresas.page }} de {{ totalPaginas }} &bull; Total {{ empresas.total }}
      </span>
      <div class="flex items-center gap-2">
        <button
          class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-50"
          :disabled="empresas.page <= 1 || empresas.cargando"
          @click="paginaAnterior"
        >
          Anterior
        </button>
        <button
          class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-50"
          :disabled="empresas.page >= totalPaginas || empresas.cargando"
          @click="paginaSiguiente"
        >
          Siguiente
        </button>
      </div>
    </div>

    <!-- Modal crear/editar empresa -->
    <EmpresaModal
      v-if="modalEmpresaAbierto"
      :empresa="empresaEditando"
      :guardando="guardandoEmpresa"
      :error="errorModalEmpresa"
      @guardar="guardarEmpresa"
      @cerrar="cerrarModalEmpresa"
    />

    <!-- Panel de detalle (modal Ver) -->
    <div
      v-if="detalleAbierto"
      class="fixed inset-0 z-40 flex items-start justify-center bg-slate-900/40 px-4 py-8 overflow-y-auto"
    >
      <div class="w-full max-w-2xl rounded-lg border border-slate-200 bg-white shadow-lg">
        <!-- Estado cargando / error de detalle -->
        <div v-if="cargandoDetalle" class="p-8 text-center text-slate-500">
          Cargando…
        </div>
        <div
          v-else-if="errorDetalle"
          class="p-6"
        >
          <p class="text-sm text-red-600">{{ errorDetalle }}</p>
          <div class="mt-4 flex justify-end">
            <button
              class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
              @click="cerrarDetalle"
            >
              Cerrar
            </button>
          </div>
        </div>
        <template v-else-if="empresas.empresaActual">
          <!-- Encabezado del panel -->
          <div class="border-b border-slate-200 px-6 py-4 flex items-center justify-between gap-4">
            <div>
              <h3 class="text-lg font-semibold text-slate-900">
                {{ empresas.empresaActual.nombre }}
              </h3>
              <p v-if="empresas.empresaActual.giro" class="text-sm text-slate-500">
                {{ empresas.empresaActual.giro }}
              </p>
            </div>
            <button
              class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
              @click="cerrarDetalle"
            >
              Cerrar
            </button>
          </div>

          <!-- Datos de la empresa -->
          <div class="px-6 py-4">
            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
              <div v-if="empresas.empresaActual.rut_empresa">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">RUT</dt>
                <dd class="mt-0.5 font-mono text-slate-800">{{ empresas.empresaActual.rut_empresa }}</dd>
              </div>
              <div v-if="empresas.empresaActual.ciudad">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Ciudad</dt>
                <dd class="mt-0.5 text-slate-800">{{ empresas.empresaActual.ciudad }}</dd>
              </div>
              <div v-if="empresas.empresaActual.direccion" class="col-span-2">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Dirección</dt>
                <dd class="mt-0.5 text-slate-800">{{ empresas.empresaActual.direccion }}</dd>
              </div>
              <div v-if="empresas.empresaActual.telefono">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Teléfono</dt>
                <dd class="mt-0.5 text-slate-800">{{ empresas.empresaActual.telefono }}</dd>
              </div>
              <div v-if="empresas.empresaActual.sitio_web">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Sitio web</dt>
                <dd class="mt-0.5">
                  <a
                    :href="empresas.empresaActual.sitio_web"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-indigo-600 hover:text-indigo-800 hover:underline break-all"
                  >
                    {{ empresas.empresaActual.sitio_web }}
                  </a>
                </dd>
              </div>
            </dl>
          </div>

          <!-- Sección supervisores -->
          <div class="border-t border-slate-200 px-6 py-4">
            <div class="flex items-center justify-between mb-3">
              <h4 class="text-sm font-semibold text-slate-700">Supervisores</h4>
              <button
                v-if="auth.esAdmin"
                class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
                @click="abrirCrearSupervisor"
              >
                Agregar supervisor
              </button>
            </div>

            <!-- Error de acción en detalle -->
            <div
              v-if="errorDetalle && !cargandoDetalle"
              class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600"
            >
              {{ errorDetalle }}
            </div>

            <div v-if="supervisoresActivos.length === 0" class="text-sm text-slate-500 py-2">
              No hay supervisores activos registrados.
            </div>

            <ul v-else class="divide-y divide-slate-100">
              <li
                v-for="sv in supervisoresActivos"
                :key="sv.id"
                class="py-3 flex items-start justify-between gap-4"
              >
                <div class="text-sm">
                  <p class="font-medium text-slate-900">{{ sv.nombre }} {{ sv.apellido }}</p>
                  <p v-if="sv.cargo || sv.profesion" class="text-slate-500">
                    {{ [sv.cargo, sv.profesion].filter(Boolean).join(' · ') }}
                  </p>
                  <p v-if="sv.telefono" class="text-slate-600">{{ sv.telefono }}</p>
                  <p v-if="sv.correo">
                    <a
                      :href="`mailto:${sv.correo}`"
                      class="text-indigo-600 hover:text-indigo-800 hover:underline"
                    >
                      {{ sv.correo }}
                    </a>
                  </p>
                </div>
                <div v-if="auth.esAdmin" class="flex items-center gap-3 flex-shrink-0">
                  <button
                    class="text-slate-700 hover:text-slate-900 hover:underline text-sm"
                    @click="abrirEditarSupervisor(sv)"
                  >
                    Editar
                  </button>
                  <button
                    class="text-red-600 hover:text-red-700 hover:underline text-sm"
                    @click="eliminarSupervisor(sv)"
                  >
                    Eliminar
                  </button>
                </div>
              </li>
            </ul>
          </div>
        </template>
      </div>
    </div>

    <!-- Modal supervisor (crear/editar), z-50 para estar sobre el panel de detalle -->
    <SupervisorModal
      v-if="modalSupervisorAbierto && empresas.empresaActual"
      :supervisor="supervisorEditando"
      :empresa-id="empresas.empresaActual.id"
      :guardando="guardandoSupervisor"
      :error="errorModalSupervisor"
      @guardar="guardarSupervisor"
      @cerrar="cerrarModalSupervisor"
    />
  </div>
</template>
