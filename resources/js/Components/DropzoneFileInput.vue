<template>
  <div>
    <form
      action="POST"
      :action="endpoint"
      class="dropzone"
      id="my-dropzone"
      ref="dropzoneForm"
    >
      <div class="dz-message">
        Click to Browse or Drop files here...
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import Dropzone from 'dropzone';

import 'dropzone/dist/dropzone.css';

const dropzoneForm = ref(null);
const props = defineProps({
  endpoint: String,
  acceptedFiles: {
    type: String,
    default: "image/*,video/*,.pdf",
  },
  maxFilesize: {
    type: Number,
    default: 20,
  },
});

onMounted(() => {
  const myDropzone = new Dropzone(dropzoneForm.value, {
    url: props.endpoint,
    maxFilesize: props.maxFilesize, // MB
    acceptedFiles: props.acceptedFiles,
    addRemoveLinks: false,
    paramName: "files",
    init: function () {
      this.on("success", function (file, response) {
        console.log("File uploaded successfully", file, response);
      });

      this.on("error", function (file, response) {
        console.error("File upload failed", file, response);
      });

      this.on("queuecomplete", function () {
        // All files finished uploading
        if (this.getQueuedFiles().length === 0 && this.getUploadingFiles().length === 0) {
          location.reload(); // Refresh the page
        }
      });
    },
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
    },
  });
});
</script>

<style>
.dropzone {
  border: 2px dashed #007bff;
  padding: 20px;
  background: #f8f9fa;
  cursor: pointer;
}
.dropzone .dz-message {
  font-size: 18px;
  color: #6c757d;
}
</style>
