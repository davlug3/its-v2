<script setup lang="ts">
import { shallowRef } from 'vue';
import { useRouter } from 'vue-router';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PageHeader from '@/components/common/PageHeader.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { api } from '@/services/api';
import { ticketCreateSchema, type TicketCreateInput } from '@/schemas/ticketCreate';

const router = useRouter();
const subject = shallowRef('');
const issue = shallowRef('');
const place = shallowRef('');
const contact = shallowRef('');
const submitting = shallowRef(false);
const error = shallowRef<string | null>(null);
const idempotencyKey = shallowRef<string | null>(null);

const submit = async (): Promise<void> => {
    error.value = null;
    const input: TicketCreateInput = {
        subject: subject.value,
        issue: issue.value,
        place: place.value,
        contact: contact.value,
    };
    try {
        await ticketCreateSchema.validate(input, { abortEarly: false });
    } catch (e) {
        const err = e as { errors?: string[] };
        error.value = err.errors?.join(' ') ?? 'Please fix the errors below.';
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
                    subject: input.subject.trim(),
                    issue: input.issue.trim(),
                    place: input.place.trim(),
                    contact: input.contact.trim(),
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
                        <Input id="subject" v-model="subject" maxlength="255" />
                    </div>
                    <div class="space-y-2">
                        <Label for="issue">Issue</Label>
                        <Textarea id="issue" v-model="issue" rows="12" />
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="place">Place</Label>
                            <Input id="place" v-model="place" />
                        </div>
                        <div class="space-y-2">
                            <Label for="contact">Contact</Label>
                            <Input id="contact" v-model="contact" />
                        </div>
                    </div>

                    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

                    <div class="flex gap-2">
                        <Button type="submit" :disabled="submitting">
                            {{ submitting ? 'Creating…' : 'Create ticket' }}
                        </Button>
                        <Button type="button" variant="outline" @click="router.back()"
                            >Cancel</Button
                        >
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
