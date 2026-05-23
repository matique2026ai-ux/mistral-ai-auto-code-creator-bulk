import express from 'express';
import MenuController from '../controllers/MenuController';

const router = express.Router();

router.get('/', MenuController.getMenuItems);

export default router;