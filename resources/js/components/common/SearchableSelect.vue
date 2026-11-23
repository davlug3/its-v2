<script setup lang="ts">
import { computed, onUnmounted, ref, watch , shallowRef } from 'vue';

import { Input } from '@/components/ui/input';

export interface SelectOption {
    value: string | number;
    label: string;
}

const props = withDefaults(
    defineProps<{
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

const model = defineModel<string | number | null>({ default: null });

const open = shallowRef(false);
const query = shallowRef('');
const debouncedQuery = shallowRef('');
const loading = shallowRef(false);
const asyncOptions = ref<SelectOption[] | null>(null);
const selectedOption = shallowRef<SelectOption | null>(null);

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
    if (selectedOption.value !== null && selectedOption.value.value === model.value) {
        return selectedOption.value.label;
    }
    const found = baseOptions.value.find((o) => o.value === model.value);
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

let blurTimer: ReturnType<typeof setTimeout> | null = null;

const onBlur = (): void => {
    // delay to allow click on option
    if (blurTimer) clearTimeout(blurTimer);
    blurTimer = setTimeout(() => {
        open.value = false;
    }, 150);
};

onUnmounted(() => {
    if (timer) clearTimeout(timer);
    if (blurTimer) clearTimeout(blurTimer);
});

const select = (opt: SelectOption): void => {
    model.value = opt.value;
    selectedOption.value = opt;
    query.value = '';
    debouncedQuery.value = '';
    asyncOptions.value = null;
    open.value = false;
};

const clear = (): void => {
    model.value = null;
    selectedOption.value = null;
    query.value = '';
};

// sync display when model changes externally
watch(
    () => model.value,
    () => {
        query.value = '';
    },
);
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
            class="absolute top-full left-0 z-50 mt-1 w-full rounded-md border border-border bg-background p-1 shadow-md"
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
            <div v-if="model !== null" class="mt-1 border-t border-border pt-1">
                <button
                    type="button"
                    class="px-2 text-xs text-muted-foreground hover:text-foreground"
                    @click="clear"
                >
                    Clear selection
                </button>
            </div>
        </div>
    </div>
</template>
