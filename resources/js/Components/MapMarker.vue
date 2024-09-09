<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header>
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600">Map Marker</span>
        </div>
      </template>

      <template #default>
        <div id="map" style="width: 100%; height: 500px;"></div> <!-- Ensure dimensions -->
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import { ref, onBeforeMount } from 'vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
  customer: Object, // Assuming customer has deliveryAddress with lat/lng
  apiKey: String,   // API key passed from the parent component or environment
  showModal: Boolean, // Modal visibility control
});

const map = ref(null);  // Reference to the map object
const center = ref({ lat: 1.3521, lng: 103.8198 });  // Singapore's coordinates
const mapLoaded = ref(false); // Flag to check if the map is loaded

const emit = defineEmits(['modalClose']);

// When the component is mounted, load the Google Maps API asynchronously
onBeforeMount(() => {
  // Check if customer data exists to set the map center
  if (props.customer && props.customer.deliveryAddress) {
    center.value = {
      lat: props.customer.deliveryAddress.latitude,
      lng: props.customer.deliveryAddress.longitude,
    };

    console.log(center.value);
  }

  // Provided snippet to dynamically load Google Maps
  (g => {
    var h, a, k, p = "The Google Maps JavaScript API", c = "google", l = "importLibrary", q = "__ib__", m = document, b = window;
    b = b[c] || (b[c] = {});
    var d = b.maps || (b.maps = {}), r = new Set, e = new URLSearchParams, u = () => h || (h = new Promise(async (f, n) => {
      await (a = m.createElement("script"));
      e.set("libraries", [...r] + "");
      for (k in g) e.set(k.replace(/[A-Z]/g, t => "_" + t[0].toLowerCase()), g[k]);
      e.set("callback", c + ".maps." + q);
      a.src = `https://maps.${c}apis.com/maps/api/js?` + e;
      d[q] = f;
      a.onerror = () => h = n(Error(p + " could not load."));
      a.nonce = m.querySelector("script[nonce]")?.nonce || "";
      m.head.append(a);
    }));
    d[l] ? console.warn(p + " only loads once. Ignoring:", g) : d[l] = (f, ...n) => r.add(f) && u().then(() => d[l](f, ...n));
  })({
    key: props.apiKey,
    v: "weekly",
  });

  // Initialize the map after the API is loaded
  window.google = window.google || {};
  window.google.maps = window.google.maps || {};
  window.google.maps.__ib__ = () => {
    const { Map } = google.maps;
    const { AdvancedMarkerElement } = google.maps.marker;

    // Initialize the map
    map.value = new Map(document.getElementById("map"), {
      zoom: 13,
      center: center.value,
    });

    // Add an Advanced Marker
    const marker = new AdvancedMarkerElement({
      map: map.value,
      position: center.value,
      title: "Customer Location",
    });

    mapLoaded.value = true; // Set map loaded flag
  };
});
</script>
