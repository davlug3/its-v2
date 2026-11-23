import type { InferType } from 'yup';
import { object, string } from 'yup';

export const ticketCreateSchema = object({
    subject: string()
        .trim()
        .required('Subject is required.')
        .max(255, 'Subject must be 255 characters or fewer.'),
    issue: string().trim().required('Issue description is required.'),
    place: string().trim().required('Place is required.'),
    contact: string().trim().required('Contact is required.'),
});

export type TicketCreateInput = InferType<typeof ticketCreateSchema>;