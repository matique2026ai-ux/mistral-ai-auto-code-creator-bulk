import { z } from 'zod';

export const reservationSchema = z.object({
  body: z.object({
    date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
    time: z.string().regex(/^\d{2}:\d{2}$/),
    number_of_people: z.number().int().min(1).max(20),
  }),
});