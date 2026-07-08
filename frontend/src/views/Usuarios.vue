<script setup>
import { ref, computed, onMounted } from 'vue'
import { useUsuariosStore } from '../stores/usuarios'
import UsuarioModal from '../components/UsuarioModal.vue'

const usuarios = useUsuariosStore()

// Buscador local: no dispara petición en cada tecla, sólo al Enter/botón.
const busqueda = ref(usuarios.filtros.q)

// Modal crear/editar.
const modalAbierto = ref(false)
const modalModo = ref('crear')
const usuarioEditando = ref(null)
const guardando = ref(false)
const errorModal = ref(null)

// Aviso con la contraseña generada (crear / regenerar).
const passwordVisible = ref(null) // { password, correoEnviado }
const copiado = ref(false)

// Errores de acciones de fila (activar/desactivar/regenerar).
const errorAccion = ref(null)

const totalPaginas = computed(() =>
  Math.max(1, Math.ceil(usuarios.total / usuarios.perPage)),
)

onMounted(() => {
  usuarios.cargar()
})

function formatearFecha(valor) {
  if (!valor) return '—'
  const d = new Date(valor)
  if (Number.isNaN(d.getTime())) return valor
  return d.toLocaleDateString('es-CL', { year: 'numeric', month: '2-digit', day: '2-digit' })
}

function buscar() {
  usuarios.filtros.q = busqueda.value.trim()
  usuarios.page = 1
  usuarios.cargar()
}

function aplicarFiltros() {
  usuarios.page = 1
  usuarios.cargar()
}

function paginaAnterior() {
  if (usuarios.page > 1) {
    usuarios.page--
    usuarios.cargar()
  }
}

function paginaSiguiente() {
  if (usuarios.page < totalPaginas.value) {
    usuarios.page++
    usuarios.cargar()
  }
}

// --- Modal crear/editar ---
function abrirCrear() {
  modalModo.value = 'crear'
  usuarioEditando.value = null
  errorModal.value = null
  modalAbierto.value = true
}

function abrirEditar(u) {
  modalModo.value = 'editar'
  usuarioEditando.value = u
  errorModal.value = null
  modalAbierto.value = true
}

async function guardarUsuario(datos) {
  guardando.value = true
  errorModal.value = null
  try {
    if (modalModo.value === 'crear') {
      const resp = await usuarios.crear(datos)
      modalAbierto.value = false
      mostrarPassword(resp.password_generada, resp.correo_enviado)
    } else {
      await usuarios.actualizar(usuarioEditando.value.id, datos)
      modalAbierto.value = false
    }
  } catch (e) {
    errorModal.value = e.message
  } finally {
    guardando.value = false
  }
}

// --- Aviso de contraseña generada ---
function mostrarPassword(password, correoEnviado) {
  passwordVisible.value = { password, correoEnviado }
  copiado.value = false
}

async function copiarPassword() {
  try {
    await navigator.clipboard.writeText(passwordVisible.value.password)
    copiado.value = true
  } catch {
    copiado.value = false
  }
}

function cerrarPassword() {
  passwordVisible.value = null
}

// --- Acciones de fila ---
async function regenerar(u) {
  errorAccion.value = null
  if (
    !confirm(
      `¿Regenerar la contraseña de ${u.nombre} ${u.apellido}? La contraseña actual dejará de ser válida.`,
    )
  ) {
    return
  }
  try {
    const resp = await usuarios.regenerar(u.id)
    mostrarPassword(resp.password_generada, resp.correo_enviado)
  } catch (e) {
    errorAccion.value = e.message
  }
}

async function desactivar(u) {
  errorAccion.value = null
  if (!confirm(`¿Desactivar a ${u.nombre} ${u.apellido}?`)) return
  try {
    await usuarios.desactivar(u.id)
  } catch (e) {
    errorAccion.value = e.message
  }
}

async function activar(u) {
  errorAccion.value = null
  try {
    await usuarios.actualizar(u.id, {
      nombre: u.nombre,
      apellido: u.apellido,
      correo: u.correo,
      rol: u.rol,
      activo: true,
    })
  } catch (e) {
    errorAccion.value = e.message
  }
}
</script>

