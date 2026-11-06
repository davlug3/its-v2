<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import { Input } from '@/components/ui/input';

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
    if (props.fetchOptions) return baseOptions.value;
    return baseOptions.value.filter((o) => o.label.toLowerCase().includes(q));
});

const selectedLabel = computed(() => {
    const found = baseOptions.value.find((o) => o.value === props.modelValue);
    return found ? found.label : '';
});

// Show selected label when closed, query when open.
const displayValue = computed(() => (open.value ? query.value : selectedLabel.value));

const onModelUpdate = (v: string | number): void => {
    query.value = String(v);
    if (!open.value) open.value = true;
};

const onFocus = (): void => {
    open.value = true;
    query.value = '';
    debouncedQuery.value = '';
    asyncOptions.value = null;
    if (props.fetchOptions) {
        loading.value = true;
        props
            .fetchOptions('')
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
};

const onBlur = (): void => {
    // delay to allow click on option
    setTimeout(() => {
        open.value = false;
    }, 150);
};

const select = (opt: SelectOption): void => {
    emit('update:modelValue', opt.value);
    query.value = '';
    debouncedQuery.value = '';
    asyncOptions.value = null;
    open.value = false;
};

const clear = (): void => {
    emit('update:modelValue', null);
    query.value = '';
};

// sync display when modelValue changes externally
watch(() => props.modelValue, () => {
    query.value = '';
});
</script>

<template>
    <div class="relative w-full">
        <Input
            :model-value="displayValue"
            :placeholder="placeholder"
            @update:model-value="onModelUpdate"
            @focus="onFocus"
            @blur="onBlur"
        />
        <div
            v-if="open"
            class="absolute left-0 top-full z-50 mt-1 w-full rounded-md border border-border bg-background p-1 shadow-md"
        >
            <p v-if="loading" class="px-2 py-1 text-sm text-muted-foreground">Loading…</p>
            <p v-else-if="filtered.length === 0" class="px-2 py-1 text-sm text-muted-foreground">
                {{ emptyText }}
            </p>
            <button
                v-for="opt in filtered"
                :key="opt.value"
                type="button"
                class="block w-full rounded-md px-2 py-1.5 text-left text-sm hover:bg-haze"
                @mousedown.prevent="select(opt)"
            >
                {{ opt.label }}
            </button>
            <div v-if="modelValue !== null" class="mt-1 border-t border-border pt-1">
                <button type="button" class="px-2 text-xs text-muted-foreground hover:text-foreground" @click="clear">
                    Clear selection
                </button>
            </div>
        </div>
    </div>
</template>