<template>
  <Head title="VM Edit" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex flex-col md:flex-row space-x-2">
        <span class="text-gray-600" v-if="opsJob && opsJob.id">
          Route Planning
        </span>
      </div>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5">
            <form @submit.prevent="submit" id="submit">
              <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">

                <!-- Date and Delivery Info -->
                <div class="sm:col-span-3">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Date </label>
                  <div class="mt-1">
                    <input
                      type="text"
                      class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                      :value="opsJob ? opsJob.date_formatted : ''"
                      disabled
                    />
                  </div>
                </div>
                <div class="sm:col-span-3">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Delivery By </label>
                  <div class="mt-1">
                    <input
                      type="text"
                      class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                      :value="opsJob && opsJob.deliveredBy ? opsJob.deliveredBy.name : ''"
                      disabled
                    />
                  </div>
                </div>

                <!-- Total Job(s) Info -->
                <div class="sm:col-span-5">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Total Job(s) </label>
                  <div class="mt-1">
                    <input
                      type="text"
                      class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                      :value="opsJob.opsJobItems ? opsJob.opsJobItems.filter((opsJobItem) => { return !opsJobItem.isOrigin && !opsJobItem.isDestination}).length : 0"
                      disabled
                    />
                  </div>
                </div>

                <!-- Map Section -->
                <div id="map" class="sm:col-span-6 mb-3" style="width: 100%; height: 600px;"></div>

                <!-- Origin and Destination Select -->
                <div class="sm:col-span-3">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                    Origin
                  </label>
                  <MultiSelect
                    v-model="form.origin_address_id"
                    :options="originAddressOptions"
                    trackBy="id"
                    valueProp="id"
                    label="full_address"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                    ref="multiselect"
                  />
                </div>
                <div class="sm:col-span-3">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                    Destination
                  </label>
                  <MultiSelect
                    v-model="form.destination_address_id"
                    :options="destinationAddressOptions"
                    trackBy="id"
                    valueProp="id"
                    label="full_address"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                    ref="multiselect"
                  />
                </div>

                <!-- Buttons to Set Origin & Destination and Regenerate Route -->
                <div class="sm:col-span-6 flex justify-between mt-4">
                  <Link :href="'/ops-jobs/' + opsJob.id + '/edit'">
                    <Button
                      type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1 "
                    >
                      <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                      <span>
                        Back
                      </span>
                    </Button>
                  </Link>
                  <div class="flex space-x-1">
                    <button
                      type="button"
                      @click.prevent="setOriginDestination"
                      class="bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded text-sm"
                    >
                      <div class="flex space-x-1 items-center">
                        <ArrowRightCircleIcon class="h-4 w-4" />
                        <span>
                          Generate Route
                        </span>
                      </div>
                    </button>
                    <button
                      type="button"
                      @click.prevent="applySequenceJobs"
                      class="bg-yellow-500 hover:bg-yellow-600 text-gray-800 font-medium py-2 px-4 rounded text-sm"
                      v-if="isSequenceGenerated"
                    >
                      <div class="flex space-x-1 items-center">
                        <BarsArrowDownIcon class="h-4 w-4" />
                        <span>
                          Apply Sequence to Job(s)
                        </span>
                      </div>
                    </button>
                  </div>
                </div>

                <!-- Generated Sequence Header -->
                <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
                  <div class="relative">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                      <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center">
                      <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Generated Sequence </span>
                    </div>
                  </div>
                </div>

                <!-- Job Sequence Table -->
                <div class="sm:col-span-6 flex flex-col">
                  <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
                    <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
                      <div class="overflow-scroll max-h-[600px] md:max-h-[800px] shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-300">
                          <thead class="bg-gray-50">
                            <tr>
                              <TableHead> <span> Prev Sequence </span> </TableHead>
                              <TableHead> <span> Generated Sequence </span> </TableHead>
                              <TableHead>
                                <div class="flex flex-col space-y-2">
                                  <span> Machine ID </span>
                                  <span> Job ID# </span>
                                  <span> Remarks </span>
                                </div>
                              </TableHead>
                              <TableHead>
                                <div class="flex flex-col space-y-2">
                                  <span> Customer (Prefix) </span>
                                  <span> Ops Note </span>
                                </div>
                              </TableHead>
                              <TableHead> Address </TableHead>
                              <TableHead> Action </TableHead>
                            </tr>
                          </thead>
                          <tbody class="bg-white">
                            <tr v-for="(opsJobItem, opsJobItemIndex) in opsJob.opsJobItems" :key="opsJobItem.id" :class="opsJobItemIndex % 2 === 0 ? undefined : 'bg-gray-100'">
                              <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                                {{ opsJobItem.sequence }}
                              </td>
                              <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                                <span v-if="isSequenceGenerated">
                                  {{ opsJobItemIndex }}
                                </span>
                              </td>
                              <td class="whitespace-pre-line py-2 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                                <div class="flex flex-col space-y-2 max-w-24">
                                  <Link :href="'/vends/customers?codes=' + opsJobItem.vend?.code" class="text-blue-700">
                                    <span> {{ opsJobItem.vend?.code }} </span>
                                  </Link>
                                  <div>
                                    <Link :href="'/ops-jobs/items/' + opsJobItem.id + '/edit'">
                                      <Button
                                        class="bg-indigo-400 hover:bg-indigo-500 text-white text-xs font-medium"
                                        v-if="permissions.includes('update operations') && opsJobItem.ref_id"
                                      >
                                        {{ opsJobItem.ref_id }}
                                      </Button>
                                    </Link>
                                  </div>
                                  <div class="text-left text-red-800"> {{ opsJobItem.remarks }} </div>
                                </div>
                              </td>
                              <td class="whitespace py-2 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                                <div class="flex flex-col space-y-1 max-w-40 md:max-w-72">
                                  <span>
                                    <span v-if="opsJobItem.customer && opsJobItem.customer.person_id">
                                      {{ opsJobItem.customer.virtual_customer_code }}
                                      ({{ opsJobItem.vend && opsJobItem.vend.vendPrefix ? opsJobItem.vend.vendPrefix.name : '' }})
                                      - {{ opsJobItem.customer.name }}
                                    </span>
                                    <span v-else>
                                      <span v-if="opsJobItem.customer && opsJobItem.customer.code"> {{ opsJobItem.customer.code }} <br> </span>
                                      {{ opsJobItem.customer && opsJobItem.customer.name ? opsJobItem.customer.name : ''}}
                                    </span>
                                  </span>
                                  <span v-if="opsJobItem.customer && opsJobItem.customer.deliveryAddress">
                                    <div class="flex space-x-2 items-center font-medium text-xs">
                                      <span class="flex space-x-1 items-center">
                                        <a
                                          :href="opsJobItem.customer.deliveryAddress.map_url ||
                                            ('https://www.google.com/maps/search/?api=1&query='
                                              + opsJobItem.customer.deliveryAddress.latitude + ',' + opsJobItem.customer.deliveryAddress.longitude)"
                                          target="_blank"
                                          rel="noopener noreferrer"
                                          class="bg-green-300 hover:bg-green-400 px-3 py-2 text-xs text-green-800 flex space-x-1 w-fit rounded shadow font-bold"
                                        >
                                          GPS
                                        </a>
                                      </span>
                                      <span> {{ opsJobItem.customer.deliveryAddress.postcode }} </span>
                                    </div>
                                  </span>
                                  <span class="text-left font-medium bg-gray-200 py-1 px-1 rounded" v-if="opsJobItem.customer && opsJobItem.customer.ops_note">
                                    {{ opsJobItem.customer.ops_note }}
                                  </span>
                                </div>
                              </td>
                              <td class="whitespace-pre-line py-2 px-1 text-sm text-left">
                                <div class="flex flex-col space-y-2 break-words max-w-32 md:max-w-72">
                                  <span>
                                    <a :href="opsJobItem.customer.deliveryAddress.map_url" class="text-blue-700" target="_blank"> {{ opsJobItem.customer.deliveryAddress.full_address }} </a>
                                  </span>
                                  <span v-if="!opsJobItem.customer.deliveryAddress.full_address">
                                    {{ opsJobItem.customer.deliveryAddress.postcode }}
                                  </span>
                                </div>
                              </td>
                              <td class="whitespace-nowrap py-2 px-1 text-sm text-center"></td>
                            </tr>

                            <!-- Fallback for no records -->
                            <tr v-if="!opsJob.opsJobItems || !opsJob.opsJobItems.length">
                              <td colspan="11" class="whitespace-nowrap py-2 text-sm font-medium text-black text-center">
                                No Records Found
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>

              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import TableHead from '@/Components/TableHead.vue'; // Retained TableHead component
