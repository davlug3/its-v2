import type { InferType } from 'yup';
import { object, string } from 'yup';

export const categoryNameSchema = object({
    name: string().trim().required('Category name is required.').max(100, 'Name must be 100 characters or fewer.'),
});

export type CategoryNameInput = InferType<typeof categoryNameSchema>;