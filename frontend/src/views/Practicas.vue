<script setup>
  import { computed, onMounted, ref, watch } from "vue";
  import { useAuthStore } from "../stores/auth";
  import { usePracticasStore } from "../stores/practicas";
  import { api } from "../services/api";

  const auth = useAuthStore();
  const practicas = usePracticasStore();

  const formulario = ref({
    estudiante_id: "",
    empresa_id: "",
    supervisor_id: "",
    semestre: "",
    fecha_inicio: "",
    fecha_termino: "",
    horas_totales: "",
    observaciones: "",
  });
  const estudiantes = ref([]);
  const empresas = ref([]);
  const supervisores = ref([]);
  const guardando = ref(false);
  const errorForm = ref(null);
  const mostrandoDetalle = ref(false);

  const totalPaginas = computed(() =>
    Math.max(1, Math.ceil(practicas.total / practicas.perPage)),
  );

  onMounted(async () => {
    practicas.cargar();
    await cargarCatalogos();
  });

  async function cargarCatalogos() {
    const [estudiantesRes, empresasRes] = await Promise.all([
      api.get("/estudiantes"),
      api.get("/empresas"),
    ]);
    estudiantes.value = estudiantesRes.data || [];
    empresas.value = empresasRes.data || [];
  }

  watch(
    () => formulario.value.empresa_id,
    async (empresaId) => {
      formulario.value.supervisor_id = "";
      supervisores.value = [];
      if (!empresaId) return;
      try {
        const data = await api.get(`/empresas/${empresaId}/supervisores`);
        supervisores.value = data.data || [];
      } catch {
        supervisores.value = [];
      }
    },
  );

  async function guardar() {
    guardando.value = true;
    errorForm.value = null;
    try {
      await practicas.crear({
        ...formulario.value,
        estudiante_id: Number(formulario.value.estudiante_id),
        empresa_id: Number(formulario.value.empresa_id),
        supervisor_id: formulario.value.supervisor_id
          ? Number(formulario.value.supervisor_id)
          : null,
        horas_totales: formulario.value.horas_totales
          ? Number(formulario.value.horas_totales)
          : null,
      });
      formulario.value = {
        estudiante_id: "",
        empresa_id: "",
        supervisor_id: "",
        semestre: "",
        fecha_inicio: "",
        fecha_termino: "",
        horas_totales: "",
        observaciones: "",
      };
    } catch (e) {
      errorForm.value = e.message;
    } finally {
      guardando.value = false;
    }
  }

  function paginaAnterior() {
    if (practicas.page > 1) {
      practicas.page -= 1;
      practicas.cargar();
    }
  }

  function paginaSiguiente() {
    if (practicas.page < totalPaginas.value) {
      practicas.page += 1;
      practicas.cargar();
    }
  }

  async function verDetalle(practica) {
    mostrandoDetalle.value = true;
    await practicas.cargarUna(practica.id);
  }

  function cerrarDetalle() {
    mostrandoDetalle.value = false;
    practicas.practicaActual = null;
  }

  async function cambiarEstado(practica, estado) {
    try {
      await practicas.cambiarEstado(practica.id, estado);
    } catch (e) {
      practicas.error = e.message;
    }
  }

  function badgeClase(estado) {
    const mapa = {
      pendiente: "bg-slate-100 text-slate-700",
      en_curso: "bg-blue-100 text-blue-700",
      avance_1: "bg-amber-100 text-amber-700",
      avance_2: "bg-violet-100 text-violet-700",
      informe_final: "bg-cyan-100 text-cyan-700",
      aprobada: "bg-green-100 text-green-700",
      reprobada: "bg-red-100 text-red-700",
      abandonada: "bg-gray-100 text-gray-700",
    };
    return mapa[estado] || "bg-slate-100 text-slate-700";
  }
</script>

