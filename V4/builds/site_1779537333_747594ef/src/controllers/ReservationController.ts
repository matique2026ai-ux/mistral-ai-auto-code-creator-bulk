import { Request, Response } from 'express';
import { Reservation } from '../models/Reservation';
import { User } from '../models/User';
import logger from '../utils/logger';

class ReservationController {
  static async createReservation(req: Request, res: Response) {
    try {
      const { date, time, guests } = req.body;
      const userId = (req as any).user.id;
      
      if (!date || !time || !guests) {
        return res.status(400).json({ error: 'Date, time, and number of guests are required' });
      }
      
      const user = await User.findByPk(userId);
      
      if (!user) {
        return res.status(404).json({ error: 'User not found' });
      }
      
      const reservation = await Reservation.create({ user_id: userId, date, time, guests });
      
      logger.info(`Reservation ${reservation.id} created by user ${userId}`);
      
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
      
      logger.info(`Fetched ${reservations.length} reservations for user ${userId}`);
      
      res.json(reservations);
    } catch (error) {
      logger.error('Get user reservations error:', error);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
}

export default ReservationController;