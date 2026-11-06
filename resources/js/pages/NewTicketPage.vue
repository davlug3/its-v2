<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PageHeader from '@/components/common/PageHeader.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { api } from '@/services/api';

interface Category {
    id: number;
    category: string;
}

const router = useRouter();
const subject = ref('');
const issue = ref('');
const categoryId = ref<number | null>(null);
const place = ref('');
const contact = ref('');
const unitsCount = ref(0);
const submitting = ref(false);
const error = ref<string | null>(null);
const idempotencyKey = ref<string | null>(null);

const categories = ref<Category[]>([]);

const loadCategories = async (): Promise<void> => {
    try {
        categories.value = await api<Category[]>('/categories');
        if (categoryId.value === null && categories.value.length > 0) {
            categoryId.value = categories.value[0].id;
        }
    } catch {
        categories.value = [];
    }
};

onMounted(loadCategories);

const submit = async (): Promise<void> => {
    error.value = null;
    if (
        subject.value.trim() === '' ||
        issue.value.trim() === '' ||
        categoryId.value === null ||
        place.value.trim() === '' ||
        contact.value.trim() === '' ||
        unitsCount.value === null
    ) {
        error.value = 'All fields are required.';
        return;
    }
    submitting.value = true;
    try {
        if (idempotencyKey.value === null) {
            idempotencyKey.value = crypto.randomUUID();
        }
        const res = await api<{ ticket_id: number }>(
            '/tickets',
            {
                method: 'POST',
                body: JSON.stringify({
                    subject: subject.value.trim(),
                    issue: issue.value.trim(),
                    category_id: categoryId.value ?? 1,
                    place: place.value.trim(),
                    contact: contact.value.trim(),
                    units_count: unitsCount.value,
                }),
            },
            idempotencyKey.value,
        );
        idempotencyKey.value = null;
        void router.push(`/tickets/${res.ticket_id}`);
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to create ticket.';
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <div class="space-y-4">
        <PageHeader title="New ticket" description="Create a new support ticket." />

        <Card>
            <CardHeader>
                <CardTitle>Create a ticket</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="subject">Subject</Label>
                        <Input id="subject" v-model="subject" maxlength="255" required />
                    </div>
                    <div class="space-y-2">
                        <Label for="issue">Issue</Label>
                        <Textarea id="issue" v-model="issue" rows="12" required />
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="category">Category</Label>
                            <select
                                id="category"
                                v-model.number="categoryId"
                                class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                            >
                                <option v-for="c in categories" :key="c.id" :value="c.id">
                                    {{ c.category }}
                                </option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <Label for="place">Place</Label>
                            <Input id="place" v-model="place" required />
                        </div>
                        <div class="space-y-2">
                            <Label for="contact">Contact</Label>
                            <Input id="contact" v-model="contact" required />
                        </div>
                        <div class="space-y-2">
                            <Label for="units">Units</Label>
                            <Input id="units" v-model.number="unitsCount" type="number" min="0" required />
                        </div>
                    </div>

                    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

                    <div class="flex gap-2">
                        <Button type="submit" :disabled="submitting">
                            {{ submitting ? 'Creating…' : 'Create ticket' }}
                        </Button>
                        <Button type="button" variant="outline" @click="router.back()">Cancel</Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>