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
        <editor-content v-if="editor" :editor="editor" class="quest-rich-editor min-h-[11rem] px-3 py-3 sm:min-h-[12rem] sm:px-4 sm:py-4" />
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

const textColor = ref('#0f172a');

const editor = useEditor({
    content: props.modelValue || '',
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
            class: 'prose prose-slate max-w-none focus:outline-none min-h-[10rem] text-[15px] font-medium leading-relaxed text-slate-800',
            spellcheck: 'true',
        },
    },
    onUpdate: ({ editor: ed }) => {
        emit('update:modelValue', ed.getHTML());
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
        if (val !== current) {
            ed.commands.setContent(val || '', false);
        }
    },
);

function onTextColor(e) {
    const hex = e?.target?.value;
    if (!hex || !editor.value) {
        return;
    }
    editor.value.chain().focus().setColor(hex).run();
}

function clearColor() {
    editor.value?.chain().focus().unsetColor().run();
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
/* TipTap / ProseMirror base (package does not ship a single global CSS in v2) */
.quest-rich-editor .ProseMirror {
    outline: none;
    min-height: 10rem;
}

.quest-rich-editor .ProseMirror p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
    color: rgb(148 163 184);
    font-weight: 600;
}

.quest-rich-editor .ProseMirror-focused {
    outline: none;
}
</style>
