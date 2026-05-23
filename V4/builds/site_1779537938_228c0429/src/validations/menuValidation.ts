import Joi from 'joi';

export const menuItemSchema = Joi.object({
  name: Joi.string().required(),
  description: Joi.string().allow(''),
  price: Joi.number().positive().required(),
  category: Joi.string().valid('starter', 'main', 'dessert', 'wine').required(),
  image_url: Joi.string().uri().allow('')
});