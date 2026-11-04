import type { InferType } from 'yup';
import { object, string } from 'yup';

export const loginSchema = object({
    username: string().trim().required('Username is required.'),
    password: string().required('Password is required.'),
});

export type LoginInput = InferType<typeof loginSchema>;
