import type { InferType } from 'yup';
import { number, object } from 'yup';

export const ticketEditSchema = object({
    category_id: number().required('Category is required.').min(1, 'Category is required.'),
    units_count: number().required('Units count is required.').min(0, 'Units count must be 0 or greater.'),
});

export type TicketEditInput = InferType<typeof ticketEditSchema>;
