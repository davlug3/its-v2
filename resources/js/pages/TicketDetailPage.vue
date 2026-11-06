<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { api } from '@/services/api';
import { useAuthStore } from '@/stores/auth';

interface Ticket {
    id: number;
    subject: string;
    issue: string;
    state_id: number;
    category_id: number;
    location_id: number;
    place: string;
    contact: string;
    units_count: number;
    created_uid: number;
    created_date: number;
    assigned_uid: number;
    resolved_uid: number;
    resolved_date: number;
    eu_uid: number;
}

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const id = Number(route.params.id);

const canAssign = computed(() => (auth.user?.acl ?? 1) >= 3);

const ticket = ref<Ticket | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const editing = ref(false);
const saving = ref(false);
const closing = ref(false);

const subject = ref('');
const issue = ref('');
const assignUid = ref(0);
const assigning = ref(false);
const assignError = ref<string | null>(null);

const stateLabel = (s: number): string => (s === 1 ? 'Open' : s === 2 ? 'In progress' : s === 3 ? 'Closed' : `State ${s}`);

const load = async (): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
        ticket.value = await api<Ticket>(`/tickets/${id}`);
        subject.value = ticket.value.subject;
        issue.value = ticket.value.issue;
        assignUid.value = ticket.value.assigned_uid;
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to load ticket.';
    } finally {
        loading.value = false;
    }
};

const save = async (): Promise<void> => {
    saving.value = true;
    error.value = null;
    try {
        await api(`/tickets/${id}`, {
            method: 'PUT',
            body: JSON.stringify({ subject: subject.value.trim(), issue: issue.value.trim() }),
        });
        editing.value = false;
        await load();
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to save.';
    } finally {
        saving.value = false;
    }
};

const close = async (): Promise<void> => {
    closing.value = true;
    error.value = null;
    try {
        await api(`/tickets/${id}/close`, { method: 'POST' });
        await load();
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to close.';
    } finally {
        closing.value = false;
    }
};

const doAssign = async (): Promise<void> => {
    assigning.value = true;
    assignError.value = null;
    try {
        await api(`/tickets/${id}/assign`, {
            method: 'PATCH',
            body: JSON.stringify({ assigned_uid: assignUid.value }),
        });
        await load();
    } catch (e) {
        assignError.value = (e as { message?: string }).message ?? 'Failed to assign.';
    } finally {
        assigning.value = false;
    }
};

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-subheading font-semibold text-foreground">Ticket #{{ id }}</h1>
            <Button variant="outline" @click="router.push('/my-tickets')">Back</Button>
        </div>

        <p v-if="loading" class="text-sm text-muted-foreground">Loading…</p>
        <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>

        <template v-else-if="ticket">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center justify-between">
                        <span>{{ ticket.subject }}</span>
                        <Badge :variant="ticket.state_id === 3 ? 'secondary' : 'default'">
                            {{ stateLabel(ticket.state_id) }}
                        </Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="editing" class="space-y-3">
                        <div class="space-y-2">
                            <Label for="subject">Subject</Label>
                            <Input id="subject" v-model="subject" />
                        </div>
                        <div class="space-y-2">
                            <Label for="issue">Issue</Label>
                            <Textarea id="issue" v-model="issue" rows="6" />
                        </div>
                        <div class="flex gap-2">
                            <Button :disabled="saving" @click="save">
                                {{ saving ? 'Saving…' : 'Save' }}
                            </Button>
                            <Button variant="outline" @click="editing = false">Cancel</Button>
                        </div>
                    </div>
                    <div v-else class="space-y-3">
                        <p class="whitespace-pre-wrap text-sm">{{ ticket.issue }}</p>
                        <div class="flex gap-2">
                            <Button variant="outline" @click="editing = true">Edit</Button>
                            <Button
                                v-if="ticket.state_id !== 3"
                                :disabled="closing"
                                @click="close"
                            >
                                {{ closing ? 'Closing…' : 'Close ticket' }}
                            </Button>
                        </div>
                    </div>

                    <dl class="grid grid-cols-2 gap-2 text-xs text-muted-foreground sm:grid-cols-4">
                        <div><dt class="font-medium">Category</dt><dd>{{ ticket.category_id }}</dd></div>
                        <div><dt class="font-medium">Location</dt><dd>{{ ticket.location_id }}</dd></div>
                        <div><dt class="font-medium">Created by</dt><dd>{{ ticket.created_uid }}</dd></div>
                        <div><dt class="font-medium">Assigned to</dt><dd>{{ ticket.assigned_uid || '—' }}</dd></div>
                    </dl>

                    <div v-if="canAssign" class="flex items-end gap-2 border-t border-border pt-3">
                        <div class="space-y-1">
                            <Label for="assign-uid">Assign to user ID</Label>
                            <Input id="assign-uid" v-model.number="assignUid" type="number" min="0" />
                        </div>
                        <Button :disabled="assigning" @click="doAssign">
                            {{ assigning ? 'Assigning…' : 'Assign' }}
                        </Button>
                        <p v-if="assignError" class="text-sm text-destructive">{{ assignError }}</p>
                    </div>
                </CardContent>
            </Card>
        </template>
    </div>
</template>