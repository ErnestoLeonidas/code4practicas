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
  const guardandoSeguimiento = ref(false);
  const errorSeguimiento = ref(null);
  const guardandoEntrega = ref(false);
  const errorEntrega = ref(null);

  const itemsChecklist = [
    { key: "reunion_1a1", label: "1:1 realizada" },
    { key: "orientaciones_claras", label: "Orientaciones claras" },
    { key: "retroalimentacion", label: "Retroalimentación entregada" },
    { key: "evidencia_registrada", label: "Evidencia registrada" },
    { key: "disponibilidad_comunicada", label: "Disponibilidad comunicada" },
    { key: "ajuste_individual", label: "Ajuste individual" },
    { key: "reflexion_guiada", label: "Reflexión guiada" },
    { key: "etica_valores", label: "Ética y valores" },
  ];

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

  function riesgoClase(riesgo) {
    const mapa = {
      bajo: "bg-emerald-100 text-emerald-700",
      medio: "bg-amber-100 text-amber-700",
      alto: "bg-red-100 text-red-700",
    };
    return mapa[riesgo] || "bg-slate-100 text-slate-700";
  }

  function porcentajeClase(porcentaje) {
    if (porcentaje >= 85) return "text-emerald-700";
    if (porcentaje >= 60) return "text-amber-700";
    return "text-red-700";
  }

  function toggleItem(semana, key, checked) {
    semana[key] = checked ? 1 : 0;
  }

  async function guardarSeguimiento(semana) {
    if (!practicas.practicaActual?.id) return;
    guardandoSeguimiento.value = true;
    errorSeguimiento.value = null;
    try {
      const payload = {
        reunion_1a1: Number(semana.reunion_1a1 || 0),
        orientaciones_claras: Number(semana.orientaciones_claras || 0),
        retroalimentacion: Number(semana.retroalimentacion || 0),
        evidencia_registrada: Number(semana.evidencia_registrada || 0),
        disponibilidad_comunicada: Number(
          semana.disponibilidad_comunicada || 0,
        ),
        ajuste_individual: Number(semana.ajuste_individual || 0),
        reflexion_guiada: Number(semana.reflexion_guiada || 0),
        etica_valores: Number(semana.etica_valores || 0),
        observaciones: semana.observaciones || "",
        fecha_registro:
          semana.fecha_registro || new Date().toISOString().slice(0, 10),
      };
      const data = await practicas.guardarSeguimiento(
        practicas.practicaActual.id,
        semana.semana,
        payload,
      );
      if (data.semana) {
        const seguimiento = Array.isArray(practicas.practicaActual?.seguimiento)
          ? [...practicas.practicaActual.seguimiento]
          : [];
        const index = seguimiento.findIndex(
          (item) => Number(item.semana) === Number(semana.semana),
        );
        if (index >= 0) {
          seguimiento[index] = { ...seguimiento[index], ...data.semana };
        }
        practicas.practicaActual = {
          ...practicas.practicaActual,
          seguimiento,
          resumen: data.resumen,
        };
      }
    } catch (e) {
      errorSeguimiento.value = e.message;
    } finally {
      guardandoSeguimiento.value = false;
    }
  }

  async function guardarEntrega(entrega) {
    if (!practicas.practicaActual?.id) return;
    guardandoEntrega.value = true;
    errorEntrega.value = null;
    try {
      const payload = {
        entregado: Number(entrega.entregado || 0),
        fecha_entrega: entrega.fecha_entrega || "",
        nota:
          entrega.nota === "" || entrega.nota === null
            ? ""
            : Number(entrega.nota),
        retroalimentacion: entrega.retroalimentacion || "",
      };
      const data = await practicas.guardarEntrega(
        practicas.practicaActual.id,
        entrega.tipo,
        payload,
      );
      if (data.entrega) {
        const entregas = Array.isArray(practicas.practicaActual?.entregas)
          ? [...practicas.practicaActual.entregas]
          : [];
        const index = entregas.findIndex((item) => item.tipo === entrega.tipo);
        if (index >= 0) {
          entregas[index] = { ...entregas[index], ...data.entrega };
        }
        practicas.practicaActual = {
          ...practicas.practicaActual,
          entregas,
          resumen_entregas: data.resumen_entregas,
        };
      }
    } catch (e) {
      errorEntrega.value = e.message;
    } finally {
      guardandoEntrega.value = false;
    }
  }

  function etiquetaEntrega(tipo) {
    const mapa = {
      avance_1: "Avance 1",
      avance_2: "Avance 2",
      informe_final: "Informe final",
    };
    return mapa[tipo] || tipo;
  }

  function estadoEntrega(entrega) {
    if (Number(entrega.entregado) === 1) return "Entregado";
    if (entrega.fecha_limite && new Date(entrega.fecha_limite) < new Date())
      return "Atrasado";
    return "Pendiente";
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

          <div
            v-if="practicas.practicaActual?.seguimiento?.length"
            class="mt-5 border-t border-slate-200 pt-4"
          >
            <div
              v-if="errorSeguimiento"
              class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600"
            >
              {{ errorSeguimiento }}
            </div>
            <div class="mb-3 flex items-center justify-between gap-4">
              <div>
                <h4 class="text-sm font-semibold text-slate-900">
                  Seguimiento semanal
                </h4>
                <p class="text-xs text-slate-500">
                  Checklists de 12 semanas con semáforo de riesgo
                </p>
              </div>
            </div>
            <div class="mb-3 grid gap-2 sm:grid-cols-3">
              <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <p
                  class="text-[11px] font-medium uppercase tracking-wide text-slate-500"
                >
                  Cumplimiento
                </p>
                <p class="text-lg font-semibold text-slate-900">
                  {{
                    practicas.practicaActual?.resumen?.cumplimiento_global ?? 0
                  }}%
                </p>
              </div>
              <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <p
                  class="text-[11px] font-medium uppercase tracking-wide text-slate-500"
                >
                  Riesgo alto
                </p>
                <p class="text-lg font-semibold text-slate-900">
                  {{
                    practicas.practicaActual?.resumen?.semanas_en_riesgo_alto ??
                    0
                  }}
                </p>
              </div>
              <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <p
                  class="text-[11px] font-medium uppercase tracking-wide text-slate-500"
                >
                  1:1 / retro
                </p>
                <p class="text-sm font-semibold text-slate-900">
                  {{
                    practicas.practicaActual?.resumen?.uno_a_uno_realizadas ?? 0
                  }}
                  /
                  {{
                    practicas.practicaActual?.resumen
                      ?.retroalimentaciones_entregadas ?? 0
                  }}
                </p>
              </div>
            </div>
            <div class="space-y-3">
              <div
                v-for="semana in practicas.practicaActual?.seguimiento || []"
                :key="semana.id || semana.semana"
                class="rounded-lg border border-slate-200 p-3"
              >
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <p class="text-sm font-semibold text-slate-900">
                      Semana {{ semana.semana }}
                    </p>
                    <p class="text-xs text-slate-500">{{ semana.foco }}</p>
                  </div>
                  <div class="flex items-center gap-2">
                    <span
                      class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700"
                      :class="porcentajeClase(semana.porcentaje)"
                    >
                      {{ semana.porcentaje ?? 0 }}%
                    </span>
                    <span
                      class="rounded-full px-2 py-1 text-xs font-semibold"
                      :class="riesgoClase(semana.riesgo)"
                    >
                      {{ semana.riesgo || "alto" }}
                    </span>
                    <button
                      class="rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-60"
                      :disabled="guardandoSeguimiento"
                      @click="guardarSeguimiento(semana)"
                    >
                      {{ guardandoSeguimiento ? "Guardando…" : "Guardar" }}
                    </button>
                  </div>
                </div>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                  <label
                    v-for="item in itemsChecklist"
                    :key="item.key"
                    class="flex items-center gap-2 rounded-md border border-slate-200 px-2 py-2 text-xs text-slate-600"
                  >
                    <input
                      type="checkbox"
                      class="h-4 w-4 rounded border-slate-300"
                      :checked="Number(semana[item.key]) === 1"
                      @change="
                        toggleItem(semana, item.key, $event.target.checked)
                      "
                    />
                    <span>{{ item.label }}</span>
                  </label>
                </div>
                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                  <input
                    v-model="semana.fecha_registro"
                    type="date"
                    class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm"
                  />
                  <textarea
                    v-model="semana.observaciones"
                    rows="2"
                    placeholder="Observaciones"
                    class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm"
                  />
                </div>
              </div>
            </div>
          </div>

          <div
            v-if="practicas.practicaActual?.entregas?.length"
            class="mt-5 border-t border-slate-200 pt-4"
          >
            <div
              v-if="errorEntrega"
              class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600"
            >
              {{ errorEntrega }}
            </div>
            <div class="mb-3 flex items-center justify-between gap-4">
              <div>
                <h4 class="text-sm font-semibold text-slate-900">
                  Entregas y notas
                </h4>
                <p class="text-xs text-slate-500">
                  Controla fechas, estado y nota final ponderada
                </p>
              </div>
              <div
                class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
              >
                Nota final:
                <span class="font-semibold">{{
                  practicas.practicaActual?.resumen_entregas
                    ?.nota_final_ponderada ?? "—"
                }}</span>
              </div>
            </div>
            <div class="grid gap-3 md:grid-cols-3">
              <div
                v-for="entrega in practicas.practicaActual?.entregas || []"
                :key="entrega.tipo"
                class="rounded-lg border border-slate-200 p-3"
              >
                <div class="flex items-center justify-between gap-2">
                  <p class="text-sm font-semibold text-slate-900">
                    {{ etiquetaEntrega(entrega.tipo) }}
                  </p>
                  <span
                    class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700"
                  >
                    {{ estadoEntrega(entrega) }}
                  </span>
                </div>
                <p class="mt-1 text-xs text-slate-500">
                  Fecha límite: {{ entrega.fecha_limite || "—" }}
                </p>
                <div class="mt-3 space-y-2">
                  <label class="flex items-center gap-2 text-xs text-slate-600">
                    <input
                      type="checkbox"
                      class="h-4 w-4 rounded border-slate-300"
                      :checked="Number(entrega.entregado) === 1"
                      @change="
                        entrega.entregado = $event.target.checked ? 1 : 0
                      "
                    />
                    Entregado
                  </label>
                  <input
                    v-model="entrega.fecha_entrega"
                    type="date"
                    class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm"
                  />
                  <input
                    v-model="entrega.nota"
                    type="number"
                    step="0.1"
                    min="1"
                    max="7"
                    placeholder="Nota"
                    class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm"
                  />
                  <textarea
                    v-model="entrega.retroalimentacion"
                    rows="2"
                    placeholder="Retroalimentación"
                    class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm"
                  />
                  <button
                    class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-60"
                    :disabled="guardandoEntrega"
                    @click="guardarEntrega(entrega)"
                  >
                    {{ guardandoEntrega ? "Guardando…" : "Guardar entrega" }}
                  </button>
                </div>
              </div>
            </div>
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