import { ArrowUturnLeftIcon, ArrowRightCircleIcon, BarsArrowDownIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
  destinationAddresses: [Array, Object],
  mapApiKey: String,
  operatorsWithAddress: [Array, Object],
  originAddresses: [Array, Object],
  opsJob: Object,
});

const emit = defineEmits(['modalClose']);

const destinationAddressOptions = ref([]);
const form = ref(useForm(getDefaultForm()));
const isSequenceGenerated = ref(false);
const operatorsWithAddress = ref([]);
const opsJob = ref(props.opsJob?.data || {});
const originAddressOptions = ref([]);
const permissions = usePage().props.auth.permissions;
const toast = useToast();
let map, directionsService;
let defaultPos = { lat: 1.3521, lng: 103.8198 };
let markers = []; // Array to store map markers
let renderers = []; // Array to store all DirectionsRenderer instances

onMounted(() => {
  destinationAddressOptions.value = props.destinationAddresses?.data?.map(address => ({
    id: address.id,
    name: address.name,
    full_address: '(' + address.name + ') ' + address.full_address,
    latitude: address.latitude,
    longitude: address.longitude,
  })) || [];
  originAddressOptions.value = props.originAddresses?.data?.map(address => ({
    id: address.id,
    name: address.name,
    full_address: '(' + address.name + ') ' + address.full_address,
    latitude: address.latitude,
    longitude: address.longitude,
  })) || [];
  operatorsWithAddress.value = props.operatorsWithAddress?.data?.map(operator => ({
    id: operator.id,
    name: operator.name,
    address: operator.address.full_address,
    value: '(' + operator.name + ')' + ' - ' + operator.address.full_address,
  })) || [];

  // Initialize Google Maps
  loadGoogleMapsApi();
});

