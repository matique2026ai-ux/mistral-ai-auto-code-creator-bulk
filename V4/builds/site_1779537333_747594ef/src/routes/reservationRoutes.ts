import express from 'express';
import ReservationController from '../controllers/ReservationController';
import authenticate from '../middlewares/authenticate';
import validateRequest from '../middlewares/validateRequest';
import { reservationSchema } from '../validations/reservationValidation';

const router = express.Router();

router.post('/', authenticate, validateRequest(reservationSchema), ReservationController.createReservation);
router.get('/user', authenticate, ReservationController.getUserReservations);

export default router;