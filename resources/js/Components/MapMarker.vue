<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header>
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600">Maps</span>
        </div>
      </template>

      <template #default>
        <div>
          <div class="flex justify-end p-2" v-if="props.customers.length > 1">
            <!-- Button to show directions -->
            <Button
              type="button"
              class="bg-sky-300 hover:bg-sky-400 px-3 py-2 text-xs text-sky-800 flex space-x-1 w-fit"
              @click="showDirections"
              v-if="isShowDirectionButton"
            >
              <MapPinIcon class="h-4 w-4" aria-hidden="true" />
              <span>Show Directions</span>
            </Button>
          </div>
          <div id="map" style="width: 100%; height: 500px;"></div>
        </div>
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import { MapPinIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
  customers: Array,  // Array of customer objects
  apiKey: String,    // API key passed from the parent component or environment
  showModal: Boolean, // Modal visibility control
  isShowDirectionButton: {
    type: [Boolean, String],
    default: false,
  }, // Control visibility of direction button
});

const emit = defineEmits(['modalClose']);

let map, directionsService;

// Function to show directions on the map with batching
const showDirections = () => {
  if (!directionsService) return;

  const batchSize = 23; // Max 23 waypoints + origin and destination = 25
  let customersWithValidAddresses = props.customers.filter(customer => customer.deliveryAddress);
  let totalBatches = Math.ceil(customersWithValidAddresses.length / batchSize);

  // Initialize request batches
  const requests = [];

  for (let i = 0; i < totalBatches; i++) {
    const batchStartIndex = i * batchSize;
    const batchCustomers = customersWithValidAddresses.slice(batchStartIndex, batchStartIndex + batchSize);

    let request = {
      travelMode: google.maps.TravelMode.DRIVING,
      waypoints: [],
      origin: null,
      destination: null,
    };

    batchCustomers.forEach((customer, index) => {
      const lat = parseFloat(customer.deliveryAddress.latitude);
      const lng = parseFloat(customer.deliveryAddress.longitude);

      // Check for valid lat/lng
      if (isNaN(lat) || isNaN(lng)) return; // Skip customer if lat/lng is invalid

      const markerPosition = new google.maps.LatLng(lat, lng);

      // Set the first customer as the origin
      if (index === 0) {
        request.origin = markerPosition;
      }
      // Set the last customer as the destination
      else if (index === batchCustomers.length - 1) {
        request.destination = markerPosition;
      }
      // Add all other customers as waypoints
      else {
        request.waypoints.push({
          location: markerPosition,
          stopover: true,
        });
      }
    });

    // Add the request if both origin and destination are valid
    if (request.origin && request.destination) {
      requests.push(request);
    }
  }

  // Function to handle directions rendering for each batch
  const renderBatchDirections = (index) => {
    if (index >= requests.length) return; // No more batches to process

    const currentRequest = requests[index];
    const batchRenderer = new google.maps.DirectionsRenderer({ suppressMarkers: true });
    batchRenderer.setMap(map);

    directionsService.route(currentRequest, (result, status) => {
      if (status === google.maps.DirectionsStatus.OK) {
        batchRenderer.setDirections(result);

        // Call the next batch after a successful request
        renderBatchDirections(index + 1);
      } else {
        console.error('Directions request failed due to ' + status);
      }
    });
  };

  // Start rendering directions for the first batch
  renderBatchDirections(0);
};

// Function to add markers during map initialization
const addMarkers = () => {
  props.customers.forEach((customer) => {
    if (customer.deliveryAddress) {
      const lat = parseFloat(customer.deliveryAddress.latitude);
      const lng = parseFloat(customer.deliveryAddress.longitude);

      if (!isNaN(lat) && !isNaN(lng)) {
        const position = new google.maps.LatLng(lat, lng);

        const marker = new google.maps.Marker({
          position,
          map,
          label: {
            text: String(customer.sequence), // Using custom sequence
            color: "#ffffff",
            fontSize: "14px",
            fontWeight: "bold",
          },
        });

        const infoWindow = new google.maps.InfoWindow({
          content: `<div>
            <span class="font-bold">${customer.vend ? customer.vend.code : ''}</span><br>
            <span class="font-medium">${customer.name}</span><br>
            <p>${customer.deliveryAddress.full_address ? customer.deliveryAddress.full_address : customer.deliveryAddress.postcode}</p>
            <a href="https://www.google.com/maps/search/?api=1&query=${position.lat()},${position.lng()}" target="_blank" class="text-blue-600 font-medium underline">View on Google Maps</a>
          </div>`,
        });

        marker.addListener('click', () => {
          infoWindow.open({
            anchor: marker,
            map,
            shouldFocus: false,
          });
        });
      }
    }
  });
};

onMounted(() => {
  // Default map position (Singapore) as fallback
  let defaultPos = { lat: 1.3521, lng: 103.8198 };

  // Load Google Maps API dynamically
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

  async function initMap() {
    const { Map } = await google.maps.importLibrary("maps");

    // Initialize DirectionsService and map
    directionsService = new google.maps.DirectionsService();

    let latSum = 0;
    let lngSum = 0;
    let validCoordsCount = 0;

    // Calculate center based on customer locations
    props.customers.forEach((customer) => {
      if (customer.deliveryAddress) {
        const lat = parseFloat(customer.deliveryAddress.latitude);
        const lng = parseFloat(customer.deliveryAddress.longitude);

        if (!isNaN(lat) && !isNaN(lng)) {
          latSum += lat;
          lngSum += lng;
          validCoordsCount += 1;
        }
      }
    });

    // Set default or calculated map position
    if (validCoordsCount > 0) {
      defaultPos = {
        lat: latSum / validCoordsCount,
        lng: lngSum / validCoordsCount,
      };
    }

    // Initialize the map
    map = new Map(document.getElementById("map"), {
      zoom: 12,
      center: defaultPos,
      mapId: "MAP_ID", // Optional custom map ID
    });

    // Add markers immediately when the map is initialized
    addMarkers();
  }

  initMap();
});
</script>
