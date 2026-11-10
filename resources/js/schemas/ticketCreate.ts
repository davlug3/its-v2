import type { InferType } from 'yup';
import { number, object, string } from 'yup';

export const ticketCreateSchema = object({
    subject: string().trim().required('Subject is required.').max(255, 'Subject must be 255 characters or fewer.'),
    issue: string().trim().required('Issue description is required.'),
    category_id: number().required('Category is required.').min(1, 'Category is required.'),
    place: string().trim().required('Place is required.'),
    contact: string().trim().required('Contact is required.'),
    units_count: number().required('Units count is required.').min(0, 'Units count must be 0 or greater.'),
});

export type TicketCreateInput = InferType<typeof ticketCreateSchema>;