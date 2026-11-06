<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

export interface SelectOption {
    value: string | number;
    label: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: string | number | null;
        options: SelectOption[];
        placeholder?: string;
        debounce?: number;
        fetchOptions?: (query: string) => Promise<SelectOption[]>;
        emptyText?: string;
    }>(),
    {
        placeholder: 'Select…',
        debounce: 300,
        emptyText: 'No results.',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string | number | null): void;
}>();

const open = ref(false);
const query = ref('');
const debouncedQuery = ref('');
const loading = ref(false);
const asyncOptions = ref<SelectOption[] | null>(null);

let timer: ReturnType<typeof setTimeout> | null = null;

const scheduleSearch = (value: string): void => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        debouncedQuery.value = value;
        if (props.fetchOptions) {
            loading.value = true;
            props
                .fetchOptions(value)
                .then((res) => {
                    asyncOptions.value = res;
                })
                .catch(() => {
                    asyncOptions.value = [];
                })
                .finally(() => {
                    loading.value = false;
                });
        }
    }, props.debounce);
};

watch(query, (v) => scheduleSearch(v));

const baseOptions = computed(() => asyncOptions.value ?? props.options);

const filtered = computed(() => {
    const q = debouncedQuery.value.trim().toLowerCase();
    if (!q) return baseOptions.value;
    if (props.fetchOptions) return baseOptions.value; // already filtered server-side
    return baseOptions.value.filter((o) => o.label.toLowerCase().includes(q));
});

const selectedLabel = computed(() => {
    const found = baseOptions.value.find((o) => o.value === props.modelValue);
    return found ? found.label : '';
});

const select = (opt: SelectOption): void => {
    emit('update:modelValue', opt.value);
    open.value = false;
    query.value = '';
    debouncedQuery.value = '';
};

const clear = (): void => {
    emit('update:modelValue', null);
};

watch(open, (v) => {
    if (v) {
        query.value = '';
        debouncedQuery.value = '';
        asyncOptions.value = null;
        if (props.fetchOptions) {
            void props.fetchOptions('').then((res) => {
                asyncOptions.value = res;
            });
        }
    }
});
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button type="button" variant="outline" class="w-full justify-between">
                <span class="truncate">{{ selectedLabel || placeholder }}</span>
                <span class="text-muted-foreground">▾</span>
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-72 p-2" align="start">
            <Input v-model="query" placeholder="Search…" class="mb-2" />
            <div class="max-h-60 overflow-y-auto">
                <p v-if="loading" class="px-2 py-1 text-sm text-muted-foreground">Loading…</p>
                <p v-else-if="filtered.length === 0" class="px-2 py-1 text-sm text-muted-foreground">
                    {{ emptyText }}
                </p>
                <button
                    v-for="opt in filtered"
                    :key="opt.value"
                    type="button"
                    class="block w-full rounded-md px-2 py-1.5 text-left text-sm hover:bg-haze"
                    @click="select(opt)"
                >
                    {{ opt.label }}
                </button>
            </div>
            <div v-if="modelValue !== null" class="mt-2 border-t border-border pt-2">
                <button type="button" class="text-xs text-muted-foreground hover:text-foreground" @click="clear">
                    Clear selection
                </button>
            </div>
        </PopoverContent>
    </Popover>
</template>