// Function to clear the existing route and markers
function clearMarkers() {
  markers.forEach(marker => marker.setMap(null)); // Clear markers
  markers = []; // Reset marker array
}

function clearRoute() {
  renderers.forEach(renderer => renderer.setMap(null)); // Clear all existing routes
  renderers = []; // Reset the renderers array
}

const regenerateRoute = () => {
  clearRoute(); // Clear the existing route
  showDirections(); // Call the function to regenerate the directions
};

function getDefaultForm() {
  return {
    destination_address_id: '',
    origin_address_id: '',
  };
}

// Google Maps API loading
function loadGoogleMapsApi() {
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
    key: props.mapApiKey,
    v: "weekly",
  });

  initMap();
}

async function initMap() {
  const { Map, DirectionsService, DirectionsRenderer } = await google.maps.importLibrary("maps");
  directionsService = new google.maps.DirectionsService();

  let latSum = 0;
  let lngSum = 0;
  let validCoordsCount = 0;

  // Calculate center based on customer locations
  if (opsJob.value.opsJobItems) {
    opsJob.value.opsJobItems.forEach((jobItem) => {
      if (jobItem.customer && jobItem.customer.deliveryAddress) {
        const lat = parseFloat(jobItem.customer.deliveryAddress.latitude);
        const lng = parseFloat(jobItem.customer.deliveryAddress.longitude);

        if (!isNaN(lat) && !isNaN(lng)) {
          latSum += lat;
          lngSum += lng;
          validCoordsCount += 1;
        }
      }
    });
  }

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

  addMarkers(); // Initially add markers
}

