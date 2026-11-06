<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { api, getToken } from '@/services/api';
import { useAuthStore } from '@/stores/auth';

interface HelpdeskUser {
    id: number;
    username: string;
    name: string;
    mname: string;
    lname: string;
    acl_id: number | null;
}

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

interface TicketLog {
    id: number;
    ticket_id: number;
    user_id: number;
    data: string;
    date: number;
    time: number;
    action: string | null;
    meta: Record<string, unknown> | null;
}

interface Attachment {
    id: number;
    file_name_org: string;
    ticket_id: number;
    storage_path: string;
    mime_type: string;
    size_bytes: number;
    uploaded_by: number;
    created_at: string;
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

const logs = ref<TicketLog[]>([]);
const commentText = ref('');
const commenting = ref(false);
const commentError = ref<string | null>(null);

const attachments = ref<Attachment[]>([]);
const uploading = ref(false);
const uploadError = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const helpdeskUsers = ref<HelpdeskUser[]>([]);

const loadHelpdeskUsers = async (): Promise<void> => {
    try {
        const all: HelpdeskUser[] = [];
        for (const acl of [3, 9]) {
            let page = 1;
            let lastPage = 1;
            do {
                const res = await api<{ data: HelpdeskUser[]; last_page: number }>(
                    `/admin/users?acl=${acl}&per_page=100&page=${page}`,
                );
                all.push(...(res.data ?? []));
                lastPage = res.last_page ?? 1;
                page += 1;
            } while (page <= lastPage);
        }
        // de-duplicate by id, sort by name
        const seen = new Set<number>();
        const unique = all.filter((u) => {
            if (seen.has(u.id)) return false;
            seen.add(u.id);
            return true;
        });
        unique.sort((a, b) => {
            const an = [a.name, a.mname, a.lname].filter(Boolean).join(' ').toLowerCase();
            const bn = [b.name, b.mname, b.lname].filter(Boolean).join(' ').toLowerCase();
            return an.localeCompare(bn);
        });
        helpdeskUsers.value = unique;
    } catch {
        helpdeskUsers.value = [];
    }
};

const userLabel = (u: HelpdeskUser): string => {
    const name = [u.name, u.mname, u.lname].filter(Boolean).join(' ').trim();
    return name ? `${name} (${u.username})` : u.username;
};

const stateLabel = (s: number): string => (s === 1 ? 'Open' : s === 2 ? 'Closed' : `State ${s}`);

const loadLogs = async (): Promise<void> => {
    try {
        logs.value = await api<TicketLog[]>(`/tickets/${id}/log`);
    } catch {
        logs.value = [];
    }
};

const loadAttachments = async (): Promise<void> => {
    try {
        attachments.value = await api<Attachment[]>(`/tickets/${id}/attachments`);
    } catch {
        attachments.value = [];
    }
};

const load = async (): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
        ticket.value = await api<Ticket>(`/tickets/${id}`);
        subject.value = ticket.value.subject;
        issue.value = ticket.value.issue;
        assignUid.value = ticket.value.assigned_uid;
        await loadLogs();
        await loadAttachments();
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

const onFileChange = async (event: Event): Promise<void> => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    uploading.value = true;
    uploadError.value = null;
    try {
        const form = new FormData();
        form.append('file', file);
        await api(`/tickets/${id}/attachments`, { method: 'POST', body: form });
        await loadAttachments();
        await loadLogs();
        if (fileInput.value) fileInput.value.value = '';
    } catch (e) {
        uploadError.value = (e as { message?: string }).message ?? 'Upload failed.';
    } finally {
        uploading.value = false;
    }
};

const downloadAttachment = async (attachment: Attachment): Promise<void> => {
    try {
        const token = getToken();
        const response = await fetch(`/api/v1/tickets/${id}/attachments/${attachment.id}/download`, {
            headers: token ? { Authorization: `Bearer ${token}` } : {},
        });
        if (!response.ok) throw new Error('Download failed.');
        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = attachment.file_name_org;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    } catch (e) {
        uploadError.value = (e as { message?: string }).message ?? 'Download failed.';
    }
};

