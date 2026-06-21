<template>
    <div
        class="overflow-hidden rounded-2xl border bg-white shadow-sm ring-1 transition"
        :class="invalid ? 'border-rose-300 ring-rose-100' : 'border-slate-200/95 ring-slate-100/90'"
    >
        <div v-if="editor" class="flex flex-wrap items-center gap-1 border-b border-slate-100 bg-slate-50/90 px-2 py-2 sm:gap-1.5 sm:px-3">
            <button
                type="button"
                class="rounded-lg px-2 py-1.5 text-xs font-black text-slate-700 transition hover:bg-white hover:text-slate-900"
                :class="editor.isActive('bold') ? 'bg-white text-primary-800 ring-1 ring-primary-200' : ''"
                title="Bold"
                @click="editor.chain().focus().toggleBold().run()"
            >
                B
            </button>
            <button
                type="button"
                class="rounded-lg px-2 py-1.5 text-xs font-black italic text-slate-700 transition hover:bg-white hover:text-slate-900"
                :class="editor.isActive('italic') ? 'bg-white text-primary-800 ring-1 ring-primary-200' : ''"
                title="Italic"
                @click="editor.chain().focus().toggleItalic().run()"
            >
                I
            </button>
            <button
                type="button"
                class="rounded-lg px-2 py-1.5 text-xs font-black underline text-slate-700 transition hover:bg-white hover:text-slate-900"
                :class="editor.isActive('underline') ? 'bg-white text-primary-800 ring-1 ring-primary-200' : ''"
                title="Underline"
                @click="editor.chain().focus().toggleUnderline().run()"
            >
                U
            </button>
            <button
                type="button"
                class="rounded-lg px-2 py-1.5 text-xs font-black text-slate-500 line-through transition hover:bg-white hover:text-slate-900"
                :class="editor.isActive('strike') ? 'bg-white text-primary-800 ring-1 ring-primary-200' : ''"
                title="Strikethrough"
                @click="editor.chain().focus().toggleStrike().run()"
            >
                S
            </button>
            <span class="mx-1 hidden h-5 w-px bg-slate-200 sm:inline" aria-hidden="true" />
            <button
                type="button"
                class="rounded-lg px-2 py-1.5 text-xs font-black text-slate-700 transition hover:bg-white hover:text-slate-900"
                :class="editor.isActive('heading', { level: 2 }) ? 'bg-white text-primary-800 ring-1 ring-primary-200' : ''"
                title="Heading 2"
                @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
            >
                H2
            </button>
            <button
                type="button"
                class="rounded-lg px-2 py-1.5 text-xs font-black text-slate-700 transition hover:bg-white hover:text-slate-900"
                :class="editor.isActive('heading', { level: 3 }) ? 'bg-white text-primary-800 ring-1 ring-primary-200' : ''"
                title="Heading 3"
                @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
            >
                H3
            </button>
            <span class="mx-1 hidden h-5 w-px bg-slate-200 sm:inline" aria-hidden="true" />
            <button
                type="button"
                class="rounded-lg px-2 py-1.5 text-xs font-black text-slate-700 transition hover:bg-white hover:text-slate-900"
                :class="editor.isActive('bulletList') ? 'bg-white text-primary-800 ring-1 ring-primary-200' : ''"
                title="Bullet list"
                @click="editor.chain().focus().toggleBulletList().run()"
            >
                • List
            </button>
            <button
                type="button"
                class="rounded-lg px-2 py-1.5 text-xs font-black text-slate-700 transition hover:bg-white hover:text-slate-900"
                :class="editor.isActive('orderedList') ? 'bg-white text-primary-800 ring-1 ring-primary-200' : ''"
                title="Numbered list"
                @click="editor.chain().focus().toggleOrderedList().run()"
            >
                1. List
            </button>
            <button
                type="button"
                class="rounded-lg px-2 py-1.5 text-xs font-black text-slate-700 transition hover:bg-white hover:text-slate-900"
                :class="editor.isActive('blockquote') ? 'bg-white text-primary-800 ring-1 ring-primary-200' : ''"
                title="Quote"
                @click="editor.chain().focus().toggleBlockquote().run()"
            >
                “ ”
            </button>
            <span class="mx-1 hidden h-5 w-px bg-slate-200 sm:inline" aria-hidden="true" />
            <label class="inline-flex cursor-pointer items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-slate-600 hover:bg-white" title="Text colour">
                <span class="font-black text-slate-500">A</span>
                <input v-model="textColor" type="color" class="h-7 w-9 cursor-pointer rounded border border-slate-200 bg-white p-0" @input="onTextColor" />
            </label>
            <button
                type="button"
                class="rounded-lg px-2 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-white hover:text-slate-900"
                title="Clear colour"
                @click="clearColor"
            >
                Reset colour
            </button>
            <button
                type="button"
                class="rounded-lg px-2 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-white hover:text-slate-900"
                title="Link"
                :class="editor.isActive('link') ? 'bg-white text-primary-800 ring-1 ring-primary-200' : ''"
                @click="setLink"
            >
                Link
            </button>
            <span class="mx-1 hidden h-5 w-px bg-slate-200 sm:inline" aria-hidden="true" />
            <button
                type="button"
                class="rounded-lg px-2 py-1.5 text-xs font-bold text-slate-500 transition hover:bg-white hover:text-rose-700"
                title="Clear formatting"
                @click="editor.chain().focus().unsetAllMarks().clearNodes().run()"
            >
                Clear
            </button>
        </div>
        <editor-content
            v-if="editor"
            :editor="editor"
            class="quest-rich-editor min-h-[11rem] bg-white px-3 py-3 text-gray-600 sm:min-h-[12rem] sm:px-4 sm:py-4"
        />
        <div
            v-else
            class="flex min-h-[11rem] items-center justify-center bg-white px-3 py-3 text-sm font-semibold text-slate-500 sm:min-h-[12rem] sm:px-4 sm:py-4"
        >
            Loading editor…
        </div>
    </div>
