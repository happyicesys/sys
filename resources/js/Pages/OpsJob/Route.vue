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
                      :value="opsJob.opsJobItems ? opsJob.opsJobItems.filter((opsJobItem) => { return !opsJobItem.is_ops_job_item }).length : 0"
                      disabled
                    />
                  </div>
                </div>

                <!-- Map Section -->
                <div id="map" class="sm:col-span-6 mb-3" style="width: 100%; height: 600px;"></div>

                <!-- Origin Select -->
                <div class="sm:col-span-6">
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

                <!-- Buttons to Set Origin and Regenerate Route -->
                <div class="sm:col-span-6 flex justify-between mt-4">
                  <a :href="'/ops-jobs/' + opsJob.id + '/edit'">
                    <Button
                      type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1 "
                    >
                      <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                      <span>
                        Back
                      </span>
                    </Button>
                  </a>
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
                          Sync Generated Sequence to Current
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
                              <TableHead>
                                <div class="flex flex-col space-x-1">
                                  <SingleSortItem modelName="sequence" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('sequence')">
                                    Current Sequence
                                  </SingleSortItem>
                                  <Button
                                    class="bg-yellow-300 hover:bg-yellow-400 text-gray-800 text-xs font-medium"
                                    @click.prevent="onRenumberItemsClicked()"
                                    v-if="opsJob.opsJobItems && opsJob.opsJobItems.length && opsJob.opsJobItems.some(item => item.status < 3) && permissions.includes('admin-access operations')"
                                  >
                                    <div class="flex space-x-1 items-center">
                                      <BarsArrowDownIcon class="h-3 w-3"></BarsArrowDownIcon>
                                      <span>
                                        Renumber
                                      </span>
                                    </div>
                                  </Button>
                                </div>
                              </TableHead>
                              <TableHead> Generated Sequence </TableHead>
                              <TableHead> Machine ID & Job ID# </TableHead>
                              <TableHead> Customer & Ops Note </TableHead>
                              <TableHeadSort modelName="delivery_postcode" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('delivery_postcode')">
                                Postcode
                              </TableHeadSort>
                              <TableHead> Address </TableHead>
                            </tr>
                          </thead>
                          <tbody class="bg-white">
                            <tr v-for="(opsJobItem, opsJobItemIndex) in opsJob.opsJobItems" :key="opsJobItem.id" :class="opsJobItemIndex % 2 === 0 ? undefined : 'bg-gray-100'">
                              <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                                <div class="flex items-center justify-center">
                                  <input
                                    type="text"
                                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-fit text-sm border-gray-300 rounded-md max-w-14 text-center"
                                    v-model="opsJobItem.sequence"
                                    :disabled="opsJobItem.status >= 3"
                                    @input="updateSequence(opsJobItem)"
                                  />
                                </div>
                              </td>
                              <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                                {{ opsJobItem.generated_sequence }}
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
                                    </div>
                                  </span>
                                  <span class="text-left font-medium bg-gray-200 py-1 px-1 rounded" v-if="opsJobItem.customer && opsJobItem.customer.ops_note">
                                    {{ opsJobItem.customer.ops_note }}
                                  </span>
                                </div>
                              </td>
                              <td class="whitespace-pre-line py-2 px-1 text-sm text-center">
                                {{ opsJobItem.delivery_postcode }}
                              </td>
                              <td class="whitespace-pre-line py-2 px-1 text-sm text-center">
                                <div class="flex flex-col space-y-2 break-words max-w-32 md:max-w-72" v-if="opsJobItem.customer && opsJobItem.customer.deliveryAddress">
                                  <span>
                                    <a :href="opsJobItem.customer.deliveryAddress.map_url" class="text-blue-700" target="_blank"> {{ opsJobItem.customer.deliveryAddress.full_address }} </a>
                                  </span>
                                  <span v-if="!opsJobItem.customer.deliveryAddress.full_address">
                                    {{ opsJobItem.customer.deliveryAddress.postcode }}
                                  </span>
                                </div>
                              </td>
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
import SingleSortItem from '@/Components/SingleSortItem.vue';
import TableHead from '@/Components/TableHead.vue'; // Retained TableHead component
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ArrowUturnLeftIcon, ArrowRightCircleIcon, BarsArrowDownIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const filters = ref({
  sortKey: '',
  sortBy: true,
});