<template>
  <div>
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-semibold text-slate-900">Usuarios</h2>
        <p class="text-sm text-slate-500">Gestión de cuentas de la plataforma.</p>
      </div>
      <button
        class="inline-flex items-center rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700"
        @click="abrirCrear"
      >
        Nuevo usuario
      </button>
    </div>

    <!-- Barra de filtros -->
    <div class="mb-4 flex flex-wrap items-end gap-3">
      <div class="flex items-end gap-2">
        <div>
          <label for="q" class="block text-xs font-medium text-slate-600 mb-1">Buscar</label>
          <input
            id="q"
            v-model="busqueda"
            type="text"
            placeholder="Nombre o correo"
            class="w-56 rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
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

      <div>
        <label for="rol" class="block text-xs font-medium text-slate-600 mb-1">Rol</label>
        <select
          id="rol"
          v-model="usuarios.filtros.rol"
          class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          @change="aplicarFiltros"
        >
          <option value="">Todos</option>
          <option value="admin">Administrador</option>
          <option value="docente">Docente</option>
        </select>
      </div>

      <div>
        <label for="activo" class="block text-xs font-medium text-slate-600 mb-1">Estado</label>
        <select
          id="activo"
          v-model="usuarios.filtros.activo"
          class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          @change="aplicarFiltros"
        >
          <option value="">Todos</option>
          <option value="1">Activos</option>
          <option value="0">Inactivos</option>
        </select>
      </div>
    </div>

    <!-- Errores de carga / acciones -->
    <div
      v-if="usuarios.error"
      class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600"
    >
      {{ usuarios.error }}
    </div>
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
            <th class="px-4 py-3">Correo</th>
            <th class="px-4 py-3">Rol</th>
            <th class="px-4 py-3">Estado</th>
            <th class="px-4 py-3">Creado</th>
            <th class="px-4 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="usuarios.cargando">
            <td class="px-4 py-6 text-center text-slate-500" colspan="6">Cargando…</td>
          </tr>
          <tr v-else-if="usuarios.lista.length === 0">
            <td class="px-4 py-6 text-center text-slate-500" colspan="6">No hay usuarios.</td>
          </tr>
          <tr v-for="u in usuarios.lista" v-else :key="u.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 font-medium text-slate-900">{{ u.nombre }} {{ u.apellido }}</td>
            <td class="px-4 py-3 text-slate-600">{{ u.correo }}</td>
            <td class="px-4 py-3">
              <span
                class="inline-flex rounded px-2 py-0.5 text-xs font-medium"
                :class="u.rol === 'admin' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-700'"
              >
                {{ u.rol === 'admin' ? 'Administrador' : 'Docente' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span
                class="inline-flex rounded px-2 py-0.5 text-xs font-medium"
                :class="u.activo ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'"
              >
                {{ u.activo ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td class="px-4 py-3 text-slate-600">{{ formatearFecha(u.creado_en) }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-3">
                <button class="text-slate-700 hover:text-slate-900 hover:underline" @click="abrirEditar(u)">
                  Editar
                </button>
                <button class="text-slate-700 hover:text-slate-900 hover:underline" @click="regenerar(u)">
                  Regenerar contraseña
                </button>
                <button
                  v-if="u.activo"
                  class="text-red-600 hover:text-red-700 hover:underline"
                  @click="desactivar(u)"
                >
                  Desactivar
                </button>
                <button
                  v-else
                  class="text-green-700 hover:text-green-800 hover:underline"
                  @click="activar(u)"
                >
                  Activar
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <div class="mt-4 flex items-center justify-between text-sm text-slate-600">
      <span>Página {{ usuarios.page }} de {{ totalPaginas }}, total {{ usuarios.total }}</span>
      <div class="flex items-center gap-2">
        <button
          class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-50"
          :disabled="usuarios.page <= 1 || usuarios.cargando"
          @click="paginaAnterior"
        >
          Anterior
        </button>
        <button
          class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-50"
          :disabled="usuarios.page >= totalPaginas || usuarios.cargando"
          @click="paginaSiguiente"
        >
          Siguiente
        </button>
      </div>
    </div>

    <!-- Modal crear/editar -->
    <UsuarioModal
      v-if="modalAbierto"
      :modo="modalModo"
      :usuario="usuarioEditando"
      :guardando="guardando"
      :error="errorModal"
      @guardar="guardarUsuario"
      @cerrar="modalAbierto = false"
    />

    <!-- Aviso de contraseña generada -->
    <div
      v-if="passwordVisible"
      class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 px-4"
    >
      <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-lg">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Contraseña generada</h3>
        <p class="text-sm text-slate-600 mb-4">
          Guarda esta contraseña ahora: no se volverá a mostrar.
        </p>

        <div class="flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
          <code class="flex-1 font-mono text-sm text-slate-800 break-all">
            {{ passwordVisible.password }}
          </code>
          <button
            class="inline-flex items-center rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100"
            @click="copiarPassword"
          >
            {{ copiado ? 'Copiado' : 'Copiar' }}
          </button>
        </div>

        <div
          v-if="!passwordVisible.correoEnviado"
          class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700"
        >
          El correo no pudo enviarse. Entrega la contraseña al usuario de forma manual.
        </div>
        <p v-else class="mt-4 text-sm text-slate-500">
          Se envió un correo al usuario con sus credenciales.
        </p>

        <div class="mt-6 flex justify-end">
          <button
            class="inline-flex items-center rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700"
            @click="cerrarPassword"
          >
            Entendido
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
