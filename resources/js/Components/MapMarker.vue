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
import {MapPinIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
  customers: Array,  // Array of customer objects
  apiKey: String,    // API key passed from the parent component or environment
  showModal: Boolean, // Modal visibility control
});

const emit = defineEmits(['modalClose']);

let map, directionsService, directionsRenderer;

// Function to show directions on the map
const showDirections = () => {
  if (!directionsService || !directionsRenderer) return;

  let request = {
    travelMode: google.maps.TravelMode.DRIVING,
    waypoints: [],
    origin: null,
    destination: null,
  };

  let firstMarker = true;

  props.customers.forEach((customer, index) => {
    const lat = parseFloat(customer.deliveryAddress.latitude);
    const lng = parseFloat(customer.deliveryAddress.longitude);

    if (!isNaN(lat) && !isNaN(lng)) {
      const pos = { lat, lng };
      const markerPosition = new google.maps.LatLng(lat, lng);

      // Set origin, waypoints, and destination
      if (firstMarker) {
        request.origin = markerPosition;
        firstMarker = false;
      } else if (index === props.customers.length - 1) {
        request.destination = markerPosition;
      } else {
        request.waypoints.push({
          location: markerPosition,
          stopover: true,
        });
      }
    }
  });

  if (request.origin && request.destination) {
    directionsService.route(request, (result, status) => {
      if (status === google.maps.DirectionsStatus.OK) {
        directionsRenderer.setDirections(result);
      }
    });
  }
};

onMounted(() => {
  // Default map position (Singapore) as fallback
  let defaultPos = { lat: 1.3521, lng: 103.8198 };

  // Load the Google Maps JavaScript API dynamically
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

    // Initialize DirectionsService and DirectionsRenderer directly from google.maps
    directionsService = new google.maps.DirectionsService();
    directionsRenderer = new google.maps.DirectionsRenderer({ suppressMarkers: true });

    let latSum = 0;
    let lngSum = 0;
    let validCoordsCount = 0;

    // Validate and sum lat/lng for multiple customers
    props.customers.forEach(customer => {
      const lat = parseFloat(customer.deliveryAddress.latitude);
      const lng = parseFloat(customer.deliveryAddress.longitude);

      if (!isNaN(lat) && !isNaN(lng)) {
        latSum += lat;
        lngSum += lng;
        validCoordsCount += 1;
      }
    });

    // If there are valid coordinates, calculate the average
    if (validCoordsCount > 0) {
      const avgLat = latSum / validCoordsCount;
      const avgLng = lngSum / validCoordsCount;
      defaultPos = { lat: avgLat, lng: avgLng };
    } else if (props.customers.length === 1) {
      const customer = props.customers[0];
      const lat = parseFloat(customer.deliveryAddress.latitude);
      const lng = parseFloat(customer.deliveryAddress.longitude);

      if (!isNaN(lat) && !isNaN(lng)) {
        defaultPos = { lat, lng };
      }
    }

    // Initialize the map with calculated or default center
    map = new Map(document.getElementById("map"), {
      zoom: 12,
      center: defaultPos,
      mapId: "MAP_ID", // Optional custom map ID
    });

    directionsRenderer.setMap(map);

    // Loop through the customers array and create markers
    props.customers.forEach((customer) => {
      const lat = parseFloat(customer.deliveryAddress.latitude);
      const lng = parseFloat(customer.deliveryAddress.longitude);

      // Only create marker if coordinates are valid
      if (!isNaN(lat) && !isNaN(lng)) {
        const pos = { lat, lng };

        const marker = new google.maps.Marker({
          position: pos,
          map: map,
          label: {
            text: String(customer.sequence),
            color: "#ffffff",
            fontSize: "14px",
            fontWeight: "bold",
          },
        });

        const googleMapsLink = `https://www.google.com/maps/search/?api=1&query=${pos.lat},${pos.lng}`;

        const infoWindow = new google.maps.InfoWindow({
          content: `<div>
              <span class="font-bold">${customer.vend.code}</span><br>
              <span class="font-medium">${customer.name}</span><br>
              <p>${customer.deliveryAddress.full_address ? customer.deliveryAddress.full_address : customer.deliveryAddress.postcode}</p>
              <a href="${googleMapsLink}" target="_blank" class="text-blue-600 font-medium underline">View on Google Maps</a>
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
    });
  }

  initMap();
});
</script>
