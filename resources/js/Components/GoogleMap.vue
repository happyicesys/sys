<template>
  <GoogleMap :api-key="apiKey" style="width: 100%; height: 600px" :center="center" :zoom=zoomLevel>
    <Marker :options="{ position: center }" />
  </GoogleMap>
</template>

<script setup>
  import { ref } from "vue";
  import { GoogleMap, Marker } from "vue3-google-map";

  const center = ref({ lat: 1.3521, lng: 103.8198 });
  const zoomLevel = ref(12);
  const apiKey = ref('AIzaSyDBvrDb4Nvv4zggmkmxQ5vGzafgqFVoqTU')
</script>