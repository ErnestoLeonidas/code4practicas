<script setup>
  import { computed, onMounted, ref } from "vue";
  import { RouterLink } from "vue-router";
  import { api } from "../services/api";
  import { useAuthStore } from "../stores/auth";

  const auth = useAuthStore();
  const cargando = ref(false);
  const error = ref("");
  const dashboard = ref(null);

  const totales = computed(() => dashboard.value?.totales_por_estado || {});
  const practicasEnRiesgo = computed(
    () => dashboard.value?.practicas_en_riesgo || [],
  );
  const entregasProximas = computed(
    () => dashboard.value?.entregas_proximas || [],
  );
  const entregasAtrasadas = computed(
    () => dashboard.value?.entregas_atrasadas || [],
  );
  const porCarrera = computed(
    () => dashboard.value?.distribucion_por_carrera || [],
  );
  const porSemestre = computed(
    () => dashboard.value?.distribucion_por_semestre || [],
  );
  const totalActivas = computed(
    () =>
      Number(totales.value.pendiente || 0) +
      Number(totales.value.en_curso || 0) +
      Number(totales.value.avance_1 || 0) +
      Number(totales.value.avance_2 || 0) +
      Number(totales.value.informe_final || 0),
  );

  const requiereAtencion = computed(() => {
    const riesgo = practicasEnRiesgo.value.map((item) => ({
      key: `riesgo-${item.id}-${item.semana}`,
      tipo: "Riesgo alto",
      detalle: `${item.estudiante_nombre} ${item.estudiante_apellido}`,
      meta: `${item.empresa_nombre} · Semana ${item.semana} · ${item.fecha_registro}`,
      tono: "amber",
    }));

    const atraso = entregasAtrasadas.value.map((item) => ({
      key: `atraso-${item.id}`,
      tipo: "Entrega atrasada",
      detalle: `${formatearTipoEntrega(item.tipo)} · ${item.estudiante_nombre} ${item.estudiante_apellido}`,
      meta: `${item.empresa_nombre} · Límite ${item.fecha_limite}`,
      tono: "rose",
    }));

    return [...atraso, ...riesgo].slice(0, 8);
  });

  function formatearTipoEntrega(tipo) {
    const mapa = {
      avance_1: "Avance 1",
      avance_2: "Avance 2",
      informe_final: "Informe final",
    };
    return mapa[tipo] || tipo;
  }

  function claseAtencion(tono) {
    if (tono === "rose") return "border-rose-200 bg-rose-50";
    return "border-amber-200 bg-amber-50";
  }

  function exportar(path) {
    window.open(`/api${path}`, "_blank");
  }

  async function cargarDashboard() {
    cargando.value = true;
    error.value = "";

    try {
      dashboard.value = await api.get("/dashboard");
    } catch (err) {
      error.value = err.message || "No se pudo cargar el tablero.";
    } finally {
      cargando.value = false;
    }
  }

  onMounted(() => {
    cargarDashboard();
  });
</script>

