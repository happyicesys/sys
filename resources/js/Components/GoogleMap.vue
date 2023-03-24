<template>
  <GoogleMap api-key="apiKey" style="width: 100%; height: 500px" :center="center" :zoom="15">
    <Marker :options="{ position: center }" />
  </GoogleMap>
</template>

<script setup>
  import { ref } from "vue";
  import { GoogleMap, Marker } from "vue3-google-map";

  const center = ref({ lat: 1.3521, lng: 103.8198 });
  const apiKey = ref('AIzaSyDBvrDb4Nvv4zggmkmxQ5vGzafgqFVoqTU')
</script>