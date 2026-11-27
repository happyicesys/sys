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
      :server="server"
      @processfile="handleProcessFile"
      ref="pond"
    />
  </div>
</template>

<script setup>
import vueFilePond from 'vue-filepond';
import 'filepond/dist/filepond.min.css';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';

import { ref } from 'vue';
import { shrinkImage } from '@/utils/shrinkImage';

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
  // Phone photos are shrunk to a 2048 px long side before upload (utils/shrinkImage.js).
  shrinkImages: {
    type: Boolean,
    default: true,
  },
});

// Per component, not FilePond's global setOptions: with two inputs on one page the last one mounted
// used to send every pond's files to its own endpoint.
const server = {
  process: (fieldName, file, metadata, load, error, progress, abort) => {
    const xhr = new XMLHttpRequest();
    let aborted = false;

    (props.shrinkImages ? shrinkImage(file) : Promise.resolve(file)).then((upload) => {
      if (aborted) return;
      const formData = new FormData();
      formData.append(fieldName, upload, upload.name);
      xhr.open('POST', props.endpoint);
      xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
      xhr.upload.onprogress = (e) => progress(e.lengthComputable, e.loaded, e.total);
      xhr.onload = () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          load(xhr.responseText);
        } else {
          error(xhr.statusText || String(xhr.status));
        }
      };
      xhr.onerror = () => error('Upload failed');
      xhr.send(formData);
    });

    return {
      abort: () => {
        aborted = true;
        xhr.abort();
        abort();
      },
    };
  },
};

function handleProcessFile() {
  const allFilesProcessed = pond.value.getFiles().every(file => file.serverId !== null);

  if (allFilesProcessed) {
    // location.reload(); // Refresh the page
  }
}
</script>