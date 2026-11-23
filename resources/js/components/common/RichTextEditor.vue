<script setup lang="ts">
import { computed, watch } from 'vue';

import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import {
    Bold,
    Heading1,
    Image as ImageIcon,
    Italic,
    List,
    ListOrdered,
    Quote,
    Redo2,
    Strikethrough,
    Underline,
    Undo2,
} from '@lucide/vue';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        id?: string;
        ariaLabel?: string;
    }>(),
    {
        modelValue: '',
        id: undefined,
        ariaLabel: 'Rich text editor',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            heading: { levels: [1, 2, 3] },
        }),
        Image,
    ],
    editorProps: {
        attributes: props.id ? { id: props.id } : undefined,
    },
    onUpdate: ({ editor }) => emit('update:modelValue', editor.getHTML()),
});

watch(
    () => props.modelValue,
    (value) => {
        const current = editor.value;
        if (current && current.getHTML() !== value) {
            current.commands.setContent(value);
        }
    },
);

const isReady = computed(() => editor.value !== null);

const toggleBold = (): void => {
    editor.value?.chain().focus().toggleBold().run();
};

const toggleItalic = (): void => {
    editor.value?.chain().focus().toggleItalic().run();
};

const toggleUnderline = (): void => {
    editor.value?.chain().focus().toggleUnderline().run();
};

const toggleStrike = (): void => {
    editor.value?.chain().focus().toggleStrike().run();
};

const toggleBulletList = (): void => {
    editor.value?.chain().focus().toggleBulletList().run();
};

const toggleOrderedList = (): void => {
    editor.value?.chain().focus().toggleOrderedList().run();
};

const toggleBlockquote = (): void => {
    editor.value?.chain().focus().toggleBlockquote().run();
};

const addHeading = (level: 1 | 2 | 3): void => {
    editor.value?.chain().focus().toggleHeading({ level }).run();
};

const addImage = (): void => {
    const src = window.prompt('Image URL');
    if (src) {
        editor.value?.chain().focus().setImage({ src }).run();
    }
};

const undo = (): void => {
    editor.value?.chain().focus().undo().run();
};

const redo = (): void => {
    editor.value?.chain().focus().redo().run();
};
</script>

<template>
    <div
        class="overflow-hidden rounded-md border border-input bg-transparent focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50"
    >
        <div class="flex flex-wrap items-center gap-0.5 border-b border-input p-1">
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 px-2"
                :disabled="!isReady"
                :aria-label="`Bold`"
                @click="toggleBold"
            >
                <Bold class="h-3.5 w-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 px-2"
                :disabled="!isReady"
                :aria-label="`Italic`"
                @click="toggleItalic"
            >
                <Italic class="h-3.5 w-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 px-2"
                :disabled="!isReady"
                :aria-label="`Underline`"
                @click="toggleUnderline"
            >
                <Underline class="h-3.5 w-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 px-2"
                :disabled="!isReady"
                :aria-label="`Strikethrough`"
                @click="toggleStrike"
            >
                <Strikethrough class="h-3.5 w-3.5" />
            </Button>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-7 px-2"
                        :disabled="!isReady"
                        :aria-label="`Heading`"
                    >
                        <Heading1 class="h-3.5 w-3.5" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                    <DropdownMenuItem @click="() => addHeading(1)">Heading 1</DropdownMenuItem>
                    <DropdownMenuItem @click="() => addHeading(2)">Heading 2</DropdownMenuItem>
                    <DropdownMenuItem @click="() => addHeading(3)">Heading 3</DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 px-2"
                :disabled="!isReady"
                :aria-label="`Bulleted list`"
                @click="toggleBulletList"
            >
                <List class="h-3.5 w-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 px-2"
                :disabled="!isReady"
                :aria-label="`Numbered list`"
                @click="toggleOrderedList"
            >
                <ListOrdered class="h-3.5 w-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 px-2"
                :disabled="!isReady"
                :aria-label="`Quote`"
                @click="toggleBlockquote"
            >
                <Quote class="h-3.5 w-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 px-2"
                :disabled="!isReady"
                :aria-label="`Insert image`"
                @click="addImage"
            >
                <ImageIcon class="h-3.5 w-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 px-2"
                :disabled="!isReady"
                :aria-label="`Undo`"
                @click="undo"
            >
                <Undo2 class="h-3.5 w-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 px-2"
                :disabled="!isReady"
                :aria-label="`Redo`"
                @click="redo"
            >
                <Redo2 class="h-3.5 w-3.5" />
            </Button>
        </div>
        <div class="min-h-40 px-3 py-2">
            <EditorContent :editor="editor" />
        </div>
    </div>
</template>
