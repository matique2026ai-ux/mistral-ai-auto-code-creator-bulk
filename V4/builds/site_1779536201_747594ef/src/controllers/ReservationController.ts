import { Request, Response } from 'express';
import { ReservationService } from '../services/ReservationService';
import { logger } from '../utils/logger';

class ReservationController {
  private reservationService: ReservationService;

  constructor() {
    this.reservationService = new ReservationService();
  }

  public createReservation = async (req: Request, res: Response) => {
    try {
      const { date, time, numberOfPeople } = req.body;
      const userId = req.user?.id;

      if (!userId) {
        return res.status(401).json({ error: 'Unauthorized' });
      }

      const reservation = await this.reservationService.createReservation({
        userId,
        date,
        time,
        numberOfPeople,
      });

      logger.info(`Reservation created: ${reservation.id}`);
      res.status(201).json(reservation);
    } catch (error) {
      logger.error(`Error creating reservation: ${error}`);
      res.status(500).json({ error: 'Internal Server Error' });
    }
  };

  public getReservations = async (req: Request, res: Response) => {
    try {
      const userId = req.user?.id;

      if (!userId) {
        return res.status(401).json({ error: 'Unauthorized' });
      }

      const reservations = await this.reservationService.getReservationsByUserId(userId);
      res.status(200).json(reservations);
    } catch (error) {
      logger.error(`Error fetching reservations: ${error}`);
      res.status(500).json({ error: 'Internal Server Error' });
    }
  };

  public updateReservation = async (req: Request, res: Response) => {
    try {
      const { id } = req.params;
      const { date, time, numberOfPeople, status } = req.body;

      const reservation = await this.reservationService.updateReservation(Number(id), {
        date,
        time,
        numberOfPeople,
        status,
      });

      logger.info(`Reservation updated: ${reservation.id}`);
      res.status(200).json(reservation);
    } catch (error) {
      logger.error(`Error updating reservation: ${error}`);
      res.status(500).json({ error: 'Internal Server Error' });
    }
  };

  public deleteReservation = async (req: Request, res: Response) => {
    try {
      const { id } = req.params;

      await this.reservationService.deleteReservation(Number(id));
      logger.info(`Reservation deleted: ${id}`);
      res.status(204).send();
    } catch (error) {
      logger.error(`Error deleting reservation: ${error}`);
      res.status(500).json({ error: 'Internal Server Error' });
    }
  };
}

export default ReservationController;