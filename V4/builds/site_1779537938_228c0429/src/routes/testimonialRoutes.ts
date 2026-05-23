import express from 'express';
import { Testimonial } from '../models/Testimonial';
import logger from '../utils/logger';

const router = express.Router();

router.get('/', async (req, res) => {
  try {
    const testimonials = await Testimonial.findAll({
      order: [['created_at', 'DESC']]
    });
    res.json(testimonials);
  } catch (error) {
    logger.error(`Error fetching testimonials: ${error}`);
    res.status(500).json({ error: 'Internal server error' });
  }
});

export default router;