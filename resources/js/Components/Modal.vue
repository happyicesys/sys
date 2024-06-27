<template>
    <TransitionRoot as="template" :show="open">
      <Dialog as="div" class="relative z-50" @close="onModalClose">
        <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                         leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
          <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
        </TransitionChild>

        <div class="fixed z-50 inset-0 overflow-y-auto">
          <div class="flex items-center justify-center min-h-full p-4 text-center sm:p-0">
            <TransitionChild as="template" enter="ease-out duration-300"
                             enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                             enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                             leave-from="opacity-100 translate-y-0 sm:scale-100"
                             leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
              <DialogPanel
                class="relative bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-visible shadow-xl transform transition-all my-2 md:my-8 max-w-4xl max-h-4xl w-full p-2 md:p-6 flex flex-col"
                @click.stop>
                <div class="mt-3 text-center sm:mt-0 sm:ml-2 sm:mr-2 sm:text-left">
                  <div class="border-b border-gray-200 bg-white px-2 py-1 md:py-3">
                    <DialogTitle as="h3" class="text-xl leading-6 font-medium text-gray-900 flex justify-between">
                      <slot name="header"/>
                      <button type="button"
                              class="bg-white rounded-md text-gray-400 hover:text-gray-500"
                              @click="onModalClose">
                        <span class="sr-only">Close</span>
                        <XMarkIcon class="h-6 w-6" aria-hidden="true"/>
                      </button>
                    </DialogTitle>
                  </div>
                  <div class="mt-5 text-lg text-gray-700 p-1">
                    <slot/>
                  </div>
                </div>
                <!-- <div class="border-t border-gray-200 bg-white mt-6 py-3 sm:mt-4 flex justify-end">
                    <slot name="footer"/>
                </div> -->
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>
  </template>

  <script setup>
  import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
  import { XMarkIcon } from '@heroicons/vue/20/solid'
  import { ref } from 'vue'

  const emit = defineEmits(['modalClose'])

  const props = defineProps({
    open: Boolean,
  })

  function onModalClose() {
    emit('modalClose')
  }
  </script>
