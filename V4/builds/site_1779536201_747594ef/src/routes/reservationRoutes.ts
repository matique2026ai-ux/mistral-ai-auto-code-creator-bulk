import { Router } from 'express';
import ReservationController from '../controllers/ReservationController';
import { authenticate } from '../middlewares/authMiddleware';

const router = Router();
const reservationController = new ReservationController();

router.post('/', authenticate, reservationController.createReservation);
router.get('/', authenticate, reservationController.getReservations);
router.put('/:id', authenticate, reservationController.updateReservation);
router.delete('/:id', authenticate, reservationController.deleteReservation);

export default router;