<template>
  <div class="space-y-6">
    <section
      class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm"
    >
      <div
        class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
      >
        <div>
          <p
            class="text-sm font-medium uppercase tracking-[0.18em] text-slate-400"
          >
            Dashboard
          </p>
          <h2 class="mt-2 text-2xl font-semibold text-slate-900">
            Hola, {{ auth.nombreCompleto }}
          </h2>
          <p class="mt-2 max-w-2xl text-sm text-slate-500">
            Resumen operativo de prácticas, entregas y alertas para seguimiento
            docente.
          </p>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-60"
            :disabled="cargando"
            @click="cargarDashboard"
          >
            {{ cargando ? "Actualizando…" : "Actualizar tablero" }}
          </button>
          <button
            class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700"
            @click="exportar('/export/estudiantes')"
          >
            Exportar estudiantes
          </button>
          <button
            class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700"
            @click="exportar('/export/practicas')"
          >
            Exportar prácticas
          </button>
        </div>
      </div>
    </section>

    <div
      v-if="error"
      class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-700"
    >
      <p class="font-medium">No se pudo cargar el dashboard.</p>
      <p class="mt-1 text-sm">{{ error }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-sm text-slate-500">Prácticas activas</p>
        <p class="mt-2 text-3xl font-semibold text-slate-900">
          {{ cargando && !dashboard ? "..." : totalActivas }}
        </p>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-sm text-slate-500">Pendientes</p>
        <p class="mt-2 text-3xl font-semibold text-slate-900">
          {{ cargando && !dashboard ? "..." : totales.pendiente || 0 }}
        </p>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-sm text-slate-500">En informe final</p>
        <p class="mt-2 text-3xl font-semibold text-slate-900">
          {{ cargando && !dashboard ? "..." : totales.informe_final || 0 }}
        </p>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-sm text-slate-500">Aprobadas</p>
        <p class="mt-2 text-3xl font-semibold text-slate-900">
          {{ cargando && !dashboard ? "..." : totales.aprobada || 0 }}
        </p>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
      <section class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div class="flex items-center justify-between gap-4">
            <div>
              <h3 class="text-lg font-semibold text-slate-900">
                Requiere atención
              </h3>
              <p class="mt-1 text-sm text-slate-500">
                Combina riesgo alto reciente y entregas atrasadas.
              </p>
            </div>
            <span
              class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700"
            >
              {{ requiereAtencion.length }} items
            </span>
          </div>

          <div
            v-if="cargando && !dashboard"
            class="mt-4 text-sm text-slate-500"
          >
            Cargando alertas...
          </div>
          <div
            v-else-if="requiereAtencion.length === 0"
            class="mt-4 rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-500"
          >
            No hay alertas críticas por ahora.
          </div>
          <ul v-else class="mt-4 space-y-3">
            <li
              v-for="item in requiereAtencion"
              :key="item.key"
              class="rounded-xl border p-4"
              :class="claseAtencion(item.tono)"
            >
              <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-slate-900">
                  {{ item.tipo }}
                </p>
                <RouterLink
                  to="/practicas"
                  class="text-xs font-medium text-slate-700 hover:underline"
                >
                  Ver prácticas
                </RouterLink>
              </div>
              <p class="mt-1 text-sm text-slate-800">{{ item.detalle }}</p>
              <p class="mt-1 text-xs text-slate-600">{{ item.meta }}</p>
            </li>
          </ul>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
          >
            <h3 class="text-lg font-semibold text-slate-900">
              Entregas próximas
            </h3>
            <p class="mt-1 text-sm text-slate-500">
              Vencen en los próximos 7 días.
            </p>
            <p class="mt-4 text-3xl font-semibold text-slate-900">
              {{ cargando && !dashboard ? "..." : entregasProximas.length }}
            </p>
          </div>
          <div
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
          >
            <h3 class="text-lg font-semibold text-slate-900">
              Entregas atrasadas
            </h3>
            <p class="mt-1 text-sm text-slate-500">
              Pendientes fuera de plazo.
            </p>
            <p class="mt-4 text-3xl font-semibold text-slate-900">
              {{ cargando && !dashboard ? "..." : entregasAtrasadas.length }}
            </p>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div class="flex items-center justify-between gap-4">
            <div>
              <h3 class="text-lg font-semibold text-slate-900">
                Accesos rápidos
              </h3>
              <p class="mt-1 text-sm text-slate-500">
                Atajos a los flujos más usados.
              </p>
            </div>
          </div>

          <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <RouterLink
              to="/practicas"
              class="rounded-xl border border-slate-200 p-4 text-sm hover:bg-slate-50"
            >
              <p class="font-semibold text-slate-900">Prácticas</p>
              <p class="mt-1 text-slate-500">Estado, seguimiento y entregas.</p>
            </RouterLink>
            <RouterLink
              to="/estudiantes"
              class="rounded-xl border border-slate-200 p-4 text-sm hover:bg-slate-50"
            >
              <p class="font-semibold text-slate-900">Estudiantes</p>
              <p class="mt-1 text-slate-500">Asignación y datos base.</p>
            </RouterLink>
            <RouterLink
              to="/empresas"
              class="rounded-xl border border-slate-200 p-4 text-sm hover:bg-slate-50"
            >
              <p class="font-semibold text-slate-900">Empresas</p>
              <p class="mt-1 text-slate-500">Supervisores y convenios.</p>
            </RouterLink>
          </div>
        </div>
      </section>

      <section class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h3 class="text-lg font-semibold text-slate-900">
            Distribución por carrera
          </h3>
          <div
            v-if="cargando && !dashboard"
            class="mt-4 text-sm text-slate-500"
          >
            Cargando distribución...
          </div>
          <div
            v-else-if="porCarrera.length === 0"
            class="mt-4 text-sm text-slate-500"
          >
            No hay prácticas registradas todavía.
          </div>
          <ul v-else class="mt-4 space-y-2 text-sm">
            <li
              v-for="item in porCarrera"
              :key="item.carrera"
              class="flex items-center justify-between gap-4 rounded-lg bg-slate-50 px-3 py-2"
            >
              <span class="text-slate-700">{{ item.carrera }}</span>
              <span class="font-semibold text-slate-900">{{ item.total }}</span>
            </li>
          </ul>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h3 class="text-lg font-semibold text-slate-900">
            Distribución por semestre
          </h3>
          <div
            v-if="cargando && !dashboard"
            class="mt-4 text-sm text-slate-500"
          >
            Cargando distribución...
          </div>
          <div
            v-else-if="porSemestre.length === 0"
            class="mt-4 text-sm text-slate-500"
          >
            No hay prácticas registradas todavía.
          </div>
          <ul v-else class="mt-4 space-y-2 text-sm">
            <li
              v-for="item in porSemestre"
              :key="item.semestre"
              class="flex items-center justify-between gap-4 rounded-lg bg-slate-50 px-3 py-2"
            >
              <span class="text-slate-700">{{ item.semestre }}</span>
              <span class="font-semibold text-slate-900">{{ item.total }}</span>
            </li>
          </ul>
        </div>
      </section>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <section
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
      >
        <div class="flex items-center justify-between gap-4">
          <div>
            <h3 class="text-lg font-semibold text-slate-900">
              Prácticas en riesgo
            </h3>
            <p class="mt-1 text-sm text-slate-500">
              Semanas rojas registradas recientemente.
            </p>
          </div>
        </div>

        <div v-if="cargando && !dashboard" class="mt-4 text-sm text-slate-500">
          Cargando prácticas en riesgo...
        </div>
        <div
          v-else-if="practicasEnRiesgo.length === 0"
          class="mt-4 text-sm text-slate-500"
        >
          No hay prácticas en riesgo recientemente.
        </div>
        <ul v-else class="mt-4 space-y-3">
          <li
            v-for="item in practicasEnRiesgo"
            :key="`${item.id}-${item.semana}`"
            class="rounded-xl border border-amber-200 bg-amber-50 p-4"
          >
            <p class="font-semibold text-slate-900">
              {{ item.estudiante_nombre }} {{ item.estudiante_apellido }}
            </p>
            <p class="mt-1 text-sm text-slate-600">
              {{ item.empresa_nombre }} · {{ item.semestre }}
            </p>
            <p class="mt-2 text-xs text-slate-600">
              Semana {{ item.semana }} · {{ item.porcentaje }}% ·
              {{ item.fecha_registro }}
            </p>
          </li>
        </ul>
      </section>

      <section
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
      >
        <div class="flex items-center justify-between gap-4">
          <div>
            <h3 class="text-lg font-semibold text-slate-900">
              Entregas próximas
            </h3>
            <p class="mt-1 text-sm text-slate-500">
              Detalle de vencimientos cercanos.
            </p>
          </div>
        </div>

        <div v-if="cargando && !dashboard" class="mt-4 text-sm text-slate-500">
          Cargando entregas...
        </div>
        <div
          v-else-if="entregasProximas.length === 0"
          class="mt-4 text-sm text-slate-500"
        >
          Ninguna entrega próxima.
        </div>
        <ul v-else class="mt-4 space-y-3">
          <li
            v-for="item in entregasProximas"
            :key="item.id"
            class="rounded-xl border border-slate-200 p-4"
          >
            <p class="font-semibold text-slate-900">
              {{ formatearTipoEntrega(item.tipo) }} · {{ item.semestre }}
            </p>
            <p class="mt-1 text-sm text-slate-600">
              {{ item.estudiante_nombre }} {{ item.estudiante_apellido }} —
              {{ item.empresa_nombre }}
            </p>
            <p class="mt-2 text-xs text-slate-500">
              Límite: {{ item.fecha_limite }}
            </p>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>
