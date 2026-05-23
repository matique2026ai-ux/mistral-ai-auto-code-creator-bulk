import express from 'express';
import { GalleryImage } from '../models/GalleryImage';
import logger from '../utils/logger';

const router = express.Router();

router.get('/', async (req, res) => {
  try {
    const galleryImages = await GalleryImage.findAll({
      order: [['created_at', 'DESC']]
    });
    res.json(galleryImages);
  } catch (error) {
    logger.error(`Error fetching gallery images: ${error}`);
    res.status(500).json({ error: 'Internal server error' });
  }
});

export default router;