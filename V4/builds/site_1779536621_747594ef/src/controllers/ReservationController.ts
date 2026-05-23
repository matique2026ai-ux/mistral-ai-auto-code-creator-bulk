import { Request, Response } from 'express';
import { Reservation } from '../models/Reservation';
import { User } from '../models/User';
import logger from '../utils/logger';

class ReservationController {
  static async createReservation(req: Request, res: Response) {
    try {
      const { date, time, number_of_people } = req.body;
      const userId = (req as any).user.id;
      
      if (!date || !time || !number_of_people) {
        return res.status(400).json({ error: 'Date, time, and number of people are required' });
      }
      
      const user = await User.findByPk(userId);
      
      if (!user) {
        return res.status(404).json({ error: 'User not found' });
      }
      
      const reservation = await Reservation.create({
        user_id: userId,
        date,
        time,
        number_of_people,
        status: 'pending'
      });
      
      logger.info(`New reservation created: ${reservation.id} for user ${userId}`);
      
      res.status(201).json(reservation);
    } catch (error) {
      logger.error('Create reservation error:', error);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
  
  static async getUserReservations(req: Request, res: Response) {
    try {
      const userId = (req as any).user.id;
      
      const reservations = await Reservation.findAll({ where: { user_id: userId } });
      
      res.json(reservations);
    } catch (error) {
      logger.error('Get user reservations error:', error);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
  
  static async cancelReservation(req: Request, res: Response) {
    try {
      const reservationId = req.params.id;
      const userId = (req as any).user.id;
      
      const reservation = await Reservation.findOne({ where: { id: reservationId, user_id: userId } });
      
      if (!reservation) {
        return res.status(404).json({ error: 'Reservation not found' });
      }
      
      await reservation.update({ status: 'cancelled' });
      
      logger.info(`Reservation ${reservationId} cancelled by user ${userId}`);
      
      res.json({ message: 'Reservation cancelled successfully' });
    } catch (error) {
      logger.error('Cancel reservation error:', error);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
}

export default ReservationController;