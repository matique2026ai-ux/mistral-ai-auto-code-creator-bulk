import Reservation from '../models/Reservation';
import { logger } from '../utils/logger';

interface CreateReservationInput {
  userId: number;
  date: Date;
  time: string;
  numberOfPeople: number;
}

interface UpdateReservationInput {
  date?: Date;
  time?: string;
  numberOfPeople?: number;
  status?: string;
}

class ReservationService {
  public async createReservation(input: CreateReservationInput): Promise<Reservation> {
    try {
      const reservation = await Reservation.create(input);
      return reservation;
    } catch (error) {
      logger.error(`Error creating reservation: ${error}`);
      throw error;
    }
  }

  public async getReservationsByUserId(userId: number): Promise<Reservation[]> {
    try {
      const reservations = await Reservation.findAll({
        where: { userId },
      });
      return reservations;
    } catch (error) {
      logger.error(`Error fetching reservations: ${error}`);
      throw error;
    }
  }

  public async updateReservation(id: number, input: UpdateReservationInput): Promise<Reservation> {
    try {
      const reservation = await Reservation.findByPk(id);
      if (!reservation) {
        throw new Error('Reservation not found');
      }
      await reservation.update(input);
      return reservation;
    } catch (error) {
      logger.error(`Error updating reservation: ${error}`);
      throw error;
    }
  }

  public async deleteReservation(id: number): Promise<void> {
    try {
      const reservation = await Reservation.findByPk(id);
      if (!reservation) {
        throw new Error('Reservation not found');
      }
      await reservation.destroy();
    } catch (error) {
      logger.error(`Error deleting reservation: ${error}`);
      throw error;
    }
  }
}

export default ReservationService;