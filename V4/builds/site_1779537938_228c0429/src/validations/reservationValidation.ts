import Joi from 'joi';

export const reservationSchema = Joi.object({
  date: Joi.date().required(),
  time: Joi.string().pattern(/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/).required(),
  number_of_people: Joi.number().integer().min(1).max(20).required()
});