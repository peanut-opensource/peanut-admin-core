<script setup lang="ts">
defineProps<{
  eyebrow: string
  title: string
}>()
</script>

<template>
  <main class="auth-page">
    <section
      class="auth-brand"
      aria-label="Peanut Admin"
    >
      <div
        class="brand-mark"
        aria-hidden="true"
      >
        P
      </div>
      <div>
        <strong>Peanut Admin</strong>
        <span>花生后台框架</span>
      </div>
    </section>
    <section class="auth-panel">
      <header class="auth-panel__header">
        <p>{{ eyebrow }}</p>
        <h1>{{ title }}</h1>
      </header>
      <slot />
    </section>
  </main>
</template>