const props = defineProps({
  originAddresses: [Array, Object],
  mapApiKey: String,
  opsJob: Object,
});

const emit = defineEmits(['modalClose']);

const originAddressOptions = ref([]);
const form = ref(useForm(getDefaultForm()));
const isSequenceGenerated = ref(false);
const opsJob = ref(props.opsJob?.data || {});
const permissions = usePage().props.auth.permissions;
const toast = useToast();
let map, directionsService;
let defaultPos = { lat: 1.3521, lng: 103.8198 };
let markers = []; // Array to store map markers
let renderers = []; // Array to store all DirectionsRenderer instances

onMounted(() => {
  originAddressOptions.value = [
    ...(Array.isArray(props.originAddresses?.data) ? props.originAddresses.data.map(address => ({
      id: address.id,
      name: address.name,
      full_address: (address.name ? '(' + address.name + ') ' : '') + address.full_address,
      latitude: address.latitude,
      longitude: address.longitude,
      is_ops_job_item: false,
    })) : []),
    ...(Array.isArray(props.opsJob.data?.opsJobItems) ? props.opsJob.data.opsJobItems.map(jobItem => ({
        id: jobItem.customer.deliveryAddress.id,
        name: jobItem.vend.code,
        full_address: (jobItem.vend.code ? '(' + jobItem.vend.code + ' - ' + jobItem.customer.name + ') ' : '') + jobItem.customer.deliveryAddress.full_address,
        latitude: jobItem.customer.deliveryAddress.latitude,
        longitude: jobItem.customer.deliveryAddress.longitude,
        is_ops_job_item: true,
    })) : []),
  ];

  // Initialize Google Maps
  loadGoogleMapsApi();
});

function applySequenceJobs() {
  const approval = confirm('Are you sure to sync and overwrite the current sequence?');
  if (!approval) {
      return;
  }

  // Create a clean serializable version of opsJobItems
const cleanOpsJobItems = opsJob.value.opsJobItems.map(item => {
  return {
    id: item.id,
    sequence: item.sequence,
    generated_sequence: item.generated_sequence,
    customer: {
      id: item.customer.id, // Only send necessary properties
      name: item.customer.name,
      deliveryAddress: {
        id: item.customer.deliveryAddress.id,
        latitude: item.customer.deliveryAddress.latitude,
        longitude: item.customer.deliveryAddress.longitude,
        full_address: item.customer.deliveryAddress.full_address
      }
    },
    vend: item.vend ? { code: item.vend.code } : null, // Only send vend code if available
    isOrigin: item.isOrigin,
    isOpsJobItem: item.isOpsJobItem
  };
});

axios.post('/ops-jobs/' + opsJob.value.id + '/sequence', {
  opsJobItems: cleanOpsJobItems // Use cleanOpsJobItems
})
.then(response => {
  location.reload()
})
.catch(error => {
  console.error(error);
});

}

// Function to clear the existing route and markers
function clearMarkers() {
  markers.forEach(marker => marker.setMap(null)); // Clear markers
  markers = []; // Reset marker array
}

function clearRoute() {
  renderers.forEach(renderer => renderer.setMap(null)); // Clear all existing routes
  renderers = []; // Reset the renderers array
}

function getDefaultForm() {
  return {
    origin_address_id: '',
  };
}

