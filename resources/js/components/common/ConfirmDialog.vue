<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

defineProps<{
    open: boolean;
    title: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    destructive?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'confirm'): void;
    (e: 'cancel'): void;
}>();

const close = (): void => emit('update:open', false);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ message }}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="close">{{ cancelLabel ?? 'Cancel' }}</Button>
                <Button :variant="destructive ? 'destructive' : 'default'" @click="emit('confirm')">
                    {{ confirmLabel ?? 'Confirm' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>