</template>

<script setup>
import Color from '@tiptap/extension-color';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import { TextStyle } from '@tiptap/extension-text-style';
import Underline from '@tiptap/extension-underline';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Write your brief…',
    },
    invalid: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const DEFAULT_TEXT_COLOR = '#4b5563';
const textColor = ref(DEFAULT_TEXT_COLOR);

// Remember the last text selection so toolbar controls that steal focus
// (e.g. the native colour picker) can re-apply marks to the intended range.
const lastSelection = ref(null);

const LIGHT_COLOR_PATTERN = /^(?:#(?:fff(?:fff)?|f[89a-f]{5})|white|rgb\(\s*255\s*,\s*255\s*,\s*255\s*\)|rgb\(\s*255\s+255\s+255\s*\))$/i;

/** Strip invisible/light inline colours saved from older editor sessions or pasted HTML. */
function sanitizeDescriptionHtml(html) {
    if (!html || typeof html !== 'string') {
        return '';
    }

    return html.replace(/color\s*:\s*([^;"']+)/gi, (match, raw) => {
        const value = String(raw).trim().toLowerCase();

        if (LIGHT_COLOR_PATTERN.test(value)) {
            return `color: ${DEFAULT_TEXT_COLOR}`;
        }

        return match;
    });
}

const editor = useEditor({
    immediatelyRender: false,
    content: sanitizeDescriptionHtml(props.modelValue || ''),
    extensions: [
        StarterKit.configure({
            heading: {
                levels: [2, 3],
            },
        }),
        Underline,
        TextStyle,
        Color,
        Link.configure({
            openOnClick: false,
            HTMLAttributes: {
                rel: 'noopener noreferrer nofollow',
                target: '_blank',
                class: 'text-primary-700 underline font-semibold',
            },
        }),
        Placeholder.configure({
            placeholder: props.placeholder,
        }),
    ],
    editorProps: {
        attributes: {
            class: 'quest-rich-prosemirror max-w-none focus:outline-none min-h-[10rem] text-[15px] font-medium leading-relaxed text-gray-600',
            style: `color: ${DEFAULT_TEXT_COLOR}`,
            spellcheck: 'true',
        },
    },
    onSelectionUpdate: ({ editor: ed }) => {
        const { from, to } = ed.state.selection;
        if (from !== to) {
            lastSelection.value = { from, to };
        }
    },
    onUpdate: ({ editor: ed }) => {
        emit('update:modelValue', sanitizeDescriptionHtml(ed.getHTML()));
    },
});

watch(
    () => props.modelValue,
    (val) => {
        const ed = editor.value;
        if (!ed) {
            return;
        }
        const current = ed.getHTML();
        const next = sanitizeDescriptionHtml(val || '');
        if (next !== current) {
            ed.commands.setContent(next, false);
        }
    },
);

/** Re-apply a mark to the remembered selection (focus may have moved to a toolbar control). */
function chainOnSelection() {
    const ed = editor.value;
    const chain = ed.chain().focus();
    const sel = lastSelection.value;
    const live = ed.state.selection;

    if (live.from === live.to && sel && sel.from !== sel.to) {
        return chain.setTextSelection(sel);
    }

    return chain;
}

function onTextColor(e) {
    const hex = e?.target?.value;
    if (!hex || !editor.value) {
        return;
    }
    textColor.value = hex;
    chainOnSelection().setColor(hex).run();
}

function clearColor() {
    if (!editor.value) {
        return;
    }
    chainOnSelection().unsetColor().run();
}

function setLink() {
    const ed = editor.value;
    if (!ed) {
        return;
    }
    const prev = ed.getAttributes('link').href;
    const url = window.prompt('Link URL (https://…)', prev || 'https://');
    if (url === null) {
        return;
    }
    const trimmed = url.trim();
    if (trimmed === '') {
        ed.chain().focus().extendMarkRange('link').unsetLink().run();

        return;
    }
    ed.chain().focus().extendMarkRange('link').setLink({ href: trimmed }).run();
}

onBeforeUnmount(() => {
    editor.value?.destroy();
});
</script>

<style>
/* TipTap / ProseMirror — explicit dark body text (no prose plugin; avoids light inherited colours) */
.quest-rich-editor .ProseMirror,
.quest-rich-editor .quest-rich-prosemirror {
    outline: none;
    min-height: 10rem;
    color: #4b5563 !important;
    caret-color: #334155;
}

/* Block-level nodes inherit the dark body colour; inline marks (span/strong/em…)
   are intentionally excluded so the colour picker can apply per-selection colours. */
.quest-rich-editor .ProseMirror :where(p, li, h2, h3, blockquote) {
    color: inherit;
}

/* Force readable body copy even when legacy drafts stored light inline colours */
.quest-rich-editor .ProseMirror [style*="color: white"],
.quest-rich-editor .ProseMirror [style*="color:white"],
.quest-rich-editor .ProseMirror [style*="color: #fff"],
.quest-rich-editor .ProseMirror [style*="color:#fff"],
.quest-rich-editor .ProseMirror [style*="color: rgb(255, 255, 255)"],
.quest-rich-editor .ProseMirror [style*="color:rgb(255, 255, 255)"] {
    color: #4b5563 !important;
}

.quest-rich-editor .ProseMirror h2 {
    font-size: 1.25rem;
    font-weight: 700;
    line-height: 1.35;
    margin-top: 0.75rem;
    margin-bottom: 0.35rem;
}

.quest-rich-editor .ProseMirror h3 {
    font-size: 1.1rem;
    font-weight: 700;
    line-height: 1.35;
    margin-top: 0.65rem;
    margin-bottom: 0.25rem;
}

.quest-rich-editor .ProseMirror ul,
.quest-rich-editor .ProseMirror ol {
    margin: 0.5rem 0;
    padding-left: 1.25rem;
}

.quest-rich-editor .ProseMirror blockquote {
    margin: 0.5rem 0;
    border-left: 3px solid rgb(203 213 225);
    padding-left: 0.85rem;
    font-style: italic;
}

.quest-rich-editor .ProseMirror p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
    color: rgb(148 163 184) !important;
    font-weight: 600;
}

.quest-rich-editor .ProseMirror-focused {
    outline: none;
}
</style>