function onRenumberItemsClicked() {
  form.value.clearErrors()
  form.value
    .transform((data) => ({
      ...data,
      opsJobItems: opsJob.value.opsJobItems,
    }))
    .post('/ops-jobs/' + opsJob.value.id + '/renumber', {
    onSuccess: () => {
      toast.success("Successfully Renumbered", {
        timeout: 3000
      });
      opsJob.value = props.opsJob.data
    },
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

function onSearchFilterUpdated() {
  const cleanFilters = JSON.parse(JSON.stringify(filters.value)); // Ensure serializable data

  router.reload({
    only: ['opsJob'],
    data: {
      ...cleanFilters, // Use cleaned version of filters
    },
    replace: true,
    preserveState: true,
    preserveScroll: true,
    onSuccess: page => {
      opsJob.value = props.opsJob ? props.opsJob.data : null;
    },
  });
}


function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}

function updateSequence(opsJobItem) {
  form.value.clearErrors();
  form.value
    .transform((data) => ({
      ...data,
      sequence: opsJobItem.sequence,
    }))
    .post('/ops-jobs/items/' + opsJobItem.id + '/update', {
    onSuccess: () => {},
    preserveScroll: true,
    preserveState: true,
    replace: true,
  });
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
              text: String(jobItem.vend.code), // Using custom sequence
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
  let origin = null;

  // Fetch the selected origin based on form input or fallback to the first opsJobItem with isOpsJobItem true
  if (form.value.origin_address_id) {
    origin = originAddressOptions.value.find(address => address.id === form.value.origin_address_id.id);
  } else {
    origin = originAddressOptions.value.filter(address => address.is_ops_job_item)[0];
  }

  // Ensure origin has valid latitude and longitude
  if (!origin || !origin.latitude || !origin.longitude) {
    console.error("Invalid origin coordinates");
    return; // Exit if origin is invalid
  }

  // Refresh opsJobItems from props to ensure you're working with the full set of data
  opsJob.value.opsJobItems = [...props.opsJob.data.opsJobItems];

  // Clear any existing origin (set isOrigin = false for all items)
  opsJob.value.opsJobItems.forEach((item) => {
    item.isOrigin = false;
    item.processed = false; // Reset the processed flag
  });

  // Clear the existing directions and markers to avoid duplication
  clearMarkers();
  clearRoute();

  // Convert origin to Google Maps LatLngLiteral format
  const originLatLng = { lat: parseFloat(origin.latitude), lng: parseFloat(origin.longitude) };

  // Check if the selected origin is already in the opsJobItems list
  let foundOriginItem = opsJob.value.opsJobItems.find(
    (item) => item.customer.deliveryAddress.id === origin.id
  );

  // If the origin is not an opsJobItem, create a new entry and add it to the start
  if (!foundOriginItem) {
    foundOriginItem = {
      customer: {
        name: origin.name,
        deliveryAddress: {
          full_address: origin.full_address,
          latitude: origin.latitude,
          longitude: origin.longitude,
        }
      },
      isOrigin: true,
      isOpsJobItem: origin.is_ops_job_item,
      processed: true, // Mark as processed
    };

    opsJob.value.opsJobItems.unshift(foundOriginItem); // Add to the beginning of the array
  } else {
    foundOriginItem.isOrigin = true; // Set isOrigin = true for existing item
    foundOriginItem.processed = true; // Mark as processed
  }

  // Now call showDirections with the new origin
  showDirections(originLatLng);
}

const showDirections = (originLatLng) => {
  isSequenceGenerated.value = true;

  // Check if originLatLng is valid
  if (!originLatLng || typeof originLatLng.lat !== 'number' || typeof originLatLng.lng !== 'number') {
    console.error("Invalid originLatLng");
    return; // Exit if originLatLng is invalid
  }

  const maxWaypoints = 23; // Max 23 waypoints + origin = 24 + destination = 25
  let customersWithValidAddresses = opsJob.value.opsJobItems.filter(
    opsJobItem => opsJobItem.customer.deliveryAddress && !opsJobItem.isOrigin
  );

  // Limit to the first 23 waypoints
  const waypoints = customersWithValidAddresses.slice(0, maxWaypoints);

  // Get the remaining opsJobItems (those that are beyond the 23 waypoints)
  const remainingOpsJobItems = customersWithValidAddresses.slice(maxWaypoints);

  // Ensure the last waypoint is correctly marked as the destination but not removed from the list
  const lastWaypoint = waypoints.length > 0
    ? waypoints[waypoints.length - 1].customer.deliveryAddress
    : null;

  // If there are no valid waypoints, we cannot proceed
  if (!lastWaypoint) {
    console.error('No valid waypoints found');
    return;
  }

  // Clear the existing directions and markers before generating new ones
  clearMarkers();
  clearRoute();

  const request = {
    travelMode: google.maps.TravelMode.DRIVING, // Travel mode
    waypoints: waypoints.map(opsJobItem => {
      return {
        location: {
          lat: parseFloat(opsJobItem.customer.deliveryAddress.latitude),
          lng: parseFloat(opsJobItem.customer.deliveryAddress.longitude),
        },
        stopover: true,
      };
    }),
    origin: originLatLng,
    destination: {
      lat: parseFloat(lastWaypoint.latitude),
      lng: parseFloat(lastWaypoint.longitude)
    }, // The last waypoint as the destination
    optimizeWaypoints: true, // Enable distance-based optimization for waypoints
  };

  if (request.origin && request.destination) {
    const directionsRenderer = new google.maps.DirectionsRenderer({
      suppressMarkers: true, // Prevent default markers
      map: map, // Attach it to the map
    });

    directionsService.route(request, (result, status) => {
      if (status === google.maps.DirectionsStatus.OK) {
        directionsRenderer.setDirections(result);
        renderers.push(directionsRenderer); // Store the renderer for later use (clear or reset)

        const optimizedOrder = result.routes[0].waypoint_order;
        const optimizedCustomers = optimizedOrder.map((orderIndex, idx) => {
          const item = waypoints[orderIndex];
          item.generated_sequence = idx + 2; // Start from 2 because 1 is reserved for origin
          return item;
        });

        // Assign generated_sequence "1" to the origin
        const originItem = opsJob.value.opsJobItems.find(item => item.isOrigin);
        if (originItem) {
          originItem.generated_sequence = 1;
        }

        // Assign sequences to remaining items starting with "R1", "R2", etc.
        remainingOpsJobItems.forEach((item, index) => {
          item.generated_sequence = `5${index + 1}`;
        });

        // Call addCustomMarkers to display markers with the sequences
        addCustomMarkers(originLatLng, optimizedCustomers, remainingOpsJobItems);

        // Update opsJobItems to include everything (origin, waypoints, remaining items)
        opsJob.value.opsJobItems = [
          originItem,  // Add the origin with sequence 1
          ...optimizedCustomers, // Add the optimized waypoints
          ...remainingOpsJobItems, // Add the rest of the opsJobItems that didn't fit in the first 23
        ];
      } else {
        console.error('Directions request failed due to ' + status);
      }
    });
  } else {
    console.error('Invalid origin or destination for route calculation.');
  }
};

function addCustomMarkers(originLatLng, optimizedCustomers = [], remainingOpsJobItems = []) {
  // Add a custom marker for the origin
  const originMarker = new google.maps.Marker({
    position: originLatLng,
    map: map,
    label: {
      text: '1', // Using custom sequence for the origin
      color: "#000000",
      fontSize: "14px",
      fontWeight: "bold",
    },
  });
  markers.push(originMarker);

  // Add custom markers for waypoints (ensure optimizedCustomers is defined)
  if (Array.isArray(optimizedCustomers)) {
    optimizedCustomers.forEach((waypoint, index) => {
      if (waypoint && waypoint.customer && waypoint.customer.deliveryAddress) {
        const latLng = {
          lat: parseFloat(waypoint.customer.deliveryAddress.latitude),
          lng: parseFloat(waypoint.customer.deliveryAddress.longitude),
        };

        const marker = new google.maps.Marker({
          position: latLng,
          map: map,
          label: {
            text: String(waypoint.generated_sequence), // Show generated sequence number
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
      }
    });
  } else {
    console.error("Invalid or empty optimizedCustomers");
  }

  // Add markers for the remaining opsJobItems (ensure remainingOpsJobItems is defined)
  if (Array.isArray(remainingOpsJobItems)) {
    remainingOpsJobItems.forEach((opsJobItem, index) => {
      if (opsJobItem && opsJobItem.customer && opsJobItem.customer.deliveryAddress) {
        const latLng = {
          lat: parseFloat(opsJobItem.customer.deliveryAddress.latitude),
          lng: parseFloat(opsJobItem.customer.deliveryAddress.longitude),
        };

        const marker = new google.maps.Marker({
          position: latLng,
          map: map,
          label: {
            text: String(opsJobItem.generated_sequence), // Show "R" sequence
            color: "#FFFFFF",
            fontSize: "14px",
            fontWeight: "bold",
          },
        });

        const infoWindow = new google.maps.InfoWindow({
          content: `<div>
              <span class="font-bold">${opsJobItem.vend ? opsJobItem.vend?.code : ''}</span><br>
              <span class="font-medium">${opsJobItem.customer.name}</span><br>
              <p>${opsJobItem.customer.deliveryAddress.full_address ? opsJobItem.customer.deliveryAddress.full_address : opsJobItem.customer.deliveryAddress.postcode}</p>
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

        markers.push(marker); // Store remaining markers
      }
    });
  } else {
    console.error("Invalid or empty remainingOpsJobItems");
  }
}



</script>
