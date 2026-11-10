import type { InferType } from 'yup';
import { object, ref, string } from 'yup';

export const passwordResetSchema = object({
    password: string()
        .required('New password is required.')
        .min(8, 'Password must be at least 8 characters.')
        .max(72, 'Password must be 72 characters or fewer.'),
    confirm: string()
        .required('Confirm password is required.')
        .oneOf([ref('password')], 'Passwords do not match.'),
});

export type PasswordResetInput = InferType<typeof passwordResetSchema>;