function addMarkers() {
  clearMarkers(); // Clear existing markers before adding new ones

  if (opsJob.value.opsJobItems) {
    opsJob.value.opsJobItems.forEach((jobItem, index) => {
      if (jobItem.customer && jobItem.customer.deliveryAddress) {
        const lat = parseFloat(jobItem.customer.deliveryAddress.latitude);
        const lng = parseFloat(jobItem.customer.deliveryAddress.longitude);

        if (!isNaN(lat) && !isNaN(lng)) {
          const position = new google.maps.LatLng(lat, lng);

          const marker = new google.maps.Marker({
            position,
            map,
            label: {
              text: String(index + 1), // Using custom sequence
              color: "#000000",
              fontSize: "14px",
              fontWeight: "bold",
            },
          });

          const infoWindow = new google.maps.InfoWindow({
            content: `<div>
              <span class="font-bold">${jobItem.vend ? jobItem.vend.code : ''}</span><br>
              <span class="font-medium">${jobItem.customer?.name}</span><br>
              <p>${jobItem.customer.deliveryAddress.full_address ? jobItem.customer.deliveryAddress.full_address : jobItem.customer.deliveryAddress.postcode}</p>
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

          markers.push(marker); // Store the marker
        }
      }
    });
  }
}

function setOriginDestination() {
  if (!form.value.origin_address_id || !form.value.destination_address_id) {
    toast.error('Please select both Origin and Destination addresses.');
    return;
  }

  // Find the selected origin and destination addresses from the options
  const origin = originAddressOptions.value.find(address => address.id === form.value.origin_address_id.id);
  const destination = destinationAddressOptions.value.find(address => address.id === form.value.destination_address_id.id);

  if (!origin || !destination) {
    toast.error('Invalid Origin or Destination address.');
    return;
  }

  // Convert origin and destination to Google Maps LatLng format
  const originLatLng = { lat: parseFloat(origin.latitude), lng: parseFloat(origin.longitude) };
  const destinationLatLng = { lat: parseFloat(destination.latitude), lng: parseFloat(destination.longitude) };

  // Insert the origin and destination into opsJob.opsJobItems as first and last items
  const originItem = {
    customer: {
      name: origin.name,
      deliveryAddress: {
        full_address: origin.full_address,
        latitude: origin.latitude,
        longitude: origin.longitude,
      }
    },
    isOrigin: true, // Mark as origin
  };

  const destinationItem = {
    customer: {
      name: destination.name,
      deliveryAddress: {
        full_address: destination.full_address,
        latitude: destination.latitude,
        longitude: destination.longitude,
      }
    },
    isDestination: true, // Mark as destination
  };

  // First, remove any existing origin and destination in opsJobItems before adding the new ones
  opsJob.value.opsJobItems = opsJob.value.opsJobItems.filter(item => !item.isOrigin && !item.isDestination);

  // Add the origin at the start of opsJobItems and destination at the end
  opsJob.value.opsJobItems.unshift(originItem); // Origin goes to the first position
  opsJob.value.opsJobItems.push(destinationItem); // Destination goes to the last position

  // Now, call showDirections with the formatted origin and destination
  showDirections(originLatLng, destinationLatLng);
}


const showDirections = (originLatLng, destinationLatLng) => {
  isSequenceGenerated.value = true;
  if (!directionsService) return;

  const batchSize = 23; // Max 23 waypoints + origin and destination = 25
  let customersWithValidAddresses = opsJob.value.opsJobItems.filter(opsJobItem => opsJobItem.customer.deliveryAddress && !opsJobItem.isOrigin && !opsJobItem.isDestination);
  let totalBatches = Math.ceil(customersWithValidAddresses.length / batchSize); // Calculate number of batches

  // Clear the existing directions and markers before generating new ones
  clearMarkers();
  clearRoute();

  let optimizedOpsJobItems = [];
  let markerLabelCounter = 1; // Start counting the marker labels from 1

  const requests = [];

  // Prepare batches of customers for the requests
  for (let i = 0; i < totalBatches; i++) {
    const batchStartIndex = i * batchSize;
    const batchCustomers = customersWithValidAddresses.slice(batchStartIndex, batchStartIndex + batchSize);

    let request = {
      travelMode: google.maps.TravelMode.DRIVING, // Travel mode
      waypoints: [],
      origin: originLatLng,
      destination: destinationLatLng,
      optimizeWaypoints: true, // Enable distance-based optimization for waypoints
    };

    // Add customers as waypoints
    batchCustomers.forEach((opsJobItem) => {
      const lat = parseFloat(opsJobItem.customer.deliveryAddress.latitude);
      const lng = parseFloat(opsJobItem.customer.deliveryAddress.longitude);

      if (isNaN(lat) || isNaN(lng)) return;

      const waypointLatLng = { lat, lng };
      request.waypoints.push({
        location: waypointLatLng,
        stopover: true,
      });
    });

    if (request.origin && request.destination) {
      requests.push(request); // Store the request for each batch
    }
  }

  // Function to process each batch sequentially
  const processBatch = (index) => {
    if (index >= requests.length) {
      // Once all batches are processed, update the table
      opsJob.value.opsJobItems = [
        opsJob.value.opsJobItems.find(item => item.isOrigin), // Keep origin
        ...optimizedOpsJobItems, // Add all optimized waypoints from all batches
        opsJob.value.opsJobItems.find(item => item.isDestination), // Keep destination
      ];
      return; // Stop when all batches have been processed
    }

    const currentRequest = requests[index];

    // Create a new DirectionsRenderer for each batch
    const batchRenderer = new google.maps.DirectionsRenderer({
      suppressMarkers: true, // Prevent default markers
      map: map, // Attach it to the map
    });

    directionsService.route(currentRequest, (result, status) => {
      if (status === google.maps.DirectionsStatus.OK) {
        batchRenderer.setDirections(result);
        renderers.push(batchRenderer); // Store the renderer for later use (clear or reset)

        const optimizedOrder = result.routes[0].waypoint_order;
        const optimizedCustomers = optimizedOrder.map(orderIndex => customersWithValidAddresses[index * batchSize + orderIndex]);
        optimizedOpsJobItems = [...optimizedOpsJobItems, ...optimizedCustomers];

        // Add custom markers for origin, destination, and waypoints with continued marker labels
        addCustomMarkers(originLatLng, destinationLatLng, optimizedCustomers, markerLabelCounter);

        // Update the counter for marker labels to continue from the last batch
        markerLabelCounter += optimizedCustomers.length;

        processBatch(index + 1);
      } else {
        console.error('Directions request failed due to ' + status);
      }
    });
  };

  processBatch(0);
};

// Add custom markers for each waypoint, with continued marker labels
function addCustomMarkers(originLatLng, destinationLatLng, waypoints, startLabel) {
  // Add a custom marker for the origin
  const originMarker = new google.maps.Marker({
    position: originLatLng,
    map: map,
    label: {
      text: '0', // Using custom sequence for the origin
      color: "#000000",
      fontSize: "14px",
      fontWeight: "bold",
    },
  });
  markers.push(originMarker);

  // Add custom markers for waypoints
  waypoints.forEach((waypoint, index) => {
    const latLng = {
      lat: parseFloat(waypoint.customer.deliveryAddress.latitude),
      lng: parseFloat(waypoint.customer.deliveryAddress.longitude),
    };

    const marker = new google.maps.Marker({
      position: latLng,
      map: map,
      label: {
        text: String(startLabel + index), // Continue marker label numbering
        color: "#000000",
        fontSize: "14px",
        fontWeight: "bold",
      },
    });

    const infoWindow = new google.maps.InfoWindow({
      content: `<div>
          <span class="font-bold">${waypoint.vend ? waypoint.vend?.code : ''}</span><br>
          <span class="font-medium">${waypoint.customer.name}</span><br>
          <p>${waypoint.customer.deliveryAddress.full_address ? waypoint.customer.deliveryAddress.full_address : waypoint.customer.deliveryAddress.postcode}</p>
          <a href="https://www.google.com/maps/search/?api=1&query=${latLng.lat},${latLng.lng}" target="_blank" class="text-blue-600 font-medium underline">View on Google Maps</a>
        </div>`,
    });

    marker.addListener('click', () => {
      infoWindow.open({
        anchor: marker,
        map,
        shouldFocus: false,
      });
    });

    markers.push(marker); // Store each marker in the array
  });

  // Add a custom marker for the destination
  const destinationMarker = new google.maps.Marker({
    position: destinationLatLng,
    map: map,
    label: {
      text: String(startLabel + waypoints.length), // Continue marker label for the destination
      color: "#000000",
      fontSize: "14px",
      fontWeight: "bold",
    },
  });
  markers.push(destinationMarker);
}
</script>
