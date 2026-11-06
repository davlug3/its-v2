<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { api } from '@/services/api';

const router = useRouter();
const subject = ref('');
const issue = ref('');
const categoryId = ref(1);
const locationId = ref(47);
const place = ref('');
const contact = ref('');
const unitsCount = ref(0);
const submitting = ref(false);
const error = ref<string | null>(null);
const idempotencyKey = ref<string | null>(null);

const submit = async (): Promise<void> => {
    error.value = null;
    if (subject.value.trim() === '' || issue.value.trim() === '') {
        error.value = 'Subject and issue are required.';
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
                    category_id: categoryId.value,
                    location_id: locationId.value,
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
        <h1 class="text-subheading font-semibold text-foreground">New ticket</h1>

        <Card>
            <CardHeader>
                <CardTitle>Create a ticket</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="subject">Subject</Label>
                        <Input id="subject" v-model="subject" maxlength="255" />
                    </div>
                    <div class="space-y-2">
                        <Label for="issue">Issue</Label>
                        <Textarea id="issue" v-model="issue" rows="6" />
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="category">Category ID</Label>
                            <Input id="category" v-model.number="categoryId" type="number" min="1" />
                        </div>
                        <div class="space-y-2">
                            <Label for="location">Location ID</Label>
                            <Input id="location" v-model.number="locationId" type="number" min="0" />
                        </div>
                        <div class="space-y-2">
                            <Label for="place">Place</Label>
                            <Input id="place" v-model="place" />
                        </div>
                        <div class="space-y-2">
                            <Label for="contact">Contact</Label>
                            <Input id="contact" v-model="contact" />
                        </div>
                        <div class="space-y-2">
                            <Label for="units">Units</Label>
                            <Input id="units" v-model.number="unitsCount" type="number" min="0" />
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