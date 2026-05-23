import express from 'express';
import ReservationController from '../controllers/ReservationController';
import { authenticate } from '../middlewares/authMiddleware';
import { validateRequest } from '../middlewares/validationMiddleware';
import { reservationSchema } from '../validations/reservationValidation';

const router = express.Router();

router.use(authenticate);

router.post('/', validateRequest(reservationSchema), ReservationController.createReservation);
router.get('/', ReservationController.getUserReservations);
router.put('/:id/cancel', ReservationController.cancelReservation);

export default router;