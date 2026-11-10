import type { InferType } from 'yup';
import { object, string } from 'yup';

export const ticketEditSchema = object({
    subject: string().trim().required('Subject is required.').max(255, 'Subject must be 255 characters or fewer.'),
    issue: string().trim().required('Issue description is required.'),
});

export type TicketEditInput = InferType<typeof ticketEditSchema>;