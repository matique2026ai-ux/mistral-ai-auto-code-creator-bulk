import express from 'express';
import ReservationController from '../controllers/ReservationController';
import validateRequest from '../middlewares/validateRequest';
import { reservationSchema } from '../validations/reservationValidation';
import authMiddleware from '../middlewares/authMiddleware';

const router = express.Router();

router.post('/', authMiddleware, validateRequest(reservationSchema), ReservationController.createReservation);
router.get('/', authMiddleware, ReservationController.getUserReservations);
router.put('/:id/cancel', authMiddleware, ReservationController.cancelReservation);

export default router;