const deleteAttachment = async (attachmentId: number): Promise<void> => {
    if (!window.confirm('Delete this attachment?')) return;
    try {
        await api(`/tickets/${id}/attachments/${attachmentId}`, { method: 'DELETE' });
        await loadAttachments();
        await loadLogs();
    } catch (e) {
        uploadError.value = (e as { message?: string }).message ?? 'Delete failed.';
    }
};

const formatBytes = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const submitComment = async (): Promise<void> => {
    if (commentText.value.trim() === '') return;
    commenting.value = true;
    commentError.value = null;
    try {
        await api(`/tickets/${id}/comment`, {
            method: 'POST',
            body: JSON.stringify({ text: commentText.value.trim() }),
        });
        commentText.value = '';
        await loadLogs();
    } catch (e) {
        commentError.value = (e as { message?: string }).message ?? 'Failed to add comment.';
    } finally {
        commenting.value = false;
    }
};

const formatLogDate = (ts: number): string => (ts ? new Date(ts * 1000).toLocaleString() : '—');

onMounted(() => {
    void load();
    void loadHelpdeskUsers();
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-subheading font-semibold text-foreground">Ticket #{{ id }}</h1>
            <Button variant="outline" @click="router.push('/my-tickets')">Back</Button>
        </div>

        <div v-if="loading" class="space-y-2">
            <Skeleton class="h-8 w-full" />
            <Skeleton class="h-8 w-5/6" />
            <Skeleton class="h-8 w-4/6" />
        </div>
        <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>

        <template v-else-if="ticket">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center justify-between">
                        <span>{{ ticket.subject }}</span>
                        <Badge :variant="ticket.state_id === 2 ? 'secondary' : 'default'">
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
                            <Label for="assign-uid">Assign to</Label>
                            <select
                                id="assign-uid"
                                v-model.number="assignUid"
                                class="h-9 min-w-[16rem] rounded-md border border-input bg-background px-2 text-sm"
                            >
                                <option :value="0">— Unassigned —</option>
                                <option v-for="u in helpdeskUsers" :key="u.id" :value="u.id">
                                    {{ userLabel(u) }}
                                </option>
                            </select>
                        </div>
                        <Button :disabled="assigning" @click="doAssign">
                            {{ assigning ? 'Assigning…' : 'Assign' }}
                        </Button>
                        <p v-if="assignError" class="text-sm text-destructive">{{ assignError }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Attachments</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p v-if="uploadError" class="text-sm text-destructive">{{ uploadError }}</p>
                    <div class="flex items-center gap-2">
                        <input
                            ref="fileInput"
                            type="file"
                            class="text-sm"
                            :disabled="uploading"
                            @change="onFileChange"
                        />
                        <span v-if="uploading" class="text-sm text-muted-foreground">Uploading…</span>
                    </div>
                    <ul v-if="attachments.length" class="space-y-2">
                        <li
                            v-for="a in attachments"
                            :key="a.id"
                            class="flex items-center justify-between rounded-md border border-border p-2 text-sm"
                        >
                            <button
                                type="button"
                                class="hover:underline"
                                @click="downloadAttachment(a)"
                            >
                                {{ a.file_name_org }} ({{ formatBytes(a.size_bytes) }})
                            </button>
                            <Button size="sm" variant="destructive" @click="deleteAttachment(a.id)">
                                Delete
                            </Button>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">No attachments.</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>History and comments</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <ul v-if="logs.length" class="space-y-3">
                        <li v-for="entry in logs" :key="entry.id" class="rounded-md border border-border p-3">
                            <div class="flex items-center justify-between text-xs text-muted-foreground">
                                <span>User {{ entry.user_id }} · {{ entry.action ?? 'note' }}</span>
                                <span>{{ formatLogDate(entry.date) }}</span>
                            </div>
                            <p class="mt-1 whitespace-pre-wrap text-sm">{{ entry.data }}</p>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">No history yet.</p>

                    <form class="space-y-2 border-t border-border pt-3" @submit.prevent="submitComment">
                        <Label for="comment">Add comment</Label>
                        <Textarea id="comment" v-model="commentText" rows="3" />
                        <p v-if="commentError" class="text-sm text-destructive">{{ commentError }}</p>
                        <Button type="submit" :disabled="commenting">
                            {{ commenting ? 'Posting…' : 'Post comment' }}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </template>
    </div>
</template>