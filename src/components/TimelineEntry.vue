<template>
  <div class="card shadow-sm p-3 mb-4" :class="category">
    <h5>
      <i :class="icon + ' me-2'"></i>
      {{ title }}
    </h5>
    <ul class="mb-0">
      <li v-for="(item, index) in details" :key="index">{{ item }}</li>
    </ul>
  </div>
</template>

<script>
export default {
  name: 'TimelineEntry',
  props: {
    title: String,
    icon: String,
    category: String,
    details: Array
  }
}
</script>

<style scoped>
.job h5 {
  color: #1fb0a8;
}

.edu h5 {
  color: #f39c12;
}

.training h5 {
  color: #9b59b6;
}

ul {
  list-style: none;
  padding-left: 0;
}

ul li::before {
  content: "• ";
  color: var(--secondary-color, #1fb0a8);
  font-weight: bold;
}
</style>