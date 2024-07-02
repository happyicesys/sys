<template>
  <div>
    <button :class="buttonClasses" @click="showToast">
      <slot></slot>
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
  color: {
    type: String,
    default: 'bg-blue-500', // Default Tailwind color
  },
  duration: {
    type: Number,
    default: 5000, // Default duration in milliseconds
  },
});

const toast = useToast();

const buttonClasses = computed(() => {
  return `${props.color} text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline`;
});

const showToast = () => {
  toast('This is a custom toast notification!', {
    timeout: props.duration,
    style: {
      backgroundColor: getBackgroundColor(props.color),
      color: 'white',
    },
  });
};

const getBackgroundColor = (colorClass) => {
  switch (colorClass) {
    case 'bg-red-500':
      return '#f56565';
    case 'bg-green-500':
      return '#48bb78';
    case 'bg-blue-500':
      return '#4299e1';
    default:
      return '#4299e1';
  }
};
</script>

<style scoped>
button {
  @apply transition duration-300 ease-in-out transform hover:scale-105;
}
</style>