<template>
  <div>
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-semibold text-slate-900">Prácticas</h2>
        <p class="text-sm text-slate-500">
          Vincula estudiantes, empresas y supervisores en una práctica con
          estados.
        </p>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
      <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="font-semibold text-slate-900">Crear práctica</h3>
          <span class="text-sm text-slate-500"
            >Genera seguimiento y entregas</span
          >
        </div>

        <div
          v-if="errorForm"
          class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600"
        >
          {{ errorForm }}
        </div>

        <div class="grid gap-3 md:grid-cols-2">
          <div>
            <label
              class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500"
              >Estudiante</label
            >
            <select
              v-model="formulario.estudiante_id"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            >
              <option value="">Selecciona un estudiante</option>
              <option v-for="est in estudiantes" :key="est.id" :value="est.id">
                {{ est.nombre }} {{ est.apellido }}
              </option>
            </select>
          </div>
          <div>
            <label
              class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500"
              >Empresa</label
            >
            <select
              v-model="formulario.empresa_id"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            >
              <option value="">Selecciona una empresa</option>
              <option v-for="emp in empresas" :key="emp.id" :value="emp.id">
                {{ emp.nombre }}
              </option>
            </select>
          </div>
          <div>
            <label
              class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500"
              >Supervisor</label
            >
            <select
              v-model="formulario.supervisor_id"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            >
              <option value="">Sin supervisor</option>
              <option v-for="sup in supervisores" :key="sup.id" :value="sup.id">
                {{ sup.nombre }} {{ sup.apellido }}
              </option>
            </select>
          </div>
          <div>
            <label
              class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500"
              >Semestre</label
            >
            <input
              v-model="formulario.semestre"
              placeholder="2026-2"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label
              class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500"
              >Fecha de inicio</label
            >
            <input
              v-model="formulario.fecha_inicio"
              type="date"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label
              class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500"
              >Fecha de término</label
            >
            <input
              v-model="formulario.fecha_termino"
              type="date"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label
              class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500"
              >Horas totales</label
            >
            <input
              v-model="formulario.horas_totales"
              type="number"
              min="0"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
          </div>
          <div class="md:col-span-2">
            <label
              class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500"
              >Observaciones</label
            >
            <textarea
              v-model="formulario.observaciones"
              rows="3"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
          </div>
        </div>

        <div class="mt-4 flex justify-end">
          <button
            class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60"
            :disabled="guardando"
            @click="guardar"
          >
            {{ guardando ? "Guardando…" : "Crear práctica" }}
          </button>
        </div>
      </div>

      <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <h3 class="mb-4 font-semibold text-slate-900">Detalle rápido</h3>
        <p v-if="!practicas.practicaActual" class="text-sm text-slate-500">
          Selecciona una práctica de la tabla para ver su estado, seguimiento y
          entregas.
        </p>
        <div v-else>
          <div class="mb-3 flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-slate-900">
                {{ practicas.practicaActual.estudiante_nombre }}
                {{ practicas.practicaActual.estudiante_apellido }}
              </p>
              <p class="text-sm text-slate-500">
                {{ practicas.practicaActual.empresa_nombre }}
              </p>
            </div>
            <span
              class="rounded-full px-2.5 py-1 text-xs font-medium"
              :class="badgeClase(practicas.practicaActual.estado)"
            >
              {{ practicas.practicaActual.estado }}
            </span>
          </div>
          <div class="space-y-2 text-sm text-slate-600">
            <p>
              <span class="font-medium text-slate-700">Semestre:</span>
              {{ practicas.practicaActual.semestre }}
            </p>
            <p>
              <span class="font-medium text-slate-700">Fechas:</span>
              {{ practicas.practicaActual.fecha_inicio }} →
              {{ practicas.practicaActual.fecha_termino }}
            </p>
            <p>
              <span class="font-medium text-slate-700">Seguimiento:</span> Se
              generan 12 semanas + 3 entregas al crear la práctica.
            </p>
          </div>
          <div class="mt-4 flex gap-2">
            <button
              v-for="estado in [
                'en_curso',
                'avance_1',
                'avance_2',
                'informe_final',
                'aprobada',
                'reprobada',
                'abandonada',
              ]"
              :key="estado"
              class="rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100"
              @click="cambiarEstado(practicas.practicaActual, estado)"
            >
              {{ estado }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div
      class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden"
    >
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead
          class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500"
        >
          <tr>
            <th class="px-4 py-3">Estudiante</th>
            <th class="px-4 py-3">Empresa</th>
            <th class="px-4 py-3">Semestre</th>
            <th class="px-4 py-3">Estado</th>
            <th class="px-4 py-3">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="practicas.cargando">
            <td colspan="5" class="px-4 py-6 text-center text-slate-500">
              Cargando prácticas…
            </td>
          </tr>
          <tr v-else-if="practicas.lista.length === 0">
            <td colspan="5" class="px-4 py-6 text-center text-slate-500">
              Aún no hay prácticas registradas.
            </td>
          </tr>
          <tr
            v-for="practica in practicas.lista"
            :key="practica.id"
            class="hover:bg-slate-50"
          >
            <td class="px-4 py-3">
              <div class="font-medium text-slate-900">
                {{ practica.estudiante_nombre }}
                {{ practica.estudiante_apellido }}
              </div>
              <div class="text-xs text-slate-500">
                {{
                  practica.supervisor_nombre
                    ? `${practica.supervisor_nombre} ${practica.supervisor_apellido}`
                    : "Sin supervisor"
                }}
              </div>
            </td>
            <td class="px-4 py-3 text-slate-600">
              {{ practica.empresa_nombre }}
            </td>
            <td class="px-4 py-3 text-slate-600">{{ practica.semestre }}</td>
            <td class="px-4 py-3">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="badgeClase(practica.estado)"
                >{{ practica.estado }}</span
              >
            </td>
            <td class="px-4 py-3">
              <button
                class="text-indigo-600 hover:text-indigo-800 hover:underline"
                @click="verDetalle(practica)"
              >
                Ver detalle
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-4 flex items-center justify-between text-sm text-slate-600">
      <span
        >Pagina {{ practicas.page }} de {{ totalPaginas }} · Total
        {{ practicas.total }}</span
      >
      <div class="flex items-center gap-2">
        <button
          class="rounded-md border border-slate-300 px-3 py-2 font-medium text-slate-700 hover:bg-slate-100"
          @click="paginaAnterior"
        >
          Anterior
        </button>
        <button
          class="rounded-md border border-slate-300 px-3 py-2 font-medium text-slate-700 hover:bg-slate-100"
          @click="paginaSiguiente"
        >
          Siguiente
        </button>
      </div>
    </div>
  </div>
</template>
