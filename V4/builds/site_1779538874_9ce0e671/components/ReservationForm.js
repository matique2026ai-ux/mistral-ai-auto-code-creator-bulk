import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { motion } from 'framer-motion';

const reservationSchema = z.object({
  date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Date invalide'),
  time: z.string().regex(/^\d{2}:\d{2}$/, 'Heure invalide'),
  guests: z.number().int().min(1, 'Minimum 1 personne').max(20, 'Maximum 20 personnes'),
  specialRequests: z.string().optional(),
});

const ReservationForm = () => {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitStatus, setSubmitStatus] = useState(null);

  const { register, handleSubmit, formState: { errors }, reset } = useForm({
    resolver: zodResolver(reservationSchema),
    defaultValues: {
      date: '',
      time: '',
      guests: 2,
      specialRequests: '',
    },
  });

  const generateTimeSlots = () => {
    const times = [];
    for (let hour = 19; hour <= 23; hour++) {
      times.push(`${hour.toString().padStart(2, '0')}:00`);
      times.push(`${hour.toString().padStart(2, '0')}:30`);
    }
    return times;
  };

  const onSubmit = async (data) => {
    setIsSubmitting(true);
    setSubmitStatus(null);

    try {
      const response = await fetch('/api/reservations', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });

      const result = await response.json();

      if (!response.ok) {
        throw new Error(result.error || 'Erreur lors de la réservation');
      }

      setSubmitStatus({ success: 'Réservation effectuée avec succès!' });
      reset();
    } catch (error) {
      setSubmitStatus({ error: error.message });
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.6 }}
      className="max-w-4xl mx-auto px-4 py-16"
    >
      <div className="bg-black bg-opacity-70 backdrop-blur-custom rounded-2xl p-8 md:p-12 border border-white border-opacity-10">
        <h2 className="text-4xl md:text-5xl font-bold text-center mb-8 text-secondary">Réservation</h2>

        {submitStatus?.success && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="mb-6 p-4 bg-green-900 bg-opacity-50 rounded-lg text-center text-green-300"
          >
            {submitStatus.success}
          </motion.div>
        )}

        {submitStatus?.error && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="mb-6 p-4 bg-red-900 bg-opacity-50 rounded-lg text-center text-red-300"
          >
            {submitStatus.error}
          </motion.div>
        )}

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label htmlFor="date" className="block text-sm font-medium mb-2 text-text-secondary">
                Date
              </label>
              <input
                type="date"
                id="date"
                {...register('date')}
                min={new Date().toISOString().split('T')[0]}
                className="w-full px-4 py-3 bg-black bg-opacity-50 border border-white border-opacity-20 rounded-lg focus:ring-2 focus:ring-secondary focus:ring-opacity-50 outline-none transition-all"
              />
              {errors.date && (
                <p className="mt-1 text-sm text-red-400">{errors.date.message}</p>
              )}
            </div>

            <div>
              <label htmlFor="time" className="block text-sm font-medium mb-2 text-text-secondary">
                Heure
              </label>
              <select
                id="time"
                {...register('time')}
                className="w-full px-4 py-3 bg-black bg-opacity-50 border border-white border-opacity-20 rounded-lg focus:ring-2 focus:ring-secondary focus:ring-opacity-50 outline-none transition-all appearance-none"
              >
                <option value="">Sélectionnez une heure</option>
                {generateTimeSlots().map((time) => (
                  <option key={time} value={time}>{time}</option>
                ))}
              </select>
              {errors.time && (
                <p className="mt-1 text-sm text-red-400">{errors.time.message}</p>
              )}
            </div>
          </div>

          <div>
            <label htmlFor="guests" className="block text-sm font-medium mb-2 text-text-secondary">
              Nombre de personnes
            </label>
            <input
              type="number"
              id="guests"
              {...register('guests', { valueAsNumber: true })}
              min="1"
              max="20"
              className="w-full px-4 py-3 bg-black bg-opacity-50 border border-white border-opacity-20 rounded-lg focus:ring-2 focus:ring-secondary focus:ring-opacity-50 outline-none transition-all"
            />
            {errors.guests && (
              <p className="mt-1 text-sm text-red-400">{errors.guests.message}</p>
            )}
          </div>

          <div>
            <label htmlFor="specialRequests" className="block text-sm font-medium mb-2 text-text-secondary">
              Demandes spéciales (optionnel)
            </label>
            <textarea
              id="specialRequests"
              {...register('specialRequests')}
              rows={3}
              className="w-full px-4 py-3 bg-black bg-opacity-50 border border-white border-opacity-20 rounded-lg focus:ring-2 focus:ring-secondary focus:ring-opacity-50 outline-none transition-all resize-none"
              placeholder="Allergies, préférences, occasions spéciales..."
            />
          </div>

          <motion.button
            type="submit"
            disabled={isSubmitting}
            whileTap={{ scale: 0.95 }}
            className={`w-full py-4 px-6 rounded-lg text-lg font-semibold transition-all duration-300 ${
              isSubmitting
                ? 'bg-secondary bg-opacity-70 cursor-not-allowed'
                : 'bg-secondary hover:bg-opacity-90'
            }`}
          >
            {isSubmitting ? (
              <span className="flex items-center justify-center">
                <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Envoi en cours...
              </span>
            ) : (
              'Réserver une table'
            )}
          </motion.button>
        </form>
      </div>
    </motion.div>
  );
};

export default ReservationForm;
