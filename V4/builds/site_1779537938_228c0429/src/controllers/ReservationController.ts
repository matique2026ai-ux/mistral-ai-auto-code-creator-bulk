import { Request, Response } from 'express';
import { Reservation } from '../models/Reservation';
import { User } from '../models/User';
import logger from '../utils/logger';
import { Op } from 'sequelize';

class ReservationController {
  async createReservation(req: Request, res: Response) {
    try {
      const { date, time, number_of_people } = req.body;
      const userId = req.user?.id;
      
      // Validation
      if (!date || !time || !number_of_people || number_of_people < 1) {
        return res.status(400).json({ error: 'Invalid reservation data' });
      }
      
      // Check availability (simplified - in production would need more complex logic)
      const existingReservations = await Reservation.count({
        where: {
          date,
          time,
          status: { [Op.not]: 'cancelled' }
        }
      });
      
      const maxCapacity = 50; // Example capacity
      if (existingReservations + number_of_people > maxCapacity) {
        return res.status(400).json({ error: 'No available slots for this time' });
      }
      
      // Create reservation
      const reservation = await Reservation.create({
        user_id: userId,
        date,
        time,
        number_of_people,
        status: 'pending'
      });
      
      logger.info(`New reservation created by user ${userId} for ${date} at ${time}`);
      res.status(201).json(reservation);
    } catch (error) {
      logger.error(`Reservation creation error: ${error}`);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
  
  async getUserReservations(req: Request, res: Response) {
    try {
      const userId = req.user?.id;
      
      const reservations = await Reservation.findAll({
        where: { user_id: userId },
        order: [['date', 'ASC']]
      });
      
      res.json(reservations);
    } catch (error) {
      logger.error(`Error fetching user reservations: ${error}`);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
  
  async cancelReservation(req: Request, res: Response) {
    try {
      const reservationId = req.params.id;
      const userId = req.user?.id;
      
      const reservation = await Reservation.findOne({
        where: { id: reservationId, user_id: userId }
      });
      
      if (!reservation) {
        return res.status(404).json({ error: 'Reservation not found' });
      }
      
      if (reservation.status === 'cancelled') {
        return res.status(400).json({ error: 'Reservation already cancelled' });
      }
      
      reservation.status = 'cancelled';
      await reservation.save();
      
      logger.info(`Reservation ${reservationId} cancelled by user ${userId}`);
      res.json({ message: 'Reservation cancelled successfully' });
    } catch (error) {
      logger.error(`Error cancelling reservation: ${error}`);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
}

export default new ReservationController();