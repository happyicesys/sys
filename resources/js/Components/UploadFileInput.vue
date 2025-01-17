<template>
  <div>
    <FilePond
      v-model="files"
      name="files"
      label-idle="Click to Browse or Drop files here..."
      :accepted-file-types="acceptedFileTypes"
      credits="false"
      allowMultiple="true"
      :maxFileSize="maxFileSize"
      @processfile="handleProcessFile"
      ref="pond"
    />
  </div>
</template>

<script setup>
import vueFilePond, { setOptions } from 'vue-filepond';
import 'filepond/dist/filepond.min.css';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';

import { ref } from 'vue';

const FilePond = vueFilePond(FilePondPluginFileValidateType, FilePondPluginFileValidateSize);
const files = ref([]);
const pond = ref(null);
const props = defineProps({
  acceptedFileTypes: {
    type: String,
    default: 'image/*, video/*, pdf',
  },
  endpoint: String,
  maxFileSize: {
    type: String,
    default: '20MB',
  },
});

setOptions({
  server: {
    process: {
      url: props.endpoint,
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
      },
    },
  },
});

function handleProcessFile() {
  const allFilesProcessed = pond.value.getFiles().every(file => file.serverId !== null);

  if (allFilesProcessed) {
    // location.reload(); // Refresh the page
  }
}
</script>