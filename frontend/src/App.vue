<script setup>
// Layout base. En v0.1.0 se añadirá navbar con sesión y botón de salir.
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-800">
    <header class="bg-white border-b border-slate-200">
      <div class="max-w-5xl mx-auto px-4 py-4">
        <h1 class="text-lg font-semibold text-slate-900">
          Seguimiento de Prácticas Profesionales
        </h1>
        <p class="text-sm text-slate-500">Duoc UC</p>
      </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-8">
      <RouterView />
    </main>
  </div>
</template>
