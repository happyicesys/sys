<script setup>
// Textarea with @-mention autocomplete + blue highlighting of @names/@aliases.
//
// Highlighting a live <textarea> isn't possible (it can't colour words), and a
// transparent-overlay hack misaligns with the textarea's own wrapping. So this
// uses a robust preview/edit toggle instead:
//   • not editing → a styled read-only view that colours @mentions blue
//   • editing      → a plain, fully-functional textarea (with the @ dropdown)
// Clicking the preview enters edit mode; blurring saves and returns to preview.
// Only one element is ever shown, so nothing can misalign.
import { ref, computed, nextTick, watch } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  // [{ id, name, alias }] — already scoped to the operator by the backend.
  users: { type: Array, default: () => [] },
  rows: { type: [Number, String], default: 3 },
  placeholder: { type: String, default: '' },
  textareaClass: { type: String, default: '' },
  autogrow: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'change']);

const taRef = ref(null);
const editing = ref(false);
const editStartValue = ref('');
const showMenu = ref(false);
const query = ref('');
const activeIndex = ref(0);
const menuStyle = ref({});
let mentionStart = -1;

const filtered = computed(() => {
  const list = props.users.filter((u) => u && u.name);
  const q = query.value.toLowerCase();
  const matched = q
    ? list.filter(
        (u) =>
          u.name.toLowerCase().includes(q) ||
          (u.alias && u.alias.toLowerCase().includes(q))
      )
    : list;
  return matched.slice(0, 8);
});

// Split the value into plain + mention segments for the blue preview. Known
// names AND aliases are matched whole (longest first) so "@Daniel Dude" and
// "@B" both colour; a word-boundary check avoids matching a longer word's prefix.
const parts = computed(() => {
  const text = props.modelValue || '';
  const tokens = [];
  for (const u of props.users) {
    if (!u) continue;
    if (u.name) tokens.push(u.name);
    if (u.alias && u.alias.trim()) tokens.push(u.alias.trim());
  }
  tokens.sort((a, b) => b.length - a.length);
  const out = [];
  let i = 0;
  const atBoundary = (idx) => idx === 0 || /\s/.test(text[idx - 1]);
  const pushPlain = (ch) => {
    const last = out[out.length - 1];
    if (last && !last.mention) last.text += ch;
    else out.push({ mention: false, text: ch });
  };
  while (i < text.length) {
    if (text[i] === '@' && atBoundary(i)) {
      let token = null;
      for (const n of tokens) {
        if (text.substr(i + 1, n.length) === n) {
          const after = text[i + 1 + n.length];
          if (!after || !/\w/.test(after)) { token = n; break; }
        }
      }
      if (!token) {
        const m = text.slice(i + 1).match(/^[\w.\-]+/);
        if (m) token = m[0];
      }
      if (token) {
        out.push({ mention: true, text: '@' + token });
        i += 1 + token.length;
        continue;
      }
    }
    pushPlain(text[i]);
    i += 1;
  }
  return out;
});

function grow() {
  if (!props.autogrow) return;
  const el = taRef.value;
  if (!el) return;
  nextTick(() => {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
  });
}

function positionMenu(el) {
  const r = el.getBoundingClientRect();
  menuStyle.value = {
    top: `${r.bottom + 4}px`,
    left: `${r.left}px`,
    minWidth: `${Math.min(Math.max(r.width, 180), 280)}px`,
  };
}

function detectMention(el) {
  const caret = el.selectionStart;
  const before = el.value.slice(0, caret);
  const m = before.match(/(?:^|\s)@([\w.\-]*)$/);
  if (m) {
    query.value = m[1];
    mentionStart = caret - m[1].length - 1;
    activeIndex.value = 0;
    if (filtered.value.length) {
      positionMenu(el);
      showMenu.value = true;
    } else {
      showMenu.value = false;
    }
  } else {
    closeMenu();
  }
}

function closeMenu() {
  showMenu.value = false;
  mentionStart = -1;
  query.value = '';
}

function onInput(e) {
  emit('update:modelValue', e.target.value);
  grow();
  detectMention(e.target);
}

function selectUser(u) {
  const el = taRef.value;
  if (!el || mentionStart < 0) return;
  const caret = el.selectionStart;
  const head = el.value.slice(0, mentionStart);
  const tail = el.value.slice(caret);
  // Prefer the short alias when set, to keep notes compact.
  const token = u.alias && u.alias.trim() ? u.alias.trim() : u.name;
  const insert = `@${token} `;
  const newValue = head + insert + tail;
  emit('update:modelValue', newValue);
  closeMenu();
  nextTick(() => {
    el.focus();
    const pos = (head + insert).length;
    el.setSelectionRange(pos, pos);
    grow();
  });
}

function onKeydown(e) {
  if (!showMenu.value || !filtered.value.length) return;
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    activeIndex.value = (activeIndex.value + 1) % filtered.value.length;
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    activeIndex.value = (activeIndex.value - 1 + filtered.value.length) % filtered.value.length;
  } else if (e.key === 'Enter' || e.key === 'Tab') {
    e.preventDefault();
    selectUser(filtered.value[activeIndex.value]);
  } else if (e.key === 'Escape') {
    closeMenu();
  }
}

function startEdit() {
  if (editing.value) return;
  editStartValue.value = props.modelValue || '';
  editing.value = true;
  nextTick(() => {
    const el = taRef.value;
    if (el) {
      el.focus();
      const len = el.value.length;
      el.setSelectionRange(len, len);
      grow();
    }
  });
}

// Blur leaves edit mode and saves (only if the value actually changed). The
// delay lets a dropdown mousedown register before we tear the textarea down.
function onBlur() {
  setTimeout(() => {
    closeMenu();
    editing.value = false;
    if ((props.modelValue || '') !== editStartValue.value) emit('change');
  }, 150);
}

watch(() => props.modelValue, grow);
</script>

<template>
  <div class="relative w-full">
    <!-- Preview: read-only, @mentions in blue. Click to edit. -->
    <div
      v-show="!editing"
      :class="[textareaClass, 'min-h-[2.25rem] cursor-text whitespace-pre-wrap break-words']"
      @click="startEdit"
    >
      <template v-if="modelValue">
        <template v-for="(part, i) in parts" :key="i">
          <span v-if="part.mention" class="font-medium text-blue-600">{{ part.text }}</span>
          <template v-else>{{ part.text }}</template>
        </template>
      </template>
      <span v-else class="text-gray-400">{{ placeholder }}</span>
    </div>

    <!-- Editor: plain textarea with the @ dropdown. -->
    <textarea
      v-show="editing"
      ref="taRef"
      :value="modelValue"
      :rows="rows"
      :placeholder="placeholder"
      :class="textareaClass"
      spellcheck="false"
      @input="onInput"
      @keydown="onKeydown"
      @blur="onBlur"
    ></textarea>

    <Teleport to="body">
      <ul
        v-if="editing && showMenu"
        :style="menuStyle"
        class="fixed z-[9999] max-h-52 overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg"
      >
        <li
          v-for="(u, i) in filtered"
          :key="u.id"
          :class="['flex cursor-pointer items-center gap-2 px-3 py-1.5', i === activeIndex ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50']"
          @mousedown.prevent="selectUser(u)"
        >
          <span class="inline-flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-gray-200 text-[10px] font-semibold text-gray-600">
            {{ (u.name || '?').charAt(0).toUpperCase() }}
          </span>
          <span class="truncate">
            {{ u.name }}<span v-if="u.alias" class="text-gray-400"> ({{ u.alias }})</span>
          </span>
        </li>
      </ul>
    </Teleport>
  </div>
</template>
